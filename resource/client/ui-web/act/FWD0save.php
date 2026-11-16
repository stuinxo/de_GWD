<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$FWD0port = trim((string)gwd_param('FWD0port', ''));
$FWD0uuidList = gwd_param('FWD0uuidList', []);
if (!is_array($FWD0uuidList)) {
    $FWD0uuidList = [];
}

gwd_update_conf(function (array $conf) use ($FWD0port, $FWD0uuidList): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    $conf['FORWARD']['FWD0'] = [
        'status' => 'on',
        'port' => $FWD0port,
        'uuid' => $FWD0uuidList,
    ];
    return $conf;
});

exec('sudo /opt/de_GWD/ui-FWD0save r >/dev/null 2>&1 &');
?>
<?php }?>
