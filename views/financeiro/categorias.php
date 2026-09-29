<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/financeiro/financeiro_lib.php';

exigirPermissao('financeiro.categorias', $url_base);

$acoes = $url_base . '/functions/financeiro/contas.php';

// Conta o uso para a tela avisar antes de tentar inativar algo em uso.
$categorias = $pdo->query("SELECT c.id_categoria, c.nome, c.tipo, c.data_cadastro,
                                  COUNT(cp.id_conta) FILTER (WHERE cp.status = 'ativa') AS em_uso,
                                  COUNT(cp.id_conta) AS total_uso
                           FROM despesa_categoria c
                           LEFT JOIN contas_pagar cp ON cp.id_categoria = c.id_categoria
                           WHERE c.status = 1
                           GROUP BY c.id_categoria, c.nome, c.tipo, c.data_cadastro
                           ORDER BY c.nome")->fetchAll(PDO::FETCH_ASSOC);

$centros = $pdo->query("SELECT cc.id_centro, cc.nome,
                               COUNT(cp.id_conta) FILTER (WHERE cp.status = 'ativa') AS em_uso
                        FROM centro_custo cc
                        LEFT JOIN contas_pagar cp ON cp.id_centro = cc.id_centro
                        WHERE cc.status = 1
                        GROUP BY cc.id_centro, cc.nome
                        ORDER BY cc.nome")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4" style="max-width: 1200px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="<?= $url_base ?>/financeiro/contas" class="text-decoration-none">Contas a Pagar</a></li>
                    <li class="breadcrumb-item active">Categorias</li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold">
                <i class="bi bi-tags me-2" style="color: var(--color-accent);" aria-hidden="true"></i>Categorias de Despesa
            </h2>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Nova categoria
        </button>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0">Categorias (<?= count($categorias) ?>)</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Nome</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Despesas</th>
                                    <th class="text-end pe-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categorias as $c): ?>
                                <tr>
                                    <td class="ps-3 fw-medium"><?= htmlspecialchars($c['nome']) ?></td>
                                    <td>
                                        <?php if ($c['tipo'] === 'funcionario'): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis">
                                                <i class="bi bi-person me-1" aria-hidden="true"></i>Funcionário
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Geral</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?= (int) $c['total_uso'] ?>
                                        <?php if ((int) $c['em_uso'] > 0): ?>
                                            <small class="text-muted d-block"><?= (int) $c['em_uso'] ?> ativa(s)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3 text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-cat"
                                                data-id="<?= (int) $c['id_categoria'] ?>"
                                                data-nome="<?= htmlspecialchars($c['nome'], ENT_QUOTES) ?>"
                                                data-tipo="<?= $c['tipo'] ?>" aria-label="Editar <?= htmlspecialchars($c['nome']) ?>">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>
                                        <?php if ((int) $c['em_uso'] === 0): ?>
                                            <form method="POST" action="<?= $acoes ?>" class="d-inline"
                                                  onsubmit="return confirm('Inativar esta categoria?');">
                                                <input type="hidden" name="acao" value="categoria">
                                                <input type="hidden" name="operacao" value="inativar">
                                                <input type="hidden" name="id_categoria" value="<?= (int) $c['id_categoria'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Inativar">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                                    title="Em uso por despesas ativas">
                                                <i class="bi bi-lock" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white small text-muted">
                    Categoria em uso não pode ser inativada, e nenhuma é apagada de verdade:
                    o histórico financeiro precisa continuar explicável.
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0">
                        <i class="bi bi-diagram-2 me-2" aria-hidden="true"></i>Centros de custo (<?= count($centros) ?>)
                    </h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                        <?php foreach ($centros as $c): ?>
                            <tr>
                                <td class="ps-3"><?= htmlspecialchars($c['nome']) ?></td>
                                <td class="text-end pe-3 text-muted small">
                                    <?= (int) $c['em_uso'] ?> despesa(s) ativa(s)
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    Os centros permitem responder "quanto se gasta com operação, logística, manutenção".
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCategoria" tabindex="-1" aria-labelledby="tituloCategoria" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= $acoes ?>">
                <input type="hidden" name="acao" value="categoria">
                <input type="hidden" name="operacao" value="criar" id="cat-operacao">
                <input type="hidden" name="id_categoria" value="" id="cat-id">

                <div class="modal-header">
                    <h5 class="modal-title" id="tituloCategoria">Nova categoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cat-nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" id="cat-nome" class="form-control" maxlength="60" required>
                    </div>
                    <div class="mb-2">
                        <label for="cat-tipo" class="form-label">Tipo</label>
                        <select name="tipo" id="cat-tipo" class="form-select">
                            <option value="geral">Geral</option>
                            <option value="funcionario">Funcionário</option>
                        </select>
                        <small class="form-text text-muted">
                            "Funcionário" faz o formulário de despesa oferecer o vínculo com o usuário.
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    $('.btn-editar-cat').on('click', function () {
        $('#cat-operacao').val('editar');
        $('#cat-id').val($(this).data('id'));
        $('#cat-nome').val($(this).data('nome'));
        $('#cat-tipo').val($(this).data('tipo'));
        $('#tituloCategoria').text('Editar categoria');
        new bootstrap.Modal(document.getElementById('modalCategoria')).show();
    });

    $('[data-bs-target="#modalCategoria"]').on('click', function () {
        $('#cat-operacao').val('criar');
        $('#cat-id').val('');
        $('#cat-nome').val('');
        $('#cat-tipo').val('geral');
        $('#tituloCategoria').text('Nova categoria');
    });
});
</script>
