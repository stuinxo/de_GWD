<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
gwd_update_conf(function (array $conf): array {
    if (!isset($conf['FORWARD']['DoGs']) || !is_array($conf['FORWARD']['DoGs'])) {
        $conf['FORWARD']['DoGs'] = [];
    }
    $conf['FORWARD']['DoGs']['status'] = 'off';
    return $conf;
});

exec('sudo /opt/de_GWD/ui-DoGsStop >/dev/null 2>&1 &');
?>
<?php }?>
