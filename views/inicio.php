<?php
/**
 * Painel inicial. Todos os números vêm de consultas às tabelas existentes —
 * nenhum valor é fixo ou fictício.
 */
$logado = !empty($_SESSION['logado']);
$eh_admin = ($dados_usuario['permissao'] ?? '') === 'Admin';

if ($logado) {
    $inicio_mes = date('Y-m-01');
    $fim_mes    = date('Y-m-d', strtotime($inicio_mes . ' +1 month'));

    $stmt = $pdo->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(total_valor), 0) AS valor, COALESCE(SUM(total_peso), 0) AS peso
                           FROM tb_pesagem
                           WHERE total_valor > 0 AND data_pesagem >= :inicio AND data_pesagem < :fim");
    $stmt->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes]);
    $compras_mes = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(total_valor), 0) AS valor
                           FROM vendas
                           WHERE data_venda >= :inicio AND data_venda < :fim");
    $stmt->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes]);
    $vendas_mes = $stmt->fetch(PDO::FETCH_ASSOC);

    $estoque_total    = (float) $pdo->query("SELECT COALESCE(SUM(qt_estoque), 0) FROM tb_material WHERE status = 1")->fetchColumn();
    $total_clientes   = (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE status = 1")->fetchColumn();
    $total_materiais  = (int) $pdo->query("SELECT COUNT(*) FROM tb_material WHERE status = 1")->fetchColumn();

    $mes_referencia = strtr(date('F/Y'), [
        'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março', 'April' => 'Abril',
        'May' => 'Maio', 'June' => 'Junho', 'July' => 'Julho', 'August' => 'Agosto',
        'September' => 'Setembro', 'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro',
    ]);
}

function inicioMoeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>

<?php if (!$logado): ?>

    <div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center py-5">
        <div class="card border-0 shadow-sm p-4 p-md-5 text-center" style="max-width: 520px;">
            <i class="bi bi-recycle display-4 mb-3" style="color: var(--color-accent);" aria-hidden="true"></i>
            <h1 class="h3 fw-bold mb-2"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Sistema de Reciclagem') ?></h1>
            <p class="text-muted mb-4">Entre com sua conta para acessar o sistema.</p>
            <a href="<?= $url_base ?>/views/user/login.php" class="btn btn-primary btn-lg">
                <i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i> Entrar
            </a>
        </div>
    </div>

<?php else: ?>

    <div class="container-fluid py-4" style="max-width: 1400px;">

        <div class="pagina-cabecalho">
            <h2 class="fw-bold">Olá, <?= htmlspecialchars(explode(' ', trim($dados_usuario['nome'] ?? 'Usuário'))[0]) ?></h2>
            <p>Resumo de <?= htmlspecialchars($mes_referencia) ?></p>
        </div>

        <?php require_once __DIR__ . '/../components/alert.php'; ?>

        <!-- Indicadores do mês -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card card-indicador h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="indicador-icone"><i class="bi bi-cart-plus" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <span class="indicador-rotulo">Compras no mês</span>
                            <span class="indicador-valor"><?= inicioMoeda((float) $compras_mes['valor']) ?></span>
                            <small class="text-muted"><?= (int) $compras_mes['qtd'] ?> compra(s) · <?= number_format((float) $compras_mes['peso'], 2, ',', '.') ?> kg</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-indicador h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="indicador-icone"><i class="bi bi-cart-check" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <span class="indicador-rotulo">Vendas no mês</span>
                            <span class="indicador-valor"><?= inicioMoeda((float) $vendas_mes['valor']) ?></span>
                            <small class="text-muted"><?= (int) $vendas_mes['qtd'] ?> venda(s)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-indicador h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="indicador-icone"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <span class="indicador-rotulo">Estoque atual</span>
                            <span class="indicador-valor"><?= number_format($estoque_total, 2, ',', '.') ?> kg</span>
                            <small class="text-muted"><?= $total_materiais ?> material(is) ativo(s)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-indicador h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <span class="indicador-icone"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
                        <div class="min-w-0">
                            <span class="indicador-rotulo">Clientes ativos</span>
                            <span class="indicador-valor"><?= $total_clientes ?></span>
                            <small class="text-muted">cadastrados no sistema</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Atalhos -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-lightning-charge me-2" aria-hidden="true"></i>Atalhos</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4 col-sm-6">
                        <a href="<?= $url_base ?>/compras" class="btn btn-outline-secondary w-100 text-start py-3">
                            <i class="bi bi-cart-plus me-2" aria-hidden="true"></i> Nova compra
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="<?= $url_base ?>/vendas" class="btn btn-outline-secondary w-100 text-start py-3">
                            <i class="bi bi-cart-check me-2" aria-hidden="true"></i> Nova venda
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="<?= $url_base ?>/compras/relatorio" class="btn btn-outline-secondary w-100 text-start py-3">
                            <i class="bi bi-bar-chart-line me-2" aria-hidden="true"></i> Relatório de compras
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="<?= $url_base ?>/clientes" class="btn btn-outline-secondary w-100 text-start py-3">
                            <i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i> Clientes
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <a href="<?= $url_base ?>/materiais" class="btn btn-outline-secondary w-100 text-start py-3">
                            <i class="bi bi-box-seam me-2" aria-hidden="true"></i> Materiais
                        </a>
                    </div>
                    <?php if ($eh_admin): ?>
                        <div class="col-md-4 col-sm-6">
                            <a href="<?= $url_base ?>/usuarios" class="btn btn-outline-secondary w-100 text-start py-3">
                                <i class="bi bi-people me-2" aria-hidden="true"></i> Usuários
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

<?php endif; ?>
