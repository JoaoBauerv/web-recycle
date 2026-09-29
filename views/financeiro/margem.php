<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/financeiro/margem_query.php';

exigirPermissao('financeiro.margem', $url_base);

$filtros = margemFiltros($_GET);
$analise = margemAnalise($pdo, $filtros);
$materiais = $analise['materiais'];
$t = $analise['totais'];

$tipos = margemTiposMaterial($pdo);
$lista_materiais = $pdo->query("SELECT id_material, nm_material FROM tb_material
                                WHERE status = 1 ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);

// Query string dos filtros, para os botões de exportação respeitarem a tela.
$qs = http_build_query([
    'inicio' => $filtros['inicio'], 'fim' => $filtros['fim'],
    'id_material' => $filtros['id_material'], 'tipo' => $filtros['tipo'],
]);

$atalhos = [
    'hoje' => 'Hoje', 'mes' => 'Este mês', 'mes_anterior' => 'Mês anterior',
    'dias30' => 'Últimos 30 dias', 'meses3' => 'Últimos 3 meses', 'ano' => 'Este ano',
];

$alertas = array_filter($materiais, fn($m) => in_array($m['alerta_margem']['chave'], ['negativa', 'comprimida'], true));
$parados = array_filter($materiais, fn($m) => in_array($m['faixa_giro']['chave'], ['parado', 'baixo'], true) && $m['estoque_atual'] > 0);
?>

<div class="container-fluid py-4" style="max-width: 1500px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1 fw-bold">
                <i class="bi bi-graph-up-arrow me-2" style="color: var(--color-accent);" aria-hidden="true"></i>Margem por Material
            </h2>
            <p class="text-muted mb-0">
                <?= date('d/m/Y', strtotime($filtros['inicio'])) ?> a <?= date('d/m/Y', strtotime($filtros['fim'])) ?>
            </p>
        </div>
        <?php if (usuarioPode('financeiro.margem_exportar')): ?>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= $url_base ?>/functions/financeiro/margem_export.php?formato=xlsx&<?= $qs ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Excel
                </a>
                <a href="<?= $url_base ?>/functions/financeiro/margem_export.php?formato=csv&<?= $qs ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i> CSV
                </a>
                <a href="<?= $url_base ?>/functions/financeiro/margem_export.php?formato=pdf&<?= $qs ?>" class="btn btn-outline-secondary" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> PDF
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="pagina" value="financeiro/margem">
                <div class="col-md-2">
                    <label for="inicio" class="form-label small text-muted mb-1">Início</label>
                    <input type="date" name="inicio" id="inicio" class="form-control" value="<?= $filtros['inicio'] ?>">
                </div>
                <div class="col-md-2">
                    <label for="fim" class="form-label small text-muted mb-1">Fim</label>
                    <input type="date" name="fim" id="fim" class="form-control" value="<?= $filtros['fim'] ?>">
                </div>
                <div class="col-md-3">
                    <label for="id_material" class="form-label small text-muted mb-1">Material</label>
                    <select name="id_material" id="id_material" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($lista_materiais as $m): ?>
                            <option value="<?= (int) $m['id_material'] ?>"
                                <?= $filtros['id_material'] === (int) $m['id_material'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nm_material']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="tipo" class="form-label small text-muted mb-1">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tipos as $tp): ?>
                            <option value="<?= htmlspecialchars($tp) ?>" <?= $filtros['tipo'] === $tp ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1" aria-hidden="true"></i> Filtrar
                    </button>
                    <a href="<?= $url_base ?>/financeiro/margem" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-1 mt-3">
                <span class="small text-muted me-1 align-self-center">Atalhos:</span>
                <?php foreach ($atalhos as $chave => $rotulo): ?>
                    <a href="<?= $url_base ?>/financeiro/margem?atalho=<?= $chave ?>"
                       class="btn btn-sm <?= $filtros['atalho'] === $chave ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= $rotulo ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Receita', financeiroMoeda($t['receita']), 'bi-cash-coin', '', number_format($t['qtd_vendida'], 2, ',', '.') . ' kg vendidos'],
            ['Custo do vendido (CMV)', financeiroMoeda($t['cmv']), 'bi-box-arrow-in-down', '', 'custo médio ponderado'],
            ['Margem bruta', financeiroMoeda($t['margem']), 'bi-graph-up', 'var(--color-accent)', 'receita − CMV'],
            ['Margem %', financeiroPercentual($t['margem_percentual']), 'bi-percent',
             ($t['margem_percentual'] !== null && $t['margem_percentual'] < MARGEM_ALERTA_PERCENTUAL) ? '#d97706' : 'var(--color-accent)',
             $t['com_venda'] . ' material(is) com venda'],
        ];
        foreach ($cards as [$rotulo, $valor, $icone, $cor, $ajuda]): ?>
            <div class="col-xl-3 col-md-6">
                <div class="card card-indicador h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="indicador-icone"><i class="bi <?= $icone ?>" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <span class="indicador-rotulo"><?= $rotulo ?></span>
                            <span class="indicador-valor" <?= $cor ? 'style="color:' . $cor . ';"' : '' ?>><?= $valor ?></span>
                            <small class="text-muted"><?= $ajuda ?></small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Nota sobre o método de custo: o número muda de significado sem ela -->
    <div class="alert alert-light border small d-flex align-items-start mb-4" role="alert">
        <i class="bi bi-info-circle me-2 mt-1" aria-hidden="true"></i>
        <div>
            O <strong>CMV</strong> usa custo médio ponderado de todas as compras até
            <?= date('d/m/Y', strtotime($filtros['fim'])) ?>, porque o sistema não controla lote:
            não há como saber por qual preço entrou o quilo que saiu.
            Ele é diferente do <strong>custo das compras do período</strong>
            (<?= financeiroMoeda($t['custo_compras']) ?>), que é o quanto se gastou comprando —
            e não o custo do que foi vendido.
        </div>
    </div>

    <!-- Alertas -->
    <?php if ($alertas || $parados): ?>
        <div class="row g-3 mb-4">
            <?php if ($alertas): ?>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #d97706 !important;">
                        <div class="card-header bg-white border-bottom">
                            <h3 class="h6 fw-semibold mb-0">
                                <i class="bi bi-exclamation-triangle me-2" style="color:#d97706;" aria-hidden="true"></i>
                                Margem comprimida ou negativa (<?= count($alertas) ?>)
                            </h3>
                        </div>
                        <div class="card-body p-0" style="max-height:280px; overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <tbody>
                                <?php foreach ($alertas as $m): ?>
                                    <tr>
                                        <td class="ps-3"><?= htmlspecialchars($m['nm_material']) ?></td>
                                        <td class="text-end"><?= financeiroPercentual($m['margem_percentual']) ?></td>
                                        <td class="pe-3">
                                            <span class="badge <?= $m['alerta_margem']['classe'] ?>">
                                                <i class="bi <?= $m['alerta_margem']['icone'] ?> me-1" aria-hidden="true"></i>
                                                <?= $m['alerta_margem']['rotulo'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($parados): ?>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #64748b !important;">
                        <div class="card-header bg-white border-bottom">
                            <h3 class="h6 fw-semibold mb-0">
                                <i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>
                                Estoque parado ou de baixo giro (<?= count($parados) ?>)
                            </h3>
                        </div>
                        <div class="card-body p-0" style="max-height:280px; overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Material</th>
                                        <th class="text-end">Estoque</th>
                                        <th class="text-end">Vendido</th>
                                        <th class="pe-3">Giro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($parados as $m): ?>
                                    <tr>
                                        <td class="ps-3"><?= htmlspecialchars($m['nm_material']) ?></td>
                                        <td class="text-end"><?= financeiroNum($m['estoque_atual']) ?></td>
                                        <td class="text-end"><?= financeiroNum($m['qtd_vendida']) ?></td>
                                        <td class="pe-3">
                                            <span class="badge <?= $m['faixa_giro']['classe'] ?>"><?= $m['faixa_giro']['rotulo'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Tabela principal -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="h6 fw-semibold mb-0"><i class="bi bi-table me-2" aria-hidden="true"></i>Análise por material</h3>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtrar por classe ABC">
                <button type="button" class="btn btn-outline-secondary active" data-abc="">Todos</button>
                <button type="button" class="btn btn-outline-secondary" data-abc="A">Curva A</button>
                <button type="button" class="btn btn-outline-secondary" data-abc="B">Curva B</button>
                <button type="button" class="btn btn-outline-secondary" data-abc="C">Curva C</button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="margemTable" class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Material</th>
                            <th class="text-end">Comprado</th>
                            <th class="text-end">Custo compras</th>
                            <th class="text-end">Preço médio compra</th>
                            <th class="text-end">Vendido</th>
                            <th class="text-end">Receita</th>
                            <th class="text-end">Preço médio venda</th>
                            <th class="text-end">Margem un.</th>
                            <th class="text-end">Margem %</th>
                            <th class="text-end">Margem total</th>
                            <th class="text-end">Estoque</th>
                            <th class="text-end">Giro</th>
                            <th class="text-center">ABC</th>
                            <th>Situação</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($materiais as $m): ?>
                        <tr data-abc="<?= $m['abc'] ?>">
                            <td class="ps-3 fw-medium">
                                <?= htmlspecialchars($m['nm_material']) ?>
                                <small class="text-muted d-block"><?= htmlspecialchars($m['tipo']) ?></small>
                            </td>
                            <td class="text-end" data-order="<?= $m['qtd_comprada'] ?>"><?= financeiroNum($m['qtd_comprada']) ?></td>
                            <td class="text-end" data-order="<?= $m['custo_compras'] ?>"><?= financeiroMoeda($m['custo_compras']) ?></td>
                            <td class="text-end" data-order="<?= $m['preco_medio_compra'] ?? -1 ?>">
                                <?= $m['preco_medio_compra'] !== null ? financeiroMoeda($m['preco_medio_compra']) : '—' ?>
                            </td>
                            <td class="text-end" data-order="<?= $m['qtd_vendida'] ?>"><?= financeiroNum($m['qtd_vendida']) ?></td>
                            <td class="text-end fw-semibold" data-order="<?= $m['receita'] ?>"><?= financeiroMoeda($m['receita']) ?></td>
                            <td class="text-end" data-order="<?= $m['preco_medio_venda'] ?? -1 ?>">
                                <?= $m['preco_medio_venda'] !== null ? financeiroMoeda($m['preco_medio_venda']) : '—' ?>
                            </td>
                            <td class="text-end <?= ($m['margem_unitaria'] !== null && $m['margem_unitaria'] < 0) ? 'text-danger' : '' ?>"
                                data-order="<?= $m['margem_unitaria'] ?? -999999 ?>">
                                <?= $m['margem_unitaria'] !== null ? financeiroMoeda($m['margem_unitaria']) : '—' ?>
                            </td>
                            <td class="text-end fw-semibold <?= ($m['margem_percentual'] !== null && $m['margem_percentual'] < 0) ? 'text-danger' : '' ?>"
                                data-order="<?= $m['margem_percentual'] ?? -999999 ?>">
                                <?= financeiroPercentual($m['margem_percentual']) ?>
                            </td>
                            <td class="text-end <?= ($m['margem_total'] !== null && $m['margem_total'] < 0) ? 'text-danger' : '' ?>"
                                style="<?= ($m['margem_total'] !== null && $m['margem_total'] >= 0) ? 'color: var(--color-accent);' : '' ?>"
                                data-order="<?= $m['margem_total'] ?? -999999 ?>">
                                <?= $m['margem_total'] !== null ? financeiroMoeda($m['margem_total']) : '—' ?>
                            </td>
                            <td class="text-end" data-order="<?= $m['estoque_atual'] ?>"><?= financeiroNum($m['estoque_atual']) ?></td>
                            <td class="text-end" data-order="<?= $m['giro'] ?? -1 ?>">
                                <?php if ($m['giro'] !== null): ?>
                                    <?= financeiroNum($m['giro'], 2) ?>
                                    <small class="d-block"><span class="badge <?= $m['faixa_giro']['classe'] ?>"><?= $m['faixa_giro']['rotulo'] ?></span></small>
                                <?php else: ?>
                                    <span class="text-muted" title="<?= $m['faixa_giro']['rotulo'] ?>">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= ['A' => 'bg-success', 'B' => 'bg-primary', 'C' => 'bg-secondary'][$m['abc']] ?? 'bg-secondary' ?>">
                                    <?= $m['abc'] ?>
                                </span>
                                <small class="text-muted d-block"><?= financeiroNum($m['receita_percentual'], 1) ?>%</small>
                            </td>
                            <td>
                                <span class="badge <?= $m['alerta_margem']['classe'] ?>">
                                    <i class="bi <?= $m['alerta_margem']['icone'] ?> me-1" aria-hidden="true"></i>
                                    <?= $m['alerta_margem']['rotulo'] ?>
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <?php if (usuarioPode('financeiro.margem_detalhe')): ?>
                                    <a href="<?= $url_base ?>/financeiro/margem-detalhe?id=<?= $m['id_material'] ?>&inicio=<?= $filtros['inicio'] ?>&fim=<?= $filtros['fim'] ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       aria-label="Detalhes de <?= htmlspecialchars($m['nm_material']) ?>">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white small text-muted">
            Curva ABC pela receita acumulada: A até <?= (int) ABC_CORTES['A'] ?>%,
            B até <?= (int) ABC_CORTES['B'] ?>%, C o restante.
            Giro = quantidade vendida ÷ saldo médio do período, com o saldo médio reconstruído
            da trilha de movimentações e ponderado pelo tempo.
            Material cuja trilha fica negativa em algum momento aparece como "sem base de cálculo",
            em vez de receber um número inventado.
        </div>
    </div>
</div>

<script>
$(function () {
    const tabela = $('#margemTable').DataTable({
        "autoWidth": false,
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
        "pageLength": 25,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[5, "desc"]],
        "columnDefs": [{ "orderable": false, "targets": [13, 14] }]
    });

    let classe = '';
    $.fn.dataTable.ext.search.push(function (settings, dados, indice, linha, contador) {
        if (settings.nTable.id !== 'margemTable' || classe === '') { return true; }
        return $(tabela.row(contador).node()).data('abc') === classe;
    });

    $('[data-abc]').filter('button').on('click', function () {
        classe = $(this).data('abc');
        $('[data-abc]').filter('button').removeClass('active');
        $(this).addClass('active');
        tabela.draw();
    });

    $('#id_material, #tipo').select2({ width: '100%', language: 'pt-BR' });
});
</script>
