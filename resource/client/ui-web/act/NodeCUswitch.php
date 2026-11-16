<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$switchNodeCU = (string)gwd_param('switchNodeCU', '');
if ($switchNodeCU === 'NodeCUshow') {
    exec('sudo /opt/de_GWD/ui-NodeCU r');
} elseif ($switchNodeCU === 'NodeCUhide') {
    exec('sudo /opt/de_GWD/ui-NodeCUhide r');
} else {
    http_response_code(400);
    exit;
}

passthru('sudo /opt/de_GWD/ui-NodeCUcheck');
?>
<?php }?>
