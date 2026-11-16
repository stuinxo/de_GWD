<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$raw = trim((string)($_GET['speedT'] ?? $_POST['speedT'] ?? ''));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit();
}
$speedT = intval($raw);
passthru("timeout 25 sudo /opt/de_GWD/ui-speedT $speedT");
?>
