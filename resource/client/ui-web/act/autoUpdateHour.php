<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$autoUpdateHourINDEX = trim(gwd_str_param('autoUpdateHourINDEX', 'off'));
if ($autoUpdateHourINDEX !== 'off' && (!ctype_digit($autoUpdateHourINDEX) || (int)$autoUpdateHourINDEX < 0 || (int)$autoUpdateHourINDEX > 23)) {
    http_response_code(400);
    exit();
}

if (!gwd_update_conf(function (array $conf) use ($autoUpdateHourINDEX): array {
    if (!isset($conf['address']) || !is_array($conf['address'])) {
        $conf['address'] = [];
    }
    $conf['address']['autoUpdateHour'] = $autoUpdateHourINDEX;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-autoUpdateHour ' . escapeshellarg($autoUpdateHourINDEX) . ' >/dev/null 2>&1 &');
?>
