<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$WGnum = intval($_GET['WGnum'] ?? 1);
if ($WGnum >= 1) {
    passthru("sudo cat /etc/wireguard/client{$WGnum}.conf");
}
?>
<?php }?>
