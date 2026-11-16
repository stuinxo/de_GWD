<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
gwd_update_conf(function (array $conf): array {
    if (!isset($conf['FORWARD']['FWD1']) || !is_array($conf['FORWARD']['FWD1'])) {
        $conf['FORWARD']['FWD1'] = [];
    }
    $conf['FORWARD']['FWD1']['status'] = 'off';
    return $conf;
});

exec('sudo /opt/de_GWD/ui-FWD1stop >/dev/null 2>&1 &');
?>
<?php }?>
