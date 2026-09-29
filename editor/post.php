<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

// A news post about the developer's own project. It shows on the project's page and the front page.
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$action = $isPost ? (string) ($_POST['do'] ?? '') : '';
$user = require_login($action === 'submit');
$project = require_project($user, (string) ($_POST['id'] ?? $_GET['id'] ?? ''));
$pid = $project['id'];
$github = github();

$state = ['title' => '', 'body' => ''];
$errors = [];
$previewHtml = null;
if ($isPost) {
    require_post_with_csrf();
    $state['title'] = trim((string) ($_POST['title'] ?? ''));
    $state['body'] = str_replace("\r\n", "\n", (string) ($_POST['body'] ?? ''));
}

if ($action === 'preview') {
    try {
        $previewHtml = preview_html($state['body']);
    } catch (HttpError $ex) {
        $errors[] = 'The preview could not be shown right now, but you can still send your post.';
    }
}

if ($action === 'submit') {
    if (mb_strlen($state['title']) < 3 || mb_strlen($state['title']) > 120) {
        $errors[] = 'Please give the post a title (up to 120 characters).';
    }
    if (mb_strlen(trim($state['body'])) < 10 || mb_strlen($state['body']) > 20000) {
        $errors[] = 'Please write the post (up to 20,000 characters).';
    }
    if (!$errors && !within_rate_limit($user)) {
        $errors[] = "You've sent a lot in the last hour. Please wait a bit before sending more.";
    }
    if (!$errors) {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $path = 'news/' . gmdate('Y-m-d') . '-' . slug($pid . '-' . $state['title']) . '.md';
        $header = implode("\n", [
            '+++',
            'title = ' . toml_string($state['title']),
            "date = $now",
            'category = ' . toml_string($project['category']),
            'author = ' . toml_string($user['name']),
            'software = ' . toml_string($pid),
            'editor = ' . toml_string($user['provider'] . ':' . $user['id']),
            // Written in the editor, so only plain formatting is kept when it's shown.
            'trusted = false',
            '+++',
        ]);
        $body = "Sent from the VivaHX editor by " . identity($user) . ".\n\n"
            . "News post about **" . str_replace(['*', '`'], '', $project['name']) . "**: " . str_replace(['*', '`'], '', $state['title']) . "\n\n"
            . 'Check **Files changed**, then merge to publish or close to decline. The date is when it was written; change it in the file to publish it as today.';
        try {
            $url = $github->proposeEdit(branch_prefix($user) . gmdate('YmdHis'),
                [['path' => $path, 'content' => $header . "\n" . rtrim($state['body']) . "\n", 'sha' => null]],
                "News: {$state['title']}", $body, commit_author($user));
        } catch (EditConflict $ex) {
            $errors[] = 'A post with this title was already sent today. Please change the title.';
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
<form method="post" action="post.php" class="editform">
  <button type="submit" name="do" value="preview" class="hidden-default" tabindex="-1" aria-hidden="true">Preview</button>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= e($pid) ?>">
  <p class="note">A news post about <?= e($project['name']) ?>. It's filed under <?= e($project['category']) ?> and shows on the front page and on <a href="<?= e(site_url("/software/$pid.html")) ?>">the project's page</a>. New releases get a post by themselves; use <a href="highlight.php?id=<?= e($pid) ?>">Release write-up</a> to add to one.</p>
  <label for="title">Title</label>
  <input type="text" id="title" name="title" value="<?= e($state['title']) ?>" maxlength="120" required>
  <label for="body">Post <span class="note">(Markdown: **bold**, *italic*, [links](http://example.com), lists)</span></label>
  <textarea id="body" name="body" rows="14" spellcheck="true"><?= e($state['body']) ?></textarea>
  <p class="actions">
    <button type="submit" name="do" value="preview">Preview</button>
    <button type="submit" name="do" value="submit" class="primary">Send for review</button>
  </p>
</form>
<?php
render('News about ' . $project['name'], (string) ob_get_clean());
