<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$updateAddr = trim(gwd_str_param('updateAddr', ''));
$updatePortRaw = trim(gwd_str_param('updatePort', '3000'));
$updateCMD = trim(gwd_str_param('updateCMD', ''));

$addrOk = (filter_var($updateAddr, FILTER_VALIDATE_IP) !== false)
    || (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $updateAddr);
$portOk = ctype_digit($updatePortRaw) && intval($updatePortRaw) >= 1 && intval($updatePortRaw) <= 65535;
$cmdOk = ($updateCMD !== '')
    && strlen($updateCMD) <= 1024
    && !preg_match('/[\x00-\x1F\x7F;`$]/', $updateCMD)
    && (preg_match('/^[A-Za-z0-9._-]+$/', $updateCMD) || preg_match('#https?://[^\s\'")<>]+#', $updateCMD));

if (!$addrOk || !$portOk || !$cmdOk) {
    http_response_code(400);
    exit('Invalid update parameters');
}

$updatePort = $updatePortRaw;
$updateToken = '';

if (!gwd_update_conf(function (array $conf) use ($updateAddr, $updatePort, $updateCMD, &$updateToken): array {
    if (!isset($conf['update']) || !is_array($conf['update'])) {
        $conf['update'] = [];
    }
    $conf['update']['updateAddr'] = $updateAddr;
    $conf['update']['updatePort'] = $updatePort;
    $conf['update']['updateCMD'] = $updateCMD;
    $existingToken = (string)($conf['update']['updateToken'] ?? '');
    if (!preg_match('/^[0-9a-f]{24,64}$/', $existingToken)) {
        $existingToken = bin2hex(random_bytes(16));
    }
    $conf['update']['updateToken'] = $existingToken;
    $updateToken = $existingToken;
    return $conf;
})) {
    http_response_code(500);
    exit('update conf failed');
}

exec('sudo /opt/de_GWD/ui-updateSave >/dev/null 2>&1', $out, $rc);
if ($rc !== 0) {
    http_response_code(502);
    exit('update save failed');
}

if (!filter_var($updateAddr, FILTER_VALIDATE_IP) && $updateAddr !== '') {
    $resolved = gethostbyname($updateAddr);
    if ($resolved !== $updateAddr) {
        $updateAddr = $resolved;
    }
}

if (filter_var($updateAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
    echo "[$updateAddr]:$updatePort/u_$updateToken/";
} else {
    echo "$updateAddr:$updatePort/u_$updateToken/";
}
?>
