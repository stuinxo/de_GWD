<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$raw = trim((string)($_GET['FWD1index'] ?? $_POST['FWD1index'] ?? '1'));
if (!ctype_digit($raw) || intval($raw) < 1) {
    http_response_code(400);
    exit();
}
$FWD1index = intval($raw);

$de_GWDconf = gwd_read_conf_object();
$uuids = (isset($de_GWDconf->FORWARD->FWD1->uuid) && is_array($de_GWDconf->FORWARD->FWD1->uuid))
    ? array_values($de_GWDconf->FORWARD->FWD1->uuid)
    : [];
$idx = $FWD1index - 1;
if (!isset($uuids[$idx]) || !is_object($uuids[$idx]) || empty($uuids[$idx]->FWD1uuid) || !is_scalar($uuids[$idx]->FWD1uuid)) {
    http_response_code(404);
    exit('Not found');
}

$FWD1uuid = trim((string)$uuids[$idx]->FWD1uuid);
$domainRaw = $de_GWDconf->FORWARD->domain ?? ($de_GWDconf->address->serverName ?? '');
$serverDomain = is_scalar($domainRaw) ? trim((string)$domainRaw) : '';
$portRaw = $de_GWDconf->FORWARD->FWD1->port ?? '443';
$FWD1port = is_scalar($portRaw) && trim((string)$portRaw) !== '' ? trim((string)$portRaw) : '443';

$pathRaw = $de_GWDconf->FORWARD->FWD1->path ?? '';
$FWD1path = is_scalar($pathRaw) ? trim((string)$pathRaw) : '';
if ($FWD1path === '') {
    $first = explode('-', $FWD1uuid)[0];
    $FWD1path = '/' . $first;
}
if (substr($FWD1path, 0, 1) !== '/') {
    $FWD1path = '/' . $FWD1path;
}
$encPath = rawurlencode($FWD1path);

$link = "vless://{$FWD1uuid}@{$serverDomain}:{$FWD1port}?encryption=none&security=tls&sni={$serverDomain}&type=xhttp&path={$encPath}&mode=packet-up#{$serverDomain}:{$FWD1port}";
print($link);
?>
