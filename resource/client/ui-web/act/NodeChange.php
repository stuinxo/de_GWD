<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$raw = trim((string)gwd_param('nodenum', '0'));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit;
}
$nodenum = intval($raw);
exec("sudo /opt/de_GWD/ui-NodeChange $nodenum >/dev/null 2>&1 &");
?>
<?php }?>
