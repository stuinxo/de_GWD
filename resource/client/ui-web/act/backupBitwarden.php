<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);
exec('sudo /opt/de_GWD/ui-backup bitwarden', $out, $rc);
if ($rc !== 0) {
    http_response_code(500);
    exit('Backup failed');
}
?>
