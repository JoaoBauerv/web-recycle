<?php
/**
 * Geração do comprovante de compra em PDF.
 *
 * Fica aqui para que o download (views/compra/pdf.php) e o envio por e-mail
 * (functions/compra/enviar_comprovante.php) produzam exatamente o mesmo
 * documento — se um mudar, o outro muda junto.
 *
 * Espera o Dompdf já carregado (vendor/autoload.php).
 */

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Carrega a compra, o cliente e os itens. Devolve null se a compra não existir.
 */
function carregarCompraParaComprovante(PDO $pdo, int $id_pesagem): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM tb_pesagem WHERE id_pesagem = ?");
    $stmt->execute([$id_pesagem]);
    $pesagem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pesagem) {
        return null;
    }

    $stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt_cliente->execute([$pesagem['id_cliente']]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt_itens = $pdo->prepare("
        SELECT a.*, b.nm_material
        FROM tb_pesagem_material A
        LEFT JOIN tb_material B ON a.id_material = b.id_material
        WHERE A.id_pesagem = ?
        ORDER BY A.id_material
    ");
    $stmt_itens->execute([$id_pesagem]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    return ['pesagem' => $pesagem, 'cliente' => $cliente, 'itens' => $itens];
}

/**
 * Monta o PDF do comprovante e devolve os bytes do arquivo.
 */
function gerarComprovantePdf(PDO $pdo, int $id_pesagem): ?string
{
    $dados = carregarCompraParaComprovante($pdo, $id_pesagem);
    if ($dados === null) {
        return null;
    }

    $pesagem = $dados['pesagem'];
    $cliente = $dados['cliente'];
    $cliente_nome = $cliente['nome'] ?? 'Desconhecido';

    $itens_agrupados = [];
    $total_tara = 0;
    $total_peso_bruto = 0;

    foreach ($dados['itens'] as $item) {
        $material   = $item['nm_material'] ?? '-';
        $peso       = $item['peso_material'] ?? 0;
        $tara       = $item['tara'] ?? 0;
        $peso_bruto = $item['peso_bruto'] ?? $peso;
        $preco      = $item['preco_un'] ?? 0;

        if (!isset($itens_agrupados[$material])) {
            $itens_agrupados[$material] = [
                'peso_bruto_total' => 0,
                'tara_total'       => 0,
                'peso_total'       => 0,
                'preco_un'         => $preco, // assumindo mesmo preço por kg
                'valor_total'      => 0,
            ];
        }

        $itens_agrupados[$material]['peso_bruto_total'] += $peso_bruto;
        $itens_agrupados[$material]['tara_total']       += $tara;
        $itens_agrupados[$material]['peso_total']       += $peso;
        $itens_agrupados[$material]['valor_total']      += $peso * $preco;
        $total_tara       += $tara;
        $total_peso_bruto += $peso_bruto;
    }

    $logoPath = __DIR__ . "/../../images/logo.png";
    $logoBase64 = is_file($logoPath)
        ? "data:image/png;base64," . base64_encode(file_get_contents($logoPath))
        : '';

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
        .logo { height: 60px; }
    </style>
</head>
<body>

<div class="header">
    <?php if ($logoBase64): ?><img src="<?= $logoBase64 ?>" class="logo"><?php endif; ?>
    <h2>Comprovante de Venda</h2>
    <small><?= htmlspecialchars($_ENV['APP_END'] ?? '') ?> | Horário: 08h - 18h</small>
</div>

<div class="card">
    <h4>Cliente</h4>
    <p><b>Nome:</b> <?= htmlspecialchars($cliente_nome) ?><br>
       <b>Email:</b> <?= htmlspecialchars($cliente['email'] ?? 'N/A') ?><br>
       <b>Telefone:</b> <?= htmlspecialchars($cliente['telefone'] ?? 'N/A') ?></p>
</div>

<div class="card">
    <h4>Informações da Venda</h4>
    <p><b>ID:</b> #<?= $id_pesagem ?><br>
       <b>Data:</b> <?= date("d/m/Y", strtotime($pesagem['data_pesagem'])) ?><br>
       <b>Horário:</b> <?= date("H:i:s", strtotime($pesagem['data_pesagem'])) ?><br>
       <b>Status:</b> Concluída</p>
</div>

<h4>Itens da Venda</h4>
<table>
    <thead>
        <tr>
            <th>Material</th>
            <th>Peso Bruto</th>
            <th>Tara</th>
            <th>Peso Líquido</th>
            <th>Preço/kg</th>
            <th>Valor Total</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($itens_agrupados as $material => $item): ?>
        <tr>
            <td><?= htmlspecialchars($material ?? '-') ?></td>
            <td><?= number_format($item['peso_bruto_total'], 2, ',', '.') ?> kg</td>
            <td><?= $item['tara_total'] > 0 ? '− ' . number_format($item['tara_total'], 2, ',', '.') . ' kg' : '-' ?></td>
            <td><?= number_format($item['peso_total'], 2, ',', '.') ?> kg</td>
            <td>R$ <?= number_format($item['preco_un'], 2, ',', '.') ?></td>
            <td>R$ <?= number_format($item['valor_total'], 2, ',', '.') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total">
            <td><b>TOTAL</b></td>
            <td><b><?= number_format($total_peso_bruto, 2, ',', '.') ?> kg</b></td>
            <td><b><?= $total_tara > 0 ? '− ' . number_format($total_tara, 2, ',', '.') . ' kg' : '-' ?></b></td>
            <td><b><?= number_format($pesagem['total_peso'], 2, ',', '.') ?> kg</b></td>
            <td>-</td>
            <td><b>R$ <?= number_format($pesagem['total_valor'], 2, ',', '.') ?></b></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
