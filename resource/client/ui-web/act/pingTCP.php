<?php
require_once('../auth.php');
gwd_require_auth();
header('Content-Type: text/plain; charset=utf-8');
$raw = trim((string)($_GET['pingTCP'] ?? $_POST['pingTCP'] ?? ''));
if (!ctype_digit($raw)) {
    http_response_code(400);
    exit();
}
$pingTCP = intval($raw);
passthru("/opt/de_GWD/ui-pingTCP $pingTCP");
?>
