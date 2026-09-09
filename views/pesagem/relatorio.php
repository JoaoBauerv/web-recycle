<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-9">
            
            <?php
            include 'inc_header_relatorios.php';
            ?>


            <?php
            // Pega o mês atual ou o mês selecionado
            $mesAtual = $_REQUEST['mes'] ?? date('Y-m');

            list($ano, $mes) = explode('-', $mesAtual);

            $data_inicio = $ano . '-' . $mes . '-01';

            $data_fim = date(
                'Y-m-d',
                strtotime($data_inicio . ' +1 month')
            );
            

            $sql = "SELECT SUM(total_valor) as total
                    FROM tb_pesagem
                    WHERE total_valor > 0
                    AND data_pesagem >= :data_inicio
                    AND data_pesagem < :data_fim";

            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':data_inicio', $data_inicio);
            $stmt->bindParam(':data_fim', $data_fim);

            $stmt->execute();

            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            var_dump($linha);
            ?>

            <div class="row justify-content-end">
                <div class="col-md-4">
                    <form action="<?= $url_base ?>/balanca/relatorio?tipo=<?= $_REQUEST['tipo'] ?>" enctype="multipart/form-data" method="post">
                        <select class="form-control" name="mes" onchange="this.form.submit()">
                            <?php
                            // Gera os últimos 12 meses
                            for ($i = 0; $i < 12; $i++) {
                                $data = date('Y-m', strtotime("-$i months"));
                                $mesNome = ucfirst(strftime('%B/%Y', strtotime($data . '-01')));
                                // Fallback para PHP 8.1+ onde strftime foi removido
                                $mesNome = ucfirst(date('F/Y', strtotime($data . '-01')));
                                
                                // Tradução manual dos meses
                                $meses = [
                                    'January' => 'Janeiro',
                                    'February' => 'Fevereiro',
                                    'March' => 'Março',
                                    'April' => 'Abril',
                                    'May' => 'Maio',
                                    'June' => 'Junho',
                                    'July' => 'Julho',
                                    'August' => 'Agosto',
                                    'September' => 'Setembro',
                                    'October' => 'Outubro',
                                    'November' => 'Novembro',
                                    'December' => 'Dezembro'
                                ];
                                
                                $mesNome = str_replace(array_keys($meses), array_values($meses), $mesNome);
                                $selected = ($data == $mesAtual) ? 'selected' : '';
                            ?>
                                <option value="<?= $data ?>" <?= $selected ?>><?= $mesNome ?></option>
                            <?php } ?>
                        </select>
                    </form>
                </div>
            </div>

<?php
// Totais do mês
$totalVendas  = $dados['total_vendas'] ?? 0;
$totalCompras = $dados['total_compras'] ?? 0;

// cálculo automático
$lucro = $totalVendas - $totalCompras;

// valores do mês anterior (para comparação)
$vendasAnterior  = $dados['vendas_anterior'] ?? 0;
$comprasAnterior = $dados['compras_anterior'] ?? 0;

// função percentual
function calcularPercentual($atual, $anterior) {
    if ($anterior == 0) return 0;
    return (($atual - $anterior) / $anterior) * 100;
}

$percVendas  = calcularPercentual($totalVendas, $vendasAnterior);
$percCompras = calcularPercentual($totalCompras, $comprasAnterior);
$percLucro   = calcularPercentual($lucro, ($vendasAnterior - $comprasAnterior));

// função moeda BR
function moeda($valor){
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>
<div role="main" class="dashboard-relatorio">

    <!-- HEADER -->
    <div class="dashboard-header mb-4">
        <div>
            <h3 class="mb-0 fw-bold">Relatório Financeiro</h3>
            <small class="text-muted">
                Período selecionado: <?= date('m/Y', strtotime($data_inicio)) ?>
            </small>
        </div>
    </div>

    <!-- CARDS -->
    <div class="row g-4 mb-4">

        <!-- VENDAS -->
        <div class="col-md-4">
            <div class="card card-financeiro vendas">
                <div class="card-body">
                    <div class="titulo">Total de Vendas</div>
                    <div class="valor"><?= moeda($linha['total']) ?></div>

                    <div class="variacao <?= $percVendas >= 0 ? 'positivo':'negativo' ?>">
                        <i class="bi <?= $percVendas >= 0 ? 'bi-arrow-up':'bi-arrow-down' ?>"></i>
                        <?= number_format($percVendas,1,',','.') ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- COMPRAS -->
        <div class="col-md-4">
            <div class="card card-financeiro compras">
                <div class="card-body">
                    <div class="titulo">Total de Compras</div>
                    <div class="valor"><?= moeda($totalCompras) ?></div>

                    <div class="variacao <?= $percCompras >= 0 ? 'positivo':'negativo' ?>">
                        <i class="bi <?= $percCompras >= 0 ? 'bi-arrow-up':'bi-arrow-down' ?>"></i>
                        <?= number_format($percCompras,1,',','.') ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- LUCRO -->
        <div class="col-md-4">
            <div class="card card-financeiro lucro">
                <div class="card-body">
                    <div class="titulo">Margem Bruta</div>
                    <div class="valor"><?= moeda($lucro) ?></div>

                    <div class="variacao <?= $percLucro >= 0 ? 'positivo':'negativo' ?>">
                        <i class="bi <?= $percLucro >= 0 ? 'bi-arrow-up':'bi-arrow-down' ?>"></i>
                        <?= number_format($percLucro,1,',','.') ?>%
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- TABELA -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">
            Detalhamento por Categoria
        </div>

        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Vendas</th>
                        <th>Compras</th>
                        <th>Margem</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach($categorias as $cat):

                    $margem = $cat['vendas'] > 0
                        ? (($cat['vendas'] - $cat['compras']) / $cat['vendas']) * 100
                        : 0;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['categoria']) ?></td>
                        <td><?= moeda($cat['vendas']) ?></td>
                        <td><?= moeda($cat['compras']) ?></td>
                        <td>
                            <span class="badge <?= $margem >= 40 ? 'bg-success':'bg-warning' ?>">
                                <?= number_format($margem,1,',','.') ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>

</div>


          
            </div>
        </div>
    </div>
</div>






<!-- Estilos customizados -->
<style>
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.avatar-circle {
    transition: all 0.2s ease;
}

.avatar-circle:hover {
    transform: scale(1.1);
}

.card {
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    border-bottom: none;
}

.badge {
    border-radius: 8px;
}

.btn-outline-light:hover {
    background-color: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
}

.table th {
    background-color: #f8f9fa;
    font-size: 14px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.border-bottom {
    border-bottom: 2px solid #e9ecef !important;
}

.border-bottom:last-child {
    border-bottom: none !important;
}

@media (max-width: 768px) {
    .d-flex.justify-content-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .btn-group {
        width: 100%;
    }
    
    .btn-group .btn {
        flex: 1;
    }
}

td {
    border-radius: 5px;
    border-width: 1px;

}
</style>

