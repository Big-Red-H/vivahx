<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

// Discord sends people back here after they sign in.
$state = (string) ($_GET['state'] ?? '');
if ($state === '' || !hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
    render_message('Login expired', '<p>That login link expired. <a href="login.php">Try again</a>.</p>');
}
unset($_SESSION['oauth_state']);

if (!empty($_GET['error'])) {
    render_message('Login canceled', '<p>Discord login was canceled. <a href="./">Try again</a> whenever you are ready.</p>');
}

try {
    $discord = discord();
    $token = $discord->exchangeCode((string) ($_GET['code'] ?? ''));
    $profile = $discord->user($token['access_token']);
    $roles = $discord->memberRoles($token['access_token']);
} catch (HttpError $e) {
    render_error($e);
}

session_regenerate_id(true);
$_SESSION['user'] = discord_access([
    'provider' => 'discord',
    'id' => (string) $profile['id'],
    'username' => (string) $profile['username'],
    'name' => (string) ($profile['global_name'] ?? $profile['username']),
    'avatar' => $profile['avatar'] ?? null,
    'token' => $token['access_token'],
    'token_expires' => time() + (int) $token['expires_in'] - 60,
], $roles);

$returnTo = $_SESSION['return_to'] ?? './';
unset($_SESSION['return_to']);
redirect(str_starts_with($returnTo, editor_path()) ? $returnTo : './');
