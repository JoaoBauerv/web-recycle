<?php
require (__DIR__ . '/../banco.php');
session_start();

if (empty($_SESSION['logado'])) {
    header('Location: '.$url_base.'/views/user/login.php');
    exit();
}

// Endpoints (controllers) que só podem ser acessados por usuários com permissao = 'Admin'.
// As TELAS (views) que exigem Admin agora são controladas pelo roteador em index2.php,
// já que toda navegação passa por lá — aqui ficam só os arquivos físicos que recebem POST direto.
$paginas_admin = [
    '/functions/cliente/registrar.php',
    '/functions/fornecedor/registrar.php',
];

$pagina_atual = $_SERVER['SCRIPT_NAME'];

foreach ($paginas_admin as $pagina_restrita) {
    if (substr($pagina_atual, -strlen($pagina_restrita)) === $pagina_restrita) {
        if (($_SESSION['permissao'] ?? '') !== 'Admin') {
            header('Location: '.$url_base.'/index2.php?msgErro=' . urlencode('Acesso restrito a administradores.'));
            exit();
        }
        break;
    }
}
?>