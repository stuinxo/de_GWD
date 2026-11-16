<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$raw = trim((string)($_GET['pingTCP'] ?? $_POST['pingTCP'] ?? ''));
if (!ctype_digit($raw)) {
    http_response_code(400);
    die();
}
$pingTCP = intval($raw);
passthru("/opt/de_GWD/ui-pingTCP $pingTCP");
die();
?>
<?php }?>
