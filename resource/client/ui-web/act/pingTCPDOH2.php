<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
passthru('/opt/de_GWD/ui-pingTCPDOH2');
die();
?>
<?php }?>
