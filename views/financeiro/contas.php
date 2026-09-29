<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/financeiro/financeiro_lib.php';

exigirPermissao('financeiro.contas', $url_base);

$pode_gerenciar = usuarioPode('financeiro.conta_gerenciar');
$pode_pagar     = usuarioPode('financeiro.pagamento');
$pode_cancelar  = usuarioPode('financeiro.conta_cancelar');
$acoes = $url_base . '/functions/financeiro/contas.php';

$situacao_sql = financeiroSituacaoSql('pa');

// ------------------------------------------------------------- indicadores
$ind = $pdo->query("
    SELECT
      COALESCE(SUM(pa.valor) FILTER (WHERE pa.status='pendente' AND pa.data_vencimento = CURRENT_DATE), 0) AS hoje,
      COALESCE(SUM(pa.valor) FILTER (WHERE pa.status='pendente'
               AND pa.data_vencimento > CURRENT_DATE
               AND pa.data_vencimento <= CURRENT_DATE + 7), 0) AS proximos7,
      COALESCE(SUM(pa.valor) FILTER (WHERE pa.status='pendente' AND pa.data_vencimento < CURRENT_DATE), 0) AS vencidas,
      COALESCE(SUM(pa.valor_pago) FILTER (WHERE pa.status='paga'
               AND date_trunc('month', pa.data_pagamento) = date_trunc('month', CURRENT_DATE)), 0) AS pagas_mes,
      COALESCE(SUM(pa.valor) FILTER (WHERE date_trunc('month', pa.data_vencimento) = date_trunc('month', CURRENT_DATE)
               AND pa.status <> 'cancelada'), 0) AS despesas_mes,
      COUNT(*) FILTER (WHERE pa.status='pendente' AND pa.data_vencimento < CURRENT_DATE) AS qtd_vencidas
    FROM contas_pagar_parcelas pa
    JOIN contas_pagar c ON c.id_conta = pa.id_conta
    WHERE c.status = 'ativa'
")->fetch(PDO::FETCH_ASSOC);

// ----------------------------------------------------------------- filtros
$f_inicio    = financeiroData($_GET['inicio'] ?? '') ?? date('Y-m-01');
$f_fim       = financeiroData($_GET['fim'] ?? '') ?? date('Y-m-t');
$f_situacao  = $_GET['situacao'] ?? '';
$f_categoria = (int) ($_GET['id_categoria'] ?? 0);
$f_centro    = (int) ($_GET['id_centro'] ?? 0);
$f_forma     = $_GET['forma'] ?? '';
$f_favorec   = trim($_GET['favorecido'] ?? '');
$f_min       = financeiroNumero($_GET['valor_min'] ?? '');
$f_max       = financeiroNumero($_GET['valor_max'] ?? '');

$where = ["pa.data_vencimento BETWEEN :inicio AND :fim"];
$params = [':inicio' => $f_inicio, ':fim' => $f_fim];

if ($f_situacao !== '')  { $where[] = "$situacao_sql = :situacao";  $params[':situacao'] = $f_situacao; }
if ($f_categoria)        { $where[] = "c.id_categoria = :cat";      $params[':cat'] = $f_categoria; }
if ($f_centro)           { $where[] = "c.id_centro = :cc";          $params[':cc'] = $f_centro; }
if ($f_forma !== '')     { $where[] = "pa.forma_pagamento = :forma"; $params[':forma'] = $f_forma; }
if ($f_favorec !== '')   { $where[] = "(LOWER(c.favorecido) LIKE :fav OR LOWER(c.descricao) LIKE :fav OR LOWER(u.nome) LIKE :fav)";
                           $params[':fav'] = '%' . mb_strtolower($f_favorec) . '%'; }
if ($f_min !== null)     { $where[] = "pa.valor >= :vmin";          $params[':vmin'] = $f_min; }
if ($f_max !== null)     { $where[] = "pa.valor <= :vmax";          $params[':vmax'] = $f_max; }

$sql = "SELECT pa.*, c.descricao, c.favorecido, c.id_conta, c.status AS status_conta,
               c.recorrente, cat.nome AS categoria, cat.tipo AS categoria_tipo,
               cc.nome AS centro, u.nome AS funcionario,
               up.nome AS pagou_nome, $situacao_sql AS situacao
        FROM contas_pagar_parcelas pa
        JOIN contas_pagar c        ON c.id_conta = pa.id_conta
        JOIN despesa_categoria cat ON cat.id_categoria = c.id_categoria
        LEFT JOIN centro_custo cc  ON cc.id_centro = c.id_centro
        LEFT JOIN tb_usuario u     ON u.id_usuario = c.id_usuario_func
        LEFT JOIN tb_usuario up    ON up.id_usuario = pa.id_usuario_pagamento
        WHERE " . implode(' AND ', $where) . "
        ORDER BY pa.data_vencimento, c.descricao, pa.numero_parcela";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_filtrado = array_sum(array_map(fn($p) => (float) $p['valor'], $parcelas));

$categorias = $pdo->query("SELECT id_categoria, nome, tipo FROM despesa_categoria WHERE status = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$centros    = $pdo->query("SELECT id_centro, nome FROM centro_custo WHERE status = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$usuarios   = $pdo->query("SELECT id_usuario, nome FROM tb_usuario WHERE status = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4" style="max-width: 1500px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1 fw-bold">
                <i class="bi bi-cash-stack me-2" style="color: var(--color-accent);" aria-hidden="true"></i>Contas a Pagar
            </h2>
            <p class="text-muted mb-0">Despesas da empresa e seus vencimentos</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= $url_base ?>/financeiro/relatorio" class="btn btn-outline-secondary">
                <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i> Relatório
            </a>
            <?php if (usuarioPode('financeiro.categorias')): ?>
                <a href="<?= $url_base ?>/financeiro/categorias" class="btn btn-outline-secondary">
                    <i class="bi bi-tags me-1" aria-hidden="true"></i> Categorias
                </a>
            <?php endif; ?>
            <?php if ($pode_gerenciar): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDespesa">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Nova despesa
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <!-- Dashboard -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['A pagar hoje', (float) $ind['hoje'], 'bi-calendar-day', ''],
            ['Próximos 7 dias', (float) $ind['proximos7'], 'bi-calendar-week', ''],
            ['Vencidas', (float) $ind['vencidas'], 'bi-exclamation-octagon', (float) $ind['vencidas'] > 0 ? '#dc2626' : '',
             (int) $ind['qtd_vencidas'] . ' parcela(s)'],
            ['Pagas no mês', (float) $ind['pagas_mes'], 'bi-check2-circle', 'var(--color-accent)'],
            ['Despesas do mês', (float) $ind['despesas_mes'], 'bi-wallet2', ''],
        ];
        foreach ($cards as $c): ?>
            <div class="col-xl col-md-4 col-6">
                <div class="card card-indicador h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="indicador-icone"><i class="bi <?= $c[2] ?>" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <span class="indicador-rotulo"><?= $c[0] ?></span>
                            <span class="indicador-valor" <?= $c[3] ? 'style="color:' . $c[3] . ';"' : '' ?>
                                  style="font-size:1.1rem;<?= $c[3] ? 'color:' . $c[3] . ';' : '' ?>">
                                <?= financeiroMoeda($c[1]) ?>
                            </span>
                            <?php if (isset($c[4])): ?><small class="text-muted"><?= $c[4] ?></small><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="pagina" value="financeiro/contas">
                <div class="col-md-2">
                    <label for="inicio" class="form-label small text-muted mb-1">Vencimento de</label>
                    <input type="date" name="inicio" id="inicio" class="form-control" value="<?= $f_inicio ?>">
                </div>
                <div class="col-md-2">
                    <label for="fim" class="form-label small text-muted mb-1">até</label>
                    <input type="date" name="fim" id="fim" class="form-control" value="<?= $f_fim ?>">
                </div>
                <div class="col-md-2">
                    <label for="situacao" class="form-label small text-muted mb-1">Situação</label>
                    <select name="situacao" id="situacao" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach (['pendente'=>'Pendente','vencida'=>'Vencida','paga'=>'Paga','cancelada'=>'Cancelada'] as $v => $r): ?>
                            <option value="<?= $v ?>" <?= $f_situacao === $v ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="id_categoria" class="form-label small text-muted mb-1">Categoria</label>
                    <select name="id_categoria" id="id_categoria" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id_categoria'] ?>" <?= $f_categoria === (int) $c['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="favorecido" class="form-label small text-muted mb-1">Favorecido / descrição</label>
                    <input type="text" name="favorecido" id="favorecido" class="form-control" value="<?= htmlspecialchars($f_favorec) ?>">
                </div>

                <div class="col-md-3">
                    <label for="id_centro" class="form-label small text-muted mb-1">Centro de custo</label>
                    <select name="id_centro" id="id_centro" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($centros as $c): ?>
                            <option value="<?= (int) $c['id_centro'] ?>" <?= $f_centro === (int) $c['id_centro'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="forma" class="form-label small text-muted mb-1">Forma</label>
                    <select name="forma" id="forma" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach (FINANCEIRO_FORMAS as $v => $r): ?>
                            <option value="<?= $v ?>" <?= $f_forma === $v ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="valor_min" class="form-label small text-muted mb-1">Valor mín.</label>
                    <input type="text" name="valor_min" id="valor_min" class="form-control" inputmode="decimal"
                           value="<?= $f_min !== null ? financeiroNum($f_min) : '' ?>">
                </div>
                <div class="col-md-2">
                    <label for="valor_max" class="form-label small text-muted mb-1">Valor máx.</label>
                    <input type="text" name="valor_max" id="valor_max" class="form-control" inputmode="decimal"
                           value="<?= $f_max !== null ? financeiroNum($f_max) : '' ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1" aria-hidden="true"></i> Filtrar
                    </button>
                    <a href="<?= $url_base ?>/financeiro/contas" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Parcelas -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="h6 fw-semibold mb-0">
                <i class="bi bi-list-ul me-2" aria-hidden="true"></i>
                Parcelas no período (<?= count($parcelas) ?>)
            </h3>
            <span class="small text-muted">
                Total filtrado: <strong style="color: var(--color-accent);"><?= financeiroMoeda($total_filtrado) ?></strong>
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (!$parcelas): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                    <h4 class="h6 text-muted mb-1">Nenhuma parcela no período</h4>
                    <p class="text-muted mb-0">Ajuste os filtros ou cadastre uma despesa.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="contasTable" class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Vencimento</th>
                                <th>Despesa</th>
                                <th>Categoria</th>
                                <th>Favorecido</th>
                                <th>Centro</th>
                                <th class="text-center">Parcela</th>
                                <th class="text-end">Valor</th>
                                <th class="text-end">Pago</th>
                                <th>Situação</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($parcelas as $p):
                            $s = financeiroSituacaoParcela($p);
                        ?>
                            <tr>
                                <td class="ps-3" data-order="<?= strtotime($p['data_vencimento']) ?>">
                                    <?= date('d/m/Y', strtotime($p['data_vencimento'])) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['descricao']) ?>
                                    <?php if ($p['recorrente'] === true || $p['recorrente'] === 't'): ?>
                                        <i class="bi bi-arrow-repeat text-muted ms-1" title="Despesa recorrente" aria-label="recorrente"></i>
                                    <?php endif; ?>
                                    <small class="text-muted d-block">#<?= (int) $p['id_conta'] ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['categoria']) ?>
                                    <?php if ($p['categoria_tipo'] === 'funcionario' && $p['funcionario']): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($p['funcionario']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-truncate" style="max-width:150px;"><?= htmlspecialchars($p['favorecido'] ?? '—') ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($p['centro'] ?? '—') ?></small></td>
                                <td class="text-center"><?= (int) $p['numero_parcela'] ?>/<?= (int) $p['total_parcelas'] ?></td>
                                <td class="text-end fw-semibold" data-order="<?= (float) $p['valor'] ?>"><?= financeiroMoeda((float) $p['valor']) ?></td>
                                <td class="text-end">
                                    <?php if ($p['valor_pago'] !== null): ?>
                                        <?= financeiroMoeda((float) $p['valor_pago']) ?>
                                        <small class="text-muted d-block">
                                            <?= date('d/m/Y', strtotime($p['data_pagamento'])) ?>
                                            <?= $p['forma_pagamento'] ? ' · ' . (FINANCEIRO_FORMAS[$p['forma_pagamento']] ?? '') : '' ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $s['classe'] ?>">
                                        <i class="bi <?= $s['icone'] ?> me-1" aria-hidden="true"></i><?= $s['rotulo'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    <?php if ($pode_pagar && $s['chave'] !== 'paga' && $s['chave'] !== 'cancelada'): ?>
                                        <button type="button" class="btn btn-sm btn-primary btn-pagar"
                                                data-parcela='<?= htmlspecialchars(json_encode([
                                                    'id' => (int) $p['id_parcela'],
                                                    'descricao' => $p['descricao'],
                                                    'numero' => (int) $p['numero_parcela'],
                                                    'total' => (int) $p['total_parcelas'],
                                                    'valor' => financeiroNum((float) $p['valor']),
                                                    'forma' => $p['forma_pagamento'],
                                                ]), ENT_QUOTES) ?>'>
                                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Pagar
                                        </button>
                                    <?php elseif ($pode_pagar && $s['chave'] === 'paga'): ?>
                                        <form method="POST" action="<?= $acoes ?>" class="d-inline"
                                              onsubmit="return confirm('Estornar este pagamento? A parcela volta a pendente.');">
                                            <input type="hidden" name="acao" value="estornar">
                                            <input type="hidden" name="id_parcela" value="<?= (int) $p['id_parcela'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Estornar pagamento">
                                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($pode_cancelar && $p['status_conta'] === 'ativa'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-cancelar"
                                                data-conta="<?= (int) $p['id_conta'] ?>"
                                                data-descricao="<?= htmlspecialchars($p['descricao'], ENT_QUOTES) ?>"
                                                title="Cancelar despesa inteira">
                                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>
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

<?php if ($pode_gerenciar): ?>
<!-- Nova despesa -->
<div class="modal fade" id="modalDespesa" tabindex="-1" aria-labelledby="tituloDespesa" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= $acoes ?>">
                <input type="hidden" name="acao" value="salvar">

                <div class="modal-header">
                    <h5 class="modal-title" id="tituloDespesa">
                        <i class="bi bi-receipt me-2" aria-hidden="true"></i>Nova despesa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                            <input type="text" name="descricao" id="descricao" class="form-control" maxlength="150" required
                                   placeholder="Ex: Energia elétrica — setembro">
                        </div>

                        <div class="col-md-6">
                            <label for="cat_form" class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select name="id_categoria" id="cat_form" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int) $c['id_categoria'] ?>" data-tipo="<?= $c['tipo'] ?>">
                                        <?= htmlspecialchars($c['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="centro_form" class="form-label">Centro de custo</label>
                            <select name="id_centro" id="centro_form" class="form-select">
                                <option value="">Não informado</option>
                                <?php foreach ($centros as $c): ?>
                                    <option value="<?= (int) $c['id_centro'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="favorecido_form" class="form-label">Favorecido</label>
                            <input type="text" name="favorecido" id="favorecido_form" class="form-control" maxlength="150"
                                   placeholder="Quem recebe">
                            <small class="form-text text-muted">
                                Texto livre: o cadastro de fornecedores deste sistema é quem compra de você, papel diferente.
                            </small>
                        </div>

                        <div class="col-md-6" id="bloco-funcionario" hidden>
                            <label for="func_form" class="form-label">Funcionário</label>
                            <select name="id_usuario_func" id="func_form" class="form-select">
                                <option value="">Não vincular</option>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= (int) $u['id_usuario'] ?>"><?= htmlspecialchars($u['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Registra o custo, não substitui folha de pagamento.</small>
                        </div>

                        <div class="col-md-4">
                            <label for="valor_form" class="form-label">Valor total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" name="valor_total" id="valor_form" class="form-control"
                                       inputmode="decimal" required placeholder="0,00">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="emissao_form" class="form-label">Data da despesa <span class="text-danger">*</span></label>
                            <input type="date" name="data_emissao" id="emissao_form" class="form-control"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="forma_form" class="form-label">Forma de pagamento</label>
                            <select name="forma_pagamento" id="forma_form" class="form-select">
                                <option value="">Não definida</option>
                                <?php foreach (FINANCEIRO_FORMAS as $v => $r): ?>
                                    <option value="<?= $v ?>"><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-semibold"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i>Parcelamento</h6>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="parcelas_form" class="form-label">Parcelas</label>
                            <input type="number" name="parcelas" id="parcelas_form" class="form-control"
                                   min="1" max="120" value="1">
                        </div>
                        <div class="col-md-4">
                            <label for="periodo_form" class="form-label">Intervalo</label>
                            <select name="periodo_parcelas" id="periodo_form" class="form-select">
                                <?php foreach (FINANCEIRO_PERIODOS as $v => $r): ?>
                                    <option value="<?= $v ?>" <?= $v === 'mensal' ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="vencimento_form" class="form-label">1º vencimento <span class="text-danger">*</span></label>
                            <input type="date" name="primeiro_vencimento" id="vencimento_form" class="form-control"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0" id="previa-parcelas"></p>

                    <hr>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="recorrente" id="recorrente_form" value="1">
                        <label class="form-check-label" for="recorrente_form">
                            Despesa recorrente
                            <small class="text-muted d-block">
                                Registra a periodicidade. A geração automática das próximas contas é etapa seguinte.
                            </small>
                        </label>
                    </div>

                    <div class="row g-3" id="bloco-recorrencia" hidden>
                        <div class="col-md-3">
                            <label for="rec_periodo" class="form-label">Periodicidade</label>
                            <select name="recorrencia_periodo" id="rec_periodo" class="form-select">
                                <?php foreach (FINANCEIRO_PERIODOS as $v => $r): ?>
                                    <option value="<?= $v ?>" <?= $v === 'mensal' ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="rec_dia" class="form-label">Dia do vencimento</label>
                            <input type="number" name="recorrencia_dia" id="rec_dia" class="form-control" min="1" max="31">
                        </div>
                        <div class="col-md-3">
                            <label for="rec_inicio" class="form-label">Início</label>
                            <input type="date" name="recorrencia_inicio" id="rec_inicio" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="rec_fim" class="form-label">Fim</label>
                            <input type="date" name="recorrencia_fim" id="rec_fim" class="form-control">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="obs_form" class="form-label">Observações</label>
                        <input type="text" name="observacoes" id="obs_form" class="form-control" maxlength="255">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar despesa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($pode_pagar): ?>
<!-- Registrar pagamento -->
<div class="modal fade" id="modalPagamento" tabindex="-1" aria-labelledby="tituloPagamento" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= $acoes ?>">
                <input type="hidden" name="acao" value="pagar">
                <input type="hidden" name="id_parcela" id="pg-parcela">

                <div class="modal-header">
                    <h5 class="modal-title" id="tituloPagamento">Registrar pagamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small mb-3" id="pg-descricao"></p>

                    <div class="mb-3">
                        <label for="pg-data" class="form-label">Data do pagamento <span class="text-danger">*</span></label>
                        <input type="date" name="data_pagamento" id="pg-data" class="form-control"
                               value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="pg-valor" class="form-label">Valor pago <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" name="valor_pago" id="pg-valor" class="form-control" inputmode="decimal" required>
                        </div>
                        <small class="form-text text-muted">
                            Pode diferir do previsto (juros, desconto); a diferença fica registrada.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="pg-forma" class="form-label">Forma <span class="text-danger">*</span></label>
                        <select name="forma_pagamento" id="pg-forma" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach (FINANCEIRO_FORMAS as $v => $r): ?>
                                <option value="<?= $v ?>"><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="pg-obs" class="form-label">Observação</label>
                        <input type="text" name="observacao" id="pg-obs" class="form-control" maxlength="255">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar pagamento</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($pode_cancelar): ?>
<div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= $acoes ?>">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id_conta" id="cn-conta">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar despesa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Cancelar <strong id="cn-descricao"></strong> e todas as parcelas <em>pendentes</em>?</p>
                    <div class="alert alert-light border small">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        Parcelas já pagas são mantidas: o dinheiro saiu de verdade e apagar isso falsearia o histórico.
                    </div>
                    <div class="mb-2">
                        <label for="cn-motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                        <input type="text" name="motivo" id="cn-motivo" class="form-control" maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                    <button type="submit" class="btn btn-danger">Cancelar despesa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(function () {
    if ($('#contasTable').length) {
        $('#contasTable').DataTable({
            "autoWidth": false,
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
            "pageLength": 25,
            "order": [[0, "asc"]],
            "columnDefs": [{ "orderable": false, "targets": [9] }]
        });
    }

    $('#id_categoria, #id_centro, #situacao, #forma, #cat_form, #centro_form, #func_form')
        .select2({ width: '100%', language: 'pt-BR', dropdownParent: $('#modalDespesa').length ? $('#modalDespesa') : undefined });

    // Categoria do tipo "funcionario" revela o vínculo com o usuário.
    $('#cat_form').on('change', function () {
        const opcao = this.options[this.selectedIndex];
        const eFuncionario = opcao && opcao.getAttribute('data-tipo') === 'funcionario';
        document.getElementById('bloco-funcionario').hidden = !eFuncionario;
    });

    $('#recorrente_form').on('change', function () {
        document.getElementById('bloco-recorrencia').hidden = !this.checked;
    });

    // Prévia do parcelamento: confere o valor antes de salvar.
    function previaParcelas() {
        const bruto = ($('#valor_form').val() || '').replace(/\./g, '').replace(',', '.');
        const total = parseFloat(bruto);
        const n = parseInt($('#parcelas_form').val(), 10) || 1;
        const alvo = document.getElementById('previa-parcelas');

        if (!total || total <= 0 || n < 1) { alvo.textContent = ''; return; }

        const centavos = Math.round(total * 100);
        const base = Math.floor(centavos / n);
        const sobra = centavos - base * n;
        const fmt = v => (v / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 });

        alvo.textContent = n === 1
            ? 'Parcela única de R$ ' + fmt(centavos)
            : n + 'x de R$ ' + fmt(base) + (sobra > 0 ? ' (a 1ª fica R$ ' + fmt(base + sobra) + ')' : '');
    }
    $('#valor_form, #parcelas_form').on('input', previaParcelas);

    $('.btn-pagar').on('click', function () {
        const p = $(this).data('parcela');
        $('#pg-parcela').val(p.id);
        $('#pg-valor').val(p.valor);
        $('#pg-forma').val(p.forma || '');
        $('#pg-descricao').text(p.descricao + ' — parcela ' + p.numero + '/' + p.total);
        new bootstrap.Modal(document.getElementById('modalPagamento')).show();
    });

    $('.btn-cancelar').on('click', function () {
        $('#cn-conta').val($(this).data('conta'));
        $('#cn-descricao').text($(this).data('descricao'));
        new bootstrap.Modal(document.getElementById('modalCancelar')).show();
    });
});
</script>
