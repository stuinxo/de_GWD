<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$raw = trim((string)($_GET['FWD0index'] ?? $_POST['FWD0index'] ?? '1'));
if (!ctype_digit($raw) || intval($raw) < 1) {
    http_response_code(400);
    exit();
}
$FWD0index = intval($raw);

$de_GWDconf = gwd_read_conf_object();
$uuids = (isset($de_GWDconf->FORWARD->FWD0->uuid) && is_array($de_GWDconf->FORWARD->FWD0->uuid))
    ? array_values($de_GWDconf->FORWARD->FWD0->uuid)
    : [];
$idx = $FWD0index - 1;
if (!isset($uuids[$idx]) || !is_object($uuids[$idx]) || empty($uuids[$idx]->FWD0uuid) || !is_scalar($uuids[$idx]->FWD0uuid)) {
    http_response_code(404);
    exit('Not found');
}

$FWD0uuid = trim((string)$uuids[$idx]->FWD0uuid);
$domainRaw = $de_GWDconf->FORWARD->domain ?? ($de_GWDconf->address->serverName ?? '');
$serverDomain = is_scalar($domainRaw) ? trim((string)$domainRaw) : '';
$portRaw = $de_GWDconf->FORWARD->FWD0->port ?? '443';
$FWD0port = is_scalar($portRaw) && trim((string)$portRaw) !== '' ? trim((string)$portRaw) : '443';

$pathRaw = $de_GWDconf->FORWARD->FWD0->path ?? '';
$FWD0path = is_scalar($pathRaw) ? trim((string)$pathRaw) : '';
if ($FWD0path === '') {
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
