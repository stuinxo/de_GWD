<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$raw = trim((string)($_GET['speedT'] ?? $_POST['speedT'] ?? ''));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit;
}
$speedT = intval($raw);
passthru("sudo /opt/de_GWD/ui-speedT $speedT");
?>
<?php }?>
