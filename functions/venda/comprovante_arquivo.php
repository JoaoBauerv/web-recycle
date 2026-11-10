<?php
/**
 * Entrega o comprovante importado de uma venda.
 *
 * A pasta uploads/comprovantes_venda/ é bloqueada por .htaccess justamente para
 * que os arquivos só saiam por aqui, depois da checagem de sessão feita pelo
 * middleware — senão bastaria adivinhar a URL para ler documento de terceiros.
 */
require_once(__DIR__ . '/../../components/middleware.php');

$id_venda = (int) ($_GET['id'] ?? 0);
if (!$id_venda) {
    http_response_code(400);
    exit('Venda não informada.');
}

$stmt = $pdo->prepare("SELECT comprovante_arquivo FROM vendas WHERE id_venda = ?");
$stmt->execute([$id_venda]);
$nome = $stmt->fetchColumn();

if (!$nome) {
    http_response_code(404);
    exit('Esta venda não tem comprovante importado.');
}

// O conteúdo fica na tabela `arquivo` (components/arquivo.php). basename()
// impede que um valor manipulado no banco aponte para outro prefixo.
require_once __DIR__ . '/../../components/arquivo.php';
$nome = basename($nome);
$arquivo = arquivoLer($pdo, 'comprovante_venda/' . $nome);

if ($arquivo === null) {
    http_response_code(404);
    exit('Arquivo do comprovante não encontrado no servidor.');
}

$extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

arquivoEnviar(
    $arquivo,
    isset($_GET['baixar']) ? 'attachment' : 'inline',
    'comprovante_venda_' . $id_venda . '.' . $extensao
);
