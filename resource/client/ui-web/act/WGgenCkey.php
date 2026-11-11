<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$WGnum = intval($_GET['WGnum'] ?? 0);
$conf = json_decode(file_get_contents('/opt/de_GWD/0conf'), true);

$WGSkey = $conf['wireguard']['server']['sprivatekey'] ?? null;

if (empty($WGSkey)) exec("sudo /opt/de_GWD/ui-WGgenSkey");

passthru("sudo /opt/de_GWD/ui-WGgenCkey $WGnum");
?>
<?php }?>
