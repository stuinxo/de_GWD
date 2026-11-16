<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$localip = trim((string)gwd_param('localip', ''));
$upstreamip = trim((string)gwd_param('upstreamip', ''));

gwd_update_conf(function (array $conf) use ($localip, $upstreamip): array {
    if (!isset($conf['address']) || !is_array($conf['address'])) {
        $conf['address'] = [];
    }
    $conf['address']['localIP'] = $localip;
    $conf['address']['upstreamIP'] = $upstreamip;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-reboot >/dev/null 2>&1 &');
?>
<?php }?>