<?php
/**
 * Monta os filtros e a consulta do Relatório de Compras.
 * Compartilhado entre a tela (relatorio.php) e a exportação em PDF (relatorio_pdf.php),
 * para garantir que os dois usem exatamente a mesma lógica de filtro/soma.
 *
 * Espera $pdo já disponível. Preenche no escopo de quem faz o require:
 *   $data_inicio, $data_fim, $filtro_cliente, $filtro_produto, $filtro_tipo,
 *   $clientes, $materiais, $tipos_material,
 *   $linhas (uma linha por item de compra, já com todos os filtros aplicados),
 *   $total_compras, $total_itens, $quantidade_total, $valor_total,
 *   $por_material, $por_cliente
 */

// -------- Filtros --------
$data_inicio = $_REQUEST['data_inicio'] ?? date('Y-m-01');
$data_fim    = $_REQUEST['data_fim'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) {
    $data_inicio = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) {
    $data_fim = date('Y-m-d');
}
if ($data_inicio > $data_fim) {
    [$data_inicio, $data_fim] = [$data_fim, $data_inicio];
}

$filtro_cliente = (int) ($_REQUEST['cliente'] ?? 0);
$filtro_produto = (int) ($_REQUEST['produto'] ?? 0);
$filtro_tipo    = trim($_REQUEST['tipo_material'] ?? '');

// -------- Dados para os selects de filtro --------
$clientes = $pdo->query("SELECT id_cliente, nome FROM clientes WHERE status = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$materiais = $pdo->query("SELECT id_material, nm_material, tipo FROM tb_material WHERE status = 1 ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);
$tipos_material = $pdo->query("SELECT DISTINCT tipo FROM tb_material WHERE status = 1 AND tipo IS NOT NULL AND tipo <> '' ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);

// -------- Consulta em nível de item (1 linha por material pesado) --------
// DATE(p.data_pesagem) BETWEEN :data_inicio AND :data_fim cobre o dia inteiro da
// data final mesmo quando data_pesagem tem horário (ex: 2026-09-30 23:59:59 entra).
$sql = "SELECT pm.id_pesagem, pm.id_material, pm.peso_material, pm.preco_un,
               p.id_cliente, p.data_pesagem, p.total_valor AS compra_total_valor,
               c.nome AS cliente_nome,
               COALESCE(m.nm_material, 'Material removido') AS nm_material,
               m.tipo AS material_tipo,
               COALESCE(m.unidade_medida, 'kg') AS unidade_medida
        FROM tb_pesagem_material pm
        JOIN tb_pesagem p ON p.id_pesagem = pm.id_pesagem
        JOIN clientes c ON c.id_cliente = p.id_cliente
        LEFT JOIN tb_material m ON m.id_material = pm.id_material
        WHERE p.total_valor > 0
          AND DATE(p.data_pesagem) BETWEEN :data_inicio AND :data_fim";

$params = [':data_inicio' => $data_inicio, ':data_fim' => $data_fim];

if ($filtro_cliente > 0) {
    $sql .= " AND p.id_cliente = :cliente";
    $params[':cliente'] = $filtro_cliente;
}
if ($filtro_produto > 0) {
    $sql .= " AND pm.id_material = :produto";
    $params[':produto'] = $filtro_produto;
}
if ($filtro_tipo !== '') {
    $sql .= " AND m.tipo = :tipo_material";
    $params[':tipo_material'] = $filtro_tipo;
}

$sql .= " ORDER BY p.data_pesagem DESC, pm.id_pesagem DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------- Totais e agrupamentos --------
// Importante: uma compra pode ter vários itens, então o valor total da compra
// (compra_total_valor) NUNCA é somado aqui — somamos sempre o valor por item
// (peso_material * preco_un), que é o que evita duplicar o total da compra.
$compras_unicas   = [];
$quantidade_total = 0.0;
$valor_total      = 0.0;
$por_material     = [];
$por_cliente      = [];

foreach ($linhas as $l) {
    $peso  = (float) $l['peso_material'];
    $valor = $peso * (float) $l['preco_un'];

    $quantidade_total += $peso;
    $valor_total      += $valor;
    $compras_unicas[$l['id_pesagem']] = true;

    $idm = $l['id_material'] ?? 0;
    if (!isset($por_material[$idm])) {
        $por_material[$idm] = ['nome' => $l['nm_material'], 'tipo' => $l['material_tipo'], 'quantidade' => 0.0, 'valor' => 0.0];
    }
    $por_material[$idm]['quantidade'] += $peso;
    $por_material[$idm]['valor']      += $valor;

    $idc = $l['id_cliente'];
    if (!isset($por_cliente[$idc])) {
        $por_cliente[$idc] = ['nome' => $l['cliente_nome'], 'compras' => [], 'quantidade' => 0.0, 'valor' => 0.0];
    }
    $por_cliente[$idc]['compras'][$l['id_pesagem']] = true;
    $por_cliente[$idc]['quantidade'] += $peso;
    $por_cliente[$idc]['valor']      += $valor;
}

$total_compras = count($compras_unicas);
$total_itens   = count($linhas);

usort($por_material, fn($a, $b) => $b['valor'] <=> $a['valor']);
usort($por_cliente, fn($a, $b) => $b['valor'] <=> $a['valor']);

function relatorioCompraMoeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function relatorioCompraPeso(float $valor, string $unidade = 'kg'): string
{
    return number_format($valor, 2, ',', '.') . ' ' . $unidade;
}
