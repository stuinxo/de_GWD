<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
gwd_update_conf(function (array $conf): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    $conf['FORWARD']['block53'] = 'on';
    return $conf;
});

exec('sudo /opt/de_GWD/ui-block53on >/dev/null 2>&1 &');
?>
<?php }?>
