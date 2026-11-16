<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
print (string)shell_exec('sudo /opt/de_GWD/ui-NodeSMcheck');
?>
