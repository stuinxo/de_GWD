<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$raw = trim((string)($_GET['WGnum'] ?? $_POST['WGnum'] ?? '1'));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit();
}
$WGnum = intval($raw);
if ($WGnum < 1 || $WGnum > 64) {
    http_response_code(400);
    exit();
}
passthru("sudo cat /etc/wireguard/client{$WGnum}.conf");
?>
