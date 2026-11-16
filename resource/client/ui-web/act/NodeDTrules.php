<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$parseIpTokens = function (string $raw): ?array {
    $tokens = preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($tokens)) {
        return [];
    }
    $clean = [];
    foreach ($tokens as $tok) {
        if (!preg_match('/^[0-9a-fA-F:.\/]+$/', $tok)) {
            return null;
        }
        $clean[] = $tok;
    }
    return array_values($clean);
};

$CHNlistProxyIP = $parseIpTokens(gwd_str_param('CHNlistProxyIP', ''));
$globalProxyIP = $parseIpTokens(gwd_str_param('globalProxyIP', ''));
$directProxyIP = $parseIpTokens(gwd_str_param('directProxyIP', ''));
if ($CHNlistProxyIP === null || $globalProxyIP === null || $directProxyIP === null) {
    http_response_code(400);
    exit('Invalid IP token');
}

if (!gwd_update_conf(function (array $conf) use ($CHNlistProxyIP, $globalProxyIP, $directProxyIP): array {
    if (!isset($conf['v2nodeDIV']['nodeDT']) || !is_array($conf['v2nodeDIV']['nodeDT'])) {
        $conf['v2nodeDIV']['nodeDT'] = [];
    }
    $conf['v2nodeDIV']['nodeDT']['CHNlistProxyIP'] = $CHNlistProxyIP;
    $conf['v2nodeDIV']['nodeDT']['globalProxyIP'] = $globalProxyIP;
    $conf['v2nodeDIV']['nodeDT']['directProxyIP'] = $directProxyIP;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-NodeDT r >/dev/null 2>&1 &');
?>