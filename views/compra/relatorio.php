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
    'cliente'       => $filtro_cliente,
    'produto'       => $filtro_produto,
    'tipo_material' => $filtro_tipo,
]);
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-11">

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
                            <label for="cliente" class="form-label small text-muted">Cliente</label>
                            <select class="form-select" id="cliente" name="cliente">
                                <option value="0">Todos</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?= (int) $c['id_cliente'] ?>" <?= $filtro_cliente === (int) $c['id_cliente'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nome']) ?>
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
                                <i class="bi bi-search me-1"></i> Pesquisar
                            </button>
                            <a href="<?= $url_base ?>/compras/relatorio" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-1"></i> Limpar filtros
                            </a>
                            <a href="<?= $url_base ?>/views/compra/relatorio_pdf.php?<?= $query_filtros ?>" target="_blank" class="btn btn-outline-primary ms-auto">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumo geral -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-cart-check me-1"></i>Total de Compras
                            </small>
                            <strong class="fs-4 d-block"><?= $total_compras ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-list-ul me-1"></i>Total de Itens
                            </small>
                            <strong class="fs-4 d-block"><?= $total_itens ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-weight me-1"></i>Quantidade Total
                            </small>
                            <strong class="fs-4 d-block"><?= relatorioCompraPeso($quantidade_total) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-currency-dollar me-1"></i>Valor Total Comprado
                            </small>
                            <strong class="fs-4 d-block" style="color: var(--color-accent);"><?= relatorioCompraMoeda($valor_total) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($total_itens === 0): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
                        <h5 class="text-muted mb-1">Nenhuma compra encontrada com esses filtros</h5>
                        <p class="text-muted mb-0">Ajuste o período, o cliente ou o material selecionado.</p>
                    </div>
                </div>
            <?php else: ?>

                <!-- Listagem detalhada -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-list-ul me-2"></i>Compras Detalhadas
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="tabelaRelatorio" class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Compra</th>
                                        <th>Data</th>
                                        <th>Cliente</th>
                                        <th>Material</th>
                                        <th class="text-end">Quantidade</th>
                                        <th class="text-end">Valor Unit.</th>
                                        <th class="text-end">Valor Total (item)</th>
                                        <th class="text-end pe-4">Valor Total (compra)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($linhas as $l):
                                    $peso  = (float) $l['peso_material'];
                                    $preco = (float) $l['preco_un'];
                                    $valorItem = $peso * $preco;
                                ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="<?= $url_base ?>/compras/detalhe?id=<?= (int) $l['id_pesagem'] ?>">
                                                #<?= (int) $l['id_pesagem'] ?>
                                            </a>
                                        </td>
                                        <td data-order="<?= strtotime($l['data_pesagem']) ?>">
                                            <?= date('d/m/Y', strtotime($l['data_pesagem'])) ?>
                                            <small class="text-muted d-block"><?= date('H:i', strtotime($l['data_pesagem'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($l['cliente_nome']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($l['nm_material']) ?>
                                            <?php if ($l['material_tipo']): ?>
                                                <small class="text-muted">/ <?= htmlspecialchars($l['material_tipo']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end" data-order="<?= $peso ?>">
                                            <?= relatorioCompraPeso($peso, $l['unidade_medida']) ?>
                                        </td>
                                        <td class="text-end" data-order="<?= $preco ?>">
                                            <?= relatorioCompraMoeda($preco) ?>
                                        </td>
                                        <td class="text-end fw-semibold" style="color: var(--color-accent);" data-order="<?= $valorItem ?>">
                                            <?= relatorioCompraMoeda($valorItem) ?>
                                        </td>
                                        <td class="text-end pe-4 text-muted" data-order="<?= (float) $l['compra_total_valor'] ?>">
                                            <?= relatorioCompraMoeda((float) $l['compra_total_valor']) ?>
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
                            <div class="card-header bg-white fw-semibold">
                                <i class="bi bi-box-seam me-2"></i>Resumo por Material
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
                                                <td class="text-end"><?= relatorioCompraPeso($m['quantidade']) ?></td>
                                                <td class="text-end pe-4 fw-semibold" style="color: var(--color-accent);"><?= relatorioCompraMoeda($m['valor']) ?></td>
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
                            <div class="card-header bg-white fw-semibold">
                                <i class="bi bi-person me-2"></i>Resumo por Cliente
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Cliente</th>
                                                <th class="text-end">Compras</th>
                                                <th class="text-end">Quantidade Total</th>
                                                <th class="text-end pe-4">Valor Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($por_cliente as $c): ?>
                                            <tr>
                                                <td class="ps-4"><?= htmlspecialchars($c['nome']) ?></td>
                                                <td class="text-end"><?= count($c['compras']) ?></td>
                                                <td class="text-end"><?= relatorioCompraPeso($c['quantidade']) ?></td>
                                                <td class="text-end pe-4 fw-semibold" style="color: var(--color-accent);"><?= relatorioCompraMoeda($c['valor']) ?></td>
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
    $('#cliente, #produto, #tipo_material').select2({
        width: '100%',
        language: 'pt-BR'
    });

    $('#tabelaRelatorio').DataTable({
        "autoWidth": false, // sem isto o DataTables grava um width inline e a tabela encolhe
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        },
        "pageLength": 5,
        "lengthMenu": [5, 10, 25, 50, 100],
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
