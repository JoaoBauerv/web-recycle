<?php
/**
 * Exporta as ocorrências de uma importação em CSV (§19).
 *
 * Os dados saem do log gravado no histórico, não de valores reenviados pelo
 * navegador, então o arquivo reflete o que o servidor realmente registrou.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');

exigirPermissao('venda.importar', $url_base);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Importação não informada.');
}

$stmt = $pdo->prepare("SELECT arquivo, erros FROM importacao_historico WHERE id_importacao = ?");
$stmt->execute([$id]);
$importacao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$importacao) {
    http_response_code(404);
    exit('Importação não encontrada.');
}

$erros = $importacao['erros'] ? json_decode($importacao['erros'], true) : [];

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="ocorrencias-importacao-' . $id . '.csv"');

$saida = fopen('php://output', 'w');

// BOM para o Excel abrir o arquivo em UTF-8 sem embaralhar os acentos.
fwrite($saida, "\xEF\xBB\xBF");

fputcsv($saida, ['Linha', 'Material na planilha', 'Motivo'], ';');

foreach ($erros as $e) {
    fputcsv($saida, [
        $e['linha'] ?? '',
        $e['material'] ?? '',
        $e['motivo'] ?? '',
    ], ';');
}

fclose($saida);
exit;
