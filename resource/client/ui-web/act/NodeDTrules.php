<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$CHNlistProxyIP = preg_split('/\s+/', trim((string)gwd_param('CHNlistProxyIP', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$globalProxyIP = preg_split('/\s+/', trim((string)gwd_param('globalProxyIP', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$directProxyIP = preg_split('/\s+/', trim((string)gwd_param('directProxyIP', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

gwd_update_conf(function (array $conf) use ($CHNlistProxyIP, $globalProxyIP, $directProxyIP): array {
    if (!isset($conf['v2nodeDIV']['nodeDT']) || !is_array($conf['v2nodeDIV']['nodeDT'])) {
        $conf['v2nodeDIV']['nodeDT'] = [];
    }
    $conf['v2nodeDIV']['nodeDT']['CHNlistProxyIP'] = array_values($CHNlistProxyIP);
    $conf['v2nodeDIV']['nodeDT']['globalProxyIP'] = array_values($globalProxyIP);
    $conf['v2nodeDIV']['nodeDT']['directProxyIP'] = array_values($directProxyIP);
    return $conf;
});

exec('sudo /opt/de_GWD/ui-NodeDT r >/dev/null 2>&1 &');
?>
<?php }?>