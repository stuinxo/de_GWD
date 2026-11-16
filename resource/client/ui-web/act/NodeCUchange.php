<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$raw = trim((string)gwd_param('nodeCUnum', '0'));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit;
}
$nodeCUnum = intval($raw);
exec("sudo /opt/de_GWD/ui-NodeCU r $nodeCUnum");
?>
<?php }?>
