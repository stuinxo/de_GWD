<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$raw = trim((string)($_GET['FWD0index'] ?? $_POST['FWD0index'] ?? '1'));
if (!ctype_digit($raw) || intval($raw) < 1) {
    http_response_code(400);
    exit;
}
$FWD0index = intval($raw);

$de_GWDconf = gwd_read_conf_object();
$serverDomain = !empty($de_GWDconf->FORWARD->domain) ? $de_GWDconf->FORWARD->domain : ($de_GWDconf->address->serverName ?? '');
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
$encPath = rawurlencode($FWD0path);

$link = "vless://{$FWD0uuid}@{$serverDomain}:{$FWD0port}?encryption=none&security=tls&sni={$serverDomain}&type=xhttp&path={$encPath}&mode=packet-up#{$serverDomain}:{$FWD0port}";
print($link);
?>
<?php }?>
