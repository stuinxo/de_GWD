<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$DoGc = trim(gwd_str_param('DoGc', ''));
$DoH1 = trim(gwd_str_param('DoH1', ''));
$DoH2 = trim(gwd_str_param('DoH2', ''));

if (preg_match('/[\s\x00-\x1F\x7F]/', $DoGc)
    || preg_match('/[\s\x00-\x1F\x7F]/', $DoH1)
    || preg_match('/[\s\x00-\x1F\x7F]/', $DoH2)) {
    http_response_code(400);
    exit('Invalid DNS upstream');
}

$chinaRaw = str_replace(["\r\n", "\r", "\n"], ' ', gwd_str_param('dnsChina', ''));
$chinaTokens = preg_split('/\s+/', trim($chinaRaw), -1, PREG_SPLIT_NO_EMPTY);
$chinaClean = [];
$skipped = 0;
if (is_array($chinaTokens)) {
    foreach ($chinaTokens as $tok) {
        $tok = trim($tok);
        if ($tok === '') {
            continue;
        }
        if (!preg_match('/^(?:\[[0-9a-fA-F:]+\](?::[0-9]{1,5})?|[0-9a-fA-F:]+|(?:[0-9]{1,3}\.){3}[0-9]{1,3}(?::[0-9]{1,5})?)$/', $tok)) {
            $skipped++;
            continue;
        }
        $chinaClean[] = $tok;
    }
}
$dnsChina = implode(' ', array_values(array_unique($chinaClean)));

// The textarea is pre-filled from ui-hostsCustomize, which prints "IP<TAB>domain",
// so tabs are legitimate separators; only other control characters are rejected.
// Invalid lines are skipped (and reported) instead of failing the whole save.
$hosts = [];
$lines = preg_split('/\r?\n/', trim(gwd_str_param('hostsCustomize', '')));
if (is_array($lines)) {
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $line)) {
            $skipped++;
            continue;
        }
        $parts = preg_split('/[\s,]+/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if (!isset($parts[0], $parts[1])
            || filter_var($parts[0], FILTER_VALIDATE_IP) === false
            || !preg_match('/^(?:\*\.)?(?:[A-Za-z0-9_-]+\.)*[A-Za-z0-9_-]+$/', $parts[1])) {
            $skipped++;
            continue;
        }
        $hosts[$parts[1]] = $parts[0];
    }
}

$DOHarray = [];
if ($DoH2 === '' && $DoH1 !== '') {
    $DoH2 = $DoH1;
}
array_push($DOHarray, $DoH1, $DoH2);

if (!gwd_update_conf(function (array $conf) use ($DoGc, $DOHarray, $dnsChina, $hosts): array {
    if (!isset($conf['dns']) || !is_array($conf['dns'])) {
        $conf['dns'] = [];
    }
    $conf['dns']['dog'] = $DoGc;
    $conf['dns']['doh'] = array_values($DOHarray);
    $conf['dns']['china'] = $dnsChina;
    $conf['dns']['hosts'] = $hosts;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui_4h r n >/dev/null 2>&1 &');
if ($skipped > 0) {
    echo "skipped $skipped";
}
?>