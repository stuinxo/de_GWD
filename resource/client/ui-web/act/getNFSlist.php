<?php
require_once('../auth.php');
gwd_require_auth();
gwd_require_post_csrf();
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$NFSserver = trim(gwd_str_param('NFSserver', ''));
$lower = strtolower($NFSserver);
$isValidIpv4 = (filter_var($NFSserver, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
$isValidHost = (bool)preg_match('/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*)$/', $NFSserver);
if ($NFSserver === ''
    || $NFSserver[0] === '-'
    || (!$isValidIpv4 && !$isValidHost)
    || $lower === 'localhost'
    || strpos($lower, '127.') === 0
    || $lower === '0.0.0.0'
    || $lower === '::1') {
    http_response_code(400);
    exit('Invalid NFS server');
}
passthru('PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:$PATH" timeout 5 showmount -e ' . escapeshellarg($NFSserver) . " 2>/dev/null | sed '1d'");
?>
