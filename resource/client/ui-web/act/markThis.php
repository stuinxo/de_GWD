<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$markThis = trim(str_replace(["\r", "\n", "\0"], '', gwd_str_param('markName', '')));
$aliasLen = function_exists('mb_strlen') ? mb_strlen($markThis, 'UTF-8') : strlen($markThis);
if ($aliasLen > 64) {
    http_response_code(400);
    exit('Alias too long');
}

if (!gwd_update_conf(function (array $conf) use ($markThis): array {
    if (!isset($conf['address']) || !is_array($conf['address'])) {
        $conf['address'] = [];
    }
    $conf['address']['alias'] = $markThis;
    return $conf;
})) {
    http_response_code(500);
    exit('Config write failed');
}

exec('sudo /opt/de_GWD/ui-markThis >/dev/null 2>&1 &');
?>