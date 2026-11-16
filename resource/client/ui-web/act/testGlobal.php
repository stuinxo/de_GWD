<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
function onlineStatus() {
    $status = array("OFFLINE", "ONLINE");
    $handle = @fsockopen("youtube.com", 443, $errno, $errstr, 3);
    if ($handle) {
        fclose($handle);
        return $status[1];
    }
    return $status[0];
}

echo onlineStatus();
?>
