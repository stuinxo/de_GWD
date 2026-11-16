<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
set_time_limit(0);
exec('sudo /opt/de_GWD/ui-backup bitwarden');
?>
<?php }?>
