<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$CFdomain = trim((string)gwd_param('CFdomain', ''));
$CFtoken = trim((string)gwd_param('CFtoken', ''));

gwd_update_conf(function (array $conf) use ($CFdomain, $CFtoken): array {
    if (!isset($conf['FORWARD']) || !is_array($conf['FORWARD'])) {
        $conf['FORWARD'] = [];
    }
    unset($conf['FORWARD']['APIkey'], $conf['FORWARD']['Email']);
    $conf['FORWARD']['domain'] = $CFdomain;
    $conf['FORWARD']['token'] = $CFtoken;
    return $conf;
});

exec('sudo nohup /usr/bin/ttyd -i lo -p 3000 -W -o /opt/de_GWD/ui-installCER >/dev/null 2>&1 &');
?>
<?php }?>
