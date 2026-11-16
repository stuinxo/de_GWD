<?php
require_once('../auth.php');
if (!isset($auth) || !$auth) {
    http_response_code(403);
    exit('Forbidden');
}

$map = [
    'gwd' => ['file' => 'de_GWD_bak', 'mime' => 'application/octet-stream'],
    'bitwarden' => ['file' => 'Bitwarden_bak.zip', 'mime' => 'application/zip'],
    'jellyfin' => ['file' => 'Jellyfin_bak.zip', 'mime' => 'application/zip'],
];

$type = (string)($_GET['type'] ?? '');
if (!isset($map[$type])) {
    http_response_code(400);
    exit('Invalid backup type');
}

$filename = $map[$type]['file'];
$path = '/opt/de_GWD/restore/' . $filename;
if (!is_file($path) || !is_readable($path)) {
    $legacyPath = __DIR__ . '/../restore/' . $filename;
    if (is_file($legacyPath) && is_readable($legacyPath)) {
        $path = $legacyPath;
    } else {
        http_response_code(404);
        exit('Backup file not found');
    }
}

header('Content-Type: ' . $map[$type]['mime']);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit();
?>
