v2.0.10
-
<?php
$branch = getenv('BRANCH') ?: 'v2';
$remote = @file_get_contents("https://raw.githubusercontent.com/stuinxo/de_GWD/{$branch}/version.php");
if ($remote) {
    $lines = explode("\n", trim($remote));
    echo trim($lines[0]);
} else {
    echo 'v2.0.10';
}
?>
