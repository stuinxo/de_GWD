<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
if (!gwd_start_ttyd('ui-installDocker')) {
    http_response_code(500);
    exit('Failed to start terminal');
}
?>
