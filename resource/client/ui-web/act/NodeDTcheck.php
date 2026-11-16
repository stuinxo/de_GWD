<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
passthru('sudo /opt/de_GWD/ui-NodeDTcheck');
?>
