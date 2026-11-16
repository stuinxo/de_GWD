<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$DoGsPort = trim((string)gwd_param('DoGsPort', ''));

gwd_update_conf(function (array $conf) use ($DoGsPort): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    $conf['FORWARD']['DoGs'] = [
        'status' => 'on',
        'port' => $DoGsPort,
    ];
    return $conf;
});

exec('sudo /opt/de_GWD/ui-DoGsSave >/dev/null 2>&1 &');
?>
<?php }?>
