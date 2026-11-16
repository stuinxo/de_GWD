<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$raw = trim(gwd_str_param('nodenum', '0'));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit();
}
$nodenum = intval($raw);
exec("sudo /opt/de_GWD/ui-NodeChange $nodenum >/dev/null 2>&1 &");
?>
