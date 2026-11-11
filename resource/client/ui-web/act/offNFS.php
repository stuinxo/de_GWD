<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$NFSpoint = trim((string)($_GET['NFSpoint'] ?? ''));
if ($NFSpoint !== '') {
    exec('sudo /opt/de_GWD/ui-NFSoff ' . escapeshellarg($NFSpoint) . ' &');
}
?>
<?php }?>
