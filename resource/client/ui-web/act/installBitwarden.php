<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
exec('sudo nohup /usr/bin/ttyd -i lo -p 3000 -W -o /opt/de_GWD/ui-installBitwarden >/dev/null 2>&1 &');
?>
<?php }?>
