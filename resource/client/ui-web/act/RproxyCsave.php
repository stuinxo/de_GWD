<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$RproxyCtunnelAddress = trim((string)gwd_param('RproxyCtunnelAddress', ''));
$RproxyCtunnelUUID = trim((string)gwd_param('RproxyCtunnelUUID', ''));
$RproxyCmappingStatus = (string)gwd_param('RproxyCmappingStatus', '');
$RproxyC1List = gwd_param('RproxyC1List', []);

gwd_update_conf(function (array $conf) use ($RproxyCtunnelAddress, $RproxyCtunnelUUID, $RproxyCmappingStatus, $RproxyC1List): array {
    if (!isset($conf['FORWARD']['Rproxy']) || !is_array($conf['FORWARD']['Rproxy'])) {
        $conf['FORWARD']['Rproxy'] = [];
    }
    $client = [
        'status' => 'on',
        'tunnel' => [
            'address' => $RproxyCtunnelAddress,
            'uuid' => $RproxyCtunnelUUID,
        ],
        'mapping' => is_array($RproxyC1List) ? array_values($RproxyC1List) : [],
    ];
    if ($RproxyCmappingStatus === 'block') {
        $client['mappingStatus'] = 'on';
    }
    $conf['FORWARD']['Rproxy']['client'] = $client;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-RproxyCsave >/dev/null 2>&1 &');
?>
<?php }?>
