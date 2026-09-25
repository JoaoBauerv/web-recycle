<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

$id_venda = (int) ($_GET['id'] ?? 0);
if (!$id_venda) {
    header('Location: ' . $url_base . '/vendas/listar');
    exit;
}

$stmt = $pdo->prepare("SELECT v.*, u.nome AS usuario_nome
                       FROM vendas v
                       LEFT JOIN tb_usuario u ON u.id_usuario = v.id_usuario
                       WHERE v.id_venda = ?");
$stmt->execute([$id_venda]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venda) {
    header('Location: ' . $url_base . '/vendas/listar');
    exit;
}

$stmt_f = $pdo->prepare("SELECT * FROM fornecedores WHERE id_fornecedor = ?");
$stmt_f->execute([$venda['id_fornecedor']]);
$fornecedor = $stmt_f->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt_itens = $pdo->prepare("
    SELECT vi.*, m.nm_material, m.tipo AS material_tipo,
           COALESCE(m.unidade_medida, 'kg') AS unidade_medida
    FROM vendas_itens vi
    LEFT JOIN tb_material m ON m.id_material = vi.id_material
    WHERE vi.id_venda = ?
    ORDER BY vi.id_venda_item
");
$stmt_itens->execute([$id_venda]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

$total_tara = array_sum(array_column($itens, 'tara'));
$total_peso_bruto = array_sum(array_map(
    fn($item) => (float) ($item['peso_bruto'] ?? $item['quantidade']),
    $itens
));

$fornecedor_nome = $fornecedor['nome_razao_social'] ?? 'Desconhecido';
?>

<div class="container-fluid py-4" style="max-width: 1400px;">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Breadcrumb e ações -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="<?= $url_base ?>/vendas/listar" class="text-decoration-none">
                                    <i class="bi bi-house-door me-1" aria-hidden="true"></i>Vendas
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Venda #<?= $id_venda ?></li>
                        </ol>
                    </nav>
                    <h2 class="mb-0 fw-bold">
                        Detalhes da Venda
                        <?php if (($venda['origem'] ?? 'manual') === 'importacao'): ?>
                            <span class="badge bg-info-subtle text-info-emphasis align-middle ms-1" style="font-size:.7rem;">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importada
                            </span>
                        <?php endif; ?>
                    </h2>
                    <?php if (!empty($venda['pedido_externo'])): ?>
                        <small class="text-muted">Pedido do cliente: <?= htmlspecialchars($venda['pedido_externo']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= $url_base ?>/vendas/listar" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                        Voltar
                    </a>
                </div>
            </div>

            <?php require_once __DIR__ . '/../../components/alert.php'; ?>

            <!-- Cards de resumo -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-truck me-1" aria-hidden="true"></i>Fornecedor
                            </small>
                            <strong class="fs-5 d-block text-truncate" title="<?= htmlspecialchars($fornecedor_nome) ?>">
                                <?= htmlspecialchars($fornecedor_nome) ?>
                            </strong>
                            <?php if (!empty($fornecedor['email'])): ?>
                                <span class="small text-muted d-block text-truncate" title="<?= htmlspecialchars($fornecedor['email']) ?>">
                                    <i class="bi bi-envelope me-1" aria-hidden="true"></i><?= htmlspecialchars($fornecedor['email']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($fornecedor['telefone'])): ?>
                                <span class="small text-muted d-block">
                                    <i class="bi bi-telephone me-1" aria-hidden="true"></i><?= htmlspecialchars($fornecedor['telefone']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-weight me-1" aria-hidden="true"></i>Peso Total
                            </small>
                            <strong class="fs-4 d-block"><?= number_format((float) $venda['total_peso'], 2, ',', '.') ?> kg</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-currency-dollar me-1" aria-hidden="true"></i>Valor Total
                            </small>
                            <strong class="fs-4 d-block" style="color: var(--color-accent);">
                                R$ <?= number_format((float) $venda['total_valor'], 2, ',', '.') ?>
                            </strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Data da Venda
                            </small>
                            <strong class="fs-5 d-block"><?= date("d/m/Y", strtotime($venda['data_venda'])) ?></strong>
                            <span class="small text-muted"><?= date("H:i", strtotime($venda['data_venda'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($venda['observacoes']) || !empty($venda['usuario_nome'])): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body d-flex flex-wrap gap-4">
                        <?php if (!empty($venda['observacoes'])): ?>
                            <div>
                                <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Observações</small>
                                <span><?= htmlspecialchars($venda['observacoes']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($venda['usuario_nome'])): ?>
                            <div class="ms-auto">
                                <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Registrada por</small>
                                <span><?= htmlspecialchars($venda['usuario_nome']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabela de Itens -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-list-ul me-2" aria-hidden="true"></i>Itens da Venda</h3>
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
                                    <th class="fw-semibold border-0 text-end">Quantidade</th>
                                    <th class="fw-semibold border-0 text-end">Preço/un</th>
                                    <th class="fw-semibold border-0 text-end">Valor Total</th>
                                    <th class="fw-semibold border-0 text-end pe-4">% do Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <span class="fw-medium"><?= htmlspecialchars($item['nm_material'] ?? 'Material removido') ?></span>
                                        <?php if (!empty($item['material_tipo'])): ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($item['material_tipo']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <?= number_format((float) ($item['peso_bruto'] ?? $item['quantidade']), 2, ',', '.') ?> <?= htmlspecialchars($item['unidade_medida']) ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <?php if (!empty($item['tara'])): ?>
                                            <span class="text-danger">− <?= number_format((float) $item['tara'], 2, ',', '.') ?> <?= htmlspecialchars($item['unidade_medida']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <span class="badge bg-success-subtle text-success px-3 py-2">
                                            <?= number_format((float) $item['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($item['unidade_medida']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-end">
                                        R$ <?= number_format((float) $item['preco_un'], 2, ',', '.') ?>
                                    </td>
                                    <td class="py-3 text-end fw-semibold" style="color: var(--color-accent);">
                                        R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?>
                                    </td>
                                    <td class="py-3 text-end pe-4">
                                        <?php $percentual = $venda['total_valor'] > 0 ? ((float) $item['valor_total'] / (float) $venda['total_valor']) * 100 : 0; ?>
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
                                        <span class="badge bg-success px-3 py-2"><?= number_format((float) $venda['total_peso'], 2, ',', '.') ?> kg</span>
                                    </th>
                                    <th class="py-3 text-end">-</th>
                                    <th class="py-3 text-end fs-6" style="color: var(--color-accent);">
                                        R$ <?= number_format((float) $venda['total_valor'], 2, ',', '.') ?>
                                    </th>
                                    <th class="py-3 text-end pe-4">100%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted mb-3" aria-hidden="true"></i>
                        <h4 class="h6 text-muted">Nenhum item encontrado</h4>
                        <p class="text-muted mb-0">Esta venda não possui itens detalhados.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comprovante importado -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0">
                        <i class="bi bi-paperclip me-2" aria-hidden="true"></i>Comprovante do comprador
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($venda['comprovante_arquivo'])): ?>

                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="me-auto">
                                <span class="d-block fw-medium">
                                    <i class="bi bi-file-earmark-check text-success me-1" aria-hidden="true"></i>
                                    Comprovante arquivado
                                </span>
                                <?php if (!empty($venda['comprovante_importado_em'])): ?>
                                    <small class="text-muted">
                                        Importado em <?= date('d/m/Y \à\s H:i', strtotime($venda['comprovante_importado_em'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= $url_base ?>/functions/venda/comprovante_arquivo.php?id=<?= $id_venda ?>"
                                   target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1" aria-hidden="true"></i> Visualizar
                                </a>
                                <a href="<?= $url_base ?>/functions/venda/comprovante_arquivo.php?id=<?= $id_venda ?>&baixar=1"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download me-1" aria-hidden="true"></i> Baixar
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="collapse" data-bs-target="#formComprovante">
                                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Substituir
                                </button>
                                <form action="<?= $url_base ?>/functions/venda/importar_comprovante.php" method="post"
                                      onsubmit="return confirm('Remover o comprovante importado desta venda?');">
                                    <input type="hidden" name="id_venda" value="<?= $id_venda ?>">
                                    <input type="hidden" name="acao" value="remover">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i> Remover
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="collapse mt-3" id="formComprovante">
                            <?php $colapsar_form = true; ?>
                    <?php else: ?>
                        <p class="text-muted mb-3">
                            Na venda quem emite o comprovante é o comprador. Guarde aqui o arquivo
                            recebido (PDF, JPG, PNG ou WebP, até 10 MB) para conferência posterior.
                        </p>
                        <div>
                            <?php $colapsar_form = false; ?>
                    <?php endif; ?>

                            <form action="<?= $url_base ?>/functions/venda/importar_comprovante.php"
                                  method="post" enctype="multipart/form-data"
                                  class="row g-2 align-items-end">
                                <input type="hidden" name="id_venda" value="<?= $id_venda ?>">
                                <div class="col-md-8">
                                    <label for="comprovante" class="form-label small text-muted mb-1">
                                        Arquivo do comprovante
                                    </label>
                                    <input type="file" name="comprovante" id="comprovante"
                                           class="form-control" required
                                           accept="application/pdf,image/jpeg,image/png,image/webp">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-upload me-1" aria-hidden="true"></i>
                                        <?= $colapsar_form ? 'Substituir comprovante' : 'Importar comprovante' ?>
                                    </button>
                                </div>
                            </form>
                        </div>
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
</style>
