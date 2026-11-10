<?php
/**
 * Ponto de entrada na Vercel: faz o papel do Apache e do .htaccess.
 *
 * O runtime vercel-php executa só arquivos dentro de api/, então todo pedido
 * dinâmico chega aqui (ver as rotas do vercel.json) e é despachado para o
 * mesmo arquivo que o Apache executaria na máquina local:
 *
 *   /caminho/arquivo.php   o próprio arquivo (views/user/login.php, functions/...)
 *   /images/user/<foto>    functions/user/foto.php, que lê a foto do banco
 *   /pagina ou /pag/acao   index2.php?pagina=..., o roteador do sistema
 *   /                      index.php
 *
 * CSS, JS e imagens versionadas não passam por aqui: a Vercel serve direto.
 * No XAMPP este arquivo não é usado.
 */

$raiz = dirname(__DIR__);

$caminho = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$caminho = '/' . trim($caminho, '/');

// As mesmas pastas que o .htaccess bloqueia, mais a própria api/.
if (preg_match('#(^|/)\.|^/(sql|vendor|docs|design-system|api|node_modules)(/|$)|^/composer\.(json|lock)$|\.md$#i', $caminho)) {
    http_response_code(404);
    exit('Não encontrado.');
}

$alvo = null;

if ($caminho === '/') {
    $alvo = $raiz . '/index.php';
} elseif (preg_match('#^/images/user/([^/]+)$#', $caminho, $m)) {
    $_GET['nome'] = $_REQUEST['nome'] = $m[1];
    $alvo = $raiz . '/functions/user/foto.php';
} elseif (str_ends_with(strtolower($caminho), '.php')) {
    // realpath() resolve ../ e confere se o arquivo existe; o prefixo garante
    // que o resultado continua dentro do projeto.
    $arquivo = realpath($raiz . $caminho);
    if ($arquivo !== false && str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR) && is_file($arquivo)) {
        $alvo = $arquivo;
    }
} elseif (preg_match('#^/([a-zA-Z0-9_\-]+(/[a-zA-Z0-9_\-]+)?)$#', $caminho, $m)) {
    // A regra do .htaccess: /pagina ou /pagina/acao => index2.php?pagina=...
    $_GET['pagina'] = $_REQUEST['pagina'] = $m[1];
    $alvo = $raiz . '/index2.php';
}

if ($alvo === null) {
    http_response_code(404);
    exit('Não encontrado.');
}

// O sistema inclui arquivos com caminho relativo (require '../../banco.php') e
// confere o nome do script (alterar_senha.php): os dois dependem de o PHP
// enxergar o arquivo como se ele tivesse sido chamado direto.
$relativo = substr($alvo, strlen($raiz));
$relativo = str_replace(DIRECTORY_SEPARATOR, '/', $relativo);
$_SERVER['SCRIPT_FILENAME'] = $alvo;
$_SERVER['SCRIPT_NAME']     = $relativo;
$_SERVER['PHP_SELF']        = $relativo;
$_SERVER['DOCUMENT_ROOT']   = $raiz;

chdir(dirname($alvo));
unset($raiz, $caminho, $arquivo, $m, $relativo);

require $alvo;
