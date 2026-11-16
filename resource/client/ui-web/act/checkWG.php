<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$checkWG = exec('sudo /opt/de_GWD/ui-checkWG');
if (file_exists('/usr/bin/wg')) {
    echo 'installed';
}
echo ' ';
if ($checkWG === 'success') {
    echo 'on';
}
?>
