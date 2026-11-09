<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$FWD1index = intval($_GET['FWD1index']);

$de_GWDconf = json_decode(file_get_contents('/opt/de_GWD/0conf'));
$serverDomain = isset($de_GWDconf->address->serverName) ? $de_GWDconf->address->serverName : '';
$FWD1port = isset($de_GWDconf->FORWARD->FWD1->port) ? $de_GWDconf->FORWARD->FWD1->port : '';
$FWD1uuid = isset($de_GWDconf->FORWARD->FWD1->uuid[$FWD1index-1]->FWD1uuid) ? $de_GWDconf->FORWARD->FWD1->uuid[$FWD1index-1]->FWD1uuid : '';

print("vmess://$FWD1uuid@$serverDomain:$FWD1port?encryption=auto&type=tcp&tfo=1#$serverDomain:$FWD1port");
?>
<?php }?>
