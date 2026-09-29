<?php
/**
 * Exporta a análise de margem em XLSX, CSV ou PDF.
 *
 * Os três formatos passam pela MESMA margemAnalise() que alimenta a tela, com
 * os mesmos filtros vindos da URL — então o arquivo não pode divergir do que o
 * usuário está vendo.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/margem_query.php');

exigirPermissao('financeiro.margem_exportar', $url_base);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use Dompdf\Options;

$filtros = margemFiltros($_GET);
$analise = margemAnalise($pdo, $filtros);
$materiais = $analise['materiais'];
$t = $analise['totais'];

$formato = $_GET['formato'] ?? 'csv';
$periodo = date('d/m/Y', strtotime($filtros['inicio'])) . ' a ' . date('d/m/Y', strtotime($filtros['fim']));
$arquivo = 'margem-' . $filtros['inicio'] . '-a-' . $filtros['fim'];

$rotulo_material = 'Todos';
if ($filtros['id_material']) {
    $stmt = $pdo->prepare("SELECT nm_material FROM tb_material WHERE id_material = ?");
    $stmt->execute([$filtros['id_material']]);
    $rotulo_material = $stmt->fetchColumn() ?: 'Todos';
}

$cabecalhos = [
    'Material', 'Tipo', 'Comprado', 'Custo das compras', 'Preço médio compra',
    'Vendido', 'Receita', 'Preço médio venda', 'Custo médio ponderado', 'CMV',
    'Margem unitária', 'Margem %', 'Margem total', 'Estoque atual',
    'Saldo médio', 'Giro', 'Curva ABC', '% da receita', 'Situação da margem', 'Situação do giro',
];

/** Uma linha de dados, na ordem dos cabeçalhos. Números crus para o Excel. */
function margemLinha(array $m, bool $formatado): array
{
    $n = fn(?float $v, int $c = 2) => $formatado ? financeiroNum($v, $c) : ($v ?? '');
    return [
        $m['nm_material'], $m['tipo'],
        $n($m['qtd_comprada']), $n($m['custo_compras']), $n($m['preco_medio_compra']),
        $n($m['qtd_vendida']), $n($m['receita']), $n($m['preco_medio_venda']),
        $n($m['custo_medio']), $n($m['cmv']),
        $n($m['margem_unitaria']), $n($m['margem_percentual']), $n($m['margem_total']),
        $n($m['estoque_atual']), $n($m['saldo_medio']), $n($m['giro']),
        $m['abc'], $n($m['receita_percentual'], 1),
        $m['alerta_margem']['rotulo'], $m['faixa_giro']['rotulo'],
    ];
}

if (ob_get_length()) {
    ob_end_clean();
}

// ==================================================================== CSV
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $arquivo . '.csv"');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");   // BOM: o Excel abre em UTF-8 sem embaralhar acentos

    fputcsv($saida, ['Análise de Margem por Material'], ';');
    fputcsv($saida, ['Período', $periodo], ';');
    fputcsv($saida, ['Material', $rotulo_material], ';');
    fputcsv($saida, ['Tipo', $filtros['tipo'] !== '' ? $filtros['tipo'] : 'Todos'], ';');
    fputcsv($saida, [], ';');
    fputcsv($saida, $cabecalhos, ';');

    foreach ($materiais as $m) {
        fputcsv($saida, margemLinha($m, true), ';');
    }

    fputcsv($saida, [], ';');
    fputcsv($saida, ['TOTAIS'], ';');
    fputcsv($saida, ['Receita', financeiroNum($t['receita'])], ';');
    fputcsv($saida, ['CMV', financeiroNum($t['cmv'])], ';');
    fputcsv($saida, ['Margem bruta', financeiroNum($t['margem'])], ';');
    fputcsv($saida, ['Margem %', financeiroNum($t['margem_percentual'])], ';');
    fputcsv($saida, ['Custo das compras do período', financeiroNum($t['custo_compras'])], ';');

    fclose($saida);
    exit;
}

// =================================================================== XLSX
if ($formato === 'xlsx') {
    $planilha = new Spreadsheet();
    $aba = $planilha->getActiveSheet();
    $aba->setTitle('Margem');

    $aba->setCellValue('A1', 'Análise de Margem por Material');
    $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $aba->setCellValue('A2', "Período: $periodo");
    $aba->setCellValue('A3', "Material: $rotulo_material · Tipo: " . ($filtros['tipo'] !== '' ? $filtros['tipo'] : 'Todos'));
    $aba->getStyle('A2:A3')->getFont()->getColor()->setRGB('64748B');

    $linha = 5;
    foreach ($cabecalhos as $i => $titulo) {
        $aba->setCellValue([$i + 1, $linha], $titulo);
    }
    $ultima_col = $aba->getCell([count($cabecalhos), $linha])->getColumn();
    $aba->getStyle("A$linha:$ultima_col$linha")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
    ]);
    $aba->freezePane("A" . ($linha + 1));

    foreach ($materiais as $m) {
        $linha++;
        foreach (margemLinha($m, false) as $i => $valor) {
            $aba->setCellValue([$i + 1, $linha], $valor);
        }
    }

    $primeira_dados = 6;
    if ($linha >= $primeira_dados) {
        $aba->getStyle("C$primeira_dados:P$linha")->getNumberFormat()->setFormatCode('#,##0.00');
    }

    $linha += 2;
    foreach ([
        ['Receita', $t['receita']], ['CMV', $t['cmv']],
        ['Margem bruta', $t['margem']], ['Margem %', $t['margem_percentual']],
        ['Custo das compras do período', $t['custo_compras']],
    ] as [$rotulo, $valor]) {
        $aba->setCellValue([1, $linha], $rotulo);
        $aba->setCellValue([2, $linha], $valor);
        $aba->getStyle([1, $linha])->getFont()->setBold(true);
        $aba->getStyle([2, $linha])->getNumberFormat()->setFormatCode('#,##0.00');
        $linha++;
    }

    foreach (range('A', $ultima_col) as $col) {
        $aba->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $arquivo . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($planilha))->save('php://output');
    exit;
}

// ==================================================================== PDF
$linhas_html = '';
foreach ($materiais as $m) {
    $cor = ($m['margem_percentual'] !== null && $m['margem_percentual'] < 0) ? 'color:#dc2626;'
         : (($m['alerta_margem']['chave'] === 'comprimida') ? 'color:#d97706;' : '');

    $linhas_html .= '<tr>'
        . '<td>' . htmlspecialchars($m['nm_material']) . '<br><span class="sub">' . htmlspecialchars($m['tipo']) . '</span></td>'
        . '<td class="n">' . financeiroNum($m['qtd_comprada']) . '</td>'
        . '<td class="n">' . financeiroNum($m['custo_compras']) . '</td>'
        . '<td class="n">' . ($m['preco_medio_compra'] !== null ? financeiroNum($m['preco_medio_compra']) : '—') . '</td>'
        . '<td class="n">' . financeiroNum($m['qtd_vendida']) . '</td>'
        . '<td class="n b">' . financeiroNum($m['receita']) . '</td>'
        . '<td class="n">' . ($m['preco_medio_venda'] !== null ? financeiroNum($m['preco_medio_venda']) : '—') . '</td>'
        . '<td class="n">' . ($m['cmv'] !== null ? financeiroNum($m['cmv']) : '—') . '</td>'
        . '<td class="n b" style="' . $cor . '">' . ($m['margem_total'] !== null ? financeiroNum($m['margem_total']) : '—') . '</td>'
        . '<td class="n b" style="' . $cor . '">' . financeiroPercentual($m['margem_percentual']) . '</td>'
        . '<td class="c">' . $m['abc'] . '</td>'
        . '</tr>';
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
    @page { margin: 14mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1e293b; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    .meta { color: #64748b; font-size: 9px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #059669; color: #fff; padding: 5px 4px; text-align: left; font-size: 8px; }
    td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
    .n { text-align: right; } .c { text-align: center; } .b { font-weight: bold; }
    .sub { color: #94a3b8; font-size: 7px; }
    .totais { margin-top: 12px; width: 60%; }
    .totais td { border: none; padding: 3px 4px; }
    .nota { margin-top: 10px; font-size: 7.5px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style></head><body>
    <h1>Análise de Margem por Material</h1>
    <div class="meta">Período: ' . $periodo
        . ' &middot; Material: ' . htmlspecialchars($rotulo_material)
        . ' &middot; Tipo: ' . htmlspecialchars($filtros['tipo'] !== '' ? $filtros['tipo'] : 'Todos')
        . ' &middot; Emitido em ' . date('d/m/Y H:i') . '</div>
    <table>
        <thead><tr>
            <th>Material</th><th class="n">Comprado</th><th class="n">Custo compras</th>
            <th class="n">Pr. médio compra</th><th class="n">Vendido</th><th class="n">Receita</th>
            <th class="n">Pr. médio venda</th><th class="n">CMV</th>
            <th class="n">Margem</th><th class="n">Margem %</th><th class="c">ABC</th>
        </tr></thead>
        <tbody>' . $linhas_html . '</tbody>
    </table>
    <table class="totais">
        <tr><td><b>Receita</b></td><td class="n">' . financeiroMoeda($t['receita']) . '</td></tr>
        <tr><td><b>CMV (custo do vendido)</b></td><td class="n">' . financeiroMoeda($t['cmv']) . '</td></tr>
        <tr><td><b>Margem bruta</b></td><td class="n">' . financeiroMoeda($t['margem']) . '</td></tr>
        <tr><td><b>Margem %</b></td><td class="n">' . financeiroPercentual($t['margem_percentual']) . '</td></tr>
        <tr><td>Custo das compras do período</td><td class="n">' . financeiroMoeda($t['custo_compras']) . '</td></tr>
    </table>
    <div class="nota">
        O CMV usa custo médio ponderado das compras até ' . date('d/m/Y', strtotime($filtros['fim'])) . ':
        o sistema não controla lote, então não há como saber por qual preço entrou o quilo que saiu.
        O custo das compras do período é o quanto se gastou comprando, e não o custo do que foi vendido.
    </div>
</body></html>';

$opcoes = new Options();
$opcoes->set('isRemoteEnabled', false);
$opcoes->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($opcoes);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream($arquivo . '.pdf', ['Attachment' => false]);
exit;
