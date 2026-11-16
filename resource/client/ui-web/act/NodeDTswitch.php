<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$switchNodeDT = (string)gwd_param('switchNodeDT', '');
if ($switchNodeDT === 'NodeDTshow') {
    exec('sudo /opt/de_GWD/ui-NodeDT r');
} elseif ($switchNodeDT === 'NodeDThide') {
    exec('sudo /opt/de_GWD/ui-NodeDThide r');
} else {
    http_response_code(400);
    exit;
}

passthru('sudo /opt/de_GWD/ui-NodeDTcheck');
?>
<?php }?>
