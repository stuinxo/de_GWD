<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$raw = trim((string)($_GET['WGnum'] ?? $_POST['WGnum'] ?? '1'));
if (!ctype_digit($raw) || intval($raw) < 1) {
    http_response_code(400);
    exit;
}
$WGnum = intval($raw);
passthru("sudo cat /etc/wireguard/client{$WGnum}.conf");
?>
<?php }?>
