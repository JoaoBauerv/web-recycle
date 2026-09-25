<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/venda/planilha.php';

exigirPermissao('material.relacao', $url_base);

$acoes = $url_base . '/functions/material/relacao.php';
$busca = trim($_GET['busca'] ?? '');
$material_foco = (int) ($_GET['material'] ?? 0);

$materiais = $pdo->query("SELECT id_material, nm_material, codigo, tipo, unidade_medida
                          FROM tb_material WHERE status = 1
                          ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);

$modelos = $pdo->query("SELECT id_modelo, nome FROM importacao_modelo
                        WHERE status = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// A busca procura tanto no apelido externo quanto no material oficial, para
// "PET B" e "PET BRANCA" levarem os dois ao mesmo lugar.
$sql = "SELECT r.*, m.nm_material, m.codigo AS material_codigo, m.tipo,
               mo.nome AS modelo_nome
        FROM material_relacao r
        JOIN tb_material m ON m.id_material = r.id_material
        LEFT JOIN importacao_modelo mo ON mo.id_modelo = r.id_modelo
        WHERE r.status = 1";
$params = [];

if ($busca !== '') {
    $sql .= " AND (r.nome_normalizado LIKE :chave
                   OR r.codigo_normalizado LIKE :chave_codigo
                   OR LOWER(m.nm_material) LIKE :bruto
                   OR LOWER(COALESCE(m.codigo, '')) LIKE :bruto)";
    $params[':chave']        = '%' . planilhaNormalizar($busca) . '%';
    $params[':chave_codigo'] = '%' . planilhaChave($busca) . '%';
    $params[':bruto']        = '%' . mb_strtolower($busca) . '%';
}

$sql .= " ORDER BY m.nm_material, r.nome_externo NULLS LAST, r.codigo_externo";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$relacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por material oficial: a tela é "um material e seus apelidos".
$por_material = [];
foreach ($relacoes as $r) {
    $por_material[$r['id_material']]['material'] = [
        'id_material' => $r['id_material'],
        'nm_material' => $r['nm_material'],
        'codigo'      => $r['material_codigo'],
        'tipo'        => $r['tipo'],
    ];
    $por_material[$r['id_material']]['relacoes'][] = $r;
}
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="<?= $url_base ?>/materiais" class="text-decoration-none">Materiais</a></li>
                    <li class="breadcrumb-item active">Relacionamentos</li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold">
                <i class="bi bi-diagram-3 me-2" style="color: var(--color-accent);"></i>Relacionamento de Materiais
            </h2>
            <p class="text-muted mb-0">Nomes e códigos que os parceiros usam para os seus materiais</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRelacao">
            <i class="bi bi-plus-lg me-1"></i> Adicionar relacionamento
        </button>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="pagina" value="materiais/relacoes">
                <div class="col-md-8">
                    <label for="busca" class="form-label small text-muted mb-1">
                        Buscar por nome externo, código externo ou material do sistema
                    </label>
                    <input type="text" name="busca" id="busca" class="form-control"
                           value="<?= htmlspecialchars($busca) ?>" placeholder="Ex: PET B">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                    <?php if ($busca !== ''): ?>
                        <a href="<?= $url_base ?>/materiais/relacoes" class="btn btn-outline-secondary">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($por_material)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-diagram-3 display-4 text-muted d-block mb-3"></i>
                <h2 class="h5 text-muted mb-1">
                    <?= $busca !== '' ? 'Nada encontrado para "' . htmlspecialchars($busca) . '"' : 'Nenhum relacionamento cadastrado' ?>
                </h2>
                <p class="text-muted mb-4">
                    Os relacionamentos também são criados sozinhos quando você resolve uma pendência durante a importação.
                </p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRelacao">
                    <i class="bi bi-plus-lg me-1"></i> Adicionar o primeiro
                </button>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($por_material as $grupo):
            $m = $grupo['material'];
        ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="h6 fw-semibold mb-0">
                            <i class="bi bi-box-seam me-2"></i><?= htmlspecialchars($m['nm_material']) ?>
                        </h3>
                        <small class="text-muted">
                            <?= htmlspecialchars($m['tipo']) ?>
                            · Código do sistema:
                            <?php if ($m['codigo']): ?>
                                <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($m['codigo']) ?></span>
                            <?php else: ?>
                                <span class="text-warning-emphasis">não definido</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <form method="POST" action="<?= $acoes ?>" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="acao" value="codigo_material">
                        <input type="hidden" name="id_material" value="<?= (int) $m['id_material'] ?>">
                        <input type="text" name="codigo" class="form-control form-control-sm" style="width:140px;"
                               placeholder="Código" maxlength="30" value="<?= htmlspecialchars($m['codigo'] ?? '') ?>"
                               aria-label="Código oficial de <?= htmlspecialchars($m['nm_material']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Salvar código</button>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Nome externo</th>
                                    <th>Código externo</th>
                                    <th>Origem</th>
                                    <th>Vale para</th>
                                    <th class="text-end pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($grupo['relacoes'] as $r): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?= $r['nome_externo'] ? htmlspecialchars($r['nome_externo']) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <?= $r['codigo_externo']
                                            ? '<span class="badge bg-light text-dark border">' . htmlspecialchars($r['codigo_externo']) . '</span>'
                                            : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($r['origem'] ?? '—') ?></small></td>
                                    <td>
                                        <?php if ($r['modelo_nome']): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis">
                                                <?= htmlspecialchars($r['modelo_nome']) ?>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted">Todos os parceiros</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-editar"
                                                data-relacao='<?= htmlspecialchars(json_encode([
                                                    'id_relacao'     => (int) $r['id_relacao'],
                                                    'id_material'    => (int) $r['id_material'],
                                                    'nome_externo'   => $r['nome_externo'],
                                                    'codigo_externo' => $r['codigo_externo'],
                                                    'id_modelo'      => $r['id_modelo'],
                                                ]), ENT_QUOTES) ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="<?= $acoes ?>" class="d-inline"
                                              onsubmit="return confirm('Remover este relacionamento?');">
                                            <input type="hidden" name="acao" value="inativar">
                                            <input type="hidden" name="id_relacao" value="<?= (int) $r['id_relacao'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    aria-label="Remover relacionamento">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal de criação/edição -->
<div class="modal fade" id="modalRelacao" tabindex="-1" aria-labelledby="modalRelacaoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= $acoes ?>">
                <input type="hidden" name="acao" value="criar" id="relacao-acao">
                <input type="hidden" name="id_relacao" value="" id="relacao-id">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalRelacaoTitulo">Adicionar relacionamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="relacao-material" class="form-label">Material do sistema <span class="text-danger">*</span></label>
                        <select name="id_material" id="relacao-material" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($materiais as $m): ?>
                                <option value="<?= (int) $m['id_material'] ?>" <?= $material_foco === (int) $m['id_material'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nm_material']) ?>
                                    <?= $m['codigo'] ? ' (' . htmlspecialchars($m['codigo']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="relacao-nome" class="form-label">Nome externo</label>
                            <input type="text" name="nome_externo" id="relacao-nome" class="form-control"
                                   maxlength="150" placeholder="Ex: PET B">
                        </div>
                        <div class="col-md-6">
                            <label for="relacao-codigo" class="form-label">Código externo</label>
                            <input type="text" name="codigo_externo" id="relacao-codigo" class="form-control"
                                   maxlength="60" placeholder="Ex: PET-B">
                        </div>
                    </div>
                    <p class="small text-muted">Preencha ao menos um dos dois.</p>

                    <div class="mb-2">
                        <label for="relacao-modelo" class="form-label">Vale para</label>
                        <select name="id_modelo" id="relacao-modelo" class="form-select">
                            <option value="">Todos os parceiros</option>
                            <?php foreach ($modelos as $mo): ?>
                                <option value="<?= (int) $mo['id_modelo'] ?>"><?= htmlspecialchars($mo['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            Escolha um modelo quando o mesmo apelido significar materiais diferentes
                            dependendo de quem manda a planilha.
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
    $('#relacao-material, #relacao-modelo').select2({
        width: '100%',
        language: 'pt-BR',
        dropdownParent: $('#modalRelacao')
    });

    $('.btn-editar').on('click', function () {
        const dados = $(this).data('relacao');

        $('#relacao-acao').val('editar');
        $('#relacao-id').val(dados.id_relacao);
        $('#relacao-nome').val(dados.nome_externo || '');
        $('#relacao-codigo').val(dados.codigo_externo || '');
        $('#relacao-material').val(dados.id_material).trigger('change');
        $('#relacao-modelo').val(dados.id_modelo || '').trigger('change');
        $('#modalRelacaoTitulo').text('Editar relacionamento');

        new bootstrap.Modal(document.getElementById('modalRelacao')).show();
    });

    // Reabrir pelo botão de adicionar tem de limpar o que a edição deixou.
    $('[data-bs-target="#modalRelacao"]').on('click', function () {
        $('#relacao-acao').val('criar');
        $('#relacao-id').val('');
        $('#relacao-nome, #relacao-codigo').val('');
        $('#relacao-material, #relacao-modelo').val('').trigger('change');
        $('#modalRelacaoTitulo').text('Adicionar relacionamento');
    });
});
</script>
