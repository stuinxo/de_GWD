<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$FWD0index = intval($_GET['FWD0index']);

$de_GWDconf = json_decode(file_get_contents('/opt/de_GWD/0conf'));
$serverDomain = isset($de_GWDconf->address->serverName) ? $de_GWDconf->address->serverName : '';
$FWD0port = isset($de_GWDconf->FORWARD->FWD0->port) ? $de_GWDconf->FORWARD->FWD0->port : '443';
$FWD0uuid = isset($de_GWDconf->FORWARD->FWD0->uuid[$FWD0index-1]->FWD0uuid) ? $de_GWDconf->FORWARD->FWD0->uuid[$FWD0index-1]->FWD0uuid : '';

$FWD0path = isset($de_GWDconf->FORWARD->FWD0->path) ? $de_GWDconf->FORWARD->FWD0->path : '';
if (empty($FWD0path)) {
    $first = explode('-', $FWD0uuid)[0];
    $FWD0path = '/' . $first;
}
if (substr($FWD0path, 0, 1) !== '/') {
    $FWD0path = '/' . $FWD0path;
}

$link = "vless://{$FWD0uuid}@{$serverDomain}:{$FWD0port}?encryption=none&security=tls&sni={$serverDomain}&type=xhttp&path={$FWD0path}&mode=packet-up#{$serverDomain}:{$FWD0port}";
print($link);
?>
<?php }?>
