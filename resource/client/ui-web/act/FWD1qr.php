<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$FWD1index = intval($_GET['FWD1index']);

$de_GWDconf = json_decode(file_get_contents('/opt/de_GWD/0conf'));
$serverDomain = isset($de_GWDconf->address->serverName) ? $de_GWDconf->address->serverName : '';
$FWD1port = isset($de_GWDconf->FORWARD->FWD1->port) ? $de_GWDconf->FORWARD->FWD1->port : '443';
$FWD1uuid = isset($de_GWDconf->FORWARD->FWD1->uuid[$FWD1index-1]->FWD1uuid) ? $de_GWDconf->FORWARD->FWD1->uuid[$FWD1index-1]->FWD1uuid : '';

$FWD1path = isset($de_GWDconf->FORWARD->FWD1->path) ? $de_GWDconf->FORWARD->FWD1->path : '';
if (empty($FWD1path)) {
    $first = explode('-', $FWD1uuid)[0];
    $FWD1path = '/' . $first;
}
if (substr($FWD1path, 0, 1) !== '/') {
    $FWD1path = '/' . $FWD1path;
}

$link = "vless://{$FWD1uuid}@{$serverDomain}:{$FWD1port}?encryption=none&security=tls&sni={$serverDomain}&type=xhttp&path={$FWD1path}&mode=packet-up#{$serverDomain}:{$FWD1port}";
print($link);
?>
<?php }?>
