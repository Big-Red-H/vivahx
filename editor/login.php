<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

if (is_dev()) {
    redirect($_SESSION['return_to'] ?? './');
}

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
if (($_GET['with'] ?? '') === 'github' && github_login_enabled()) {
    redirect(github_login()->authorizeUrl($_SESSION['oauth_state']));
}
if (discord_enabled()) {
    redirect(discord()->authorizeUrl($_SESSION['oauth_state']));
}
render_message('Login is not set up', '<p>Signing in isn\'t available yet.</p>');
