<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$updateAddr = trim((string)($_GET['updateAddr'] ?? ''));
$updatePort = trim((string)($_GET['updatePort'] ?? '3000'));
$updateCMD = trim((string)($_GET['updateCMD'] ?? ''));

$conf = json_decode(file_get_contents('/opt/de_GWD/0conf'), true);
$conf['update']['updateAddr'] = $updateAddr;
$conf['update']['updatePort'] = $updatePort;
$conf['update']['updateCMD'] = $updateCMD;
$newJsonString = json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents('/opt/de_GWD/0conf', $newJsonString);

exec("sudo /opt/de_GWD/ui-updateSave >/dev/null 2>&1 &");

if (!filter_var($updateAddr, FILTER_VALIDATE_IP) && $updateAddr !== '') {
    $updateAddr = gethostbyname($updateAddr);
}

echo "$updateAddr:$updatePort";
?>
<?php }?>
