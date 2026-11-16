<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$ddnsCF = exec('sudo /opt/de_GWD/ui-checkDDNScf');
if ($ddnsCF === 'success') {
    echo 'installed';
}
?>
