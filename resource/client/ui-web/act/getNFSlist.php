<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$NFSserver = trim((string)($_GET['NFSserver'] ?? $_POST['NFSserver'] ?? ''));
if ($NFSserver === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $NFSserver)) {
    http_response_code(400);
    exit;
}
passthru('sudo showmount -e ' . escapeshellarg($NFSserver) . " | sed '1d'");
?>
<?php }?>
