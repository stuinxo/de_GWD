<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$autoUpdateHourINDEX = trim((string)gwd_param('autoUpdateHourINDEX', 'off'));
if ($autoUpdateHourINDEX !== 'off' && (!ctype_digit($autoUpdateHourINDEX) || (int)$autoUpdateHourINDEX < 0 || (int)$autoUpdateHourINDEX > 23)) {
    http_response_code(400);
    exit;
}

gwd_update_conf(function (array $conf) use ($autoUpdateHourINDEX): array {
    if (!isset($conf['address']) || !is_array($conf['address'])) {
        $conf['address'] = [];
    }
    $conf['address']['autoUpdateHour'] = $autoUpdateHourINDEX;
    return $conf;
});

exec('sudo /opt/de_GWD/ui-autoUpdateHour ' . escapeshellarg($autoUpdateHourINDEX) . ' >/dev/null 2>&1 &');
?>
<?php }?>
