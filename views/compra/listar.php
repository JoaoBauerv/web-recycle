<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

// JOIN com clientes: antes o nome era buscado com uma query dentro do laço,
// ou seja, uma consulta por linha da tabela.
$compras = $pdo->query("SELECT p.id_pesagem, p.total_peso, p.total_valor, p.data_pesagem,
                               c.nome AS cliente_nome
                        FROM tb_pesagem p
                        LEFT JOIN clientes c ON c.id_cliente = p.id_cliente
                        WHERE p.total_valor > 0
                        ORDER BY p.data_pesagem DESC")->fetchAll(PDO::FETCH_ASSOC);

$total_compras = count($compras);
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <?php include 'inc_header_relatorios.php'; ?>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if ($total_compras === 0): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                <h2 class="h5 text-muted mb-1">Nenhuma compra registrada</h2>
                <p class="text-muted mb-4">Quando você comprar material de um cliente, o histórico aparece aqui.</p>
                <a href="<?= $url_base ?>/compras" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Registrar a primeira compra
                </a>
            </div>
        </div>

    <?php else: ?>

        <!-- Lista -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h6 fw-semibold mb-0">
                    <i class="bi bi-list-ul me-2" aria-hidden="true"></i>Compras realizadas
                </h2>
                <a href="<?= $url_base ?>/compras" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Nova compra
                </a>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="comprasTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Compra</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th class="text-end">Peso</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($compras as $c):
                            $nome = $c['cliente_nome'] ?: 'Cliente removido';
                        ?>
                            <tr>
                                <td class="ps-4 fw-semibold">#<?= (int) $c['id_pesagem'] ?></td>
                                <td><?= htmlspecialchars($nome) ?></td>
                                <td data-order="<?= strtotime($c['data_pesagem']) ?>">
                                    <?= date('d/m/Y', strtotime($c['data_pesagem'])) ?>
                                    <small class="text-muted d-block"><?= date('H:i', strtotime($c['data_pesagem'])) ?></small>
                                </td>
                                <td class="text-end" data-order="<?= (float) $c['total_peso'] ?>">
                                    <?= number_format((float) $c['total_peso'], 2, ',', '.') ?> kg
                                </td>
                                <td class="text-end fw-semibold" style="color: var(--color-accent);"
                                    data-order="<?= (float) $c['total_valor'] ?>">
                                    R$ <?= number_format((float) $c['total_valor'], 2, ',', '.') ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= $url_base ?>/compras/detalhe?id=<?= (int) $c['id_pesagem'] ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       aria-label="Ver detalhes da compra #<?= (int) $c['id_pesagem'] ?>">
                                        <i class="bi bi-eye me-1" aria-hidden="true"></i> Detalhes
                                    </a>
                                    <a href="<?= $url_base ?>/compras/comprovante?id=<?= (int) $c['id_pesagem'] ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       aria-label="Ver comprovante da compra #<?= (int) $c['id_pesagem'] ?>">
                                        <i class="bi bi-receipt me-1" aria-hidden="true"></i> Comprovante
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
    if (!$('#comprasTable').length) return;

    $('#comprasTable').DataTable({
        "autoWidth": false, // sem isto o DataTables grava um width inline e a tabela encolhe
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
