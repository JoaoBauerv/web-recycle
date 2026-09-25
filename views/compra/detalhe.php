<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
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
$stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt_cliente->execute([$pesagem['id_cliente']]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

// Buscar itens da pesagem
$stmt_itens = $pdo->prepare("
    SELECT a.* , b.nm_material
    FROM tb_pesagem_material A
    LEFT JOIN tb_material B ON a.id_material = b.id_material
    WHERE A.id_pesagem = ?
    ORDER BY A.id_material
");
$stmt_itens->execute([$id_pesagem]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

$total_tara = array_sum(array_column($itens, 'tara'));
$total_peso_bruto = array_sum(array_map(
    fn($item) => (float) ($item['peso_bruto'] ?? $item['peso_material']),
    $itens
));

$cliente_nome = $cliente ? $cliente['nome'] : 'Desconhecido';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Breadcrumb e ações -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="<?=$url_base?>/compras/listar" class="text-decoration-none">
                                    <i class="bi bi-house-door me-1"></i>
                                    Compras
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Compra #<?= $id_pesagem ?></li>
                        </ol>
                    </nav>
                    <h2 class="mb-0 fw-bold">Detalhes da Compra</h2>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?=$url_base?>/compras/comprovante?id=<?=$id_pesagem?>" class="btn btn-outline-primary">
                        <i class="bi bi-receipt me-1"></i>
                        Ver Comprovante
                    </a>
                    <a href="<?=$url_base?>/compras/listar" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>
                        Voltar
                    </a>
                </div>
            </div>

            <!-- Cards de resumo -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-person me-1"></i>Cliente
                            </small>
                            <strong class="fs-5 d-block text-truncate" title="<?= htmlspecialchars($cliente_nome) ?>">
                                <?= htmlspecialchars($cliente_nome) ?>
                            </strong>
                            <?php if (!empty($cliente['email'])): ?>
                                <span class="small text-muted d-block text-truncate" title="<?= htmlspecialchars($cliente['email']) ?>">
                                    <i class="bi bi-envelope me-1" aria-hidden="true"></i><?= htmlspecialchars($cliente['email']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($cliente['telefone'])): ?>
                                <span class="small text-muted d-block">
                                    <i class="bi bi-telephone me-1" aria-hidden="true"></i><?= htmlspecialchars($cliente['telefone']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-weight me-1"></i>Peso Total
                            </small>
                            <strong class="fs-4 d-block"><?= number_format((float) $pesagem['total_peso'], 2, ',', '.') ?> kg</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-currency-dollar me-1"></i>Valor Total
                            </small>
                            <strong class="fs-4 d-block" style="color: var(--color-accent);">
                                R$ <?= number_format((float) $pesagem['total_valor'], 2, ',', '.') ?>
                            </strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-calendar-event me-1"></i>Data da Compra
                            </small>
                            <strong class="fs-5 d-block"><?= date("d/m/Y", strtotime($pesagem['data_pesagem'])) ?></strong>
                            <span class="small text-muted"><?= date("H:i", strtotime($pesagem['data_pesagem'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Itens -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Itens da Compra</h5>
                    <span class="badge bg-secondary"><?= count($itens) ?> <?= count($itens) === 1 ? 'item' : 'itens' ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($itens) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-semibold border-0 ps-4">Material</th>
                                    <th class="fw-semibold border-0 text-end">Peso Bruto</th>
                                    <th class="fw-semibold border-0 text-end">Tara</th>
                                    <th class="fw-semibold border-0 text-end">Peso Líquido</th>
                                    <th class="fw-semibold border-0 text-end">Preço/kg</th>
                                    <th class="fw-semibold border-0 text-end">Valor Total</th>
                                    <th class="fw-semibold border-0 text-end pe-4">% do Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <span class="fw-medium"><?= htmlspecialchars($item['nm_material'] ?? 'Produto não encontrado') ?></span>
                                        <small class="text-muted d-block">Item #<?= $item['id_material'] ?></small>
                                    </td>
                                    <td class="py-3 text-end">
                                        <?= number_format((float) ($item['peso_bruto'] ?? $item['peso_material']), 2, ',', '.') ?> kg
                                    </td>
                                    <td class="py-3 text-end">
                                        <?php if (!empty($item['tara'])): ?>
                                            <span class="text-danger">− <?= number_format((float) $item['tara'], 2, ',', '.') ?> kg</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <span class="badge bg-success-subtle text-success px-3 py-2">
                                            <?= number_format((float) $item['peso_material'], 2, ',', '.') ?> kg
                                        </span>
                                    </td>
                                    <td class="py-3 text-end">
                                        R$ <?= number_format($item['preco_un'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                    <td class="py-3 text-end fw-semibold" style="color: var(--color-accent);">
                                        R$ <?= number_format(($item['preco_un'] * ($item['peso_material'] ?? 0)), 2, ',', '.') ?>
                                    </td>
                                    <td class="py-3 text-end pe-4">
                                        <?php $percentual = $pesagem['total_valor'] > 0 ? (($item['preco_un'] * ($item['peso_material'] ?? 0)) / $pesagem['total_valor']) * 100 : 0; ?>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="progress me-2" style="width: 60px; height: 6px;">
                                                <div class="progress-bar" style="width: <?= $percentual ?>%; background-color: var(--color-accent);"></div>
                                            </div>
                                            <small class="text-muted"><?= number_format($percentual, 1) ?>%</small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">TOTAL</th>
                                    <th class="py-3 text-end"><?= number_format($total_peso_bruto, 2, ',', '.') ?> kg</th>
                                    <th class="py-3 text-end">
                                        <?= $total_tara > 0 ? '− ' . number_format($total_tara, 2, ',', '.') . ' kg' : '-' ?>
                                    </th>
                                    <th class="py-3 text-end">
                                        <span class="badge bg-success px-3 py-2"><?= number_format((float) $pesagem['total_peso'], 2, ',', '.') ?> kg</span>
                                    </th>
                                    <th class="py-3 text-end">-</th>
                                    <th class="py-3 text-end fs-6" style="color: var(--color-accent);">
                                        R$ <?= number_format((float) $pesagem['total_valor'], 2, ',', '.') ?>
                                    </th>
                                    <th class="py-3 text-end pe-4">100%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum item encontrado</h5>
                        <p class="text-muted mb-0">Esta compra não possui itens detalhados.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

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

@media print {
    .breadcrumb, .btn { display: none !important; }
}

@media (max-width: 768px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        align-items: flex-start !important;
    }
}
</style>
