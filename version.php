v1.27.0
-
<?php
$str= file_get_contents('https://raw.githubusercontent.com/stuinxo/de_GWD/main/version.php');
$array=explode('-', $str);
echo $array[0];
?>
