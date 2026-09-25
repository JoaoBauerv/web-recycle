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

// basename() impede que um valor manipulado no banco escape da pasta (../).
$caminho = __DIR__ . '/../../uploads/comprovantes_venda/' . basename($nome);

if (!is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo do comprovante não encontrado no servidor.');
}

$extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
$tipos = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

$baixar = isset($_GET['baixar']);

header('Content-Type: ' . ($tipos[$extensao] ?? 'application/octet-stream'));
header('Content-Disposition: ' . ($baixar ? 'attachment' : 'inline')
    . '; filename="comprovante_venda_' . $id_venda . '.' . $extensao . '"');
header('Content-Length: ' . filesize($caminho));
header('X-Content-Type-Options: nosniff');

readfile($caminho);
