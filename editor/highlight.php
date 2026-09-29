<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

// The developer's own write-up of a release, shown above the notes pulled from GitHub, on the
// project's page and in the release's news post.
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$action = $isPost ? (string) ($_POST['do'] ?? '') : '';
$user = require_login($action === 'submit');
$project = require_project($user, (string) ($_POST['id'] ?? $_GET['id'] ?? ''));
$pid = $project['id'];
$github = github();

if (!$project['releases']) {
    render_message('No releases yet', '<p>' . e($project['name']) . ' has no releases on VivaHX yet. <a href="./">Back to your projects</a>.</p>');
}
$bySlug = array_column($project['releases'], null, 'slug');
$slug = (string) ($_POST['release'] ?? $_GET['release'] ?? $project['releases'][0]['slug']);
if (!isset($bySlug[$slug])) {
    $slug = $project['releases'][0]['slug'];
}
$release = $bySlug[$slug];
$path = "projects/$pid/highlights/$slug.md";

$errors = [];
$previewHtml = null;
$text = '';
$sha = null;
if ($isPost && $action !== 'pick') {
    require_post_with_csrf();
    $text = str_replace("\r\n", "\n", (string) ($_POST['body'] ?? ''));
    $sha = ($_POST['sha'] ?? '') !== '' ? (string) $_POST['sha'] : null;
} else {
    try {
        $file = $github->file($path);
    } catch (HttpError $e) {
        render_error($e);
    }
    $text = $file['text'] ?? '';
    $sha = $file['sha'] ?? null;
}

if ($action === 'preview') {
    try {
        $previewHtml = trim($text) !== '' ? preview_html($text) : '<p class="note">(Empty.)</p>';
    } catch (HttpError $ex) {
        $errors[] = 'The preview could not be shown right now, but you can still send it.';
    }
}

if ($action === 'submit') {
    if (mb_strlen($text) > 10000) {
        $errors[] = 'The write-up is too long (up to 10,000 characters).';
    }
    if (trim($text) === '' && $sha === null) {
        $errors[] = 'Please write something first.';
    }
    if (!$errors && !within_rate_limit($user)) {
        $errors[] = "You've sent a lot in the last hour. Please wait a bit before sending more.";
    }
    if (!$errors) {
        $change = trim($text) === ''
            ? ['path' => $path, 'delete' => true, 'sha' => $sha]
            : ['path' => $path, 'content' => rtrim($text) . "\n", 'sha' => $sha];
        $body = "Sent from the VivaHX editor by " . identity($user) . ".\n\n"
            . (trim($text) === '' ? 'Removes the write-up for ' : 'Write-up for ')
            . "**" . str_replace(['*', '`'], '', $project['name'] . ' ' . $release['version']) . "**, shown above its release notes on " . site_url("/software/$pid.html") . "\n\n"
            . 'Check **Files changed**, then merge to publish or close to decline.';
        try {
            $url = $github->proposeEdit(branch_prefix($user) . gmdate('YmdHis'), [$change],
                "{$project['name']} {$release['version']}: release write-up", $body, commit_author($user));
        } catch (EditConflict $ex) {
            $errors[] = 'Someone else changed this write-up after you opened it. Copy your text somewhere safe, <a href="highlight.php?id=' . e($pid) . '&amp;release=' . e($slug) . '">reload</a>, and try again.';
        } catch (HttpError $ex) {
            render_error($ex);
        }
        if (!$errors) {
            render_sent($url, $project, $github);
        }
    }
}

ob_start();
?>
<?= messages($errors) ?>
<?php if ($previewHtml !== null): ?>
<div class="preview"><p class="preview-label">Preview (not sent yet)</p><?= $previewHtml ?></div>
<?php endif ?>
<form method="get" action="highlight.php" class="editform">
  <input type="hidden" name="id" value="<?= e($pid) ?>">
  <label for="release">Release</label>
  <select id="release" name="release">
    <?php foreach ($project['releases'] as $r): ?>
    <option value="<?= e($r['slug']) ?>"<?= $r['slug'] === $slug ? ' selected' : '' ?>><?= e($r['title']) ?></option>
    <?php endforeach ?>
  </select>
  <button type="submit">Open</button>
</form>
<form method="post" action="highlight.php" class="editform">
  <button type="submit" name="do" value="preview" class="hidden-default" tabindex="-1" aria-hidden="true">Preview</button>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= e($pid) ?>">
  <input type="hidden" name="release" value="<?= e($slug) ?>">
  <input type="hidden" name="sha" value="<?= e($sha) ?>">
  <p class="note">Your own words about <b><?= e($release['title']) ?></b>: what's new and why it matters. It's shown above the release notes<?= $project['github'] ? ' from GitHub' : '' ?>. Leave it empty and send to remove it.</p>
  <label for="body">Write-up <span class="note">(Markdown)</span></label>
  <textarea id="body" name="body" rows="12" spellcheck="true"><?= e($text) ?></textarea>
  <p class="actions">
    <button type="submit" name="do" value="preview">Preview</button>
    <button type="submit" name="do" value="submit" class="primary">Send for review</button>
  </p>
</form>
<?php
render($project['name'] . ': release write-up', (string) ob_get_clean());
