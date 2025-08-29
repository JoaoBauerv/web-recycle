<?php
require (__DIR__ . '/../banco.php');

if (empty($_SESSION['logado'])) {
    header('Location: '.$url_base.'/views/user/login.php');
    exit();
}
?>