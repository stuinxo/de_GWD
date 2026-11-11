<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$conf = json_decode(file_get_contents('/opt/de_GWD/0conf'), true);
$CFdomain = trim($_GET['CFdomain'] ?? '');
$CFtoken = trim($_GET['CFtoken'] ?? '');

unset($conf['ddns']['ddns3322'], $conf['ddns']['ddnsCF']['cfAPIkey'], $conf['ddns']['ddnsCF']['cfEmail']);
$conf['ddns']['ddnsCF']['cfDomain'] = $CFdomain;
$conf['ddns']['ddnsCF']['cfToken'] = $CFtoken;
$newJsonString = json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents('/opt/de_GWD/0conf', $newJsonString);

exec('sudo /opt/de_GWD/ui-ddnsCFon &');
?>
<?php }?>
