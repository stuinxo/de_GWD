<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$ssAddress = trim($_GET['ssAddress'] ?? '');
$ssPort = trim($_GET['ssPort'] ?? '');
$ssUUID = trim($_GET['ssUUID'] ?? '');
$ssSNI = trim($_GET['ssSNI'] ?? '');
$ssPBK = trim($_GET['ssPBK'] ?? '');
$ssSID = trim($_GET['ssSID'] ?? '');

$data = json_decode(file_get_contents('/opt/de_GWD/0conf'), true);
$data['v2nodeDIV']['ss'] = array();
$data['v2nodeDIV']['ss']['ssAddress'] = $ssAddress;
$data['v2nodeDIV']['ss']['ssPort'] = $ssPort;
$data['v2nodeDIV']['ss']['ssUUID'] = $ssUUID;
$data['v2nodeDIV']['ss']['ssSNI'] = $ssSNI;
$data['v2nodeDIV']['ss']['ssPBK'] = $ssPBK;
$data['v2nodeDIV']['ss']['ssSID'] = $ssSID;
$newJsonString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents('/opt/de_GWD/0conf', $newJsonString);

exec('sudo /opt/de_GWD/ui-changeNodeSS r &');
?>
<?php }?>
