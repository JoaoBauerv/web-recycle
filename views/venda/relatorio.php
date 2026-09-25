<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

$tela_relatorio = true;

require __DIR__ . '/relatorio_query.php';

$query_filtros = http_build_query([
    'data_inicio'   => $data_inicio,
    'data_fim'      => $data_fim,
    'fornecedor'    => $filtro_fornecedor,
    'produto'       => $filtro_produto,
    'tipo_material' => $filtro_tipo,
]);
?>
<div class="container-fluid py-4" style="max-width: 1400px;">
    <div class="row">
        <div class="col-12">

            <?php include 'inc_header_relatorios.php'; ?>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-3 col-6">
                            <label for="data_inicio" class="form-label small text-muted">Data inicial</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="data_fim" class="form-label small text-muted">Data final</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="fornecedor" class="form-label small text-muted">Fornecedor</label>
                            <select class="form-select" id="fornecedor" name="fornecedor">
                                <option value="0">Todos</option>
                                <?php foreach ($fornecedores as $f): ?>
                                    <option value="<?= (int) $f['id_fornecedor'] ?>" <?= $filtro_fornecedor === (int) $f['id_fornecedor'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['nome_razao_social']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="produto" class="form-label small text-muted">Material</label>
                            <select class="form-select" id="produto" name="produto">
                                <option value="0">Todos os materiais</option>
                                <?php foreach ($materiais as $m): ?>
                                    <option value="<?= (int) $m['id_material'] ?>" <?= $filtro_produto === (int) $m['id_material'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['nm_material']) ?> / <?= htmlspecialchars($m['tipo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <label for="tipo_material" class="form-label small text-muted">Tipo de material</label>
                            <select class="form-select" id="tipo_material" name="tipo_material">
                                <option value="">Todos os tipos</option>
                                <?php foreach ($tipos_material as $t): ?>
                                    <option value="<?= htmlspecialchars($t) ?>" <?= $filtro_tipo === $t ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1" aria-hidden="true"></i> Pesquisar
                            </button>
                            <a href="<?= $url_base ?>/vendas/relatorio" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-1" aria-hidden="true"></i> Limpar filtros
                            </a>
                            <a href="<?= $url_base ?>/views/venda/relatorio_pdf.php?<?= $query_filtros ?>" target="_blank" class="btn btn-outline-primary ms-auto">
                                <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> Exportar PDF
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumo geral -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card card-indicador h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <span class="indicador-icone"><i class="bi bi-cart-check" aria-hidden="true"></i></span>
                            <div>
                                <span class="indicador-rotulo">Total de Vendas</span>
                                <span class="indicador-valor"><?= $total_vendas ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card card-indicador h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <span class="indicador-icone"><i class="bi bi-list-ul" aria-hidden="true"></i></span>
                            <div>
                                <span class="indicador-rotulo">Total de Itens</span>
                                <span class="indicador-valor"><?= $total_itens ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card card-indicador h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <span class="indicador-icone"><i class="bi bi-weight" aria-hidden="true"></i></span>
                            <div>
                                <span class="indicador-rotulo">Quantidade Total</span>
                                <span class="indicador-valor"><?= relatorioVendaPeso($quantidade_total) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card card-indicador h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <span class="indicador-icone"><i class="bi bi-currency-dollar" aria-hidden="true"></i></span>
                            <div>
                                <span class="indicador-rotulo">Valor Total Vendido</span>
                                <span class="indicador-valor" style="color: var(--color-accent);"><?= relatorioVendaMoeda($valor_total) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($total_itens === 0): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                        <h3 class="h5 text-muted mb-1">Nenhuma venda encontrada com esses filtros</h3>
                        <p class="text-muted mb-0">Ajuste o período, o fornecedor ou o material selecionado.</p>
                    </div>
                </div>
            <?php else: ?>

                <!-- Listagem detalhada -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h3 class="h6 fw-semibold mb-0"><i class="bi bi-list-ul me-2" aria-hidden="true"></i>Vendas Detalhadas</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="tabelaRelatorioVenda" class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Venda</th>
                                        <th>Data</th>
                                        <th>Fornecedor</th>
                                        <th>Material</th>
                                        <th class="text-end">Quantidade</th>
                                        <th class="text-end">Valor Unit.</th>
                                        <th class="text-end">Valor Total (item)</th>
                                        <th class="text-end pe-4">Valor Total (venda)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($linhas as $l): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="<?= $url_base ?>/vendas/detalhe?id=<?= (int) $l['id_venda'] ?>">
                                                #<?= (int) $l['id_venda'] ?>
                                            </a>
                                        </td>
                                        <td data-order="<?= strtotime($l['data_venda']) ?>">
                                            <?= date('d/m/Y', strtotime($l['data_venda'])) ?>
                                            <small class="text-muted d-block"><?= date('H:i', strtotime($l['data_venda'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($l['fornecedor_nome']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($l['nm_material']) ?>
                                            <?php if ($l['material_tipo']): ?>
                                                <small class="text-muted">/ <?= htmlspecialchars($l['material_tipo']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end" data-order="<?= (float) $l['quantidade'] ?>">
                                            <?= relatorioVendaPeso((float) $l['quantidade'], $l['unidade_medida']) ?>
                                        </td>
                                        <td class="text-end" data-order="<?= (float) $l['preco_un'] ?>">
                                            <?= relatorioVendaMoeda((float) $l['preco_un']) ?>
                                        </td>
                                        <td class="text-end fw-semibold" style="color: var(--color-accent);" data-order="<?= (float) $l['valor_total'] ?>">
                                            <?= relatorioVendaMoeda((float) $l['valor_total']) ?>
                                        </td>
                                        <td class="text-end pe-4 text-muted" data-order="<?= (float) $l['venda_total_valor'] ?>">
                                            <?= relatorioVendaMoeda((float) $l['venda_total_valor']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Resumos por agrupamento -->
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom">
                                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-box-seam me-2" aria-hidden="true"></i>Resumo por Material</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Material</th>
                                                <th class="text-end">Quantidade Total</th>
                                                <th class="text-end pe-4">Valor Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($por_material as $m): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <?= htmlspecialchars($m['nome']) ?>
                                                    <?php if ($m['tipo']): ?>
                                                        <small class="text-muted">/ <?= htmlspecialchars($m['tipo']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end"><?= relatorioVendaPeso($m['quantidade']) ?></td>
                                                <td class="text-end pe-4 fw-semibold" style="color: var(--color-accent);"><?= relatorioVendaMoeda($m['valor']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom">
                                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-truck me-2" aria-hidden="true"></i>Resumo por Fornecedor</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Fornecedor</th>
                                                <th class="text-end">Vendas</th>
                                                <th class="text-end">Quantidade Total</th>
                                                <th class="text-end pe-4">Valor Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($por_fornecedor as $f): ?>
                                            <tr>
                                                <td class="ps-4"><?= htmlspecialchars($f['nome']) ?></td>
                                                <td class="text-end"><?= count($f['vendas']) ?></td>
                                                <td class="text-end"><?= relatorioVendaPeso($f['quantidade']) ?></td>
                                                <td class="text-end pe-4 fw-semibold" style="color: var(--color-accent);"><?= relatorioVendaMoeda($f['valor']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<style>
.card {
    border-radius: 12px;
    overflow: hidden;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}
</style>

<script>
$(function () {
    $('#fornecedor, #produto, #tipo_material').select2({
        width: '100%',
        language: 'pt-BR'
    });

    if (!$('#tabelaRelatorioVenda').length) return;

    $('#tabelaRelatorioVenda').DataTable({
        "autoWidth": false,
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
        "pageLength": 25,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[1, "desc"]],
        "responsive": true,
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "columnDefs": [
            { "orderable": false, "targets": [0] },
            { "className": "text-end", "targets": [4, 5, 6, 7] }
        ]
    });
});
</script>
