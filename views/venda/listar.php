<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

$vendas = $pdo->query("SELECT v.id_venda, v.total_peso, v.total_valor, v.status, v.data_venda, v.observacoes,
                              f.nome_razao_social, f.cidade, f.estado,
                              u.nome AS nome_usuario
                       FROM vendas v
                       JOIN fornecedores f ON f.id_fornecedor = v.id_fornecedor
                       LEFT JOIN tb_usuario u ON u.id_usuario = v.id_usuario
                       ORDER BY v.data_venda DESC")->fetchAll(PDO::FETCH_ASSOC);

// Carrega todos os itens de uma vez e agrupa por venda (evita uma query por linha da tabela).
$itens_por_venda = [];
if ($vendas) {
    $stmt_itens = $pdo->query("SELECT vi.id_venda, vi.quantidade, vi.preco_un, vi.valor_total, vi.peso_bruto, vi.tara,
                                      m.nm_material, m.tipo, m.unidade_medida
                               FROM vendas_itens vi
                               JOIN tb_material m ON m.id_material = vi.id_material
                               ORDER BY m.nm_material");
    foreach ($stmt_itens->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itens_por_venda[$item['id_venda']][] = $item;
    }
}

$total_geral = array_sum(array_column($vendas, 'total_valor'));
$peso_geral  = array_sum(array_column($vendas, 'total_peso'));
?>

<div class="container-fluid py-4" style="max-width: 1200px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-clock-history me-2"></i>Vendas realizadas</h2>
            <p class="text-muted mb-0">Histórico de vendas de materiais para fornecedores</p>
        </div>
        <a href="<?= $url_base ?>/vendas" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nova venda
        </a>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if (empty($vendas)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-cart-x display-4 text-muted d-block mb-3"></i>
                <h5 class="text-muted mb-2">Nenhuma venda registrada ainda</h5>
                <p class="text-muted mb-4">Quando você vender materiais do estoque, o histórico aparece aqui.</p>
                <a href="<?= $url_base ?>/vendas" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Registrar a primeira venda
                </a>
            </div>
        </div>
    <?php else: ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Vendas</small>
                        <strong class="fs-4"><?= count($vendas) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Peso vendido</small>
                        <strong class="fs-4"><?= number_format($peso_geral, 2, ',', '.') ?> kg</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">Faturamento</small>
                        <strong class="fs-4" style="color: var(--color-accent);">R$ <?= number_format($total_geral, 2, ',', '.') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Venda</th>
                            <th>Fornecedor</th>
                            <th>Data</th>
                            <th class="text-end">Peso</th>
                            <th class="text-end">Valor</th>
                            <th class="text-end pe-4"><span class="visually-hidden">Itens</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vendas as $venda): ?>
                        <?php $itens = $itens_por_venda[$venda['id_venda']] ?? []; ?>
                        <tr>
                            <td class="ps-4 fw-semibold">#<?= (int) $venda['id_venda'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($venda['nome_razao_social']) ?></strong>
                                <?php $local = trim(($venda['cidade'] ?? '') . '/' . ($venda['estado'] ?? ''), '/'); ?>
                                <?php if ($local): ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($local) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($venda['data_venda'])) ?>
                                <small class="text-muted d-block"><?= date('H:i', strtotime($venda['data_venda'])) ?></small>
                            </td>
                            <td class="text-end"><?= number_format((float) $venda['total_peso'], 2, ',', '.') ?> kg</td>
                            <td class="text-end fw-semibold" style="color: var(--color-accent);">
                                R$ <?= number_format((float) $venda['total_valor'], 2, ',', '.') ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-outline-secondary btn-sm" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#itens-<?= (int) $venda['id_venda'] ?>"
                                        aria-expanded="false" aria-controls="itens-<?= (int) $venda['id_venda'] ?>">
                                    <i class="bi bi-list-ul me-1"></i>
                                    <?= count($itens) ?> <?= count($itens) === 1 ? 'item' : 'itens' ?>
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse" id="itens-<?= (int) $venda['id_venda'] ?>">
                            <td colspan="6" class="bg-light px-4 py-3">
                                <?php if ($venda['observacoes']): ?>
                                    <p class="text-muted mb-3">
                                        <i class="bi bi-chat-left-text me-1"></i>
                                        <?= htmlspecialchars($venda['observacoes']) ?>
                                    </p>
                                <?php endif; ?>
                                <table class="table table-sm mb-2">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th class="text-end">Qtd.</th>
                                            <th class="text-end">Preço un.</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($itens as $item): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($item['nm_material']) ?>
                                                <small class="text-muted">/ <?= htmlspecialchars($item['tipo']) ?></small>
                                            </td>
                                            <td class="text-end">
                                                <?= number_format((float) $item['quantidade'], 2, ',', '.') ?>
                                                <small class="text-muted"><?= htmlspecialchars($item['unidade_medida']) ?></small>
                                                <?php if (!empty($item['tara'])): ?>
                                                    <small class="text-muted d-block">
                                                        bruto <?= number_format((float) $item['peso_bruto'], 2, ',', '.') ?>
                                                        − tara <?= number_format((float) $item['tara'], 2, ',', '.') ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">R$ <?= number_format((float) $item['preco_un'], 2, ',', '.') ?></td>
                                            <td class="text-end">R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if ($venda['nome_usuario']): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-person me-1"></i>
                                        Registrada por <?= htmlspecialchars($venda['nome_usuario']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
