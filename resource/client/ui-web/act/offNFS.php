<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$NFSpoint = trim(gwd_str_param('NFSpoint', ''));
if (!preg_match('/^\/mnt\/[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)*$/', $NFSpoint)) {
    http_response_code(400);
    exit('Invalid mount point');
}
exec('sudo /opt/de_GWD/ui-NFSoff ' . escapeshellarg($NFSpoint) . ' >/dev/null 2>&1 &');
?>
