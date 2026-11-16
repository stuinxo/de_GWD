<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
function sys_linux()
{
    $uptimestr = @file("/proc/uptime");
    $uptimestr = explode(" ", implode("", $uptimestr ?: array("0")));
    $uptimestr = (float)trim($uptimestr[0]);
    $min = $uptimestr / 60;
    $hours = $min / 60;
    $days = (int)floor($hours / 24);
    $hours = (int)floor($hours - ($days * 24));
    $min = (int)floor($min - ($days * 60 * 24) - ($hours * 60));
    $res['uptime'] = '';
    if ($days !== 0) $res['uptime'] = $days."天";
    if ($hours !== 0) $res['uptime'] .= $hours."小时";
    $res['uptime'] .= $min."分钟";
    return $res;
}

$sysInfo = sys_linux();
echo $sysInfo['uptime'];
?>
