<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
$keys = [
    'openai', 'claude', 'gemini', 'grok',
    'youtube', 'netflix', 'hdh', 'bahamut', 'tvb',
    'telegram', 'twitter', 'discord', 'reddit', 'github', 'wikipedia',
    'apple', 'steam'
];
$args = [];
foreach ($keys as $k) {
    if (isset($_GET[$k])) {
        $val = intval($_GET[$k]);
        $args[] = escapeshellarg("{$k}={$val}");
    }
}
$arg_str = implode(' ', $args);
exec("sudo /opt/de_GWD/ui-NodeSM r {$arg_str}");
?>
<?php }?>
