<?php
require_once (__DIR__ . '/../../components/middleware.php');
include '../../components/sidebar.php';

// Verificar se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id_pesagem = (int) $_GET['id'];

// Buscar dados da pesagem
$stmt = $pdo->prepare("SELECT * FROM tb_pesagem WHERE id_pesagem = ?");
$stmt->execute([$id_pesagem]);
$pesagem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pesagem) {
    header('Location: index.php');
    exit;
}

// Buscar dados do cliente
$stmt_cliente = $pdo->prepare("SELECT * FROM tb_usuario WHERE id_usuario = ?");
$stmt_cliente->execute([$pesagem['id_cliente']]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

// Buscar itens da pesagem (assumindo que existe uma tabela tb_itens_pesagem)
$stmt_itens = $pdo->prepare("
    SELECT a.* , b.nm_material
    FROM tb_pesagem_material A 
    LEFT JOIN tb_material B ON a.id_material = b.id_material 
    WHERE A.id_pesagem = ? 
    ORDER BY A.id_material
");
$stmt_itens->execute([$id_pesagem]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

$cliente_nome = $cliente ? $cliente['nome'] : 'Desconhecido';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-9">
            <!-- Breadcrumb e ações -->
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
                            <li class="breadcrumb-item active">Pesagem #<?= $id_pesagem ?></li>
                        </ol>
                    </nav>
                    <h2 class="mb-0 text-dark fw-bold">Detalhes da Pesagem</h2>
                </div>
                <div class="d-flex gap-2">
                    <!-- <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>
                        Imprimir
                    </button> -->
                    <!-- <a href="editar.php?id=<? $id_pesagem ?>" class="btn btn-warning">
                        <i class="bi bi-pencil me-1"></i>
                        Editar
                    </a> -->
                    <a href="listar.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Voltar
                    </a>
                </div>
            </div>

            <!-- Cards de informações principais -->
            <div class="row mb-4">
                <!-- Card do Cliente -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar-lg bg-primary text-white me-3 d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px; border-radius: 12px; font-size: 18px; font-weight: 700;">
                                    <?= strtoupper(substr($cliente_nome, 0, 1)) ?>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 12px; letter-spacing: 1px;">Cliente</h6>
                                    <h5 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($cliente_nome) ?></h5>
                                </div>
                            </div>
                            <?php if ($cliente): ?>
                            <div class="mt-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-envelope text-muted me-2"></i>
                                    <span class="text-muted"><?= htmlspecialchars($cliente['email'] ?? 'N/A') ?></span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-telephone text-muted me-2"></i>
                                    <span class="text-muted"><?= htmlspecialchars($cliente['telefone'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Card do Peso Total -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success-subtle text-success me-3 d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px; border-radius: 12px; font-size: 20px;">
                                    <i class="bi bi-bar-chart"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 12px; letter-spacing: 1px;">Peso Total</h6>
                                    <h5 class="mb-0 fw-bold text-success"><?= htmlspecialchars($pesagem['total_peso']) ?> kg</h5>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>

                <!-- Card do Valor Total -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning-subtle text-warning me-3 d-flex align-items-center justify-content-center" 
                                     style="width: 50px; height: 50px; border-radius: 12px; font-size: 20px;">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1 text-uppercase fw-semibold" style="font-size: 12px; letter-spacing: 1px;">Valor Total</h6>
                                    <h5 class="mb-0 fw-bold text-warning">R$ <?= number_format($pesagem['total_valor'], 2, ',', '.') ?></h5>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Preço médio/kg:</small>
                                <strong class="text-dark">
                                    R$ <?= number_format($pesagem['total_valor'] / $pesagem['total_peso'], 2, ',', '.') ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações da Pesagem -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0 fw-semibold">
                                <i class="bi bi-info-circle me-2"></i>
                                Informações da Pesagem
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar-event text-primary me-3 fs-5"></i>
                                        <div>
                                            <small class="text-muted d-block">Data da Pesagem</small>
                                            <strong class="text-dark">
                                                <?= date("d/m/Y", strtotime($pesagem['data_pesagem'])) ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-clock text-info me-3 fs-5"></i>
                                        <div>
                                            <small class="text-muted d-block">Horário</small>
                                            <strong class="text-dark">
                                                <?= date("H:i:s", strtotime($pesagem['data_pesagem'])) ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-hash text-secondary me-3 fs-5"></i>
                                        <div>
                                            <small class="text-muted d-block">ID da Pesagem</small>
                                            <strong class="text-dark">#<?= $id_pesagem ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-check-circle text-success me-3 fs-5"></i>
                                        <div>
                                            <small class="text-muted d-block">Status</small>
                                            <span class="badge bg-success px-2 py-1">Concluída</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Itens -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient d-flex justify-content-between align-items-center" 
                             style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h5 class="text-white mb-0 fw-semibold">
                                <i class="bi bi-list-ul me-2"></i>
                                Itens da Pesagem
                            </h5>
                            <span class="badge bg-white text-primary px-3 py-2">
                                <?= count($itens) ?> itens
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($itens) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="fw-semibold border-0 ps-4">Material</th>
                                            <th class="fw-semibold border-0">Peso (kg)</th>
                                            <th class="fw-semibold border-0">Preço/kg</th>
                                            <th class="fw-semibold border-0">Valor Total</th>
                                            <th class="fw-semibold border-0">% do Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($itens as $item): ?>
                                        <tr class="border-bottom">
                                            <td class="ps-4 py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary-subtle text-primary me-3 d-flex align-items-center justify-content-center" 
                                                         style="width: 35px; height: 35px; border-radius: 8px; font-size: 14px;">
                                                        <i class="bi bi-box"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-medium text-dark">
                                                            <?= htmlspecialchars($item['nm_material'] ?? 'Produto não encontrado') ?>
                                                        </span>
                                                        <small class="text-muted d-block">
                                                            Item #<?= $item['id_material'] ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <span class="badge bg-success-subtle text-success px-3 py-2">
                                                    <?= htmlspecialchars($item['peso_material']) ?> kg
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <span class="text-dark fw-medium">
                                                    R$ <?= number_format($item['preco_un'] ?? 0, 2, ',', '.') ?>
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <span class="text-success fw-bold">
                                                    R$ <?= number_format(($item['preco_un'] * ($item['peso_material'] ?? 0)), 2, ',', '.') ?>
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <?php $percentual = (($item['preco_un'] * ($item['peso_material'] )) / $pesagem['total_valor']) * 100; ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress me-2" style="width: 60px; height: 8px;">
                                                        <div class="progress-bar bg-primary" style="width: <?= $percentual ?>%"></div>
                                                    </div>
                                                    <small class="text-muted fw-medium">
                                                        <?= number_format($percentual, 1) ?>%
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th class="ps-4 py-3 fw-bold">TOTAL</th>
                                            <th class="py-3">
                                                <span class="badge bg-success px-3 py-2 fw-bold">
                                                    <?= htmlspecialchars($pesagem['total_peso']) ?> kg
                                                </span>
                                            </th>
                                            <th class="py-3">-</th>
                                            <th class="py-3">
                                                <span class="text-success fw-bold fs-6">
                                                    R$ <?= number_format($pesagem['total_valor'], 2, ',', '.') ?>
                                                </span>
                                            </th>
                                            <th class="py-3">
                                                <span class="badge bg-primary px-3 py-2 fw-bold">100%</span>
                                                <div style="text-align: right;">
                                                <a href="pdf.php?id=<?=$id_pesagem?>" target="_blank" class="btn btn-primary btn-sm" > 
                                                <i class="bi bi-envelope-paper"></i> Imprimir 
                                                </a>
                                                </div>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                                <h5 class="text-muted">Nenhum item encontrado</h5>
                                <p class="text-muted">Esta pesagem não possui itens detalhados.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estilos customizados -->
<style>
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.avatar-lg:hover {
    transform: scale(1.05);
    transition: all 0.2s ease;
}

.card {
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.progress {
    border-radius: 10px;
    background-color: #f8f9fa;
}

.progress-bar {
    border-radius: 10px;
}

.border-bottom {
    border-bottom: 1px solid #e9ecef !important;
}

.border-bottom:last-child {
    border-bottom: none !important;
}

.breadcrumb-item a:hover {
    color: #0d6efd !important;
}

@media print {
    .d-flex.justify-content-between .d-flex {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    
    .bg-gradient {
        background: #6c757d !important;
        color: white !important;
    }
}

@media (max-width: 768px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.gap-2 {
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<script>
// Animação de entrada dos cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Melhorar a experiência de impressão
window.addEventListener('beforeprint', function() {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing');
});
</script>