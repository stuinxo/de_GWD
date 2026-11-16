<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$updateAddr = trim((string)gwd_param('updateAddr', ''));
$updatePortRaw = trim((string)gwd_param('updatePort', '3000'));
$updatePort = (ctype_digit($updatePortRaw) && intval($updatePortRaw) >= 1 && intval($updatePortRaw) <= 65535) ? $updatePortRaw : '3000';
$updateCMD = trim((string)gwd_param('updateCMD', ''));
$updateToken = '';

gwd_update_conf(function (array $conf) use ($updateAddr, $updatePort, $updateCMD, &$updateToken): array {
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
});

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

echo "$updateAddr:$updatePort/u_$updateToken/";
?>
<?php }?>
