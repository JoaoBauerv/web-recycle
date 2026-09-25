<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/estoque/estoque_lib.php';

exigirPermissao('estoque.visualizar', $url_base);

$id_material = (int) ($_GET['id'] ?? 0);
if (!$id_material) {
    header('Location: ' . $url_base . '/estoque');
    exit;
}

$stmt = $pdo->prepare("SELECT id_material, nm_material, tipo, unidade_medida, codigo,
                              COALESCE(qt_estoque, 0) AS saldo,
                              preco_compra, preco_venda, estoque_minimo
                       FROM tb_material
                       WHERE id_material = ?");
$stmt->execute([$id_material]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    header('Location: ' . $url_base . '/estoque?msgErro=' . urlencode('Material não encontrado.'));
    exit;
}

$material['saldo'] = (float) $material['saldo'];
$material['estoque_minimo'] = $material['estoque_minimo'] !== null ? (float) $material['estoque_minimo'] : null;
$situacao = estoqueSituacao($material['saldo'], $material['estoque_minimo']);

$pode_ajustar = usuarioPode('estoque.ajustar');
$acoes = $url_base . '/functions/estoque/ajustar.php';

$movimentacoes = $pdo->prepare("SELECT em.id_movimentacao, em.data_movimentacao, em.tipo, em.quantidade,
                                       em.saldo_apos, em.origem_tipo, em.origem_id, em.observacoes,
                                       u.nome AS usuario_nome
                                FROM estoque_movimentacoes em
                                LEFT JOIN tb_usuario u ON u.id_usuario = em.id_usuario
                                WHERE em.id_material = ?
                                ORDER BY em.data_movimentacao DESC, em.id_movimentacao DESC");
$movimentacoes->execute([$id_material]);
$movimentacoes = $movimentacoes->fetchAll(PDO::FETCH_ASSOC);

// Totais do extrato, pela mesma regra de sinal usada em todo o sistema.
$total_entradas = $total_saidas = 0.0;
foreach ($movimentacoes as $mov) {
    if (estoqueSinal($mov['tipo']) > 0) {
        $total_entradas += (float) $mov['quantidade'];
    } else {
        $total_saidas += (float) $mov['quantidade'];
    }
}

// O saldo tem de ser reconstruível a partir da trilha. Quando não bate, é sinal
// de que algo mexeu em qt_estoque sem gravar movimentação — vale avisar na tela.
$saldo_calculado = round($total_entradas - $total_saidas, 2);
$saldo_confere = abs($saldo_calculado - $material['saldo']) < 0.005;

[$classe_selo, $icone_selo, $rotulo_selo] = ESTOQUE_SELOS[$situacao];
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item">
                        <a href="<?= $url_base ?>/estoque" class="text-decoration-none">
                            <i class="bi bi-boxes me-1" aria-hidden="true"></i>Estoque
                        </a>
                    </li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($material['nm_material']) ?></li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold">
                <?= htmlspecialchars($material['nm_material']) ?>
                <span class="badge <?= $classe_selo ?> align-middle ms-1" style="font-size:.7rem;">
                    <i class="bi <?= $icone_selo ?> me-1" aria-hidden="true"></i><?= $rotulo_selo ?>
                </span>
            </h2>
            <small class="text-muted">
                <?= htmlspecialchars($material['tipo']) ?>
                <?= $material['codigo'] ? ' · código ' . htmlspecialchars($material['codigo']) : '' ?>
            </small>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($pode_ajustar): ?>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAjusteMaterial">
                    <i class="bi bi-plus-slash-minus me-1" aria-hidden="true"></i> Lançamento manual
                </button>
            <?php endif; ?>
            <a href="<?= $url_base ?>/estoque" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Voltar
            </a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if (!$saldo_confere): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2" aria-hidden="true"></i>
            <strong>Saldo não bate com o extrato.</strong>
            O cadastro diz <?= number_format($material['saldo'], 2, ',', '.') ?>
            <?= htmlspecialchars($material['unidade_medida']) ?>, mas a soma das movimentações dá
            <?= number_format($saldo_calculado, 2, ',', '.') ?>.
            Diferença de <?= number_format($material['saldo'] - $saldo_calculado, 2, ',', '.') ?>.
            Isso indica alteração de saldo sem movimentação registrada.
        </div>
    <?php endif; ?>

    <!-- Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Saldo atual</span>
                        <span class="indicador-valor">
                            <?= number_format($material['saldo'], 2, ',', '.') ?>
                            <small class="text-muted"><?= htmlspecialchars($material['unidade_medida']) ?></small>
                        </span>
                        <small class="text-muted">
                            <?= $material['estoque_minimo'] !== null
                                ? 'mínimo ' . number_format($material['estoque_minimo'], 2, ',', '.')
                                : 'sem mínimo definido' ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-arrow-down-circle" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Total que entrou</span>
                        <span class="indicador-valor"><?= number_format($total_entradas, 2, ',', '.') ?></span>
                        <small class="text-muted">desde o primeiro registro</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-arrow-up-circle" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Total que saiu</span>
                        <span class="indicador-valor"><?= number_format($total_saidas, 2, ',', '.') ?></span>
                        <small class="text-muted"><?= count($movimentacoes) ?> movimentação(ões)</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card card-indicador h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="indicador-icone"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <span class="indicador-rotulo">Valor imobilizado</span>
                        <span class="indicador-valor" style="color: var(--color-accent);">
                            R$ <?= number_format($material['saldo'] * (float) $material['preco_compra'], 2, ',', '.') ?>
                        </span>
                        <small class="text-muted">
                            a R$ <?= number_format((float) $material['preco_compra'], 2, ',', '.') ?> de compra
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Extrato -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h3 class="h6 fw-semibold mb-0">
                <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Extrato de movimentação
            </h3>
        </div>

        <div class="card-body p-0">
            <?php if (empty($movimentacoes)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                    <h4 class="h6 text-muted mb-1">Nenhuma movimentação registrada</h4>
                    <p class="text-muted mb-0">Este material ainda não entrou nem saiu do estoque.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="extratoTable" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Data</th>
                                <th>Tipo</th>
                                <th>Origem</th>
                                <th class="text-end">Quantidade</th>
                                <th class="text-end">Saldo após</th>
                                <th>Usuário</th>
                                <th class="pe-4">Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($movimentacoes as $mov):
                            $entrada = estoqueSinal($mov['tipo']) > 0;
                            $origem = estoqueRotuloOrigem($mov['origem_tipo']);

                            // Origem clicável só quando existe documento do outro lado.
                            $destino = ($origem['rota'] && $mov['origem_id'])
                                ? $url_base . '/' . $origem['rota'] . '?id=' . (int) $mov['origem_id']
                                : null;
                        ?>
                            <tr>
                                <td class="ps-4" data-order="<?= strtotime($mov['data_movimentacao']) ?>">
                                    <?= date('d/m/Y', strtotime($mov['data_movimentacao'])) ?>
                                    <small class="text-muted d-block"><?= date('H:i', strtotime($mov['data_movimentacao'])) ?></small>
                                </td>

                                <td>
                                    <span class="badge <?= $entrada ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <i class="bi <?= $entrada ? 'bi-arrow-down' : 'bi-arrow-up' ?> me-1" aria-hidden="true"></i>
                                        <?= ucfirst($mov['tipo']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($destino): ?>
                                        <a href="<?= $destino ?>" class="text-decoration-none">
                                            <i class="bi <?= $origem['icone'] ?> me-1" aria-hidden="true"></i>
                                            <?= $origem['rotulo'] ?> #<?= (int) $mov['origem_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="bi <?= $origem['icone'] ?> me-1" aria-hidden="true"></i>
                                            <?= $origem['rotulo'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end fw-semibold <?= $entrada ? 'text-success' : 'text-danger' ?>"
                                    data-order="<?= ($entrada ? 1 : -1) * (float) $mov['quantidade'] ?>">
                                    <?= $entrada ? '+' : '−' ?> <?= number_format((float) $mov['quantidade'], 2, ',', '.') ?>
                                    <small class="text-muted"><?= htmlspecialchars($material['unidade_medida']) ?></small>
                                </td>

                                <td class="text-end" data-order="<?= (float) $mov['saldo_apos'] ?>">
                                    <?= number_format((float) $mov['saldo_apos'], 2, ',', '.') ?>
                                </td>

                                <td><small><?= htmlspecialchars($mov['usuario_nome'] ?? '—') ?></small></td>

                                <td class="pe-4">
                                    <small class="text-muted"><?= htmlspecialchars($mov['observacoes'] ?? '') ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($pode_ajustar): ?>
    <div class="modal fade" id="modalAjusteMaterial" tabindex="-1" aria-labelledby="tituloAjusteMaterial" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="<?= $acoes ?>">
                    <input type="hidden" name="acao" value="movimentar">
                    <input type="hidden" name="id_material" value="<?= $id_material ?>">
                    <input type="hidden" name="voltar_para" value="extrato">

                    <div class="modal-header">
                        <h5 class="modal-title" id="tituloAjusteMaterial">
                            Lançamento em <?= htmlspecialchars($material['nm_material']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Saldo atual: <strong><?= number_format($material['saldo'], 2, ',', '.') ?>
                            <?= htmlspecialchars($material['unidade_medida']) ?></strong>
                        </p>

                        <div class="mb-3">
                            <span class="form-label d-block">Tipo <span class="text-danger">*</span></span>
                            <?php foreach (ESTOQUE_TIPOS_MANUAIS as $valor => $info): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo"
                                           id="mat-tipo-<?= $valor ?>" value="<?= $valor ?>" required
                                           <?= $valor === 'entrada' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="mat-tipo-<?= $valor ?>">
                                        <i class="bi <?= $info['icone'] ?> me-1" aria-hidden="true"></i><?= $info['rotulo'] ?>
                                        <small class="text-muted d-block"><?= $info['ajuda'] ?></small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-3">
                            <label for="mat-quantidade" class="form-label">Quantidade <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="quantidade" id="mat-quantidade" class="form-control"
                                       inputmode="decimal" placeholder="0,00" required>
                                <span class="input-group-text"><?= htmlspecialchars($material['unidade_medida']) ?></span>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="mat-motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                            <input type="text" name="observacoes" id="mat-motivo" class="form-control"
                                   maxlength="200" required placeholder="Ex: sobra encontrada na contagem">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Lançar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
$(function () {
    if (!$('#extratoTable').length) return;

    $('#extratoTable').DataTable({
        "autoWidth": false,
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
        "pageLength": 25,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[0, "desc"]],
        "columnDefs": [{ "orderable": false, "targets": [6] }]
    });
});
</script>
