<?php
require_once (__DIR__ . '/../../components/middleware.php');
require_once __DIR__ . '/../../vendor/autoload.php';
require '../../banco.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require __DIR__ . '/relatorio_query.php';

$logoPath = __DIR__ . "/../../images/logo.png";
$logoBase64 = is_file($logoPath)
    ? "data:image/png;base64," . base64_encode(file_get_contents($logoPath))
    : '';

$filtros_aplicados = [];
if ($filtro_fornecedor > 0) {
    foreach ($fornecedores as $f) {
        if ((int) $f['id_fornecedor'] === $filtro_fornecedor) {
            $filtros_aplicados[] = 'Fornecedor: ' . $f['nome_razao_social'];
        }
    }
}
if ($filtro_produto > 0) {
    foreach ($materiais as $m) {
        if ((int) $m['id_material'] === $filtro_produto) {
            $filtros_aplicados[] = 'Material: ' . $m['nm_material'];
        }
    }
}
if ($filtro_tipo !== '') {
    $filtros_aplicados[] = 'Tipo: ' . $filtro_tipo;
}

ob_start();
?>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        h2 { margin:0; color:#222; text-align:center; }
        .header { text-align:center; margin-bottom:15px; }
        .header small { font-size:10px; color:#666; }
        table { width:100%; border-collapse: collapse; margin-top:8px; }
        table th, table td { border:1px solid #ccc; padding:5px; font-size:10px; }
        table th { background:#f2f2f2; }
        .total { font-weight:bold; background:#f9f9f9; }
        .logo { height: 50px; }
        .resumo-kpi { display:table; width:100%; margin-bottom:10px; }
        .resumo-kpi .item { display:table-cell; width:25%; text-align:center; border:1px solid #ccc; padding:8px; }
        .resumo-kpi .item b { display:block; font-size:13px; }
        .resumo-kpi .item span { font-size:9px; color:#666; text-transform:uppercase; }
        h4.section { margin-top:15px; margin-bottom:0; }
    </style>
</head>
<body>

<div class="header">
    <?php if ($logoBase64): ?><img src="<?= $logoBase64 ?>" class="logo"><?php endif; ?>
    <h2>Relatório de Vendas de Materiais Recicláveis</h2>
    <small>
        Período: <?= date('d/m/Y', strtotime($data_inicio)) ?> até <?= date('d/m/Y', strtotime($data_fim)) ?>
        <?= $filtros_aplicados ? ' | ' . implode(' | ', $filtros_aplicados) : '' ?>
    </small>
</div>

<div class="resumo-kpi">
    <div class="item"><span>Total de Vendas</span><b><?= $total_vendas ?></b></div>
    <div class="item"><span>Total de Itens</span><b><?= $total_itens ?></b></div>
    <div class="item"><span>Quantidade Total</span><b><?= relatorioVendaPeso($quantidade_total) ?></b></div>
    <div class="item"><span>Valor Total Vendido</span><b><?= relatorioVendaMoeda($valor_total) ?></b></div>
</div>

<h4 class="section">Vendas Detalhadas</h4>
<table>
    <thead>
        <tr>
            <th>Venda</th>
            <th>Data</th>
            <th>Fornecedor</th>
            <th>Material</th>
            <th>Quantidade</th>
            <th>Valor Unit.</th>
            <th>Valor Total (item)</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($linhas)): ?>
        <tr><td colspan="7" style="text-align:center;">Nenhuma venda encontrada com esses filtros.</td></tr>
    <?php else: foreach ($linhas as $l): ?>
        <tr>
            <td>#<?= (int) $l['id_venda'] ?></td>
            <td><?= date('d/m/Y H:i', strtotime($l['data_venda'])) ?></td>
            <td><?= htmlspecialchars($l['fornecedor_nome']) ?></td>
            <td><?= htmlspecialchars($l['nm_material']) ?></td>
            <td><?= relatorioVendaPeso((float) $l['quantidade'], $l['unidade_medida']) ?></td>
            <td><?= relatorioVendaMoeda((float) $l['preco_un']) ?></td>
            <td><?= relatorioVendaMoeda((float) $l['valor_total']) ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($linhas)): ?>
    <tfoot>
        <tr class="total">
            <td colspan="4">TOTAL</td>
            <td><?= relatorioVendaPeso($quantidade_total) ?></td>
            <td>-</td>
            <td><?= relatorioVendaMoeda($valor_total) ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<?php if (!empty($por_material)): ?>
<h4 class="section">Resumo por Material</h4>
<table>
    <thead>
        <tr><th>Material</th><th>Quantidade Total</th><th>Valor Total</th></tr>
    </thead>
    <tbody>
    <?php foreach ($por_material as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['nome']) ?></td>
            <td><?= relatorioVendaPeso($m['quantidade']) ?></td>
            <td><?= relatorioVendaMoeda($m['valor']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if (!empty($por_fornecedor)): ?>
<h4 class="section">Resumo por Fornecedor</h4>
<table>
    <thead>
        <tr><th>Fornecedor</th><th>Vendas</th><th>Quantidade Total</th><th>Valor Total</th></tr>
    </thead>
    <tbody>
    <?php foreach ($por_fornecedor as $f): ?>
        <tr>
            <td><?= htmlspecialchars($f['nome']) ?></td>
            <td><?= count($f['vendas']) ?></td>
            <td><?= relatorioVendaPeso($f['quantidade']) ?></td>
            <td><?= relatorioVendaMoeda($f['valor']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$dompdf->stream('relatorio_vendas_' . date('Y-m-d', strtotime($data_inicio)) . '_a_' . date('Y-m-d', strtotime($data_fim)) . '.pdf', ["Attachment" => false]);
