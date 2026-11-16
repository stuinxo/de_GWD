<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$DoGc = trim((string)gwd_param('DoGc', ''));
$DoH1 = trim((string)gwd_param('DoH1', ''));
$DoH2 = trim((string)gwd_param('DoH2', ''));

$dnsChina = str_replace(["\r\n", "\r", "\n"], ' ', (string)gwd_param('dnsChina', ''));
$dnsChina = trim((string)preg_replace('/\s+/', ' ', $dnsChina));

$hosts = [];
$lines = preg_split('/\r?\n/', trim((string)gwd_param('hostsCustomize', '')));
if (is_array($lines)) {
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = preg_split('/[\s,]+/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if (isset($parts[0], $parts[1]) && $parts[0] !== '' && $parts[1] !== '') {
            $hosts[$parts[1]] = $parts[0];
        }
    }
}

$DOHarray = [];
if ($DoH2 === '' && $DoH1 !== '') {
    $DoH2 = $DoH1;
}
array_push($DOHarray, $DoH1, $DoH2);

gwd_update_conf(function (array $conf) use ($DoGc, $DOHarray, $dnsChina, $hosts): array {
    if (!isset($conf['dns']) || !is_array($conf['dns'])) {
        $conf['dns'] = [];
    }
    $conf['dns']['dog'] = $DoGc;
    $conf['dns']['doh'] = $DOHarray;
    $conf['dns']['china'] = $dnsChina;
    $conf['dns']['hosts'] = $hosts;
    return $conf;
});

exec('sudo /opt/de_GWD/ui_4h r n >/dev/null 2>&1 &');
?>
<?php }?>