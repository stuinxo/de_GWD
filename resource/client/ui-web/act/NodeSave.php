<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$rawList = gwd_param('nodeList', []);
$nodeList = is_array($rawList) ? $rawList : [];

gwd_update_conf(function (array $conf) use ($nodeList): array {
    $conf['v2node'] = $nodeList;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-NodeSave r >/dev/null 2>&1 &');
?>
<?php }?>