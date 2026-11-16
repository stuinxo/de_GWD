<?php
    if (!function_exists('gwd_h')) {
        function gwd_h($val): string {
            if ($val === null || !is_scalar($val)) {
                return '';
            }
            return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    if (!function_exists('gwd_conf_path')) {
        function gwd_conf_path(): string {
            $env = getenv('DEGWD_CONF_PATH');
            return ($env !== false && $env !== '') ? $env : '/opt/de_GWD/0conf';
        }
    }

    if (!function_exists('gwd_open_conf_lock')) {
        function gwd_open_conf_lock(string $confPath, int $lockMode) {
            $lockPath = $confPath . '.lock';
            $lockFp = @fopen($lockPath, 'c+');
            if ($lockFp === false && is_file($confPath)) {
                $lockFp = @fopen($confPath, 'r+');
            }
            if ($lockFp !== false) {
                @flock($lockFp, $lockMode);
            }
            return $lockFp;
        }
    }

    if (!function_exists('gwd_read_conf_object')) {
        function gwd_read_conf_object(): object {
            $confPath = gwd_conf_path();
            $lockFp = gwd_open_conf_lock($confPath, LOCK_SH);
            $raw = @file_get_contents($confPath);
            $obj = is_string($raw) ? json_decode($raw, false) : null;
            if (!is_object($obj)) {
                $bakRaw = @file_get_contents($confPath . '_bak');
                $bakObj = is_string($bakRaw) ? json_decode($bakRaw, false) : null;
                $obj = is_object($bakObj) ? $bakObj : new stdClass();
            }
            if ($lockFp !== false) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
            return $obj;
        }
    }

    if (!function_exists('gwd_update_conf')) {
        function gwd_update_conf(callable $mutator): bool {
            $confPath = gwd_conf_path();
            $lockFp = gwd_open_conf_lock($confPath, LOCK_EX);
            $raw = @file_get_contents($confPath);
            $conf = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($conf)) {
                $bakRaw = @file_get_contents($confPath . '_bak');
                $bakData = is_string($bakRaw) ? json_decode($bakRaw, true) : null;
                $conf = is_array($bakData) ? $bakData : [];
            }
            $result = $mutator($conf);
            if (is_array($result)) {
                $conf = $result;
            }
            if (!is_array($conf)) {
                if ($lockFp !== false) {
                    @flock($lockFp, LOCK_UN);
                    @fclose($lockFp);
                }
                return false;
            }
            $json = json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || $json === '' || json_decode($json, true) === null) {
                if ($lockFp !== false) {
                    @flock($lockFp, LOCK_UN);
                    @fclose($lockFp);
                }
                return false;
            }
            $json .= "\n";
            $ok = false;
            $dir = dirname($confPath);
            $tmpPath = is_writable($dir) ? @tempnam($dir, '.0conf.tmp.') : false;
            if ($tmpPath !== false) {
                $written = @file_put_contents($tmpPath, $json);
                if ($written === strlen($json)) {
                    @chmod($tmpPath, 0660);
                    $ok = @rename($tmpPath, $confPath);
                }
                if (!$ok) {
                    @unlink($tmpPath);
                }
            }
            if (!$ok) {
                $fp = @fopen($confPath, 'c+');
                if ($fp !== false) {
                    @rewind($fp);
                    $written = @fwrite($fp, $json);
                    if ($written === strlen($json)) {
                        @ftruncate($fp, strlen($json));
                        @fflush($fp);
                        $ok = true;
                    }
                    @fclose($fp);
                }
            }
            if ($lockFp !== false) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
            return $ok;
        }
    }

    if (!function_exists('gwd_require_post_csrf')) {
        function gwd_require_post_csrf(): void {
            global $csrfToken;
            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
            $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
            if ($method !== 'POST' || !isset($csrfToken) || $csrfToken === '' || !hash_equals($csrfToken, $provided)) {
                http_response_code(403);
                exit('Forbidden');
            }
        }
    }

    if (!function_exists('gwd_param')) {
        function gwd_param(string $key, $default = '') {
            return $_POST[$key] ?? $default;
        }
    }

    if (!function_exists('gwd_str_param')) {
        function gwd_str_param(string $key, string $default = ''): string {
            if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
                return $default;
            }
            return (string)$_POST[$key];
        }
    }

    if (!function_exists('gwd_require_auth')) {
        function gwd_require_auth(int $status = 401): void {
            global $auth;
            if (empty($auth)) {
                http_response_code($status);
                exit($status === 403 ? 'Forbidden' : 'Unauthorized');
            }
        }
    }

    if (!function_exists('gwd_start_ttyd')) {
        function gwd_start_ttyd(string $scriptName): bool {
            if (!preg_match('/^ui-[A-Za-z0-9_-]+$/', $scriptName)) {
                return false;
            }
            // Anchored pattern: an unanchored `pkill -f` would also match the wrapping
            // `sh -c` and `sudo` processes whose command lines contain the same string.
            @exec("sudo pkill -f '^/usr/bin/ttyd -i lo -p 3000' >/dev/null 2>&1");
            // Wait for the old instance to release the port so the readiness probe
            // below cannot be satisfied by a dying process.
            for ($i = 0; $i < 20; $i++) {
                $errno = 0;
                $errstr = '';
                $sock = @fsockopen('127.0.0.1', 3000, $errno, $errstr, 0.1);
                if ($sock === false) {
                    break;
                }
                @fclose($sock);
                usleep(50000);
            }
            @exec('sudo nohup /usr/bin/ttyd -i lo -p 3000 -W -o /opt/de_GWD/' . $scriptName . ' >/dev/null 2>&1 &');
            for ($i = 0; $i < 20; $i++) {
                $errno = 0;
                $errstr = '';
                $sock = @fsockopen('127.0.0.1', 3000, $errno, $errstr, 0.1);
                if ($sock !== false) {
                    @fclose($sock);
                    return true;
                }
                usleep(50000);
            }
            return true;
        }
    }

    if (!function_exists('gwd_expected_password_hash')) {
        function gwd_expected_password_hash(object $conf): ?string {
            $pwd = (isset($conf->address->PWD) && is_string($conf->address->PWD))
                ? trim($conf->address->PWD)
                : '';
            if (preg_match('/^[0-9a-f]{64}$/i', $pwd)) {
                return strtolower($pwd);
            }
            $bakRaw = @file_get_contents(gwd_conf_path() . '_bak');
            $bakObj = is_string($bakRaw) ? json_decode($bakRaw, false) : null;
            if (is_object($bakObj) && isset($bakObj->address->PWD) && is_string($bakObj->address->PWD)) {
                $bakPwd = trim($bakObj->address->PWD);
                if (preg_match('/^[0-9a-f]{64}$/i', $bakPwd)) {
                    return strtolower($bakPwd);
                }
            }
            return null;
        }
    }

    if (!function_exists('gwd_login_rl_path')) {
        function gwd_login_rl_path(): string {
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'local');
            $key = md5(gwd_conf_path() . '|' . $ip);
            return rtrim(sys_get_temp_dir(), '/\\') . '/de_GWD_login_rl_' . $key . '.json';
        }
    }

    if (!function_exists('gwd_login_is_rate_limited')) {
        function gwd_login_is_rate_limited(): bool {
            $path = gwd_login_rl_path();
            if (!is_file($path)) {
                return false;
            }
            $fp = @fopen($path, 'c+');
            if ($fp === false) {
                return false;
            }
            @flock($fp, LOCK_EX);
            $raw = @stream_get_contents($fp);
            $now = time();
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $recent = [];
            if (is_array($data)) {
                foreach ($data as $ts) {
                    if (is_int($ts) && ($now - $ts) < 60 && $ts <= ($now + 5)) {
                        $recent[] = $ts;
                    }
                }
            }
            $json = json_encode($recent);
            @rewind($fp);
            if (is_string($json)) {
                @fwrite($fp, $json);
                @ftruncate($fp, strlen($json));
            }
            @fflush($fp);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return count($recent) >= 10;
        }
    }

    if (!function_exists('gwd_record_login_failure')) {
        function gwd_record_login_failure(): void {
            $path = gwd_login_rl_path();
            $fp = @fopen($path, 'c+');
            if ($fp === false) {
                return;
            }
            @flock($fp, LOCK_EX);
            $raw = @stream_get_contents($fp);
            $now = time();
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $recent = [];
            if (is_array($data)) {
                foreach ($data as $ts) {
                    if (is_int($ts) && ($now - $ts) < 60 && $ts <= ($now + 5)) {
                        $recent[] = $ts;
                    }
                }
            }
            $recent[] = $now;
            $json = json_encode($recent);
            @rewind($fp);
            if (is_string($json)) {
                @fwrite($fp, $json);
                @ftruncate($fp, strlen($json));
            }
            @fflush($fp);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @chmod($path, 0600);
        }
    }

    if (!function_exists('gwd_clear_login_failures')) {
        function gwd_clear_login_failures(): void {
            $path = gwd_login_rl_path();
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    $conf = gwd_read_conf_object();
    $GWDpwhash = gwd_expected_password_hash($conf);


    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST' && isset($_POST['logout']))
    {
        unset($_SESSION["GWDhash"], $_SESSION["csrf_token"]);
        setcookie('persistentlogin', '', ['expires' => 1, 'path' => '/', 'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax']);
        session_regenerate_id(true);
        session_write_close();
        exit();
    }
    $auth = false;


    $pwInput = $_POST['gwdpw'] ?? null;
    if ($pwInput !== null)
    {
        if (gwd_login_is_rate_limited()) {
            http_response_code(429);
            header('Retry-After: 60');
            session_write_close();
            exit('Too Many Requests');
        }
        if ($GWDpwhash !== null && is_scalar($pwInput) && (string)$pwInput !== '')
        {
            $logininput = strtolower(hash('sha256', hash('sha256', (string)$pwInput)));
            if (hash_equals($GWDpwhash, $logininput))
            {
                gwd_clear_login_failures();
                session_regenerate_id(true);
                $_SESSION["GWDhash"] = $GWDpwhash;
                if (empty($_SESSION["csrf_token"])) {
                    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                }
                $auth = true;
            }
            else
            {
                gwd_record_login_failure();
                $auth = false;
            }
        }
        else
        {
            gwd_record_login_failure();
            $auth = false;
        }
    }
    else if (isset($_SESSION["GWDhash"]) && is_string($_SESSION["GWDhash"]) && $GWDpwhash !== null)
    {
        if (hash_equals($GWDpwhash, strtolower($_SESSION["GWDhash"])))
        {
            if (empty($_SESSION["csrf_token"])) {
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
            }
            $auth = true;
        }
        else
        {
            $auth = false;
        }
    }
    else
    {
        $auth = false;
    }
    $csrfToken = (string)($_SESSION["csrf_token"] ?? '');
    session_write_close();
    if (!$auth && $pwInput !== null && basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'auth.php') {
        http_response_code(401);
    }
?>
