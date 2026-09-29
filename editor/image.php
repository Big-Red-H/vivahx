<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

// Shows an uploaded screenshot that hasn't been sent yet, to the person who uploaded it.
require_login();
$image = null;
foreach ($_SESSION['uploads'] ?? [] as $form) {
    $image = $image ?? ($form[(string) ($_GET['id'] ?? '')] ?? null);
}
if (!$image || !is_file($image['file'])) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $image['mime']);
header('Cache-Control: private, max-age=3600');
readfile($image['file']);
