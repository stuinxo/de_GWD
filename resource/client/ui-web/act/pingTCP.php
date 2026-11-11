<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$pingTCP = intval($_GET['pingTCP'] ?? 0);
passthru("/opt/de_GWD/ui-pingTCP $pingTCP");
die();
?>
<?php }?>
