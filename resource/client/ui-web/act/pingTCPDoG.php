<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
passthru('/opt/de_GWD/ui-pingTCPDoG');
die();
?>
<?php }?>
