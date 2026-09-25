<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

$vendas = $pdo->query("SELECT v.id_venda, v.total_peso, v.total_valor, v.status, v.data_venda, v.observacoes,
                              f.nome_razao_social, f.cidade, f.estado,
                              u.nome AS nome_usuario
                       FROM vendas v
                       LEFT JOIN fornecedores f ON f.id_fornecedor = v.id_fornecedor
                       LEFT JOIN tb_usuario u ON u.id_usuario = v.id_usuario
                       ORDER BY v.data_venda DESC")->fetchAll(PDO::FETCH_ASSOC);

$total_vendas = count($vendas);
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <?php include 'inc_header_relatorios.php'; ?>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if ($total_vendas === 0): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-cart-x display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                <h2 class="h5 text-muted mb-1">Nenhuma venda registrada ainda</h2>
                <p class="text-muted mb-4">Quando você vender materiais do estoque, o histórico aparece aqui.</p>
                <a href="<?= $url_base ?>/vendas" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Registrar a primeira venda
                </a>
            </div>
        </div>

    <?php else: ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h6 fw-semibold mb-0">
                    <i class="bi bi-list-ul me-2" aria-hidden="true"></i>Vendas realizadas
                </h2>
                <a href="<?= $url_base ?>/vendas" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Nova venda
                </a>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="vendasTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Venda</th>
                                <th>Fornecedor</th>
                                <th>Data</th>
                                <th class="text-end">Peso</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vendas as $v):
                            $nome = $v['nome_razao_social'] ?: 'Fornecedor removido';
                            $local = trim(($v['cidade'] ?? '') . '/' . ($v['estado'] ?? ''), '/');
                        ?>
                            <tr>
                                <td class="ps-4 fw-semibold">#<?= (int) $v['id_venda'] ?></td>
                                <td>
                                    <?= htmlspecialchars($nome) ?>
                                    <?php if ($local): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($local) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-order="<?= strtotime($v['data_venda']) ?>">
                                    <?= date('d/m/Y', strtotime($v['data_venda'])) ?>
                                    <small class="text-muted d-block"><?= date('H:i', strtotime($v['data_venda'])) ?></small>
                                </td>
                                <td class="text-end" data-order="<?= (float) $v['total_peso'] ?>">
                                    <?= number_format((float) $v['total_peso'], 2, ',', '.') ?> kg
                                </td>
                                <td class="text-end fw-semibold" style="color: var(--color-accent);"
                                    data-order="<?= (float) $v['total_valor'] ?>">
                                    R$ <?= number_format((float) $v['total_valor'], 2, ',', '.') ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= $url_base ?>/vendas/detalhe?id=<?= (int) $v['id_venda'] ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       aria-label="Ver detalhes da venda #<?= (int) $v['id_venda'] ?>">
                                        <i class="bi bi-eye me-1" aria-hidden="true"></i> Detalhes
                                    </a>
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
    if (!$('#vendasTable').length) return;

    $('#vendasTable').DataTable({
        "autoWidth": false,
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[2, "desc"]],
        "responsive": true,
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "columnDefs": [
            { "orderable": false, "targets": [5] },
            { "className": "text-end", "targets": [3, 4] }
        ]
    });
});
</script>
