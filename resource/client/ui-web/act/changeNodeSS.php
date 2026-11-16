<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$ssAddress = trim(gwd_str_param('ssAddress', ''));
$ssPort = trim(gwd_str_param('ssPort', ''));
$ssUUID = trim(gwd_str_param('ssUUID', ''));
$ssSNI = trim(gwd_str_param('ssSNI', ''));
$ssPBK = trim(gwd_str_param('ssPBK', ''));
$ssSID = trim(gwd_str_param('ssSID', ''));

if (preg_match('/^\[([^\]]+)\](?::([0-9]+))?$/', $ssAddress, $m)) {
    if ($ssPort === '' && isset($m[2]) && $m[2] !== '') {
        $ssPort = $m[2];
    }
    $ssAddress = $m[1];
} elseif (preg_match('/^([^:]+):([0-9]+)$/', $ssAddress, $m)) {
    if ($ssPort === '') {
        $ssPort = $m[2];
    }
    $ssAddress = $m[1];
}
if ($ssPort === '') {
    $ssPort = '443';
}

$addrOk = (filter_var($ssAddress, FILTER_VALIDATE_IP) !== false)
    || (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $ssAddress);
$sniOk = ($ssSNI === '')
    || (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $ssSNI);

if ($ssAddress === ''
    || !$addrOk
    || !ctype_digit($ssPort) || intval($ssPort) < 1 || intval($ssPort) > 65535
    || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $ssUUID)
    || !$sniOk
    || !preg_match('/^[A-Za-z0-9+\/_=-]{20,64}$/', $ssPBK)
    || !preg_match('/^[0-9a-fA-F]{0,16}$/', $ssSID)) {
    http_response_code(400);
    echo "invalid";
    exit();
}

if (!gwd_update_conf(function (array $conf) use ($ssAddress, $ssPort, $ssUUID, $ssSNI, $ssPBK, $ssSID): array {
    if (!isset($conf['v2nodeDIV']) || !is_array($conf['v2nodeDIV'])) {
        $conf['v2nodeDIV'] = [];
    }
    $conf['v2nodeDIV']['ss'] = [
        'status' => 'on',
        'ssAddress' => $ssAddress,
        'ssPort' => $ssPort,
        'ssUUID' => $ssUUID,
        'ssSNI' => $ssSNI,
        'ssPBK' => $ssPBK,
        'ssSID' => $ssSID,
    ];
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-changeNodeSS r >/dev/null 2>&1 &');
echo "ok";
?>
