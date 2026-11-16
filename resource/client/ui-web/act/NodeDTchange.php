<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$raw = trim((string)gwd_param('nodeDTnum', '0'));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit;
}
$nodeDTnum = intval($raw);
exec("sudo /opt/de_GWD/ui-NodeDT r $nodeDTnum");
?>
<?php }?>
