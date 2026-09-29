<?php
declare(strict_types=1);

final class EditConflict extends RuntimeException
{
}

/** The repo side: reading files and proposing changes as pull requests, with the editor's token. */
final class GitHub
{
    private const API = 'https://api.github.com';

    /** In dry-run mode (local testing) write requests are recorded here instead of sent. */
    public array $dryRunLog = [];

    public function __construct(
        private string $token,
        private string $repo,
        private string $branch,
        private bool $dryRun = false,
    ) {
    }

    /** ['text' => file contents, 'sha' => blob sha], or null if the file doesn't exist. */
    public function file(string $path): ?array
    {
        [$status, $data] = $this->request('GET', "/repos/{$this->repo}/contents/" . $this->encodePath($path) . '?ref=' . rawurlencode($this->branch));
        if ($status === 404) {
            return null;
        }
        $this->expect($status, [200], $data, "Could not load $path from GitHub.");
        return ['text' => base64_decode($data['content']), 'sha' => $data['sha']];
    }

    /** name => sha for the files in a folder; empty if there's no such folder. */
    public function folder(string $path): array
    {
        [$status, $data] = $this->request('GET', "/repos/{$this->repo}/contents/" . $this->encodePath($path) . '?ref=' . rawurlencode($this->branch));
        if ($status === 404) {
            return [];
        }
        $this->expect($status, [200], $data, "Could not list $path on GitHub.");
        $files = [];
        foreach (is_array($data) ? $data : [] as $item) {
            if (($item['type'] ?? '') === 'file') {
                $files[$item['name']] = $item['sha'];
            }
        }
        return $files;
    }

    /** Open pull requests whose branch starts with $prefix. */
    public function openPullRequests(string $prefix): array
    {
        [$status, $data] = $this->request('GET', "/repos/{$this->repo}/pulls?state=open&per_page=100");
        $this->expect($status, [200], $data, 'Could not load pending edits from GitHub.');
        return array_values(array_filter($data, fn ($pr) => str_starts_with($pr['head']['ref'], $prefix)));
    }

    /**
     * Creates a branch, commits each change to it, and opens a pull request.
     *
     * @param array $files list of ['path', 'content' => raw bytes, 'sha' => existing blob sha or null],
     *                     or ['path', 'delete' => true, 'sha'] to remove a file
     * @return string pull request URL
     */
    public function proposeEdit(string $branch, array $files, string $title, string $body, array $author): string
    {
        [$status, $ref] = $this->request('GET', "/repos/{$this->repo}/git/ref/heads/" . rawurlencode($this->branch));
        $this->expect($status, [200], $ref, 'Could not read the main branch.');

        $this->write('POST', "/repos/{$this->repo}/git/refs", [
            'ref' => "refs/heads/$branch",
            'sha' => $ref['object']['sha'],
        ], [201], 'Could not create a branch for your edit.');

        try {
            foreach ($files as $file) {
                if (!empty($file['delete'])) {
                    $this->write('DELETE', "/repos/{$this->repo}/contents/" . $this->encodePath($file['path']), [
                        'message' => $title,
                        'sha' => $file['sha'],
                        'branch' => $branch,
                        'author' => $author,
                    ], [200], "Could not remove {$file['path']}.");
                    continue;
                }
                $payload = [
                    'message' => $title,
                    'content' => base64_encode($file['content']),
                    'branch' => $branch,
                    'author' => $author,
                ];
                if ($file['sha'] !== null) {
                    $payload['sha'] = $file['sha'];
                }
                $this->write('PUT', "/repos/{$this->repo}/contents/" . $this->encodePath($file['path']), $payload, [200, 201], "Could not save {$file['path']}.");
            }
            $pr = $this->write('POST', "/repos/{$this->repo}/pulls", [
                'title' => $title,
                'head' => $branch,
                'base' => $this->branch,
                'body' => $body,
                'maintainer_can_modify' => true,
            ], [201], 'Could not open a pull request.');
        } catch (Throwable $e) {
            // Don't leave half-finished branches behind.
            $this->write('DELETE', "/repos/{$this->repo}/git/refs/heads/" . $this->encodePath($branch), null, [204, 404, 422], '');
            throw $e;
        }
        return $pr['html_url'];
    }

    /** Opens an issue (used for "please link me to this project"). Returns its URL. */
    public function openIssue(string $title, string $body, array $labels): string
    {
        $issue = $this->write('POST', "/repos/{$this->repo}/issues", [
            'title' => $title,
            'body' => $body,
            'labels' => $labels,
        ], [201], 'Could not send your request.');
        return $issue['html_url'];
    }

    /** GitHub-flavored Markdown rendered to sanitized HTML, for previews. */
    public function markdown(string $text): string
    {
        [$status, $data] = $this->request('POST', '/markdown', json_encode(['text' => $text, 'mode' => 'markdown']));
        $this->expect($status, [200], $data, 'Could not render the preview.');
        return (string) $data;
    }

    private function write(string $method, string $path, ?array $payload, array $ok, string $failure): array
    {
        if ($this->dryRun) {
            $this->dryRunLog[] = [$method, $path, $payload === null ? null : array_diff_key($payload, ['content' => 1])];
            return ['html_url' => "https://github.com/{$this->repo}/pulls (dry run)"];
        }
        [$status, $data] = $this->request($method, $path, $payload === null ? null : json_encode($payload));
        if ($status === 409 || ($status === 422 && is_array($data) && str_contains(json_encode($data), 'sha'))) {
            throw new EditConflict('This was changed by someone else after you opened it.');
        }
        $this->expect($status, $ok, $data, $failure);
        return is_array($data) ? $data : [];
    }

    private function request(string $method, string $path, ?string $body = null): array
    {
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        return http_request($method, self::API . $path, $headers, $body);
    }

    private function expect(int $status, array $ok, mixed $data, string $failure): void
    {
        if (!in_array($status, $ok, true)) {
            throw new HttpError($status, is_string($data) ? $data : (string) json_encode($data), $failure);
        }
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
