<?php
require (__DIR__ . '/../banco.php');
session_start();

if (empty($_SESSION['logado'])) {
    header('Location: '.$url_base.'/views/user/login.php');
    exit();
}
?>