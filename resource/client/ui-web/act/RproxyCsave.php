<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$RproxyCtunnelAddress = trim(gwd_str_param('RproxyCtunnelAddress', ''));
$RproxyCtunnelUUID = trim(gwd_str_param('RproxyCtunnelUUID', ''));
$RproxyCmappingStatus = gwd_str_param('RproxyCmappingStatus', '');
$rawList = gwd_param('RproxyC1List', []);

$hostOnly = $RproxyCtunnelAddress;
$portValid = true;
if (preg_match('/^\[([^\]]+)\](?::([0-9]+))?$/', $RproxyCtunnelAddress, $m)) {
    $hostOnly = $m[1];
    if (isset($m[2]) && $m[2] !== '') {
        $portValid = ctype_digit($m[2]) && intval($m[2]) >= 1 && intval($m[2]) <= 65535;
    }
} elseif (preg_match('/^([^:]+):([0-9]+)$/', $RproxyCtunnelAddress, $m)) {
    $hostOnly = $m[1];
    $portValid = ctype_digit($m[2]) && intval($m[2]) >= 1 && intval($m[2]) <= 65535;
}
$hostValid = (filter_var($hostOnly, FILTER_VALIDATE_IP) !== false)
    || (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $hostOnly);

if ($RproxyCtunnelAddress === ''
    || !$hostValid
    || !$portValid
    || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $RproxyCtunnelUUID)
    || !is_array($rawList)) {
    http_response_code(400);
    exit('Invalid reverse proxy client configuration');
}

$RproxyC1List = [];
foreach ($rawList as $entry) {
    if (!is_array($entry)
        || !isset($entry['extPort'], $entry['extProtocol'], $entry['intIP'], $entry['intPort'])
        || !is_scalar($entry['extPort'])
        || !is_scalar($entry['extProtocol'])
        || !is_scalar($entry['intIP'])
        || !is_scalar($entry['intPort'])) {
        http_response_code(400);
        exit('Invalid mapping entry');
    }
    $extPort = trim((string)$entry['extPort']);
    $extProto = trim((string)$entry['extProtocol']);
    $intIP = trim((string)$entry['intIP']);
    $intPort = trim((string)$entry['intPort']);
    $intIpOk = (filter_var($intIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
        || (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $intIP);
    if (!ctype_digit($extPort) || intval($extPort) < 1 || intval($extPort) > 65535
        || !in_array($extProto, ['tcp', 'udp', 'tcp,udp'], true)
        || !$intIpOk
        || !ctype_digit($intPort) || intval($intPort) < 1 || intval($intPort) > 65535) {
        http_response_code(400);
        exit('Invalid mapping parameters');
    }
    $RproxyC1List[] = [
        'extPort' => $extPort,
        'extProtocol' => $extProto,
        'intIP' => $intIP,
        'intPort' => $intPort,
    ];
}

if (!gwd_update_conf(function (array $conf) use ($RproxyCtunnelAddress, $RproxyCtunnelUUID, $RproxyCmappingStatus, $RproxyC1List): array {
    if (!isset($conf['FORWARD']['Rproxy']) || !is_array($conf['FORWARD']['Rproxy'])) {
        $conf['FORWARD']['Rproxy'] = [];
    }
    $client = [
        'status' => 'on',
        'tunnel' => [
            'address' => $RproxyCtunnelAddress,
            'uuid' => $RproxyCtunnelUUID,
        ],
        'mapping' => array_values($RproxyC1List),
    ];
    if ($RproxyCmappingStatus === 'block') {
        $client['mappingStatus'] = 'on';
    }
    $conf['FORWARD']['Rproxy']['client'] = $client;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-RproxyCsave >/dev/null 2>&1 &');
?>
