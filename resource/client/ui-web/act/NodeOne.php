<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
gwd_update_conf(function (array $conf): array {
    if (!isset($conf['v2nodeDIV']) || !is_array($conf['v2nodeDIV'])) {
        $conf['v2nodeDIV'] = [];
    }
    foreach (['nodeSM', 'nodeDT', 'nodeCU'] as $sub) {
        if (!isset($conf['v2nodeDIV'][$sub]) || !is_array($conf['v2nodeDIV'][$sub])) {
            $conf['v2nodeDIV'][$sub] = [];
        }
        $conf['v2nodeDIV'][$sub]['status'] = 'off';
    }
    if (isset($conf['v2nodeDIV']['ss']) && is_array($conf['v2nodeDIV']['ss'])) {
        $conf['v2nodeDIV']['ss']['status'] = 'off';
    }
    return $conf;
});

exec('sudo /opt/de_GWD/ui-NodeOne f r >/dev/null 2>&1 &');
?>
<?php }?>