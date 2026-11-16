<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$NFSpoint = trim((string)gwd_param('NFSpoint', ''));
if ($NFSpoint === '' || strpos($NFSpoint, '-') === 0) {
    http_response_code(400);
    exit;
}
exec('sudo /opt/de_GWD/ui-NFSoff ' . escapeshellarg($NFSpoint) . ' >/dev/null 2>&1 &');
?>
<?php }?>
