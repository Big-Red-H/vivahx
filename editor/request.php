<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

// "Please link me to this project": opens an issue for the maintainers.
require_post_with_csrf();
$user = require_login();
$project = project((string) ($_POST['project'] ?? ''));
$note = trim(str_replace(["\r", "\n"], ' ', (string) ($_POST['note'] ?? '')));
if (!$project || mb_strlen($note) < 3 || mb_strlen($note) > 300) {
    render_message('Something is missing', '<p>Please choose a project and say how you\'re involved. <a href="./">Go back</a>.</p>');
}
if (!within_rate_limit($user)) {
    render_message('Please wait a bit', '<p>You\'ve sent a lot in the last hour. Please try again later.</p>');
}

$field = $user['provider'] === 'github' ? 'github_editors' : 'discord_editors';
$value = $user['provider'] === 'github' ? $user['username'] : $user['id'];
$body = "Sent from the VivaHX editor by " . identity($user) . ".\n\n"
    . "They'd like to edit **" . str_replace(['*', '`'], '', $project['name']) . "** (`{$project['id']}`).\n\n"
    . "> " . str_replace(['<', '>'], ['&lt;', '&gt;'], $note) . "\n\n"
    . "To link them, set this in `projects/{$project['id']}/project.toml`:\n\n"
    . "```toml\n$field = [" . toml_string($value) . "]\n```\n"
    . ($user['provider'] === 'discord' ? "\nThey also need the Client, Server or Tracker Dev role on the Hotline Discord.\n" : '')
    . "\nClose this issue to decline.";
try {
    $url = github()->openIssue("Link {$user['name']} to {$project['name']}", $body, ['editor-access']);
} catch (HttpError $e) {
    render_error($e);
}
render('Request sent', '<p>Thanks! A maintainer will look at your request. Once you\'re linked, '
    . e($project['name']) . ' shows up on <a href="./">your projects</a>.</p><p><a href="' . e($url) . '">See your request on GitHub</a></p>');
