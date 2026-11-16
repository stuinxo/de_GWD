<?php
    if (!function_exists('gwd_h')) {
        function gwd_h($val): string {
            return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
                    if (@ftruncate($fp, 0)) {
                        @rewind($fp);
                        $written = @fwrite($fp, $json);
                        @fflush($fp);
                        $ok = ($written === strlen($json));
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
    if (isset($conf->address->PWD) && $conf->address->PWD !== '')
    {
        $GWDpwhash = $conf->address->PWD;
    }
    else
    {
        $GWDpwhash = "f69ad6fd10ecc116889c9985d4b45ffaf51875af3198d4e6b6eceb1080ca1e99";
    }


    if(isset($_POST['logout']) || isset($_GET['logout']))
    {
        unset($_SESSION["GWDhash"], $_SESSION["csrf_token"]);
        setcookie('persistentlogin', '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_regenerate_id(true);
        session_write_close();
        exit();
    }
    $auth = false;


    $pwInput = $_POST['gwdpw'] ?? null;
    if ($pwInput !== null)
    {
        $logininput = hash('sha256', hash('sha256', (string)$pwInput));
        if (hash_equals($GWDpwhash, $logininput))
        {
            session_regenerate_id(true);
            $_SESSION["GWDhash"] = $GWDpwhash;
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
    else if (isset($_SESSION["GWDhash"]))
    {
        if(hash_equals($GWDpwhash, (string)$_SESSION["GWDhash"]))
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
?>
