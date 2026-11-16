<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_FILES["file"]["error"], $_FILES["file"]["name"], $_FILES["file"]["tmp_name"])
    || $_FILES["file"]["error"] !== UPLOAD_ERR_OK
    || (int)($_FILES["file"]["size"] ?? 0) <= 0
    || $_FILES["file"]["name"] !== "Bitwarden_bak.zip") {
    http_response_code(400);
    exit('Invalid backup file');
}
$restoreDir = is_dir('/opt/de_GWD/restore') ? '/opt/de_GWD/restore' : (__DIR__ . '/../restore');
if (!move_uploaded_file($_FILES['file']['tmp_name'], $restoreDir . '/Bitwarden_bak.zip')) {
    http_response_code(500);
    exit('Upload failed');
}
exec('sudo /opt/de_GWD/ui-restoreBitwarden >/dev/null 2>&1 &');
?>
