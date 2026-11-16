<?php require_once('../auth.php'); ?>
<?php if (isset($auth) && $auth) {?>
<?php
gwd_require_post_csrf();
if (isset($_FILES["file"]["error"], $_FILES["file"]["name"], $_FILES["file"]["tmp_name"])
    && $_FILES["file"]["error"] === UPLOAD_ERR_OK
    && $_FILES["file"]["name"] === "Jellyfin_bak.zip")
{
	$restoreDir = is_dir('/opt/de_GWD/restore') ? '/opt/de_GWD/restore' : (__DIR__ . '/../restore');
	if (move_uploaded_file($_FILES['file']['tmp_name'], $restoreDir . '/Jellyfin_bak.zip')) {
		exec('sudo /opt/de_GWD/ui-restoreJellyfin &');
	}
}
?>
<?php }?>
