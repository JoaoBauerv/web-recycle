<?php
require (__DIR__ . '/../banco.php');
require_once (__DIR__ . '/csrf.php');
session_start();

if (empty($_SESSION['logado'])) {
    // Endpoints consumidos por AJAX definem MIDDLEWARE_RESPOSTA_JSON antes de
    // incluir este arquivo: redirecionar devolveria o HTML do login dentro de
    // uma resposta que o JavaScript tenta ler como JSON, e o erro que aparece
    // na tela não teria nada a ver com a causa real (sessão expirada).
    if (defined('MIDDLEWARE_RESPOSTA_JSON')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => false,
            'msg'    => 'Sessão expirada. Faça login novamente.',
        ]);
        exit();
    }

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