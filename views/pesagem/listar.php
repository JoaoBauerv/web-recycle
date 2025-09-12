<?php
require_once (__DIR__ . '/../../components/middleware.php');
include '../../components/sidebar.php';

$stmt = $pdo->prepare("SELECT  * FROM tb_pesagem WHERE total_valor > 0 ORDER BY data_pesagem ASC");
$stmt->execute();
$rowCount = $stmt->rowCount();

if ($rowCount > 0):
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-9">
            <!-- Header da página -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="listar.php" class="text-decoration-none">
                                    <i class="bi bi-house-door me-1"></i>
                                    Pesagens
                                </a>
                            </li>
                        </ol>
                    </nav>
                    <h2 class="mb-1 text-dark fw-bold">Relatório de Pesagens</h2>
                    <p class="text-muted mb-0">Visualize todas as pesagens com valores registrados</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Voltar
                    </a>
                </div>
            </div>

            <!-- Card principal -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title text-white mb-0 fw-semibold">
                                <i class="bi bi-table me-2"></i>
                                Dados das Pesagens
                            </h5>
                        </div>
                       
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="usuariosTable" class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="fw-semibold text-dark border-0 ps-4">
                                        <i class="bi bi-person-fill me-2 text-primary"></i>
                                        Cliente
                                    </th>
                                    <th class="fw-semibold text-dark border-0">
                                        <i class="bi bi-weight me-2 text-success"></i>
                                        Peso Total
                                    </th>
                                    <th class="fw-semibold text-dark border-0">
                                        <i class="bi bi-currency-dollar me-2 text-warning"></i>
                                        Valor Total
                                    </th>
                                    <th class="fw-semibold text-dark border-0">
                                        <i class="bi bi-calendar-event me-2 text-info"></i>
                                        Data da Pesagem
                                    </th>
                                     <th class="fw-semibold text-dark border-0">
                                    </th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <a href="pesagem.php?id=<?=$row['id_pesagem']?>">
                                <tr class="">
                                    <?php 
                                        $stmt_nome = $pdo->prepare("SELECT nome FROM tb_usuario WHERE id_usuario = ".$row['id_cliente']." ");
                                        $stmt_nome->execute();
                                        $usuario = $stmt_nome->fetch(PDO::FETCH_ASSOC);
                                        $nome = $usuario ? $usuario['nome'] : 'Desconhecido';
                                    ?>
                                    <td class="fw-medium text-dark ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle bg-primary text-white me-3 d-flex align-items-center justify-content-center" 
                                                 style="width: 35px; height: 35px; border-radius: 50%; font-size: 14px; font-weight: 600;">
                                                <?= strtoupper(substr($nome, 0, 1)) ?>
                                            </div>
                                            <span><?= htmlspecialchars($nome) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-success-subtle text-success px-3 py-2 fs-6 fw-semibold">
                                            <?= htmlspecialchars($row["total_peso"]) ?> kg
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span class="text-success fw-bold fs-6">
                                            R$ <?= number_format($row["total_valor"], 2, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium text-dark">
                                                <?= date("d/m/Y", strtotime($row['data_pesagem'])) ?>
                                            </span>
                                            <small class="text-muted">
                                                <?= date("H:i:s", strtotime($row['data_pesagem'])) ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="text-center py-3">
                                        <a href="pesagem.php?id=<?=$row['id_pesagem']?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye me-1"></i>
                                            Ver Detalhes
                                        </a>                                   
                                    </td>
                                </tr>
                                </a>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Footer do card -->
                <div class="card-footer bg-light border-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Dados atualizados em tempo real
                            </small>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">
                                Última atualização: <?= date('d/m/Y H:i') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                    </div>
                    <h4 class="text-muted mb-3">Nenhuma pesagem encontrada</h4>
                    <p class="text-muted mb-4">
                        Não há registros de pesagens com valores no momento.
                    </p>
                    <button class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>
                        Nova Pesagem
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Estilos customizados -->
<style>
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.avatar-circle {
    transition: all 0.2s ease;
}

.avatar-circle:hover {
    transform: scale(1.1);
}

.card {
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    border-bottom: none;
}

.badge {
    border-radius: 8px;
}

.btn-outline-light:hover {
    background-color: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
}

.table th {
    background-color: #f8f9fa;
    font-size: 14px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.border-bottom {
    border-bottom: 2px solid #e9ecef !important;
}

.border-bottom:last-child {
    border-bottom: none !important;
}

@media (max-width: 768px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .btn-group {
        width: 100%;
    }
    
    .btn-group .btn {
        flex: 1;
    }
}

td {
    border-radius: 5px;
    border-width: 1px;

}
</style>

<!-- DataTable CSS/JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#usuariosTable').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json"
        },
        "pageLength": 10,
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