<?php
// =========================================================================
// Proteção contra CSRF (Cross-Site Request Forgery)
//
// Sem isso, um site qualquer consegue montar um formulário apontando para um
// endpoint nosso e fazer o navegador do usuário logado enviá-lo: o cookie de
// sessão viaja junto e o servidor executa a ação achando que foi o usuário.
// O token resolve porque o site de fora não tem como ler o valor guardado na
// sessão para copiá-lo no formulário.
//
// O token é um só por sessão, e não muda a cada requisição de propósito: token
// rotativo quebra o uso normal de duas abas abertas ao mesmo tempo.
// =========================================================================

/**
 * Token da sessão atual, criando-o na primeira chamada.
 */
function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Campo escondido para colar dentro de um <form method="POST">.
 */
function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Confere o token recebido. Aceita tanto o campo do formulário quanto o
 * cabeçalho X-CSRF-Token, usado pelas telas que enviam por AJAX.
 *
 * hash_equals compara em tempo constante: um == comum vaza, pelo tempo de
 * resposta, quantos caracteres do começo o atacante já acertou.
 */
function csrfValido(): bool
{
    $enviado  = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $guardado = $_SESSION['csrf_token'] ?? '';

    return $enviado !== '' && $guardado !== '' && hash_equals($guardado, $enviado);
}

/**
 * Exige o token num endpoint que responde por redirect. Em falha, manda o
 * usuário de volta com a mensagem de erro no padrão do components/alert.php.
 */
function csrfExigir(string $url_destino): void
{
    if (csrfValido()) {
        return;
    }

    $separador = str_contains($url_destino, '?') ? '&' : '?';
    header('Location: ' . $url_destino . $separador . 'msgErro='
        . urlencode('Requisição inválida ou sessão expirada. Abra a tela de novo e tente outra vez.'));
    exit;
}

/**
 * Checagem central: toda requisição POST precisa trazer o token.
 *
 * Chamada pelo middleware (endpoints em functions/) e pelo roteador (telas
 * que processam o próprio formulário). Antes cada endpoint precisava lembrar
 * de chamar csrfExigir(), e a maioria não chamava — pagamento de parcela,
 * ajuste de estoque e finalizar compra estavam entre os desprotegidos.
 *
 * O token chega sem ninguém precisar mexer nos formulários: o layout
 * (index2.php) injeta o campo em todo <form method="post"> e o cabeçalho
 * X-CSRF-Token nas chamadas AJAX.
 */
function csrfExigirEmPost(string $url_base): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || csrfValido()) {
        return;
    }

    if (defined('MIDDLEWARE_RESPOSTA_JSON')) {
        csrfExigirJson();
    }

    // Volta para a tela de onde o formulário veio, se ela for deste sistema.
    $origem = $_SERVER['HTTP_REFERER'] ?? '';
    $destino = str_starts_with($origem, $url_base . '/') ? strtok($origem, '#') : $url_base . '/index2.php';
    $destino = preg_replace('/([?&])msg(Erro|Sucesso|Aviso)=[^&]*&?/', '$1', $destino);
    csrfExigir(rtrim($destino, '?&'));
}

/**
 * Mesma checagem para endpoint consumido por AJAX, que espera JSON de volta.
 */
function csrfExigirJson(): void
{
    if (csrfValido()) {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => false,
        'msg'    => 'Requisição inválida ou sessão expirada. Recarregue a página.',
    ]);
    exit;
}
