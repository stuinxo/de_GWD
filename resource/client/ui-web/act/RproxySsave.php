<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$RproxyStunnelPort = trim(gwd_str_param('RproxyStunnelPort', ''));
$RproxyStunnelUUID = trim(gwd_str_param('RproxyStunnelUUID', ''));
$RproxySmappingStatus = gwd_str_param('RproxySmappingStatus', '');
$rawList = gwd_param('RproxyS1List', []);

if (!ctype_digit($RproxyStunnelPort)
    || intval($RproxyStunnelPort) < 1
    || intval($RproxyStunnelPort) > 65535
    || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $RproxyStunnelUUID)
    || !is_array($rawList)) {
    http_response_code(400);
    exit('Invalid reverse proxy server configuration');
}

$RproxyS1List = [];
foreach ($rawList as $entry) {
    if (!is_array($entry)
        || !isset($entry['port'], $entry['protocol'])
        || !is_scalar($entry['port'])
        || !is_scalar($entry['protocol'])) {
        http_response_code(400);
        exit('Invalid mapping entry');
    }
    $port = trim((string)$entry['port']);
    $proto = trim((string)$entry['protocol']);
    if (!ctype_digit($port) || intval($port) < 1 || intval($port) > 65535
        || !in_array($proto, ['tcp', 'udp', 'tcp,udp'], true)) {
        http_response_code(400);
        exit('Invalid mapping port or protocol');
    }
    $RproxyS1List[] = ['port' => $port, 'protocol' => $proto];
}

if (!gwd_update_conf(function (array $conf) use ($RproxyStunnelPort, $RproxyStunnelUUID, $RproxySmappingStatus, $RproxyS1List): array {
    if (!isset($conf['FORWARD']['Rproxy']) || !is_array($conf['FORWARD']['Rproxy'])) {
        $conf['FORWARD']['Rproxy'] = [];
    }
    $server = [
        'status' => 'on',
        'tunnel' => [
            'port' => $RproxyStunnelPort,
            'uuid' => $RproxyStunnelUUID,
        ],
        'mapping' => array_values($RproxyS1List),
    ];
    if ($RproxySmappingStatus === 'block') {
        $server['mappingStatus'] = 'on';
    }
    $conf['FORWARD']['Rproxy']['server'] = $server;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-RproxySsave >/dev/null 2>&1 &');
?>
