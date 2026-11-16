<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$NFSlist = gwd_param('NFSlist', []);
if (!is_array($NFSlist)) {
    $NFSlist = [];
}

gwd_update_conf(function (array $conf) use ($NFSlist): array {
    if (!isset($conf['app']) || !is_array($conf['app'])) {
        $conf['app'] = [];
    }
    $conf['app']['NFS'] = $NFSlist;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-NFSon >/dev/null 2>&1 &');
?>
<?php }?>