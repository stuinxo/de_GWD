<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$raw = trim(gwd_str_param('WGnum', ''));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit();
}
$WGnum = intval($raw);
if ($WGnum < 0 || $WGnum > 63) {
    http_response_code(400);
    exit();
}
$lockFp = @fopen(rtrim(sys_get_temp_dir(), '/\\') . '/de_GWD_wgkey.lock', 'c+');
if ($lockFp !== false) {
    @flock($lockFp, LOCK_EX);
}
$conf = gwd_read_conf_object();
$WGSkey = $conf->wireguard->server->sprivatekey ?? null;
if (empty($WGSkey)) {
    exec('sudo /opt/de_GWD/ui-WGgenSkey >/dev/null 2>&1', $skOut, $skRc);
    if ($skRc !== 0) {
        if ($lockFp !== false) {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
        }
        http_response_code(500);
        exit('Server key generation failed');
    }
}
exec("sudo /opt/de_GWD/ui-WGgenCkey $WGnum", $ckOut, $ckRc);
if ($lockFp !== false) {
    @flock($lockFp, LOCK_UN);
    @fclose($lockFp);
}
if ($ckRc !== 0) {
    http_response_code(500);
    exit('Client key generation failed');
}
echo implode("\n", $ckOut) . "\n";
?>
