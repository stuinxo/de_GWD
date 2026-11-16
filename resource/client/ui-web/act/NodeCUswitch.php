<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
$switchNodeCU = gwd_str_param('switchNodeCU', '');
if ($switchNodeCU === 'NodeCUshow') {
    exec('sudo /opt/de_GWD/ui-NodeCU r');
} elseif ($switchNodeCU === 'NodeCUhide') {
    exec('sudo /opt/de_GWD/ui-NodeCUhide r');
} else {
    http_response_code(400);
    exit();
}

passthru('sudo /opt/de_GWD/ui-NodeCUcheck');
?>
