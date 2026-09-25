<?php
/**
 * Gera o modelo .xlsx da planilha de venda.
 *
 * Além dos cabeçalhos, leva uma aba com os materiais que existem em estoque hoje:
 * quem preenche precisa escrever o nome exatamente como está cadastrado, e ter a
 * lista na própria planilha evita a maior fonte de erro na importação.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/planilha.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$materiais = $pdo->query("SELECT nm_material, codigo, tipo, unidade_medida, preco_venda,
                                 COALESCE(qt_estoque, 0) AS qt_estoque
                          FROM tb_material
                          WHERE status = 1 AND COALESCE(qt_estoque, 0) > 0
                          ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);

$fornecedor = $pdo->query("SELECT nome_razao_social FROM fornecedores
                           WHERE status = 1 ORDER BY nome_razao_social LIMIT 1")->fetchColumn();

$planilha = new Spreadsheet();
$planilha->getProperties()
    ->setCreator('Sistema de Reciclagem')
    ->setTitle('Modelo de importação de venda');

// ------------------------------------------------------------------ aba venda
$aba = $planilha->getActiveSheet();
$aba->setTitle('Venda');

$colunas = array_values(VENDA_PLANILHA_COLUNAS);
foreach ($colunas as $i => $titulo) {
    $aba->setCellValue([$i + 1, 1], $titulo);
}

$ultima = chr(ord('A') + count($colunas) - 1);

$aba->getStyle("A1:{$ultima}1")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$aba->getRowDimension(1)->setRowHeight(24);

// Linha de exemplo, com material real quando houver estoque.
// A ordem acompanha VENDA_PLANILHA_COLUNAS.
$exemplo = [
    $fornecedor ?: 'Nome do cliente',
    $materiais[0]['codigo'] ?? 'PET-BR',
    $materiais[0]['nm_material'] ?? 'Nome do material',
    120.50,
    5.00,
    2.35,
    '45872',
    date('d/m/Y'),
];
foreach ($exemplo as $i => $valor) {
    $aba->setCellValue([$i + 1, 2], $valor);
}
$aba->getStyle("A2:{$ultima}2")->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
$aba->getStyle('D2:F2')->getNumberFormat()->setFormatCode('#,##0.00');

foreach (range('A', $ultima) as $letra) {
    $aba->getColumnDimension($letra)->setWidth(in_array($letra, ['A', 'C'], true) ? 32 : 18);
}

$aba->setCellValue('A4', 'Como preencher:');
$aba->setCellValue('A5', '• Apague a linha 2 (exemplo) e escreva uma linha por material vendido.');
$aba->setCellValue('A6', '• Cliente, Pedido e Data só precisam aparecer na primeira linha.');
$aba->setCellValue('A7', '• Basta Código OU Material — se vierem os dois, o código tem prioridade.');
$aba->setCellValue('A8', '• Nome diferente do cadastro? O sistema pergunta uma vez e guarda a relação.');
$aba->setCellValue('A9', '• Tara é opcional; Quantidade e Valor unitário são obrigatórios e maiores que zero.');
$aba->setCellValue('A10', '• Pedido evita importar a mesma venda duas vezes.');
$aba->setCellValue('A11', '• A ordem das colunas pode ser outra: no sistema você indica qual coluna é qual.');
$aba->getStyle('A4')->getFont()->setBold(true);
$aba->getStyle('A4:A11')->getFont()->getColor()->setRGB('475569');

$aba->getStyle("A1:{$ultima}2")->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

// -------------------------------------------------------------- aba materiais
$lista = $planilha->createSheet();
$lista->setTitle('Materiais');

$lista->fromArray(['Material', 'Código', 'Tipo', 'Unidade', 'Estoque disponível', 'Último preço vendido'], null, 'A1');
$lista->getStyle('A1:F1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
]);

$linha = 2;
foreach ($materiais as $m) {
    $lista->setCellValue([1, $linha], $m['nm_material']);
    $lista->setCellValue([2, $linha], $m['codigo']);
    $lista->setCellValue([3, $linha], $m['tipo']);
    $lista->setCellValue([4, $linha], $m['unidade_medida']);
    $lista->setCellValue([5, $linha], (float) $m['qt_estoque']);
    $lista->setCellValue([6, $linha], $m['preco_venda'] !== null ? (float) $m['preco_venda'] : '');
    $linha++;
}

if ($linha === 2) {
    $lista->setCellValue('A2', 'Nenhum material em estoque no momento.');
}

$lista->getStyle('E2:F' . max($linha - 1, 2))->getNumberFormat()->setFormatCode('#,##0.00');
foreach (['A' => 32, 'B' => 14, 'C' => 18, 'D' => 12, 'E' => 20, 'F' => 24] as $letra => $largura) {
    $lista->getColumnDimension($letra)->setWidth($largura);
}
$lista->freezePane('A2');

$planilha->setActiveSheetIndex(0);
$aba->setSelectedCell('A2');

// O writer escreve direto na saída, então nada pode ter sido impresso antes.
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="modelo-venda.xlsx"');
header('Cache-Control: max-age=0');

(new Xlsx($planilha))->save('php://output');
exit;
