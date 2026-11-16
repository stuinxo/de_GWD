<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$wanIP = exec('sudo /opt/de_GWD/ui-wanIP_cn');
if (filter_var($wanIP, FILTER_VALIDATE_IP)) {
    echo $wanIP;
}
?>
