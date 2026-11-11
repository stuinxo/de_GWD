<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$speedT = intval($_GET['speedT'] ?? 0);
passthru("sudo /opt/de_GWD/ui-speedT $speedT");
?>
<?php }?>
