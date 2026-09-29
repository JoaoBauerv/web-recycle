<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/financeiro/margem_query.php';
require_once __DIR__ . '/../../functions/estoque/estoque_lib.php';

exigirPermissao('financeiro.margem_detalhe', $url_base);

$id_material = (int) ($_GET['id'] ?? 0);
if (!$id_material) {
    header('Location: ' . $url_base . '/financeiro/margem');
    exit;
}

$filtros = margemFiltros($_GET);
$filtros['id_material'] = $id_material;

$analise = margemAnalise($pdo, $filtros);
$m = $analise['materiais'][$id_material] ?? null;

if (!$m) {
    header('Location: ' . $url_base . '/financeiro/margem?msgErro=' . urlencode('Material não encontrado ou inativo.'));
    exit;
}

$p = [':id' => $id_material, ':inicio' => $filtros['inicio'] . ' 00:00:00', ':fim' => $filtros['fim'] . ' 23:59:59'];

$compras = $pdo->prepare("SELECT p.id_pesagem, p.data_pesagem, c.nome AS cliente,
                                 pm.peso_material, pm.peso_bruto, pm.tara, pm.preco_un,
                                 pm.peso_material * pm.preco_un AS total
                          FROM tb_pesagem_material pm
                          JOIN tb_pesagem p ON p.id_pesagem = pm.id_pesagem
                          LEFT JOIN clientes c ON c.id_cliente = p.id_cliente
                          WHERE pm.id_material = :id AND p.data_pesagem BETWEEN :inicio AND :fim
                          ORDER BY p.data_pesagem DESC");
$compras->execute($p);
$compras = $compras->fetchAll(PDO::FETCH_ASSOC);

$vendas = $pdo->prepare("SELECT v.id_venda, v.data_venda, f.nome_razao_social AS fornecedor,
                                vi.quantidade, vi.peso_bruto, vi.tara, vi.preco_un, vi.valor_total
                         FROM vendas_itens vi
                         JOIN vendas v ON v.id_venda = vi.id_venda
                         LEFT JOIN fornecedores f ON f.id_fornecedor = v.id_fornecedor
                         WHERE vi.id_material = :id AND v.data_venda BETWEEN :inicio AND :fim
                         ORDER BY v.data_venda DESC");
$vendas->execute($p);
$vendas = $vendas->fetchAll(PDO::FETCH_ASSOC);

$movimentacoes = $pdo->prepare("SELECT em.data_movimentacao, em.tipo, em.quantidade, em.origem_tipo,
                                       em.origem_id, em.observacoes
                                FROM estoque_movimentacoes em
                                WHERE em.id_material = :id AND em.data_movimentacao BETWEEN :inicio AND :fim
                                ORDER BY em.data_movimentacao DESC");
$movimentacoes->execute($p);
$movimentacoes = $movimentacoes->fetchAll(PDO::FETCH_ASSOC);

// Série para o gráfico de evolução de preços: compra e venda no mesmo eixo.
$serie = [];
foreach ($compras as $c) {
    $serie[] = ['data' => substr($c['data_pesagem'], 0, 10), 'tipo' => 'compra', 'preco' => (float) $c['preco_un']];
}
foreach ($vendas as $v) {
    $serie[] = ['data' => substr($v['data_venda'], 0, 10), 'tipo' => 'venda', 'preco' => (float) $v['preco_un']];
}
usort($serie, fn($a, $b) => $a['data'] <=> $b['data']);
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item">
                        <a href="<?= $url_base ?>/financeiro/margem?inicio=<?= $filtros['inicio'] ?>&fim=<?= $filtros['fim'] ?>"
                           class="text-decoration-none">Margem por Material</a>
                    </li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($m['nm_material']) ?></li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold">
                <?= htmlspecialchars($m['nm_material']) ?>
                <span class="badge <?= $m['alerta_margem']['classe'] ?> align-middle ms-1" style="font-size:.7rem;">
                    <i class="bi <?= $m['alerta_margem']['icone'] ?> me-1" aria-hidden="true"></i><?= $m['alerta_margem']['rotulo'] ?>
                </span>
                <span class="badge <?= ['A'=>'bg-success','B'=>'bg-primary','C'=>'bg-secondary'][$m['abc']] ?? 'bg-secondary' ?> align-middle" style="font-size:.7rem;">
                    Curva <?= $m['abc'] ?>
                </span>
            </h2>
            <small class="text-muted">
                <?= htmlspecialchars($m['tipo']) ?> ·
                <?= date('d/m/Y', strtotime($filtros['inicio'])) ?> a <?= date('d/m/Y', strtotime($filtros['fim'])) ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= $url_base ?>/estoque/extrato?id=<?= $id_material ?>" class="btn btn-outline-secondary">
                <i class="bi bi-clock-history me-1" aria-hidden="true"></i> Extrato de estoque
            </a>
            <a href="<?= $url_base ?>/financeiro/margem?inicio=<?= $filtros['inicio'] ?>&fim=<?= $filtros['fim'] ?>"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Voltar
            </a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <!-- Resumo do período -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-cart-plus me-2" aria-hidden="true"></i>Compras no período</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 fw-normal text-muted">Quantidade comprada</dt>
                        <dd class="col-5 text-end fw-semibold"><?= financeiroNum($m['qtd_comprada']) ?> <?= htmlspecialchars($m['unidade']) ?></dd>

                        <dt class="col-7 fw-normal text-muted">Custo total</dt>
                        <dd class="col-5 text-end fw-semibold"><?= financeiroMoeda($m['custo_compras']) ?></dd>

                        <dt class="col-7 fw-normal text-muted">Preço médio de compra</dt>
                        <dd class="col-5 text-end fw-semibold">
                            <?= $m['preco_medio_compra'] !== null ? financeiroMoeda($m['preco_medio_compra']) . '/' . htmlspecialchars($m['unidade']) : '—' ?>
                        </dd>

                        <dt class="col-7 fw-normal text-muted">Lançamentos</dt>
                        <dd class="col-5 text-end"><?= count($compras) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-cart-check me-2" aria-hidden="true"></i>Vendas no período</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 fw-normal text-muted">Quantidade vendida</dt>
                        <dd class="col-5 text-end fw-semibold"><?= financeiroNum($m['qtd_vendida']) ?> <?= htmlspecialchars($m['unidade']) ?></dd>

                        <dt class="col-7 fw-normal text-muted">Receita</dt>
                        <dd class="col-5 text-end fw-semibold"><?= financeiroMoeda($m['receita']) ?></dd>

                        <dt class="col-7 fw-normal text-muted">Preço médio de venda</dt>
                        <dd class="col-5 text-end fw-semibold">
                            <?= $m['preco_medio_venda'] !== null ? financeiroMoeda($m['preco_medio_venda']) . '/' . htmlspecialchars($m['unidade']) : '—' ?>
                        </dd>

                        <dt class="col-7 fw-normal text-muted">Lançamentos</dt>
                        <dd class="col-5 text-end"><?= count($vendas) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid var(--color-accent) !important;">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-graph-up-arrow me-2" aria-hidden="true"></i>Resultado</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 fw-normal text-muted">Custo médio ponderado</dt>
                        <dd class="col-5 text-end"><?= $m['custo_medio'] !== null ? financeiroMoeda($m['custo_medio']) : '—' ?></dd>

                        <dt class="col-7 fw-normal text-muted">CMV do período</dt>
                        <dd class="col-5 text-end"><?= $m['cmv'] !== null ? financeiroMoeda($m['cmv']) : '—' ?></dd>

                        <dt class="col-7 fw-normal text-muted">Margem unitária</dt>
                        <dd class="col-5 text-end fw-semibold <?= ($m['margem_unitaria'] !== null && $m['margem_unitaria'] < 0) ? 'text-danger' : '' ?>">
                            <?= $m['margem_unitaria'] !== null ? financeiroMoeda($m['margem_unitaria']) : '—' ?>
                        </dd>

                        <dt class="col-7 fw-normal text-muted">Margem %</dt>
                        <dd class="col-5 text-end fw-semibold <?= ($m['margem_percentual'] !== null && $m['margem_percentual'] < 0) ? 'text-danger' : '' ?>">
                            <?= financeiroPercentual($m['margem_percentual']) ?>
                        </dd>

                        <dt class="col-7 fw-normal text-muted">Margem total</dt>
                        <dd class="col-5 text-end fw-semibold"
                            style="<?= ($m['margem_total'] !== null && $m['margem_total'] >= 0) ? 'color: var(--color-accent);' : '' ?>">
                            <?= $m['margem_total'] !== null ? financeiroMoeda($m['margem_total']) : '—' ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Estoque e giro -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Estoque atual</span>
                        <span class="indicador-valor"><?= financeiroNum($m['estoque_atual']) ?> <?= htmlspecialchars($m['unidade']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Giro no período</span>
                        <span class="indicador-valor"><?= $m['giro'] !== null ? financeiroNum($m['giro']) : '—' ?></span>
                        <small class="text-muted"><?= $m['faixa_giro']['rotulo'] ?></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-speedometer" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Saldo médio do período</span>
                        <span class="indicador-valor"><?= $m['saldo_medio'] !== null ? financeiroNum($m['saldo_medio']) : '—' ?></span>
                        <small class="text-muted">
                            <?= $m['saldo_medio'] !== null ? 'ponderado pelo tempo' : 'trilha incoerente no período' ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Evolução de preços -->
    <?php if (count($serie) > 1): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-graph-up me-2" aria-hidden="true"></i>Evolução de preços</h3>
            </div>
            <div class="card-body">
                <canvas id="graficoPrecos" height="80"
                        aria-label="Gráfico de evolução dos preços de compra e venda no período" role="img"></canvas>
                <p class="visually-hidden">
                    Preço médio de compra <?= $m['preco_medio_compra'] !== null ? financeiroMoeda($m['preco_medio_compra']) : 'não disponível' ?>,
                    preço médio de venda <?= $m['preco_medio_venda'] !== null ? financeiroMoeda($m['preco_medio_venda']) : 'não disponível' ?>.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Históricos -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-cart-plus me-2" aria-hidden="true"></i>Histórico de compras</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!$compras): ?>
                        <p class="text-muted text-center py-4 mb-0">Nenhuma compra no período.</p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Data</th><th>Cliente</th>
                                        <th class="text-end">Qtd.</th><th class="text-end">Preço</th>
                                        <th class="text-end pe-3">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($compras as $c): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <a href="<?= $url_base ?>/compras/detalhe?id=<?= (int) $c['id_pesagem'] ?>" class="text-decoration-none">
                                                <?= date('d/m/Y', strtotime($c['data_pesagem'])) ?>
                                            </a>
                                        </td>
                                        <td class="text-truncate" style="max-width:130px;"><?= htmlspecialchars($c['cliente'] ?? '—') ?></td>
                                        <td class="text-end"><?= financeiroNum((float) $c['peso_material']) ?></td>
                                        <td class="text-end"><?= financeiroMoeda((float) $c['preco_un']) ?></td>
                                        <td class="text-end pe-3"><?= financeiroMoeda((float) $c['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-cart-check me-2" aria-hidden="true"></i>Histórico de vendas</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!$vendas): ?>
                        <p class="text-muted text-center py-4 mb-0">Nenhuma venda no período.</p>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Data</th><th>Fornecedor</th>
                                        <th class="text-end">Qtd.</th><th class="text-end">Preço</th>
                                        <th class="text-end pe-3">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($vendas as $v): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <a href="<?= $url_base ?>/vendas/detalhe?id=<?= (int) $v['id_venda'] ?>" class="text-decoration-none">
                                                <?= date('d/m/Y', strtotime($v['data_venda'])) ?>
                                            </a>
                                        </td>
                                        <td class="text-truncate" style="max-width:130px;"><?= htmlspecialchars($v['fornecedor'] ?? '—') ?></td>
                                        <td class="text-end"><?= financeiroNum((float) $v['quantidade']) ?></td>
                                        <td class="text-end"><?= financeiroMoeda((float) $v['preco_un']) ?></td>
                                        <td class="text-end pe-3 fw-semibold"><?= financeiroMoeda((float) $v['valor_total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Movimentações de estoque -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white border-bottom">
            <h3 class="h6 fw-semibold mb-0">
                <i class="bi bi-arrow-left-right me-2" aria-hidden="true"></i>
                Movimentações de estoque no período (<?= count($movimentacoes) ?>)
            </h3>
        </div>
        <div class="card-body p-0">
            <?php if (!$movimentacoes): ?>
                <p class="text-muted text-center py-4 mb-0">Nenhuma movimentação no período.</p>
            <?php else: ?>
                <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Data</th><th>Tipo</th><th>Origem</th>
                                <th class="text-end pe-3">Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($movimentacoes as $mv):
                            $entrada = estoqueSinal($mv['tipo']) > 0;
                            $origem = estoqueRotuloOrigem($mv['origem_tipo']);
                            $destino = ($origem['rota'] && $mv['origem_id'])
                                ? $url_base . '/' . $origem['rota'] . '?id=' . (int) $mv['origem_id'] : null;
                        ?>
                            <tr>
                                <td class="ps-3"><?= date('d/m/Y H:i', strtotime($mv['data_movimentacao'])) ?></td>
                                <td>
                                    <span class="badge <?= $entrada ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <?= ucfirst($mv['tipo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($destino): ?>
                                        <a href="<?= $destino ?>" class="text-decoration-none"><?= $origem['rotulo'] ?> #<?= (int) $mv['origem_id'] ?></a>
                                    <?php else: ?>
                                        <span class="text-muted"><?= $origem['rotulo'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3 <?= $entrada ? 'text-success' : 'text-danger' ?>">
                                    <?= $entrada ? '+' : '−' ?> <?= financeiroNum((float) $mv['quantidade']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (count($serie) > 1): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
$(function () {
    const serie = <?= json_encode($serie) ?>;
    const datas = [...new Set(serie.map(p => p.data))].sort();

    // Em dias com mais de um lançamento, mostra a média do dia.
    function mediaPorDia(tipo) {
        return datas.map(function (d) {
            const pontos = serie.filter(p => p.data === d && p.tipo === tipo);
            if (!pontos.length) return null;
            return pontos.reduce((s, p) => s + p.preco, 0) / pontos.length;
        });
    }

    new Chart(document.getElementById('graficoPrecos'), {
        type: 'line',
        data: {
            labels: datas.map(d => d.split('-').reverse().join('/')),
            datasets: [
                { label: 'Preço de compra', data: mediaPorDia('compra'),
                  borderColor: '#334155', backgroundColor: '#33415522', spanGaps: true, tension: .2 },
                { label: 'Preço de venda', data: mediaPorDia('venda'),
                  borderColor: '#059669', backgroundColor: '#05966922', spanGaps: true, tension: .2 }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: { callbacks: { label: c => c.dataset.label + ': R$ ' +
                    (c.parsed.y ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) } }
            },
            scales: { y: { beginAtZero: false, ticks: { callback: v => 'R$ ' + v.toLocaleString('pt-BR') } } }
        }
    });
});
</script>
<?php endif; ?>
