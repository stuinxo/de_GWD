<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$DoGsPort = trim(gwd_str_param('DoGsPort', ''));
if (!ctype_digit($DoGsPort) || intval($DoGsPort) < 1 || intval($DoGsPort) > 65535) {
    http_response_code(400);
    exit('Invalid port');
}

if (!gwd_update_conf(function (array $conf) use ($DoGsPort): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    $conf['FORWARD']['DoGs'] = [
        'status' => 'on',
        'port' => $DoGsPort,
    ];
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-DoGsSave >/dev/null 2>&1 &');
?>
