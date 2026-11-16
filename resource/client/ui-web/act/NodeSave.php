<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$rawList = gwd_param('nodeList', []);
if (!is_array($rawList)) {
    http_response_code(400);
    exit('Invalid node list');
}

// Invalid rows are skipped (and reported) instead of rejecting the whole save,
// so one legacy/malformed entry cannot block editing the rest of the list.
$nodeList = [];
$skipped = 0;
foreach ($rawList as $node) {
    if (!is_array($node)
        || !isset($node['domain'], $node['name'], $node['uuid'])
        || !is_scalar($node['domain'])
        || !is_scalar($node['name'])
        || !is_scalar($node['uuid'])) {
        $skipped++;
        continue;
    }
    $domain = trim((string)$node['domain']);
    $tls = (isset($node['tls']) && is_scalar($node['tls'])) ? trim((string)$node['tls']) : '';
    $name = trim(str_replace(["\r", "\n", "\0"], '', (string)$node['name']));
    $uuid = trim((string)$node['uuid']);
    $path = (isset($node['path']) && is_scalar($node['path'])) ? trim((string)$node['path']) : '';
    if ($domain === ''
        || preg_match('/[\s\x00-\x1F\x7F]/', $domain)
        || preg_match('/[\s\x00-\x1F\x7F]/', $tls)
        || $name === ''
        || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $uuid)
        || preg_match('/[\s\x00-\x1F\x7F]/', $path)) {
        $skipped++;
        continue;
    }
    $nodeList[] = [
        'domain' => $domain,
        'tls' => $tls,
        'name' => $name,
        'uuid' => $uuid,
        'path' => $path,
    ];
}

// Never wipe the node list because every submitted row was invalid.
if (count($rawList) > 0 && count($nodeList) === 0) {
    http_response_code(400);
    exit('All node entries are invalid');
}

if (!gwd_update_conf(function (array $conf) use ($nodeList): array {
    $conf['v2node'] = array_values($nodeList);
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-NodeSave r >/dev/null 2>&1 &');
if ($skipped > 0) {
    echo "skipped $skipped";
}
?>
