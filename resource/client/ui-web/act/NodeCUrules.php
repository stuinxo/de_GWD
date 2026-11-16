<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$customDomainRaw = (string)gwd_param('customDomain', '');
$customIPRaw = (string)gwd_param('customIP', '');

$customDomain = preg_split('/\r?\n/', $customDomainRaw);
$customDomain = is_array($customDomain) ? array_values(array_filter(array_map('trim', $customDomain))) : [];

$customIP = preg_split('/\r?\n/', $customIPRaw);
$customIP = is_array($customIP) ? array_values(array_filter(array_map('trim', $customIP))) : [];

gwd_update_conf(function (array $conf) use ($customDomain, $customIP): array {
    if (!isset($conf['v2nodeDIV']['nodeCU']) || !is_array($conf['v2nodeDIV']['nodeCU'])) {
        $conf['v2nodeDIV']['nodeCU'] = [];
    }
    $conf['v2nodeDIV']['nodeCU']['rulesDomain'] = $customDomain;
    $conf['v2nodeDIV']['nodeCU']['rulesIP'] = $customIP;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-NodeCU r >/dev/null 2>&1 &');
?>
<?php }?>
