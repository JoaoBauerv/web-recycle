<?php
require_once(__DIR__ . '/../../banco.php');
session_start();

// Apagar só o conteúdo deixa o cookie com o identificador antigo no
// navegador; a sessão é esvaziada, destruída e o cookie expirado.
$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => $cookie['path'],
    'domain'   => $cookie['domain'],
    'secure'   => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => $cookie['samesite'],
]);
session_destroy();

header("Location: $url_base/views/user/login.php?msgSucesso=" . urlencode('Logout realizado com sucesso!'));
exit;
