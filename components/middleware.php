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

// Quem ainda precisa trocar a senha só pode trocar a senha. O roteador já
// barrava as telas; sem esta checagem os endpoints continuavam aceitando
// POST direto de uma conta com senha temporária.
if (!empty($_SESSION['precisa_alterar_senha'])) {
    if (defined('MIDDLEWARE_RESPOSTA_JSON')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => false, 'msg' => 'Troque sua senha antes de continuar.']);
        exit();
    }
    header('Location: '.$url_base.'/views/user/alterar_senha.php');
    exit();
}

// Todo endpoint que altera dado recebe POST com token. GET só é aceito nos que
// apenas leem e entregam arquivo — sem esta lista, um link (ou uma <img> num
// site qualquer) bastava para disparar ?acao=excluir ou ?acao=finalizar, e por
// GET nem o token nem o SameSite do cookie protegem.
const MIDDLEWARE_GET_PERMITIDO = [
    '/functions/compra/exportar_planilha.php',
    '/functions/financeiro/margem_export.php',
    '/functions/venda/comprovante_arquivo.php',
    '/functions/venda/importacao_erros_csv.php',
    '/functions/venda/planilha_modelo.php',
    '/functions/calendario/listar.php',
    // Chega por redirect de views/user/register.php, que já conferiu o token;
    // os dados vêm da sessão, não da URL.
    '/functions/user/registrar.php',
];

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$script = $_SERVER['SCRIPT_NAME'];
$get_permitido = false;
foreach (MIDDLEWARE_GET_PERMITIDO as $endpoint) {
    if (str_ends_with($script, $endpoint)) {
        $get_permitido = true;
        break;
    }
}

if ($metodo !== 'POST' && !$get_permitido) {
    header('Location: '.$url_base.'/index2.php?msgErro=' . urlencode('Ação inválida.'));
    exit();
}

csrfExigirEmPost($url_base);

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