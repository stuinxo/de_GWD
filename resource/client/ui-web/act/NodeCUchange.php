<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$nodeCUnum = intval($_GET['nodeCUnum'] ?? 0);
exec("sudo /opt/de_GWD/ui-NodeCU r $nodeCUnum");
?>
<?php }?>
