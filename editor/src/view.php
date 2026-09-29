<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function avatar(array $user): string
{
    if (empty($user['avatar'])) {
        return '';
    }
    $src = $user['provider'] === 'github'
        ? $user['avatar']
        : 'https://cdn.discordapp.com/avatars/' . rawurlencode($user['id']) . '/' . rawurlencode($user['avatar']) . '.png?size=32';
    return '<img src="' . e($src) . '" alt="" width="16" height="16"> ';
}

function render(string $title, string $body): never
{
    $user = $_SESSION['user'] ?? null;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> - VivaHX editor</title>
<link rel="stylesheet" href="editor.css">
</head>
<body>
<div id="header"><a href="<?= e(site_url('/')) ?>"><img src="<?= e(site_url('/images/vivahx.gif')) ?>" width="420" height="85" alt="VivaHX"></a></div>
<div id="topbar">
  <a href="<?= e(site_url('/')) ?>">Back to VivaHX</a> |
  <a href="./">Your projects</a>
  <?php if ($user): ?>
    | <span class="who"><?= avatar($user) ?><?= e($user['name']) ?> <span class="note">(<?= $user['provider'] === 'github' ? 'GitHub' : 'Discord' ?>)</span></span>
    <form method="post" action="logout.php" class="inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button type="submit" class="linkbutton">Log out</button></form>
  <?php endif ?>
</div>
<div id="content">
  <h1><?= e($title) ?></h1>
  <?= $body ?>
</div>
</body>
</html>
    <?php
    exit;
}

function render_message(string $title, string $html): never
{
    render($title, $html);
}

function render_error(Throwable $e): never
{
    error_log('vivahx editor: ' . $e->getMessage() . ($e instanceof HttpError ? " [HTTP {$e->status}] {$e->body}" : ''));
    http_response_code(502);
    render('Something went wrong', '<p>' . e($e->getMessage()) . '</p><p>Please try again in a few minutes. If it keeps happening, let us know on the Hotline Discord.</p>');
}

/** Errors and notices at the top of a form. */
function messages(array $errors, string $notice = ''): string
{
    $html = '';
    if ($errors) {
        $html .= '<div class="errors" role="alert">' . implode('', array_map(fn ($m) => "<p>$m</p>", $errors)) . '</div>';
    }
    if ($notice !== '') {
        $html .= '<p class="notice" role="status">' . e($notice) . '</p>';
    }
    return $html;
}

/** "Thanks, it's been sent" after a pull request is opened. */
function render_sent(string $url, array $project, GitHub $github): never
{
    $log = !empty($github->dryRunLog) ? '<h2>Dry run</h2><pre>' . e(json_encode($github->dryRunLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>' : '';
    render('Sent for review', '<p>Thanks! Your change has been sent to the VivaHX maintainers. It appears on the site once one of them approves it.</p>'
        . '<p><a href="' . e($url) . '">See it on GitHub</a> &middot; <a href="./">Your projects</a> &middot; '
        . '<a href="' . e(site_url('/software/' . $project['id'] . '.html')) . '">' . e($project['name']) . ' on VivaHX</a></p>' . $log);
}
