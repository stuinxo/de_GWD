<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$CFdomain = trim((string)gwd_param('CFdomain', ''));
$CFtoken = trim((string)gwd_param('CFtoken', ''));

gwd_update_conf(function (array $conf) use ($CFdomain, $CFtoken): array {
    if (!isset($conf['ddns']) || !is_array($conf['ddns'])) {
        $conf['ddns'] = [];
    }
    unset($conf['ddns']['ddns3322']);
    if (!isset($conf['ddns']['ddnsCF']) || !is_array($conf['ddns']['ddnsCF'])) {
        $conf['ddns']['ddnsCF'] = [];
    }
    unset($conf['ddns']['ddnsCF']['cfAPIkey'], $conf['ddns']['ddnsCF']['cfEmail']);
    $conf['ddns']['ddnsCF']['cfDomain'] = $CFdomain;
    $conf['ddns']['ddnsCF']['cfToken'] = $CFtoken;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-ddnsCFon >/dev/null 2>&1 &');
?>
<?php }?>
