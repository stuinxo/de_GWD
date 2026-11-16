<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$RproxyStunnelPort = trim((string)gwd_param('RproxyStunnelPort', ''));
$RproxyStunnelUUID = trim((string)gwd_param('RproxyStunnelUUID', ''));
$RproxySmappingStatus = (string)gwd_param('RproxySmappingStatus', '');
$RproxyS1List = gwd_param('RproxyS1List', []);

gwd_update_conf(function (array $conf) use ($RproxyStunnelPort, $RproxyStunnelUUID, $RproxySmappingStatus, $RproxyS1List): array {
    if (!isset($conf['FORWARD']['Rproxy']) || !is_array($conf['FORWARD']['Rproxy'])) {
        $conf['FORWARD']['Rproxy'] = [];
    }
    $server = [
        'status' => 'on',
        'tunnel' => [
            'port' => $RproxyStunnelPort,
            'uuid' => $RproxyStunnelUUID,
        ],
        'mapping' => is_array($RproxyS1List) ? array_values($RproxyS1List) : [],
    ];
    if ($RproxySmappingStatus === 'block') {
        $server['mappingStatus'] = 'on';
    }
    $conf['FORWARD']['Rproxy']['server'] = $server;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-RproxySsave >/dev/null 2>&1 &');
?>
<?php }?>
