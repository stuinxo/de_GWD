<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$WGaddress = trim(gwd_str_param('WGaddress', ''));
$WGport = trim(gwd_str_param('WGport', ''));
$sprivatekey = trim(gwd_str_param('sprivatekey', ''));
$spublickey = trim(gwd_str_param('spublickey', ''));
$rawClients = gwd_param('WGclientsList', []);

$isValidAddr = (filter_var($WGaddress, FILTER_VALIDATE_IP) !== false)
    || (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $WGaddress);
if (!$isValidAddr
    || !ctype_digit($WGport) || intval($WGport) < 1 || intval($WGport) > 65535
    || !preg_match('/^[A-Za-z0-9+\/]{42,43}=$/', $sprivatekey)
    || !preg_match('/^[A-Za-z0-9+\/]{42,43}=$/', $spublickey)
    || !is_array($rawClients)) {
    http_response_code(400);
    exit('Invalid WireGuard configuration');
}

$WGclientsList = [];
foreach ($rawClients as $client) {
    if (!is_array($client)) {
        http_response_code(400);
        exit('Invalid WireGuard client entry');
    }
    $cpriv = isset($client['cprivatekey']) && is_scalar($client['cprivatekey']) ? trim((string)$client['cprivatekey']) : '';
    $cpub = isset($client['cpublickey']) && is_scalar($client['cpublickey']) ? trim((string)$client['cpublickey']) : '';
    $mark = isset($client['mark']) && is_scalar($client['mark']) ? trim(str_replace(["\r", "\n", "\0"], '', (string)$client['mark'])) : '';
    if (($cpriv !== '' && !preg_match('/^[A-Za-z0-9+\/]{42,43}=$/', $cpriv))
        || ($cpub !== '' && !preg_match('/^[A-Za-z0-9+\/]{42,43}=$/', $cpub))) {
        http_response_code(400);
        exit('Invalid WireGuard client key');
    }
    $WGclientsList[] = [
        'cprivatekey' => $cpriv,
        'cpublickey' => $cpub,
        'mark' => $mark,
    ];
}

if (!gwd_update_conf(function (array $conf) use ($WGaddress, $WGport, $sprivatekey, $spublickey, $WGclientsList): array {
    $conf['wireguard'] = [
        'server' => [
            'WGaddress' => $WGaddress,
            'WGport' => $WGport,
            'sprivatekey' => $sprivatekey,
            'spublickey' => $spublickey,
        ],
        'clients' => array_values($WGclientsList),
    ];
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-WGon >/dev/null 2>&1 &');
?>