<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
passthru('/opt/de_GWD/ui-pingTCPDOH1');
?>
