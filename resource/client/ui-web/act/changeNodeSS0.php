<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
gwd_update_conf(function (array $conf): array {
    if (!isset($conf['v2nodeDIV']) || !is_array($conf['v2nodeDIV'])) {
        $conf['v2nodeDIV'] = [];
    }
    $conf['v2nodeDIV']['ss'] = [];
    return $conf;
});

exec('sudo /opt/de_GWD/ui-changeNodeSS0 >/dev/null 2>&1 &');
?>
<?php }?>