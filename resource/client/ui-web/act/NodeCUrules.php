<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$customDomainRaw = gwd_str_param('customDomain', '');
$customIPRaw = gwd_str_param('customIP', '');

$customDomainLines = preg_split('/\r?\n/', $customDomainRaw);
$customDomain = [];
if (is_array($customDomainLines)) {
    foreach ($customDomainLines as $line) {
        $line = trim((string)preg_replace('/[\x00-\x1F\x7F]/', '', $line));
        if ($line !== '') {
            $customDomain[] = $line;
        }
    }
}

$customIPLines = preg_split('/\r?\n/', $customIPRaw);
$customIP = [];
if (is_array($customIPLines)) {
    foreach ($customIPLines as $line) {
        $line = trim((string)preg_replace('/[\x00-\x1F\x7F]/', '', $line));
        if ($line !== '') {
            $customIP[] = $line;
        }
    }
}

if (!gwd_update_conf(function (array $conf) use ($customDomain, $customIP): array {
    if (!isset($conf['v2nodeDIV']['nodeCU']) || !is_array($conf['v2nodeDIV']['nodeCU'])) {
        $conf['v2nodeDIV']['nodeCU'] = [];
    }
    $conf['v2nodeDIV']['nodeCU']['rulesDomain'] = array_values($customDomain);
    $conf['v2nodeDIV']['nodeCU']['rulesIP'] = array_values($customIP);
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-NodeCU r >/dev/null 2>&1 &');
?>
