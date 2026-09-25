<?php
/**
 * Monta os filtros e a consulta do Relatório de Vendas.
 * Compartilhado entre a tela (relatorio.php) e a exportação em PDF
 * (relatorio_pdf.php), para os dois usarem exatamente a mesma lógica.
 *
 * Espera $pdo já disponível. Preenche no escopo de quem faz o require:
 *   $data_inicio, $data_fim, $filtro_fornecedor, $filtro_produto, $filtro_tipo,
 *   $fornecedores, $materiais, $tipos_material,
 *   $linhas (uma linha por item de venda, já com todos os filtros aplicados),
 *   $total_vendas, $total_itens, $quantidade_total, $valor_total,
 *   $por_material, $por_fornecedor
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

$filtro_fornecedor = (int) ($_REQUEST['fornecedor'] ?? 0);
$filtro_produto    = (int) ($_REQUEST['produto'] ?? 0);
$filtro_tipo       = trim($_REQUEST['tipo_material'] ?? '');

// -------- Dados para os selects de filtro --------
$fornecedores = $pdo->query("SELECT id_fornecedor, nome_razao_social FROM fornecedores WHERE status = 1 ORDER BY nome_razao_social")->fetchAll(PDO::FETCH_ASSOC);
$materiais = $pdo->query("SELECT id_material, nm_material, tipo FROM tb_material WHERE status = 1 ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);
$tipos_material = $pdo->query("SELECT DISTINCT tipo FROM tb_material WHERE status = 1 AND tipo IS NOT NULL AND tipo <> '' ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);

// -------- Consulta em nível de item (1 linha por material vendido) --------
// DATE(v.data_venda) BETWEEN cobre o dia inteiro da data final mesmo quando
// data_venda tem horário.
$sql = "SELECT vi.id_venda, vi.id_material, vi.quantidade, vi.preco_un, vi.valor_total,
               vi.peso_bruto, vi.tara,
               v.id_fornecedor, v.data_venda, v.total_valor AS venda_total_valor,
               v.status AS venda_status,
               f.nome_razao_social AS fornecedor_nome,
               COALESCE(m.nm_material, 'Material removido') AS nm_material,
               m.tipo AS material_tipo,
               COALESCE(m.unidade_medida, 'kg') AS unidade_medida
        FROM vendas_itens vi
        JOIN vendas v ON v.id_venda = vi.id_venda
        JOIN fornecedores f ON f.id_fornecedor = v.id_fornecedor
        LEFT JOIN tb_material m ON m.id_material = vi.id_material
        WHERE DATE(v.data_venda) BETWEEN :data_inicio AND :data_fim";

$params = [':data_inicio' => $data_inicio, ':data_fim' => $data_fim];

if ($filtro_fornecedor > 0) {
    $sql .= " AND v.id_fornecedor = :fornecedor";
    $params[':fornecedor'] = $filtro_fornecedor;
}
if ($filtro_produto > 0) {
    $sql .= " AND vi.id_material = :produto";
    $params[':produto'] = $filtro_produto;
}
if ($filtro_tipo !== '') {
    $sql .= " AND m.tipo = :tipo_material";
    $params[':tipo_material'] = $filtro_tipo;
}

$sql .= " ORDER BY v.data_venda DESC, vi.id_venda DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------- Totais e agrupamentos --------
// Importante: uma venda pode ter vários itens, então o valor total da venda
// (venda_total_valor) NUNCA é somado aqui — somamos sempre vendas_itens.valor_total,
// que já vem gravado por item e evita multiplicar o total da venda.
$vendas_unicas    = [];
$quantidade_total = 0.0;
$valor_total      = 0.0;
$por_material     = [];
$por_fornecedor   = [];

foreach ($linhas as $l) {
    $qtd   = (float) $l['quantidade'];
    $valor = (float) $l['valor_total'];

    $quantidade_total += $qtd;
    $valor_total      += $valor;
    $vendas_unicas[$l['id_venda']] = true;

    $idm = $l['id_material'] ?? 0;
    if (!isset($por_material[$idm])) {
        $por_material[$idm] = ['nome' => $l['nm_material'], 'tipo' => $l['material_tipo'], 'quantidade' => 0.0, 'valor' => 0.0];
    }
    $por_material[$idm]['quantidade'] += $qtd;
    $por_material[$idm]['valor']      += $valor;

    $idf = $l['id_fornecedor'];
    if (!isset($por_fornecedor[$idf])) {
        $por_fornecedor[$idf] = ['nome' => $l['fornecedor_nome'], 'vendas' => [], 'quantidade' => 0.0, 'valor' => 0.0];
    }
    $por_fornecedor[$idf]['vendas'][$l['id_venda']] = true;
    $por_fornecedor[$idf]['quantidade'] += $qtd;
    $por_fornecedor[$idf]['valor']      += $valor;
}

$total_vendas = count($vendas_unicas);
$total_itens  = count($linhas);

usort($por_material, fn($a, $b) => $b['valor'] <=> $a['valor']);
usort($por_fornecedor, fn($a, $b) => $b['valor'] <=> $a['valor']);

function relatorioVendaMoeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function relatorioVendaPeso(float $valor, string $unidade = 'kg'): string
{
    return number_format($valor, 2, ',', '.') . ' ' . $unidade;
}
