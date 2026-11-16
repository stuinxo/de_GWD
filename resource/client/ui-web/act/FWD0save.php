<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$FWD0port = trim(gwd_str_param('FWD0port', ''));
$rawList = gwd_param('FWD0uuidList', []);
if (!ctype_digit($FWD0port) || intval($FWD0port) < 1 || intval($FWD0port) > 65535 || !is_array($rawList)) {
    http_response_code(400);
    exit('Invalid forward0 configuration');
}

$FWD0uuidList = [];
foreach ($rawList as $item) {
    if (!is_array($item) || !isset($item['FWD0uuid']) || !is_scalar($item['FWD0uuid'])) {
        http_response_code(400);
        exit('Invalid UUID entry');
    }
    $uuid = trim((string)$item['FWD0uuid']);
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $uuid)) {
        http_response_code(400);
        exit('Invalid UUID format');
    }
    $mark = (isset($item['FWD0mark']) && is_scalar($item['FWD0mark']))
        ? trim(str_replace(["\r", "\n", "\0"], '', (string)$item['FWD0mark']))
        : '';
    $FWD0uuidList[] = ['FWD0uuid' => $uuid, 'FWD0mark' => $mark];
}
if (empty($FWD0uuidList)) {
    http_response_code(400);
    exit('Empty UUID list');
}

if (!gwd_update_conf(function (array $conf) use ($FWD0port, $FWD0uuidList): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    $conf['FORWARD']['FWD0'] = [
        'status' => 'on',
        'port' => $FWD0port,
        'uuid' => array_values($FWD0uuidList),
    ];
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-FWD0save r >/dev/null 2>&1 &');
?>
