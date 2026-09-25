<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../functions/venda/importacao_lib.php';

exigirPermissao('venda.importar', $url_base);

$acoes = $url_base . '/functions/venda/importacao_acoes.php';
$estado = $_SESSION['importacao'] ?? null;

$modelos = $pdo->query("SELECT m.id_modelo, m.nome, f.nome_razao_social
                        FROM importacao_modelo m
                        LEFT JOIN fornecedores f ON f.id_fornecedor = m.id_fornecedor
                        WHERE m.status = 1
                        ORDER BY m.nome")->fetchAll(PDO::FETCH_ASSOC);

$fornecedores = $pdo->query("SELECT id_fornecedor, nome_razao_social
                             FROM fornecedores WHERE status = 1
                             ORDER BY nome_razao_social")->fetchAll(PDO::FETCH_ASSOC);

// Só carrega a planilha quando há importação em andamento.
$linhas = [];
$colunas = [];
$previa = null;
$erro_leitura = '';

if ($estado) {
    try {
        $linhas = importacaoLerPlanilha($estado['arquivo_tmp']);
        $colunas = importacaoColunasDisponiveis($linhas, $estado['linha_cabecalho']);

        if ($estado['etapa'] === 'preview') {
            $previa = importacaoMontarPreview(
                $pdo,
                $linhas,
                $estado['linha_cabecalho'],
                $estado['mapa'],
                $estado['id_modelo'],
                $estado['edicoes'] ?? []
            );
        }
    } catch (Throwable $e) {
        $erro_leitura = $e->getMessage();
    }
}

$materiais_lista = $pdo->query("SELECT id_material, nm_material, codigo, unidade_medida,
                                       COALESCE(qt_estoque, 0) AS qt_estoque
                                FROM tb_material WHERE status = 1
                                ORDER BY nm_material")->fetchAll(PDO::FETCH_ASSOC);

$badge_situacao = [
    'ok'              => ['bg-success-subtle text-success', 'bi-check-circle', 'Reconhecido'],
    'nao_reconhecido' => ['bg-warning-subtle text-warning-emphasis', 'bi-question-circle', 'Sem relação'],
    'estoque'         => ['bg-danger-subtle text-danger', 'bi-exclamation-triangle', 'Sem estoque'],
    'erro'            => ['bg-danger-subtle text-danger', 'bi-x-circle', 'Erro'],
    'ignorado'        => ['bg-secondary-subtle text-secondary', 'bi-dash-circle', 'Ignorado'],
];
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="<?= $url_base ?>/vendas" class="text-decoration-none">Vendas</a></li>
                    <li class="breadcrumb-item active">Importar</li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold">
                <i class="bi bi-file-earmark-spreadsheet me-2" style="color: var(--color-accent);"></i>Importar Vendas
            </h2>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= $url_base ?>/vendas/importacoes" class="btn btn-outline-secondary">
                <i class="bi bi-clock-history me-1"></i> Histórico
            </a>
            <a href="<?= $url_base ?>/materiais/relacoes" class="btn btn-outline-secondary">
                <i class="bi bi-diagram-3 me-1"></i> Relacionamentos
            </a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <?php if ($erro_leitura): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Não consegui reler a planilha: <?= htmlspecialchars($erro_leitura) ?>
            <a href="<?= $acoes ?>?acao=cancelar" class="alert-link ms-2">Recomeçar</a>
        </div>
    <?php endif; ?>

    <!-- Passos -->
    <?php
    $etapa_atual = !$estado ? 1 : ($estado['etapa'] === 'mapear' ? 2 : 3);
    $passos = [1 => 'Arquivo', 2 => 'Colunas', 3 => 'Conferência'];
    ?>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach ($passos as $n => $rotulo): ?>
            <div class="px-3 py-2 rounded-3 border <?= $n === $etapa_atual ? 'border-2' : 'opacity-50' ?>"
                 style="<?= $n === $etapa_atual ? 'border-color: var(--color-accent) !important;' : '' ?>">
                <span class="badge rounded-pill <?= $n < $etapa_atual ? 'bg-success' : ($n === $etapa_atual ? '' : 'bg-secondary') ?>"
                      style="<?= $n === $etapa_atual ? 'background: var(--color-accent);' : '' ?>">
                    <?= $n < $etapa_atual ? '<i class="bi bi-check"></i>' : $n ?>
                </span>
                <span class="ms-1 <?= $n === $etapa_atual ? 'fw-semibold' : '' ?>"><?= $rotulo ?></span>
            </div>
        <?php endforeach; ?>
    </div>

<?php if (!$estado): ?>

    <!-- ================================================== ETAPA 1: ARQUIVO -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-upload me-2"></i>Enviar planilha</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= $acoes ?>" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="enviar">

                        <div class="mb-3">
                            <label for="planilha" class="form-label">Arquivo</label>
                            <input type="file" name="planilha" id="planilha" class="form-control" required
                                   accept=".xlsx,.xls,.ods,.csv">
                            <small class="form-text text-muted">Excel, OpenDocument ou CSV, até 5 MB.</small>
                        </div>

                        <div class="mb-3">
                            <label for="id_modelo" class="form-label">Modelo de importação</label>
                            <select name="id_modelo" id="id_modelo" class="form-select">
                                <option value="">Detectar colunas automaticamente</option>
                                <?php foreach ($modelos as $m): ?>
                                    <option value="<?= (int) $m['id_modelo'] ?>">
                                        <?= htmlspecialchars($m['nome']) ?><?= $m['nome_razao_social'] ? ' — ' . htmlspecialchars($m['nome_razao_social']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                O modelo guarda em qual coluna está cada informação e o de/para daquele parceiro.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right me-1"></i> Ler planilha
                        </button>
                    </form>

                    <hr>
                    <a href="<?= $url_base ?>/functions/venda/planilha_modelo.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1"></i> Baixar modelo (.xlsx)
                    </a>
                    <small class="d-block text-muted mt-2">
                        O modelo traz uma aba com os materiais em estoque e seus códigos —
                        útil quando o parceiro ainda não tem planilha própria.
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-info-circle me-2"></i>Como funciona</h3>
                </div>
                <div class="card-body">
                    <ol class="mb-3 ps-3">
                        <li class="mb-2">Você envia a planilha do parceiro, na ordem de colunas que ela tiver.</li>
                        <li class="mb-2">Indica qual coluna é o quê — ou escolhe um modelo já salvo.</li>
                        <li class="mb-2">O sistema identifica cada material pelo <strong>de/para</strong> e mostra o estoque antes e depois.</li>
                        <li class="mb-2">O que não for reconhecido você relaciona na hora, e a relação fica salva.</li>
                        <li>Só depois de você confirmar a venda é criada e o estoque baixado.</li>
                    </ol>
                    <div class="alert alert-light border mb-0 small">
                        <i class="bi bi-shield-check me-1"></i>
                        A planilha nunca cria material novo no cadastro. Nome desconhecido vira pendência
                        para você resolver, não um produto inventado.
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($estado['etapa'] === 'mapear' && !$erro_leitura): ?>

    <!-- ================================================== ETAPA 2: COLUNAS -->
    <form method="POST" action="<?= $acoes ?>">
        <input type="hidden" name="acao" value="mapear">

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h3 class="h6 fw-semibold mb-0"><i class="bi bi-table me-2"></i>Mapeamento das colunas</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            Arquivo: <strong><?= htmlspecialchars($estado['arquivo_nome']) ?></strong>
                        </p>

                        <div class="mb-3">
                            <label for="linha_cabecalho" class="form-label">Linha do cabeçalho</label>
                            <input type="number" min="1" max="15" class="form-control" style="max-width:120px;"
                                   name="linha_cabecalho" id="linha_cabecalho"
                                   value="<?= (int) $estado['linha_cabecalho'] + 1 ?>">
                        </div>

                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Campo do sistema</th>
                                    <th>Coluna da planilha</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (VENDA_PLANILHA_ROTULOS as $campo => $rotulo):
                                $obrigatorio = in_array($campo, VENDA_PLANILHA_OBRIGATORIAS, true);
                            ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($rotulo) ?>
                                        <?php if ($obrigatorio): ?>
                                            <span class="text-danger" title="Obrigatório">*</span>
                                        <?php endif; ?>
                                        <?php if ($campo === 'fornecedor'): ?>
                                            <small class="text-muted d-block">para quem você está vendendo</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select name="campo[<?= $campo ?>]" class="form-select form-select-sm">
                                            <option value="">— não usar —</option>
                                            <?php foreach ($colunas as $i => $titulo): ?>
                                                <option value="<?= $i ?>"
                                                    <?= (isset($estado['mapa'][$campo]) && (int) $estado['mapa'][$campo] === $i) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($titulo) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p class="small text-muted">
                            Informe pelo menos a coluna do material <em>ou</em> a do código.
                        </p>

                        <hr>

                        <div class="mb-2">
                            <label for="nome_modelo" class="form-label">Salvar como modelo (opcional)</label>
                            <input type="text" name="nome_modelo" id="nome_modelo" class="form-control"
                                   placeholder="Ex: Venda - Bauer Metais" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="id_fornecedor_modelo" class="form-label">Parceiro do modelo (opcional)</label>
                            <select name="id_fornecedor_modelo" id="id_fornecedor_modelo" class="form-select">
                                <option value="">Sem parceiro específico</option>
                                <?php foreach ($fornecedores as $f): ?>
                                    <option value="<?= (int) $f['id_fornecedor'] ?>"><?= htmlspecialchars($f['nome_razao_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Amarrar o modelo a um parceiro deixa o de/para valer só para ele —
                                útil quando o mesmo apelido significa materiais diferentes em empresas diferentes.
                            </small>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="<?= $acoes ?>?acao=cancelar" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="bi bi-arrow-right me-1"></i> Continuar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h3 class="h6 fw-semibold mb-0"><i class="bi bi-eye me-2"></i>Primeiras linhas do arquivo</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:44px;">#</th>
                                        <?php foreach ($colunas as $i => $titulo): ?>
                                            <th><?= htmlspecialchars(importacaoLetraColuna($i)) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach (array_slice($linhas, 0, 12, true) as $n => $linha): ?>
                                    <tr class="<?= $n === (int) $estado['linha_cabecalho'] ? 'table-warning' : '' ?>">
                                        <td class="text-muted"><?= $n + 1 ?></td>
                                        <?php foreach (array_keys($colunas) as $i): ?>
                                            <td><?= htmlspecialchars(mb_strimwidth((string) ($linha[$i] ?? ''), 0, 20, '…')) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white small text-muted">
                        A linha destacada é a que está marcada como cabeçalho.
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php elseif ($previa): ?>

    <!-- =============================================== ETAPA 3: CONFERÊNCIA -->
    <?php
    $c = $previa['contagem'];
    $pendentes = $c['nao_reconhecido'];
    $problemas = $c['erro'] + $c['estoque'];
    $pode_confirmar = $c['ok'] > 0;

    // Valores do cabeçalho: o que o usuário digitou vence o que veio da planilha,
    // para uma confirmação recusada não apagar o que ele já tinha preenchido.
    $cab = $estado['cabecalho'] ?? [];
    $sel_fornecedor = $cab['id_fornecedor'] ?? ($previa['fornecedor']['id_fornecedor'] ?? 0);
    $sel_pedido     = $cab['pedido_externo'] ?? $previa['pedido'];
    $sel_obs        = $cab['observacoes'] ?? ('Importado de ' . $estado['arquivo_nome']);
    $sel_data       = $cab['data_venda'] ?? null;
    if (!$sel_data) {
        $sel_data = $previa['data_venda'] ? date('Y-m-d', strtotime($previa['data_venda'])) : date('Y-m-d');
    }

    $tem_tara_planilha = isset($estado['mapa']['tara']);
    ?>

    <div class="row g-3 mb-4">
        <?php
        $cartoes = [
            ['Itens na planilha', count($previa['itens']), 'bi-list-ol', ''],
            ['Prontos para venda', $c['ok'], 'bi-check-circle', 'var(--color-accent)'],
            ['Precisam de relação', $pendentes, 'bi-question-circle', $pendentes ? '#d97706' : ''],
            ['Com problema', $problemas, 'bi-exclamation-triangle', $problemas ? '#dc2626' : ''],
        ];
        foreach ($cartoes as [$rotulo, $valor, $icone, $cor]): ?>
            <div class="col-md-3 col-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <small class="text-muted text-uppercase d-block mb-1" style="font-size:.75rem;letter-spacing:.05em;">
                            <i class="bi <?= $icone ?> me-1"></i><?= $rotulo ?>
                        </small>
                        <strong class="fs-3" <?= $cor ? 'style="color:' . $cor . ';"' : '' ?>><?= $valor ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tudo (cabeçalho + itens) num formulário só, para as edições irem juntas -->
    <form method="POST" action="<?= $acoes ?>" id="form-importacao">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-truck me-2"></i>Dados da venda</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="id_fornecedor" class="form-label">
                            Fornecedor <span class="text-danger">*</span>
                        </label>
                        <select name="id_fornecedor" id="id_fornecedor" class="form-select" required>
                            <option value="">Selecione o fornecedor...</option>
                            <?php foreach ($fornecedores as $f): ?>
                                <option value="<?= (int) $f['id_fornecedor'] ?>"
                                    <?= (int) $sel_fornecedor === (int) $f['id_fornecedor'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['nome_razao_social']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($previa['fornecedor']): ?>
                            <small class="text-success">
                                <i class="bi bi-check-circle me-1"></i>
                                Identificado pela planilha ("<?= htmlspecialchars($previa['fornecedor_texto']) ?>").
                            </small>
                        <?php elseif ($previa['fornecedor_texto'] !== ''): ?>
                            <small class="text-warning-emphasis">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                "<?= htmlspecialchars($previa['fornecedor_texto']) ?>" não está no cadastro — escolha acima
                                ou <a href="<?= $url_base ?>/fornecedores/novo" target="_blank">cadastre</a>.
                            </small>
                        <?php else: ?>
                            <small class="text-muted">A planilha não trouxe o fornecedor.</small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-2">
                        <label for="data_venda" class="form-label">Data da venda</label>
                        <input type="date" name="data_venda" id="data_venda" class="form-control"
                               value="<?= htmlspecialchars($sel_data) ?>">
                        <?php if ($previa['data_venda']): ?>
                            <small class="form-text text-muted">Lida da planilha.</small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-2">
                        <label for="pedido_externo" class="form-label">Nº do pedido</label>
                        <input type="text" name="pedido_externo" id="pedido_externo" class="form-control"
                               maxlength="60" value="<?= htmlspecialchars($sel_pedido) ?>">
                        <small class="form-text text-muted">Evita importar duas vezes.</small>
                    </div>

                    <div class="col-md-4">
                        <label for="observacoes" class="form-label">Observações</label>
                        <input type="text" name="observacoes" id="observacoes" class="form-control"
                               value="<?= htmlspecialchars($sel_obs) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="h6 fw-semibold mb-0"><i class="bi bi-card-checklist me-2"></i>Conferência dos itens</h3>
                    <small class="text-muted">
                        Todos os campos são editáveis. Altere e use <em>Recalcular</em> para rever o estoque.
                        <?php if (!$tem_tara_planilha): ?>
                            A planilha não tem coluna de tara — preencha aqui se houver.
                        <?php endif; ?>
                    </small>
                </div>
                <span class="text-muted small">
                    Total:
                    <strong style="color: var(--color-accent);">R$ <?= number_format($previa['total_valor'], 2, ',', '.') ?></strong>
                    · <?= number_format($previa['total_peso'], 2, ',', '.') ?> kg
                </span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:56px;">Linha</th>
                                <th style="min-width:150px;">Na planilha</th>
                                <th style="min-width:230px;">Material do sistema</th>
                                <th class="text-end" style="width:120px;">Quantidade</th>
                                <th class="text-end" style="width:110px;">Tara</th>
                                <th class="text-end" style="width:100px;">Líquido</th>
                                <th class="text-end" style="width:130px;">Valor un.</th>
                                <th class="text-end" style="width:110px;">Subtotal</th>
                                <th class="text-end" style="width:110px;">Estoque depois</th>
                                <th style="width:150px;">Situação</th>
                                <th class="text-center pe-3" style="width:64px;">Tirar</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($previa['itens'] as $item):
                            [$classe, $icone, $rotulo] = $badge_situacao[$item['situacao']];
                            $n = $item['linha'];
                        ?>
                            <tr class="linha-item <?= $item['situacao'] === 'ignorado' ? 'opacity-50' : '' ?>"
                                data-linha="<?= $n ?>">
                                <td class="ps-3 text-muted"><?= $n ?></td>

                                <td>
                                    <span class="d-block text-truncate" style="max-width:150px;"
                                          title="<?= htmlspecialchars($item['planilha_nome'] ?: '—') ?>">
                                        <?= htmlspecialchars($item['planilha_nome'] ?: '—') ?>
                                    </span>
                                    <?php if ($item['planilha_codigo'] !== ''): ?>
                                        <small class="text-muted">cód. <?= htmlspecialchars($item['planilha_codigo']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($item['editado']): ?>
                                        <small class="badge bg-info-subtle text-info-emphasis">editado</small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <select name="itens[<?= $n ?>][id_material]"
                                            class="form-select form-select-sm select-material"
                                            <?= $item['situacao'] === 'nao_reconhecido' ? 'data-pendente="1"' : '' ?>>
                                        <option value="">— escolher material —</option>
                                        <?php foreach ($item['sugestoes'] as $s): ?>
                                            <option value="<?= (int) $s['id_material'] ?>">
                                                ★ <?= htmlspecialchars($s['nm_material']) ?>
                                                <?= $s['codigo'] ? ' (' . htmlspecialchars($s['codigo']) . ')' : '' ?>
                                                — sugestão <?= $s['score'] ?>%
                                            </option>
                                        <?php endforeach; ?>
                                        <?php foreach ($materiais_lista as $m): ?>
                                            <option value="<?= (int) $m['id_material'] ?>"
                                                <?= (int) $item['id_material'] === (int) $m['id_material'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['nm_material']) ?>
                                                <?= $m['codigo'] ? ' (' . htmlspecialchars($m['codigo']) . ')' : '' ?>
                                                — <?= number_format((float) $m['qt_estoque'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade_medida']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($item['via']): ?>
                                        <small class="text-muted"><?= importacaoRotuloVia($item['via']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <input type="text" inputmode="decimal"
                                           name="itens[<?= $n ?>][quantidade_bruta]"
                                           class="form-control form-control-sm text-end campo-num campo-qtd"
                                           value="<?= $item['quantidade_bruta'] !== null ? number_format($item['quantidade_bruta'], 2, ',', '') : '' ?>"
                                           aria-label="Quantidade da linha <?= $n ?>">
                                </td>

                                <td>
                                    <input type="text" inputmode="decimal"
                                           name="itens[<?= $n ?>][tara]"
                                           class="form-control form-control-sm text-end campo-num campo-tara"
                                           value="<?= number_format((float) $item['tara'], 2, ',', '') ?>"
                                           aria-label="Tara da linha <?= $n ?>">
                                </td>

                                <td class="text-end fw-semibold campo-liquido">
                                    <?= $item['quantidade'] !== null ? number_format($item['quantidade'], 2, ',', '.') : '—' ?>
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" inputmode="decimal"
                                               name="itens[<?= $n ?>][preco_un]"
                                               class="form-control text-end campo-num campo-preco"
                                               value="<?= $item['preco_un'] !== null ? number_format($item['preco_un'], 2, ',', '') : '' ?>"
                                               aria-label="Valor unitário da linha <?= $n ?>">
                                    </div>
                                </td>

                                <td class="text-end fw-semibold campo-subtotal" style="color: var(--color-accent);">
                                    <?= $item['valor_total'] !== null ? 'R$ ' . number_format($item['valor_total'], 2, ',', '.') : '—' ?>
                                </td>

                                <td class="text-end <?= ($item['estoque_depois'] !== null && $item['estoque_depois'] < 0) ? 'text-danger fw-semibold' : '' ?>">
                                    <?= $item['estoque_depois'] !== null ? number_format($item['estoque_depois'], 2, ',', '.') : '—' ?>
                                    <?php if ($item['estoque_antes'] !== null): ?>
                                        <small class="text-muted d-block">
                                            de <?= number_format($item['estoque_antes'], 2, ',', '.') ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge <?= $classe ?>"><i class="bi <?= $icone ?> me-1"></i><?= $rotulo ?></span>
                                    <?php if ($item['motivo']): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($item['motivo']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center pe-3">
                                    <input type="checkbox" class="form-check-input" value="1"
                                           name="itens[<?= $n ?>][ignorar]"
                                           <?= $item['situacao'] === 'ignorado' ? 'checked' : '' ?>
                                           aria-label="Remover a linha <?= $n ?> da venda">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($pendentes > 0): ?>
                <div class="card-footer bg-white">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        As opções marcadas com ★ são sugestões por semelhança — confira antes de aceitar,
                        elas não valem sozinhas. Ao confirmar, o material que você escolher vira
                        relacionamento e a próxima planilha do mesmo parceiro já reconhece sozinha.
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <a href="<?= $acoes ?>?acao=cancelar" class="btn btn-outline-danger"
                   onclick="return confirm('Cancelar a importação e descartar o arquivo?');">
                    <i class="bi bi-x-lg me-1"></i> Cancelar
                </a>

                <button type="submit" name="acao" value="recalcular" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i> Recalcular
                </button>

                <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($pendentes > 0 || $problemas > 0): ?>
                        <span class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            <?= $pendentes + $problemas ?> <?= ($pendentes + $problemas) === 1 ? 'item ficará de fora' : 'itens ficarão de fora' ?>.
                        </span>
                    <?php endif; ?>

                    <button type="submit" name="acao" value="confirmar" class="btn btn-primary btn-lg"
                            <?= $pode_confirmar ? '' : 'disabled' ?>
                            onclick="return confirm('Confirmar a venda e dar baixa no estoque?');">
                        <i class="bi bi-check2-circle me-1"></i>
                        Confirmar venda
                    </button>
                </div>
            </div>
        </div>
    </form>

<?php endif; ?>
</div>

<script>
$(function () {
    $('.select-material, #id_fornecedor, #id_modelo, #id_fornecedor_modelo').select2({
        width: '100%',
        language: 'pt-BR'
    });

    // Recalcula líquido e subtotal enquanto o usuário digita. É só conforto
    // visual: quem vale é a conta do servidor, refeita no Recalcular/Confirmar
    // — o estoque, por depender das outras linhas, só atualiza lá.
    function numero(campo) {
        const bruto = (campo.value || '').trim().replace(/[^\d,.-]/g, '');
        if (bruto === '') return null;
        // Aceita 1.234,56 e 1234.56: o separador decimal é o último que aparecer.
        const virgula = bruto.lastIndexOf(',');
        const ponto = bruto.lastIndexOf('.');
        let limpo = bruto;
        if (virgula > -1 && ponto > -1) {
            limpo = virgula > ponto
                ? bruto.replace(/\./g, '').replace(',', '.')
                : bruto.replace(/,/g, '');
        } else if (virgula > -1) {
            limpo = bruto.replace(',', '.');
        }
        const valor = parseFloat(limpo);
        return isNaN(valor) ? null : valor;
    }

    function formatar(valor) {
        return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function atualizarLinha(linha) {
        const bruto = numero(linha.querySelector('.campo-qtd')) || 0;
        const tara = numero(linha.querySelector('.campo-tara')) || 0;
        const preco = numero(linha.querySelector('.campo-preco')) || 0;
        const liquido = bruto - tara;

        const celulaLiquido = linha.querySelector('.campo-liquido');
        const celulaSubtotal = linha.querySelector('.campo-subtotal');

        celulaLiquido.textContent = liquido > 0 ? formatar(liquido) : '—';
        celulaLiquido.classList.toggle('text-danger', bruto > 0 && liquido <= 0);
        celulaSubtotal.textContent = liquido > 0 && preco > 0 ? 'R$ ' + formatar(liquido * preco) : '—';
    }

    document.querySelectorAll('.linha-item').forEach(function (linha) {
        linha.querySelectorAll('.campo-num').forEach(function (campo) {
            campo.addEventListener('input', function () { atualizarLinha(linha); });
        });

        // Tirar a linha da venda apaga visualmente sem perder o que foi digitado.
        const tirar = linha.querySelector('input[type="checkbox"]');
        if (tirar) {
            tirar.addEventListener('change', function () {
                linha.classList.toggle('opacity-50', this.checked);
            });
        }
    });
});
</script>
