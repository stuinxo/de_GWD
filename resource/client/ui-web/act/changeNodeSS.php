<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$ssAddress = trim((string)gwd_param('ssAddress', ''));
$ssPort = trim((string)gwd_param('ssPort', ''));
$ssUUID = trim((string)gwd_param('ssUUID', ''));
$ssSNI = trim((string)gwd_param('ssSNI', ''));
$ssPBK = trim((string)gwd_param('ssPBK', ''));
$ssSID = trim((string)gwd_param('ssSID', ''));

if (preg_match('/^([^:]+):([0-9]+)$/', $ssAddress, $m)) {
    if ($ssPort === '') {
        $ssPort = $m[2];
    }
    $ssAddress = $m[1];
}
if ($ssPort === '') {
    $ssPort = '443';
}

if ($ssAddress === '' || $ssUUID === '' || $ssPBK === '' || !ctype_digit($ssPort) || intval($ssPort) < 1 || intval($ssPort) > 65535) {
    http_response_code(400);
    echo "invalid";
    exit;
}

gwd_update_conf(function (array $conf) use ($ssAddress, $ssPort, $ssUUID, $ssSNI, $ssPBK, $ssSID): array {
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
});

exec('sudo /opt/de_GWD/ui-changeNodeSS r >/dev/null 2>&1 &');
echo "ok";
?>
<?php }?>
