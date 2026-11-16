<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
gwd_update_conf(function (array $conf): array {
    if (!isset($conf['FORWARD']['Rproxy']['client']) || !is_array($conf['FORWARD']['Rproxy']['client'])) {
        $conf['FORWARD']['Rproxy']['client'] = [];
    }
    $conf['FORWARD']['Rproxy']['client']['status'] = 'off';
    return $conf;
});

exec('sudo /opt/de_GWD/ui-RproxyCstop >/dev/null 2>&1 &');
?>
<?php }?>
