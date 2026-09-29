<?php
declare(strict_types=1);

/**
 * "Sign in with GitHub" (an OAuth App owned by the Big-Red-H organization). Asks for no scopes:
 * only the public profile, and which public repos the person can push to, which is how a
 * developer is recognized as a project's own.
 */
final class GitHubLogin
{
    public function __construct(private array $cfg, private string $redirectUri)
    {
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => $this->cfg['client_id'],
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => '',
            'allow_signup' => 'false',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): string
    {
        [$status, $data] = http_request('POST', 'https://github.com/login/oauth/access_token', [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'client_id' => $this->cfg['client_id'],
            'client_secret' => $this->cfg['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]));
        if ($status !== 200 || empty($data['access_token'])) {
            throw new HttpError($status, json_encode($data), 'GitHub login failed.');
        }
        return (string) $data['access_token'];
    }

    public function user(string $token): array
    {
        return $this->get('/user', $token);
    }

    /** Lowercased "owner/repo" of every repo this person can push to. */
    public function pushableRepos(string $token): array
    {
        $repos = [];
        for ($page = 1; $page <= 10; $page++) {
            $batch = $this->get('/user/repos?per_page=100&affiliation=owner,collaborator,organization_member&page=' . $page, $token);
            foreach ($batch as $repo) {
                if (!empty($repo['permissions']['push'])) {
                    $repos[] = strtolower((string) $repo['full_name']);
                }
            }
            if (count($batch) < 100) {
                break;
            }
        }
        return $repos;
    }

    private function get(string $path, string $token): array
    {
        [$status, $data] = http_request('GET', 'https://api.github.com' . $path, [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $token,
        ]);
        if ($status !== 200 || !is_array($data)) {
            throw new HttpError($status, json_encode($data), 'GitHub request failed.');
        }
        return $data;
    }
}
