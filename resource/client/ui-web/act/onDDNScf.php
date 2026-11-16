<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$CFdomain = trim(gwd_str_param('CFdomain', ''));
$CFtoken = trim(gwd_str_param('CFtoken', ''));

if (!preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/', $CFdomain)
    || $CFtoken === ''
    || preg_match('/[\s\x00-\x1F\x7F]/', $CFtoken)) {
    http_response_code(400);
    exit('Invalid Cloudflare configuration');
}

if (!gwd_update_conf(function (array $conf) use ($CFdomain, $CFtoken): array {
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
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-ddnsCFon >/dev/null 2>&1 &');
?>
