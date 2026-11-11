<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$NFSserver = trim((string)($_GET['NFSserver'] ?? ''));
if ($NFSserver !== '' && strpos($NFSserver, '-') !== 0) {
    passthru('sudo showmount -e ' . escapeshellarg($NFSserver) . " | sed '1d'");
}
?>
<?php }?>
