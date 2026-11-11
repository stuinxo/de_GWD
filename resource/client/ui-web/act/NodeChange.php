<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$nodenum = intval($_GET['nodenum'] ?? 0);
exec("sudo /opt/de_GWD/ui-NodeChange $nodenum &");
?>
<?php }?>
