<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$WGaddress = trim((string)gwd_param('WGaddress', ''));
$WGport = trim((string)gwd_param('WGport', ''));
$sprivatekey = trim((string)gwd_param('sprivatekey', ''));
$spublickey = trim((string)gwd_param('spublickey', ''));
$WGclientsList = gwd_param('WGclientsList', []);
if (!is_array($WGclientsList)) {
    $WGclientsList = [];
}

gwd_update_conf(function (array $conf) use ($WGaddress, $WGport, $sprivatekey, $spublickey, $WGclientsList): array {
    $conf['wireguard'] = [
        'server' => [
            'WGaddress' => $WGaddress,
            'WGport' => $WGport,
            'sprivatekey' => $sprivatekey,
            'spublickey' => $spublickey,
        ],
        'clients' => $WGclientsList,
    ];
    return $conf;
});

exec('sudo /opt/de_GWD/ui-WGon >/dev/null 2>&1 &');
?>
<?php }?>