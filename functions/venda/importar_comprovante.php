<?php
/**
 * Importa o comprovante emitido pelo comprador e arquiva junto da venda.
 *
 * Na venda quem emite o documento é quem compra o material — este sistema só
 * guarda o arquivo recebido para conferência posterior.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once __DIR__ . '/../funcoes.php';

const COMPROVANTE_TAMANHO_MAX = 10 * 1024 * 1024; // 10 MB

// O MIME é conferido pelo conteúdo do arquivo (finfo), não pela extensão nem
// pelo que o navegador declara — ambos são facilmente forjados.
const COMPROVANTE_TIPOS = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
];

$id_venda = (int) ($_POST['id_venda'] ?? 0);
$destino  = $url_base . '/vendas/detalhe?id=' . $id_venda;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id_venda) {
    header("Location: $url_base/vendas/listar");
    exit;
}

function importacaoErro(string $destino, string $mensagem): void
{
    header("Location: $destino&msgErro=" . urlencode($mensagem));
    exit;
}

$stmt = $pdo->prepare("SELECT id_venda, comprovante_arquivo FROM vendas WHERE id_venda = ?");
$stmt->execute([$id_venda]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venda) {
    importacaoErro($destino, 'Venda não encontrada.');
}

// ---------------------------------------------------------------- remoção
if (($_POST['acao'] ?? '') === 'remover') {
    if (!empty($venda['comprovante_arquivo'])) {
        $caminho = __DIR__ . '/../../uploads/comprovantes_venda/' . $venda['comprovante_arquivo'];
        if (is_file($caminho)) {
            unlink($caminho);
        }
    }

    $pdo->prepare("UPDATE vendas SET comprovante_arquivo = NULL, comprovante_importado_em = NULL WHERE id_venda = ?")
        ->execute([$id_venda]);

    registraMovimentacao(
        $_SESSION['id_usuario'],
        $_SESSION['id_usuario'],
        sprintf('Comprovante da venda #%d removido', $id_venda),
        'Comprovante venda',
        $pdo
    );

    header("Location: $destino&msgSucesso=" . urlencode('Comprovante removido.'));
    exit;
}

// ---------------------------------------------------------------- upload
$arquivo = $_FILES['comprovante'] ?? null;

if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    importacaoErro($destino, 'Selecione o arquivo do comprovante.');
}

if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    $motivos = [
        UPLOAD_ERR_INI_SIZE   => 'O arquivo excede o limite do servidor (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'O arquivo excede o limite do formulário.',
        UPLOAD_ERR_PARTIAL    => 'O envio foi interrompido antes de terminar.',
        UPLOAD_ERR_NO_TMP_DIR => 'Servidor sem diretório temporário configurado.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo.',
    ];
    importacaoErro($destino, $motivos[$arquivo['error']] ?? 'Falha no envio do arquivo.');
}

if ($arquivo['size'] > COMPROVANTE_TAMANHO_MAX) {
    importacaoErro($destino, 'Arquivo muito grande. O limite é 10 MB.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($arquivo['tmp_name']);

if (!isset(COMPROVANTE_TIPOS[$mime])) {
    importacaoErro($destino, 'Formato não aceito. Envie PDF, JPG, PNG ou WebP.');
}

$pasta = __DIR__ . '/../../uploads/comprovantes_venda/';
if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
    importacaoErro($destino, 'Não foi possível criar a pasta de comprovantes.');
}
if (!is_writable($pasta)) {
    importacaoErro($destino, 'A pasta de comprovantes não tem permissão de escrita.');
}

// Nome gerado pelo sistema: o nome enviado pelo usuário nunca vira caminho.
$nome_arquivo = sprintf('venda_%d_%s.%s', $id_venda, bin2hex(random_bytes(8)), COMPROVANTE_TIPOS[$mime]);

if (!move_uploaded_file($arquivo['tmp_name'], $pasta . $nome_arquivo)) {
    importacaoErro($destino, 'Não foi possível salvar o arquivo no servidor.');
}

// Substituição: remove o anterior para não deixar arquivo solto na pasta.
if (!empty($venda['comprovante_arquivo'])) {
    $antigo = $pasta . $venda['comprovante_arquivo'];
    if (is_file($antigo)) {
        unlink($antigo);
    }
}

$pdo->prepare("UPDATE vendas SET comprovante_arquivo = :arquivo, comprovante_importado_em = NOW() WHERE id_venda = :id")
    ->execute([':arquivo' => $nome_arquivo, ':id' => $id_venda]);

registraMovimentacao(
    $_SESSION['id_usuario'],
    $_SESSION['id_usuario'],
    sprintf('Comprovante importado na venda #%d', $id_venda),
    'Comprovante venda',
    $pdo
);

header("Location: $destino&msgSucesso=" . urlencode('Comprovante importado com sucesso!'));
exit;
