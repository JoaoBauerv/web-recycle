<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

require_once __DIR__ . '/../../components/permissoes.php';
require_once __DIR__ . '/../../functions/financeiro/financeiro_lib.php';
require_once __DIR__ . '/../../functions/financeiro/margem_query.php';

exigirPermissao('financeiro.relatorios', $url_base);

$inicio = financeiroData($_GET['inicio'] ?? '') ?? date('Y-m-01');
$fim    = financeiroData($_GET['fim'] ?? '') ?? date('Y-m-t');
if ($inicio > $fim) { [$inicio, $fim] = [$fim, $inicio]; }

$p = [':inicio' => $inicio, ':fim' => $fim];
$situacao = financeiroSituacaoSql('pa');

// Só despesas ativas entram no relatório: conta cancelada não é despesa.
$base = "FROM contas_pagar_parcelas pa
         JOIN contas_pagar c ON c.id_conta = pa.id_conta
         WHERE c.status = 'ativa' AND pa.status <> 'cancelada'
           AND pa.data_vencimento BETWEEN :inicio AND :fim";

$resumo = $pdo->prepare("SELECT
      COALESCE(SUM(pa.valor), 0)                                                    AS total,
      COALESCE(SUM(pa.valor_pago) FILTER (WHERE pa.status = 'paga'), 0)             AS pago,
      COALESCE(SUM(pa.valor) FILTER (WHERE $situacao = 'pendente'), 0)              AS pendente,
      COALESCE(SUM(pa.valor) FILTER (WHERE $situacao = 'vencida'), 0)               AS vencido,
      COUNT(*)                                                                      AS parcelas,
      COUNT(DISTINCT c.id_conta)                                                    AS despesas
    $base");
$resumo->execute($p);
$resumo = $resumo->fetch(PDO::FETCH_ASSOC);

$por_categoria = $pdo->prepare("SELECT cat.nome, cat.tipo,
         COALESCE(SUM(pa.valor), 0) AS total,
         COALESCE(SUM(pa.valor_pago) FILTER (WHERE pa.status='paga'), 0) AS pago,
         COUNT(*) AS parcelas
    FROM contas_pagar_parcelas pa
    JOIN contas_pagar c ON c.id_conta = pa.id_conta
    JOIN despesa_categoria cat ON cat.id_categoria = c.id_categoria
    WHERE c.status = 'ativa' AND pa.status <> 'cancelada'
      AND pa.data_vencimento BETWEEN :inicio AND :fim
    GROUP BY cat.nome, cat.tipo
    ORDER BY total DESC");
$por_categoria->execute($p);
$por_categoria = $por_categoria->fetchAll(PDO::FETCH_ASSOC);

$por_centro = $pdo->prepare("SELECT COALESCE(cc.nome, 'Sem centro') AS nome,
         COALESCE(SUM(pa.valor), 0) AS total, COUNT(*) AS parcelas
    FROM contas_pagar_parcelas pa
    JOIN contas_pagar c ON c.id_conta = pa.id_conta
    LEFT JOIN centro_custo cc ON cc.id_centro = c.id_centro
    WHERE c.status = 'ativa' AND pa.status <> 'cancelada'
      AND pa.data_vencimento BETWEEN :inicio AND :fim
    GROUP BY 1 ORDER BY total DESC");
$por_centro->execute($p);
$por_centro = $por_centro->fetchAll(PDO::FETCH_ASSOC);

$por_mes = $pdo->prepare("SELECT to_char(pa.data_vencimento, 'YYYY-MM') AS mes,
         COALESCE(SUM(pa.valor), 0) AS total,
         COALESCE(SUM(pa.valor_pago) FILTER (WHERE pa.status='paga'), 0) AS pago
    FROM contas_pagar_parcelas pa
    JOIN contas_pagar c ON c.id_conta = pa.id_conta
    WHERE c.status = 'ativa' AND pa.status <> 'cancelada'
      AND pa.data_vencimento BETWEEN :inicio AND :fim
    GROUP BY 1 ORDER BY 1");
$por_mes->execute($p);
$por_mes = $por_mes->fetchAll(PDO::FETCH_ASSOC);

$por_favorecido = $pdo->prepare("SELECT COALESCE(NULLIF(c.favorecido, ''), u.nome, '(não informado)') AS nome,
         COALESCE(SUM(pa.valor), 0) AS total, COUNT(*) AS parcelas
    FROM contas_pagar_parcelas pa
    JOIN contas_pagar c ON c.id_conta = pa.id_conta
    LEFT JOIN tb_usuario u ON u.id_usuario = c.id_usuario_func
    WHERE c.status = 'ativa' AND pa.status <> 'cancelada'
      AND pa.data_vencimento BETWEEN :inicio AND :fim
    GROUP BY 1 ORDER BY total DESC LIMIT 15");
$por_favorecido->execute($p);
$por_favorecido = $por_favorecido->fetchAll(PDO::FETCH_ASSOC);

$funcionarios = $pdo->prepare("SELECT COALESCE(SUM(pa.valor), 0) AS total, COUNT(DISTINCT c.id_conta) AS despesas
    FROM contas_pagar_parcelas pa
    JOIN contas_pagar c ON c.id_conta = pa.id_conta
    JOIN despesa_categoria cat ON cat.id_categoria = c.id_categoria
    WHERE c.status = 'ativa' AND pa.status <> 'cancelada'
      AND cat.tipo = 'funcionario'
      AND pa.data_vencimento BETWEEN :inicio AND :fim");
$funcionarios->execute($p);
$funcionarios = $funcionarios->fetch(PDO::FETCH_ASSOC);

// Resultado operacional: margem dos materiais MENOS despesas do mesmo período.
$analise = margemAnalise($pdo, ['inicio' => $inicio, 'fim' => $fim, 'id_material' => null, 'tipo' => '']);
$m = $analise['totais'];
$despesas_periodo = (float) $resumo['total'];
$resultado = $m['margem'] - $despesas_periodo;

$total_geral = (float) $resumo['total'];
?>

<div class="container-fluid py-4" style="max-width: 1400px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="<?= $url_base ?>/financeiro/contas" class="text-decoration-none">Contas a Pagar</a></li>
                    <li class="breadcrumb-item active">Relatório</li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold">
                <i class="bi bi-bar-chart-line me-2" style="color: var(--color-accent);" aria-hidden="true"></i>Relatório Financeiro
            </h2>
            <small class="text-muted"><?= date('d/m/Y', strtotime($inicio)) ?> a <?= date('d/m/Y', strtotime($fim)) ?></small>
        </div>
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <input type="hidden" name="pagina" value="financeiro/relatorio">
            <div>
                <label for="inicio" class="form-label small text-muted mb-1">De</label>
                <input type="date" name="inicio" id="inicio" class="form-control" value="<?= $inicio ?>">
            </div>
            <div>
                <label for="fim" class="form-label small text-muted mb-1">Até</label>
                <input type="date" name="fim" id="fim" class="form-control" value="<?= $fim ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i> Filtrar</button>
        </form>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <!-- Resumo das despesas -->
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Total de despesas', (float) $resumo['total'], 'bi-wallet2', '', (int) $resumo['despesas'] . ' despesa(s)'],
            ['Pago', (float) $resumo['pago'], 'bi-check2-circle', 'var(--color-accent)', ''],
            ['Pendente', (float) $resumo['pendente'], 'bi-clock', '', ''],
            ['Vencido', (float) $resumo['vencido'], 'bi-exclamation-octagon', (float) $resumo['vencido'] > 0 ? '#dc2626' : '', ''],
            ['Com funcionários', (float) $funcionarios['total'], 'bi-people', '', (int) $funcionarios['despesas'] . ' despesa(s)'],
        ] as $c): ?>
            <div class="col-xl col-md-4 col-6">
                <div class="card card-indicador h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="indicador-icone"><i class="bi <?= $c[2] ?>" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <span class="indicador-rotulo"><?= $c[0] ?></span>
                            <span class="indicador-valor" style="font-size:1.05rem;<?= $c[3] ? 'color:' . $c[3] . ';' : '' ?>">
                                <?= financeiroMoeda($c[1]) ?>
                            </span>
                            <?php if ($c[4]): ?><small class="text-muted"><?= $c[4] ?></small><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Resultado operacional -->
    <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid var(--color-accent) !important;">
        <div class="card-header bg-white border-bottom">
            <h3 class="h6 fw-semibold mb-0"><i class="bi bi-calculator me-2" aria-hidden="true"></i>Resultado do período</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="max-width:520px;">
                    <tbody>
                        <tr>
                            <td>Receita das vendas</td>
                            <td class="text-end fw-semibold"><?= financeiroMoeda($m['receita']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">− Custo dos materiais vendidos (CMV)</td>
                            <td class="text-end text-muted">− <?= financeiroMoeda($m['cmv']) ?></td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-semibold">= Margem bruta dos materiais</td>
                            <td class="text-end fw-semibold" style="color: var(--color-accent);"><?= financeiroMoeda($m['margem']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">− Despesas operacionais do período</td>
                            <td class="text-end text-muted">− <?= financeiroMoeda($despesas_periodo) ?></td>
                        </tr>
                        <tr class="border-top border-2">
                            <td class="fw-bold">= Resultado operacional</td>
                            <td class="text-end fw-bold fs-5 <?= $resultado < 0 ? 'text-danger' : '' ?>"
                                style="<?= $resultado >= 0 ? 'color: var(--color-accent);' : '' ?>">
                                <?= financeiroMoeda($resultado) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-light border small mt-3 mb-0" role="alert">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                <strong>Margem dos materiais</strong> e <strong>resultado após despesas</strong> são coisas diferentes.
                A margem por material mede a rentabilidade do que se compra e vende; as despesas gerais
                (aluguel, folha, energia) não entram no custo de nenhum material e só aparecem aqui.
                As despesas contam pelo <em>vencimento</em> no período, não pelo pagamento.
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Por categoria -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0">Despesas por categoria</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!$por_categoria): ?>
                        <p class="text-muted text-center py-4 mb-0">Nenhuma despesa no período.</p>
                    <?php else: ?>
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="ps-3">Categoria</th><th class="text-end">Total</th>
                                    <th class="text-end">Pago</th><th class="pe-3" style="width:110px;">%</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($por_categoria as $c):
                                $pct = $total_geral > 0 ? ((float) $c['total'] / $total_geral) * 100 : 0; ?>
                                <tr>
                                    <td class="ps-3">
                                        <?= htmlspecialchars($c['nome']) ?>
                                        <?php if ($c['tipo'] === 'funcionario'): ?>
                                            <i class="bi bi-person text-muted ms-1" title="Funcionários" aria-hidden="true"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-semibold"><?= financeiroMoeda((float) $c['total']) ?></td>
                                    <td class="text-end text-muted"><?= financeiroMoeda((float) $c['pago']) ?></td>
                                    <td class="pe-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:6px;">
                                                <div class="progress-bar" style="width:<?= $pct ?>%; background-color: var(--color-accent);"></div>
                                            </div>
                                            <small class="text-muted"><?= financeiroNum($pct, 1) ?>%</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Por centro de custo -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0">Despesas por centro de custo</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!$por_centro): ?>
                        <p class="text-muted text-center py-4 mb-0">Nenhuma despesa no período.</p>
                    <?php else: ?>
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="ps-3">Centro</th><th class="text-end">Parcelas</th><th class="text-end pe-3">Total</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($por_centro as $c): ?>
                                <tr>
                                    <td class="ps-3"><?= htmlspecialchars($c['nome']) ?></td>
                                    <td class="text-end text-muted"><?= (int) $c['parcelas'] ?></td>
                                    <td class="text-end pe-3 fw-semibold"><?= financeiroMoeda((float) $c['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Por mês -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0">Despesas por mês de vencimento</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!$por_mes): ?>
                        <p class="text-muted text-center py-4 mb-0">Nenhuma despesa no período.</p>
                    <?php else: ?>
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="ps-3">Mês</th><th class="text-end">Total</th><th class="text-end pe-3">Pago</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($por_mes as $mes): ?>
                                <tr>
                                    <td class="ps-3"><?= date('m/Y', strtotime($mes['mes'] . '-01')) ?></td>
                                    <td class="text-end fw-semibold"><?= financeiroMoeda((float) $mes['total']) ?></td>
                                    <td class="text-end pe-3 text-muted"><?= financeiroMoeda((float) $mes['pago']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Por favorecido -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h3 class="h6 fw-semibold mb-0">Maiores favorecidos</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!$por_favorecido): ?>
                        <p class="text-muted text-center py-4 mb-0">Nenhuma despesa no período.</p>
                    <?php else: ?>
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="ps-3">Favorecido</th><th class="text-end">Parcelas</th><th class="text-end pe-3">Total</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($por_favorecido as $f): ?>
                                <tr>
                                    <td class="ps-3 text-truncate" style="max-width:220px;"><?= htmlspecialchars($f['nome']) ?></td>
                                    <td class="text-end text-muted"><?= (int) $f['parcelas'] ?></td>
                                    <td class="text-end pe-3 fw-semibold"><?= financeiroMoeda((float) $f['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
