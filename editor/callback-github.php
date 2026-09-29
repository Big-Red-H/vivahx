<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

// GitHub sends people back here after they sign in.
$state = (string) ($_GET['state'] ?? '');
if ($state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    render_message('Login expired', '<p>That login link expired. <a href="login.php?with=github">Try again</a>.</p>');
}
unset($_SESSION['oauth_state']);

if (!empty($_GET['error'])) {
    render_message('Login canceled', '<p>GitHub login was canceled. <a href="./">Try again</a> whenever you are ready.</p>');
}

try {
    $login = github_login();
    $token = $login->exchangeCode((string) ($_GET['code'] ?? ''));
    $profile = $login->user($token);
    $repos = $login->pushableRepos($token);
} catch (HttpError $e) {
    render_error($e);
}

session_regenerate_id(true);
$_SESSION['user'] = github_access([
    'provider' => 'github',
    'id' => (string) $profile['id'],
    'username' => (string) $profile['login'],
    'name' => (string) (($profile['name'] ?? '') ?: $profile['login']),
    'avatar' => $profile['avatar_url'] ?? null,
    'token' => $token,
    // GitHub OAuth App tokens don't expire; the session ends after 8 idle hours anyway.
    'token_expires' => PHP_INT_MAX,
], $repos);

$returnTo = $_SESSION['return_to'] ?? './';
unset($_SESSION['return_to']);
redirect(str_starts_with($returnTo, editor_path()) ? $returnTo : './');
