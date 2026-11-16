<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
if (!gwd_update_conf(function (array $conf): array {
    if (!isset($conf['v2nodeDIV']) || !is_array($conf['v2nodeDIV'])) {
        $conf['v2nodeDIV'] = [];
    }
    foreach (['nodeSM', 'nodeDT', 'nodeCU'] as $sub) {
        if (!isset($conf['v2nodeDIV'][$sub]) || !is_array($conf['v2nodeDIV'][$sub])) {
            $conf['v2nodeDIV'][$sub] = [];
        }
        $conf['v2nodeDIV'][$sub]['status'] = 'off';
    }
    if (isset($conf['v2nodeDIV']['ss']) && is_array($conf['v2nodeDIV']['ss'])) {
        $conf['v2nodeDIV']['ss']['status'] = 'off';
    }
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-NodeOne f r >/dev/null 2>&1 &');
?>