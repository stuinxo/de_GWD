<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$listB = preg_split('/\r?\n/', (string)gwd_param('listB', ''));
$listB = is_array($listB) ? array_values(array_filter(array_map('trim', $listB))) : [];

$listW = preg_split('/\r?\n/', (string)gwd_param('listW', ''));
$listW = is_array($listW) ? array_values(array_filter(array_map('trim', $listW))) : [];

gwd_update_conf(function (array $conf) use ($listB, $listW): array {
    if (!isset($conf['dns']) || !is_array($conf['dns'])) {
        $conf['dns'] = [];
    }
    $conf['dns']['listB'] = $listB;
    $conf['dns']['listW'] = $listW;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-DNSsplit >/dev/null 2>&1 &');
?>
<?php }?>