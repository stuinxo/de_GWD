v2.1.0
-
<?php
$branch = getenv('BRANCH') ?: 'v2';
$ctx = stream_context_create(['http' => ['timeout' => 3], 'ssl' => ['verify_peer' => true]]);
$remote = @file_get_contents("https://raw.githubusercontent.com/stuinxo/de_GWD/{$branch}/version.php", false, $ctx);
if ($remote) {
    $lines = explode("\n", trim($remote));
    $ver = trim($lines[0]);
    echo preg_match('/^v?[0-9]+\.[0-9]+/', $ver) ? $ver : 'v2.1.0';
} else {
    echo 'v2.1.0';
}
?>
