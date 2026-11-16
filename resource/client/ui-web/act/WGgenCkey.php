<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$raw = trim((string)gwd_param('WGnum', ''));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit;
}
$WGnum = intval($raw);
$conf = gwd_read_conf_object();

$WGSkey = $conf->wireguard->server->sprivatekey ?? null;

if (empty($WGSkey)) exec("sudo /opt/de_GWD/ui-WGgenSkey");

passthru("sudo /opt/de_GWD/ui-WGgenCkey $WGnum");
?>
<?php }?>
