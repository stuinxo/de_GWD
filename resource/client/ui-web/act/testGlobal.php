<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
function onlineStatus(){
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
<?php }?>
