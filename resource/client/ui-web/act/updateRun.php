<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
exec('sudo systemctl start updateGWD >/dev/null 2>&1 &');
?>
<?php }?>