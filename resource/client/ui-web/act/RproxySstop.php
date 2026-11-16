<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
if (!gwd_update_conf(function (array $conf): array {
    if (!isset($conf['FORWARD']['Rproxy']['server']) || !is_array($conf['FORWARD']['Rproxy']['server'])) {
        $conf['FORWARD']['Rproxy']['server'] = [];
    }
    $conf['FORWARD']['Rproxy']['server']['status'] = 'off';
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-RproxySstop >/dev/null 2>&1 &');
?>
