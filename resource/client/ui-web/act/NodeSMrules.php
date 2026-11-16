<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
$keys = [
    'openai', 'claude', 'gemini', 'grok',
    'youtube', 'netflix', 'hdh', 'bahamut', 'tvb',
    'telegram', 'twitter', 'discord', 'reddit', 'github', 'wikipedia',
    'apple', 'steam'
];
$args = [];
foreach ($keys as $k) {
    $raw = gwd_param($k, null);
    if ($raw !== null && $raw !== '') {
        $rawStr = trim((string)$raw);
        if (!ctype_digit($rawStr)) {
            http_response_code(400);
            exit;
        }
        $val = intval($rawStr);
        $args[] = escapeshellarg("{$k}={$val}");
    }
}
$arg_str = implode(' ', $args);
exec("sudo /opt/de_GWD/ui-NodeSM r {$arg_str}");
?>
<?php }?>
