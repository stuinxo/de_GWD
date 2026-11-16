<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
exec('sudo /opt/de_GWD/ui-installZ >/dev/null 2>&1 &');
?>
