<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$lockFp = @fopen(rtrim(sys_get_temp_dir(), '/\\') . '/de_GWD_wgkey.lock', 'c+');
if ($lockFp !== false) {
    @flock($lockFp, LOCK_EX);
}
exec('sudo /opt/de_GWD/ui-WGgenSkey >/dev/null 2>&1', $out, $rc);
if ($lockFp !== false) {
    @flock($lockFp, LOCK_UN);
    @fclose($lockFp);
}
if ($rc !== 0) {
    http_response_code(500);
    exit('Server key generation failed');
}
?>