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
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    unset($conf['FORWARD']['APIkey'], $conf['FORWARD']['Email']);
    $conf['FORWARD']['domain'] = $CFdomain;
    $conf['FORWARD']['token'] = $CFtoken;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

if (!gwd_start_ttyd('ui-installCER')) {
    http_response_code(500);
    exit('Failed to start terminal');
}
?>
