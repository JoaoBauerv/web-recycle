<?php
require_once (__DIR__ . '/../../components/middleware.php');
require_once __DIR__ . '/../../vendor/autoload.php';
require '../../banco.php';


use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID não informado.");
}
$id_pesagem = (int) $_GET['id'];

// Buscar dados da pesagem
$stmt = $pdo->prepare("SELECT * FROM tb_pesagem WHERE id_pesagem = ?");
$stmt->execute([$id_pesagem]);
$pesagem = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pesagem) {
    die("Pesagem não encontrada.");
}

// Buscar cliente
$stmt_cliente = $pdo->prepare("SELECT * FROM tb_usuario WHERE id_usuario = ?");
$stmt_cliente->execute([$pesagem['id_cliente']]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
$cliente_nome = $cliente ? $cliente['nome'] : 'Desconhecido';

// Buscar itens
$stmt_itens = $pdo->prepare("
    SELECT a.* , b.nm_material
    FROM tb_pesagem_material A 
    LEFT JOIN tb_material B ON a.id_material = b.id_material 
    WHERE A.id_pesagem = ? 
    ORDER BY A.id_material
");
$stmt_itens->execute([$id_pesagem]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

// Gerar HTML
ob_start();
?>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h2 { margin:0; color:#222; text-align:center; }
        .header { text-align:center; margin-bottom:20px; }
        .header small { font-size:11px; color:#666; }
        .card { border:1px solid #ccc; padding:10px; margin-bottom:10px; border-radius:6px; }
        .card h4 { margin:0 0 5px 0; }
        table { width:100%; border-collapse: collapse; margin-top:10px; }
        table th, table td { border:1px solid #ccc; padding:6px; font-size:11px; }
        table th { background:#f2f2f2; }
        .total { font-weight:bold; background:#f9f9f9; }
    </style>
</head>
<body>

<div class="header">
    <h2>Relatório de Pesagem</h2>
    <small>Preços sujeitos à alteração<br>
    <?= date("d/m/Y H:i", strtotime("now")) ?><br>
    Endereço da Empresa | Horário: 08h - 18h</small>
</div>

<div class="card">
    <h4>Cliente</h4>
    <p><b>Nome:</b> <?= htmlspecialchars($cliente_nome) ?><br>
       <b>Email:</b> <?= htmlspecialchars($cliente['email'] ?? 'N/A') ?><br>
       <b>Telefone:</b> <?= htmlspecialchars($cliente['telefone'] ?? 'N/A') ?></p>
</div>

<div class="card">
    <h4>Informações da Pesagem</h4>
    <p><b>ID:</b> #<?= $id_pesagem ?><br>
       <b>Data:</b> <?= date("d/m/Y", strtotime($pesagem['data_pesagem'])) ?><br>
       <b>Horário:</b> <?= date("H:i:s", strtotime($pesagem['data_pesagem'])) ?><br>
       <b>Status:</b> Concluída</p>
</div>

<div class="card">
    <h4>Totais</h4>
    <p><b>Peso Total:</b> <?= $pesagem['total_peso'] ?> kg<br>
       <b>Valor Total:</b> R$ <?= number_format($pesagem['total_valor'], 2, ',', '.') ?><br>
       <b>Preço Médio/kg:</b> R$ <?= number_format($pesagem['total_valor'] / $pesagem['total_peso'], 2, ',', '.') ?></p>
</div>

<h4>Itens da Pesagem</h4>
<table>
    <thead>
        <tr>
            <th>Material</th>
            <th>Peso (kg)</th>
            <th>Preço/kg</th>
            <th>Valor Total</th>
            <th>%</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($itens as $item): 
        $valorItem = $item['preco_un'] * ($item['peso_material'] ?? 0);
        $percentual = ($valorItem / $pesagem['total_valor']) * 100;
    ?>
        <tr>
            <td><?= htmlspecialchars($item['nm_material'] ?? '-') ?></td>
            <td><?= htmlspecialchars($item['peso_material']) ?></td>
            <td>R$ <?= number_format($item['preco_un'], 2, ',', '.') ?></td>
            <td>R$ <?= number_format($valorItem, 2, ',', '.') ?></td>
            <td><?= number_format($percentual, 1) ?>%</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total">
            <td><b>TOTAL</b></td>
            <td><b><?= $pesagem['total_peso'] ?> kg</b></td>
            <td>-</td>
            <td><b>R$ <?= number_format($pesagem['total_valor'], 2, ',', '.') ?></b></td>
            <td>100%</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
<?php
$html = ob_get_clean();

// Config Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Baixar ou abrir no navegador
$dompdf->stream("pesagem_$id_pesagem.pdf", ["Attachment" => false]);
