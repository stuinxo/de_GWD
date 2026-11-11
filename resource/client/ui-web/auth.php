<?php
    session_start();
    $conf = @json_decode(@file_get_contents('/opt/de_GWD/0conf'));
    if (isset($conf->address->PWD) && $conf->address->PWD !== '')
    {
        $GWDpwhash = $conf->address->PWD;
    }
    else
    {
        $GWDpwhash = "f69ad6fd10ecc116889c9985d4b45ffaf51875af3198d4e6b6eceb1080ca1e99";
    }


    if(isset($_GET['logout']))
    {
        unset($_SESSION["GWDhash"]);
        setcookie('persistentlogin', '');
        session_write_close();
        exit();
    }
    $auth = false;


    $pwInput = $_POST['gwdpw'] ?? $_GET['gwdpw'] ?? null;
    if ($pwInput !== null)
    {
        $logininput = hash('sha256', hash('sha256', (string)$pwInput));
        if (hash_equals($GWDpwhash, $logininput))
        {
            $_SESSION["GWDhash"] = $GWDpwhash;
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
    session_write_close();
?>
