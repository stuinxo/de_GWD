<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$FWD1port = trim((string)gwd_param('FWD1port', ''));
$FWD1uuidList = gwd_param('FWD1uuidList', []);
if (!is_array($FWD1uuidList)) {
    $FWD1uuidList = [];
}
$FWD1upstream = trim((string)gwd_param('FWD1upstream', ''));

gwd_update_conf(function (array $conf) use ($FWD1port, $FWD1uuidList, $FWD1upstream): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    $conf['FORWARD']['FWD1'] = [
        'status' => 'on',
        'port' => $FWD1port,
        'uuid' => $FWD1uuidList,
        'upstream' => $FWD1upstream,
    ];
    return $conf;
});

exec('sudo /opt/de_GWD/ui-FWD1save >/dev/null 2>&1 &');
?>
<?php }?>
