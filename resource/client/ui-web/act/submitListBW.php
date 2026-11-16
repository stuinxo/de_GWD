<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$cleanLines = function (string $raw): array {
    $lines = preg_split('/\r?\n/', $raw);
    $out = [];
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim((string)preg_replace('/[\x00-\x1F\x7F]/', '', $line));
            if ($line !== '') {
                $out[] = $line;
            }
        }
    }
    return array_values($out);
};

$listB = $cleanLines(gwd_str_param('listB', ''));
$listW = $cleanLines(gwd_str_param('listW', ''));

if (!gwd_update_conf(function (array $conf) use ($listB, $listW): array {
    if (!isset($conf['dns']) || !is_array($conf['dns'])) {
        $conf['dns'] = [];
    }
    $conf['dns']['listB'] = $listB;
    $conf['dns']['listW'] = $listW;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-DNSsplit >/dev/null 2>&1 &');
?>