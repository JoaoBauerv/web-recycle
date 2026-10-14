<?php
/**
 * Exporta uma compra no formato da planilha de importação de VENDA.
 *
 * Serve ao caso em que as duas pontas usam este sistema. A mesma transação é
 * compra para quem recebe o material e venda para quem entrega: esta empresa
 * registra a compra, exporta o arquivo e entrega ao parceiro, que o importa em
 * vendas sem redigitar nada.
 *
 * A inversão de perspectiva está na coluna "Fornecedor". Na planilha de venda
 * ela identifica QUEM COMPRA — e, do ponto de vista de quem vai importar, quem
 * compra somos nós. Por isso ela recebe o nome desta empresa (APP_NAME), e não
 * o do cliente que nos vendeu.
 *
 * O layout vem de VENDA_PLANILHA_COLUNAS, a mesma constante que o modelo e o
 * leitor usam. Mudar a ordem ou o nome de uma coluna lá reflete aqui sozinho.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../venda/planilha.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$id_pesagem = (int) ($_GET['id'] ?? 0);

function exportarVoltar(string $url_base, string $mensagem): void
{
    header("Location: $url_base/compras/listar?msgErro=" . urlencode($mensagem));
    exit;
}

if (!$id_pesagem) {
    exportarVoltar($url_base, 'Compra não informada.');
}

$stmt = $pdo->prepare("SELECT p.id_pesagem, p.data_pesagem, p.status, c.nome AS cliente_nome
                       FROM pesagem p
                       LEFT JOIN clientes c ON c.id_cliente = p.id_cliente
                       WHERE p.id_pesagem = :id");
$stmt->execute([':id' => $id_pesagem]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    exportarVoltar($url_base, 'Compra não encontrada.');
}

// Compra cancelada não vira venda de ninguém: o material não mudou de mãos.
// Exportá-la faria o parceiro lançar uma venda que não aconteceu.
if (($compra['status'] ?? 'ativa') === 'cancelada') {
    exportarVoltar($url_base, 'Compra #' . $id_pesagem . ' está cancelada e não pode ser exportada.');
}

$itens = $pdo->prepare("SELECT m.nm_material, m.codigo,
                               pm.peso_bruto, pm.tara, pm.preco_un
                        FROM pesagem_material pm
                        JOIN material m ON m.id_material = pm.id_material
                        WHERE pm.id_pesagem = :id
                        ORDER BY m.nm_material");
$itens->execute([':id' => $id_pesagem]);
$itens = $itens->fetchAll(PDO::FETCH_ASSOC);

if (!$itens) {
    exportarVoltar($url_base, 'Compra #' . $id_pesagem . ' não tem itens para exportar.');
}

$empresa = trim((string) ($_ENV['APP_NAME'] ?? ''));
if ($empresa === '') {
    exportarVoltar($url_base, 'Defina APP_NAME no arquivo .env: é o nome que identifica esta empresa na planilha exportada.');
}

/**
 * Número do pedido que o parceiro verá.
 *
 * Do outro lado isso vira vendas.pedido_externo, que tem índice único: um
 * número solto como "12" colidiria com o pedido 12 de qualquer outro
 * fornecedor. Por isso o identificador carrega uma sigla desta empresa, o que
 * o torna único na base de quem importa. O campo é varchar(60), daí o corte.
 */
function exportarPedido(string $empresa, int $id_pesagem): string
{
    $sigla = preg_replace('/[^A-Za-z0-9]/', '', $empresa);
    $sigla = strtoupper(substr($sigla, 0, 12)) ?: 'COMPRA';
    return substr($sigla . '-' . $id_pesagem, 0, 60);
}

$pedido = exportarPedido($empresa, $id_pesagem);
$data   = date('d/m/Y', strtotime($compra['data_pesagem']));

// ------------------------------------------------------------------ planilha
$planilha = new Spreadsheet();
$planilha->getProperties()
    ->setCreator($empresa)
    ->setTitle('Venda ' . $pedido);

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

// Uma linha por item, na ordem de VENDA_PLANILHA_COLUNAS.
$linha = 2;
foreach ($itens as $item) {
    $valores = [
        $empresa,                        // Fornecedor: quem compra, na ótica de quem importa
        $item['codigo'] ?? '',
        $item['nm_material'],
        (float) $item['peso_bruto'],
        (float) $item['tara'],
        (float) $item['preco_un'],
        $pedido,
        $data,
    ];
    foreach ($valores as $i => $valor) {
        $aba->setCellValue([$i + 1, $linha], $valor);
    }
    $linha++;
}

$ultima_linha = $linha - 1;
$aba->getStyle("D2:F{$ultima_linha}")->getNumberFormat()->setFormatCode('#,##0.00');

foreach (range('A', $ultima) as $letra) {
    $aba->getColumnDimension($letra)->setWidth(in_array($letra, ['A', 'C'], true) ? 32 : 18);
}

$aba->freezePane('A2');

$arquivo = sprintf('venda-%s-%s.xlsx', $pedido, date('Ymd', strtotime($compra['data_pesagem'])));

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Cache-Control: max-age=0');

(new Xlsx($planilha))->save('php://output');
exit;
