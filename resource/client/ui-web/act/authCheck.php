<?php
require_once('../auth.php');
if (isset($auth) && $auth) {
    http_response_code(204);
    exit();
}
http_response_code(401);
exit('Unauthorized');
?>
