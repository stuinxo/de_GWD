<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$FWD1port = trim(gwd_str_param('FWD1port', ''));
$rawList = gwd_param('FWD1uuidList', []);
$FWD1upstream = trim(gwd_str_param('FWD1upstream', ''));

if (!ctype_digit($FWD1port)
    || intval($FWD1port) < 1
    || intval($FWD1port) > 65535
    || !is_array($rawList)
    || $FWD1upstream === ''
    || preg_match('/[\x00-\x1F\x7F]/', $FWD1upstream)) {
    http_response_code(400);
    exit('Invalid forward1 configuration');
}

$FWD1uuidList = [];
foreach ($rawList as $item) {
    if (!is_array($item) || !isset($item['FWD1uuid']) || !is_scalar($item['FWD1uuid'])) {
        http_response_code(400);
        exit('Invalid UUID entry');
    }
    $uuid = trim((string)$item['FWD1uuid']);
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $uuid)) {
        http_response_code(400);
        exit('Invalid UUID format');
    }
    $mark = (isset($item['FWD1mark']) && is_scalar($item['FWD1mark']))
        ? trim(str_replace(["\r", "\n", "\0"], '', (string)$item['FWD1mark']))
        : '';
    $FWD1uuidList[] = ['FWD1uuid' => $uuid, 'FWD1mark' => $mark];
}
if (empty($FWD1uuidList)) {
    http_response_code(400);
    exit('Empty UUID list');
}

if (!gwd_update_conf(function (array $conf) use ($FWD1port, $FWD1uuidList, $FWD1upstream): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    $conf['FORWARD']['FWD1'] = [
        'status' => 'on',
        'port' => $FWD1port,
        'uuid' => array_values($FWD1uuidList),
        'upstream' => $FWD1upstream,
    ];
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-FWD1save >/dev/null 2>&1 &');
?>
