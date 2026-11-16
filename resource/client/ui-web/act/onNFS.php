<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$rawList = gwd_param('NFSlist', []);
if (!is_array($rawList)) {
    http_response_code(400);
    exit('Invalid NFS list');
}

// Invalid entries are skipped (and reported) instead of rejecting the whole list.
// The same rules are enforced again by ui-NFSon when writing /etc/auto.nfs.
$NFSlist = [];
$skipped = 0;
foreach ($rawList as $entry) {
    if (!is_array($entry)
        || !isset($entry['NFSpoint'], $entry['NFSserver'], $entry['NFSaddress'])
        || !is_scalar($entry['NFSpoint'])
        || !is_scalar($entry['NFSserver'])
        || !is_scalar($entry['NFSaddress'])) {
        $skipped++;
        continue;
    }
    $pt = trim((string)$entry['NFSpoint']);
    $srv = trim((string)$entry['NFSserver']);
    $addr = trim((string)$entry['NFSaddress']);
    $lower = strtolower($srv);
    $isValidIpv4 = (filter_var($srv, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
    $isValidHost = (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $srv);
    if (!preg_match('/^\/mnt\/[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)*$/', $pt)
        || $srv === ''
        || (!$isValidIpv4 && !$isValidHost)
        || $lower === 'localhost'
        || strpos($lower, '127.') === 0
        || $lower === '0.0.0.0'
        || !preg_match('/^\/[^\s\x00-\x1F\x7F]*$/', $addr)) {
        $skipped++;
        continue;
    }
    $NFSlist[] = [
        'NFSpoint' => $pt,
        'NFSserver' => $srv,
        'NFSaddress' => $addr,
    ];
}

// Do not silently drop every mount because all submitted entries were invalid.
if (count($rawList) > 0 && count($NFSlist) === 0) {
    http_response_code(400);
    exit('All NFS entries are invalid');
}

if (!gwd_update_conf(function (array $conf) use ($NFSlist): array {
    if (!isset($conf['app']) || !is_array($conf['app'])) {
        $conf['app'] = [];
    }
    $conf['app']['NFS'] = array_values($NFSlist);
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-NFSon >/dev/null 2>&1 &');
if ($skipped > 0) {
    echo "skipped $skipped";
}
?>
