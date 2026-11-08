<?php
    session_start();
    $conf = json_decode(file_get_contents('/opt/de_GWD/0conf'));
    $GWDpwhash = isset($conf->address->PWD) ? $conf->address->PWD : "";


    if(isset($_GET['logout']))
    {
        unset($_SESSION["GWDhash"]);
        setcookie('persistentlogin', '');
        exit();
    }
    $auth = false;


    if(isset($_GET['gwdpw']))
    {
        $logininput = hash('sha256',hash('sha256',$_GET['gwdpw']));
        if( $GWDpwhash === $logininput )
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
        if($_SESSION["GWDhash"] === $GWDpwhash)
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
?>