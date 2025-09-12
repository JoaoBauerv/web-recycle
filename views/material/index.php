<?php 
require_once (__DIR__ . '/../../components/middleware.php');
include '../../components/sidebar.php'; 

$sql = "SELECT * FROM tb_material WHERE status = 1 ORDER BY nm_material ASC";
$stmt = $pdo->query($sql);
$materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="card bg-secondary text-light shadow-lg p-4 rounded-4">
        <h2 class="text-center mb-4"><i class="bi bi-box-seam-fill"></i> Materiais Cadastrados</h2>

        <?php require_once '../../components/alert.php'; ?>

        <?php if (count($materiais) > 0): ?>
            <div class="table-responsive">
                <table id="materiaisTable" class="table table-dark table-hover align-middle text-center rounded-3 overflow-hidden">
                    <thead class="table-primary text-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Preço Normal <i class="bi bi-cash"></i></th>
                            <th>Preço Especial <i class="bi bi-cash-coin"></i></th>
                            <th>Estoque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($materiais as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nm_material']) ?></td>
                            <td><?= htmlspecialchars($p['tipo']) ?></td>
                            <td data-order="<?= $p['preco_compra'] ?>">R$ <?= number_format($p['preco_compra'], 2, ',', '.') ?></td>
                            <td data-order="<?= $p['preco_especial'] ?>">R$ <?= number_format($p['preco_especial'], 2, ',', '.') ?></td>
                            <td><?= $p['qt_estoque'] ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="edit.php?id=<?=$p['id_material']?>" 
                                       class="btn btn-warning btn-sm" 
                                       title="Editar Material">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a href="<?=$url_base?>/functions/material/registrar.php?id=<?=$p['id_material']?>&acao=excluir" 
                                       onclick="return confirm('Tem certeza que deseja excluir este material?')" 
                                       class="btn btn-danger btn-sm"
                                       title="Excluir Material">
                                        <i class="fas fa-trash"></i> Excluir
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center"><i class="bi bi-exclamation-triangle-fill"></i> Nenhum material cadastrado.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mt-3">
            <a href="create.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Novo Material
            </a>
        </div>
    </div>
</div>

</style>

<!-- DataTable CSS/JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#materiaisTable').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        },
        "pageLength": 5,
        "lengthMenu": [5, 10, 25, 50, 100],
        "order": [[3, "desc"]],
        "responsive": true,
        "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "columnDefs": [
            { "orderable": true, "targets": [0, 1, 2, 3] },
            { "searchable": true, "targets": [0, 3] },
            { "className": "text-center", "targets": [1, 2] }
        ],
        "drawCallback": function() {
            // Adiciona animação suave após cada redraw
            $('tbody tr').css('opacity', '0').animate({ opacity: 1 }, 300);
        }
    });

    // Animação de entrada
    $('tbody tr').css('opacity', '0').each(function(i) {
        $(this).delay(i * 50).animate({ opacity: 1 }, 300);
    });
});
</script>