<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
exigirPermissao('venda.importar', $url_base);

$importacoes = $pdo->query("SELECT h.*, u.nome AS usuario_nome, m.nome AS modelo_nome,
                                   v.id_venda
                            FROM importacao_historico h
                            LEFT JOIN tb_usuario u ON u.id_usuario = h.id_usuario
                            LEFT JOIN importacao_modelo m ON m.id_modelo = h.id_modelo
                            LEFT JOIN vendas v ON v.id_importacao = h.id_importacao
                            ORDER BY h.data_importacao DESC
                            LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

$rotulo_status = [
    'processando'      => ['bg-secondary', 'bi-hourglass-split', 'Processando'],
    'concluido'        => ['bg-success', 'bi-check-circle', 'Concluído'],
    'concluido_avisos' => ['bg-warning text-dark', 'bi-exclamation-triangle', 'Concluído com avisos'],
    'erro'             => ['bg-danger', 'bi-x-circle', 'Erro'],
    'cancelado'        => ['bg-dark', 'bi-slash-circle', 'Cancelado'],
];
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="<?= $url_base ?>/vendas" class="text-decoration-none">Vendas</a></li>
                    <li class="breadcrumb-item active">Histórico de importações</li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold">
                <i class="bi bi-clock-history me-2" style="color: var(--color-accent);"></i>Histórico de Importações
            </h2>
        </div>
        <a href="<?= $url_base ?>/vendas/importar" class="btn btn-primary">
            <i class="bi bi-upload me-1"></i> Nova importação
        </a>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if (empty($importacoes)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-earmark-spreadsheet display-4 text-muted d-block mb-3"></i>
                <h2 class="h5 text-muted mb-1">Nenhuma importação registrada</h2>
                <p class="text-muted mb-4">Quando você importar uma planilha de venda, o registro aparece aqui.</p>
                <a href="<?= $url_base ?>/vendas/importar" class="btn btn-primary">
                    <i class="bi bi-upload me-1"></i> Importar planilha
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="importacoesTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Arquivo</th>
                                <th>Modelo</th>
                                <th>Data</th>
                                <th>Usuário</th>
                                <th class="text-end">Registros</th>
                                <th class="text-end">Valor total</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Venda</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($importacoes as $imp):
                            [$classe, $icone, $texto] = $rotulo_status[$imp['status']] ?? ['bg-secondary', 'bi-circle', $imp['status']];
                            $erros = $imp['erros'] ? json_decode($imp['erros'], true) : [];
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <strong><?= htmlspecialchars($imp['arquivo']) ?></strong>
                                    <?php if ($imp['pedido_externo']): ?>
                                        <small class="text-muted d-block">pedido <?= htmlspecialchars($imp['pedido_externo']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $imp['modelo_nome'] ? htmlspecialchars($imp['modelo_nome']) : '<span class="text-muted">—</span>' ?></td>
                                <td data-order="<?= strtotime($imp['data_importacao']) ?>">
                                    <?= date('d/m/Y', strtotime($imp['data_importacao'])) ?>
                                    <small class="text-muted d-block"><?= date('H:i', strtotime($imp['data_importacao'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($imp['usuario_nome'] ?? '—') ?></td>
                                <td class="text-end">
                                    <?= (int) $imp['registros_importados'] ?> de <?= (int) $imp['total_registros'] ?>
                                    <?php if ($imp['registros_com_erro'] > 0): ?>
                                        <small class="text-danger d-block"><?= (int) $imp['registros_com_erro'] ?> com problema</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold" data-order="<?= (float) $imp['valor_total'] ?>">
                                    R$ <?= number_format((float) $imp['valor_total'], 2, ',', '.') ?>
                                </td>
                                <td>
                                    <span class="badge <?= $classe ?>"><i class="bi <?= $icone ?> me-1"></i><?= $texto ?></span>
                                    <?php if ($erros): ?>
                                        <button type="button" class="btn btn-link btn-sm p-0 d-block text-decoration-none"
                                                data-bs-toggle="modal" data-bs-target="#erros<?= (int) $imp['id_importacao'] ?>">
                                            ver <?= count($erros) ?> ocorrência<?= count($erros) === 1 ? '' : 's' ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($imp['id_venda']): ?>
                                        <a href="<?= $url_base ?>/vendas/detalhe?id=<?= (int) $imp['id_venda'] ?>"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i> #<?= (int) $imp['id_venda'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modais com o log de cada importação -->
        <?php foreach ($importacoes as $imp):
            $erros = $imp['erros'] ? json_decode($imp['erros'], true) : [];
            if (!$erros) continue;
        ?>
            <div class="modal fade" id="erros<?= (int) $imp['id_importacao'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Ocorrências — <?= htmlspecialchars($imp['arquivo']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th class="ps-3">Linha</th><th>Material</th><th class="pe-3">Motivo</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($erros as $e): ?>
                                    <tr>
                                        <td class="ps-3"><?= (int) ($e['linha'] ?? 0) ?: '—' ?></td>
                                        <td><?= htmlspecialchars($e['material'] ?? '') ?></td>
                                        <td class="pe-3"><?= htmlspecialchars($e['motivo'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <a href="<?= $url_base ?>/functions/venda/importacao_erros_csv.php?id=<?= (int) $imp['id_importacao'] ?>"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download me-1"></i> Exportar CSV
                            </a>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
$(function () {
    if (!$('#importacoesTable').length) return;

    $('#importacoesTable').DataTable({
        "autoWidth": false,
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
        "pageLength": 15,
        "order": [[2, "desc"]],
        "columnDefs": [{ "orderable": false, "targets": [6, 7] }]
    });
});
</script>
