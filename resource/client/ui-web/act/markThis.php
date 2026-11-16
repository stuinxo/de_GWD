<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$markThis = trim((string)gwd_param('markName', ''));

gwd_update_conf(function (array $conf) use ($markThis): array {
    if (!isset($conf['address']) || !is_array($conf['address'])) {
        $conf['address'] = [];
    }
    $conf['address']['alias'] = $markThis;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-markThis >/dev/null 2>&1 &');
?>
<?php }?>