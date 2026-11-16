<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$localip = trim(gwd_str_param('localip', ''));
$upstreamip = trim(gwd_str_param('upstreamip', ''));

$ipParts = explode('/', $localip);
$localValid = false;
if (count($ipParts) === 1) {
    $localValid = (filter_var($ipParts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
} elseif (count($ipParts) === 2) {
    $localValid = (filter_var($ipParts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
        && ctype_digit($ipParts[1])
        && intval($ipParts[1]) >= 0
        && intval($ipParts[1]) <= 32;
}
$upstreamValid = (filter_var($upstreamip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
if (!$localValid || !$upstreamValid) {
    http_response_code(400);
    exit('Invalid IP configuration');
}

if (!gwd_update_conf(function (array $conf) use ($localip, $upstreamip): array {
    if (!isset($conf['address']) || !is_array($conf['address'])) {
        $conf['address'] = [];
    }
    $conf['address']['localIP'] = $localip;
    $conf['address']['upstreamIP'] = $upstreamip;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-reboot >/dev/null 2>&1 &');
?>