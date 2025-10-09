<?php
require '../../vendor/autoload.php';
require '../../banco.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Configurações
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 
$dompdf = new Dompdf($options);

if($_REQUEST['tipo']=='todos'){
    $pesquisa_tipo = '';
}else{
    $pesquisa_tipo = "AND tipo = '".$_REQUEST['tipo']."'";
}

$sql = "SELECT * FROM tb_material WHERE status = 1 ".$pesquisa_tipo." ORDER BY tipo ASC";
$stmt = $pdo->query($sql);
$materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Montar as linhas da tabela em HTML
$tableRows = '';
foreach ($materiais as $p) {
    if($_REQUEST['preco'] === 'normal'){
        $preco = $p['preco_compra'];
        $fornecedor = '';
    }else{
        $preco = $p['preco_especial'];
        $fornecedor = ' - Fornecedor';
    }


    $tableRows .= 
                '<tr>
                    <td>' . htmlspecialchars($p['nm_material']) . '</td>
                    <td>' . htmlspecialchars($p['tipo']) . '</td>
                    <td>R$ ' . number_format($preco, 2, ',', '.') . '</td>
                </tr>';
            }

// Nome da empresa e data
$empresa = $_ENV['APP_NAME'];
$dataGeracao = date("d/m/Y");
$logoPath = __DIR__ . "/../../images/logo.png"; // caminho absoluto
$logoBase64 = "data:image/png;base64," . base64_encode(file_get_contents($logoPath));
$endereco  = $_ENV['APP_END'];
$horario   = "Seg a Sex: 08h as 12 e 13 as 18h |";

$html = '
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; }
    .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
    .logo { height: 60px; }
    .empresa { text-align: center; flex-grow: 1; font-size: 18px; font-weight: bold; color: #2c3e50; }
    .data { font-size: 12px; color: #555; text-align: right; }
    .endereco {font-size: 12px; color: #555; text-align:left;}
    h2 { text-align: center; margin: 20px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #555; padding: 8px; text-align: center; }
    th { background: #f2f2f2; font-weight: bold; }
    tr:nth-child(even) { background: #fafafa; }
</style>
</head>
<body>

<div class="header">
    <img src="'.$logoBase64.'" class="logo">
    
    <div class="empresa">
        <strong>'.$empresa.'</strong><br>
        <p style="font-size:16px; color:#555;">'.$endereco.'</p>
        <p style="font-size:14px; color:#555;">Horário de funcionamento: '.$horario.'</p>
    </div>
    
    <div class="data">
        <em>Preços diariamente sujeitos à alteração</em><br>
        '.$dataGeracao.'
    </div>
</div>

<h2>Tabela de Preços'.$fornecedor.'</h2>

<table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Preço</th>
        </tr>
    </thead>
    <tbody>' . $tableRows . '</tbody>
</table>

</body>
</html>
';

// Carregar HTML
$dompdf->loadHtml($html);

// Definir papel e orientação
$dompdf->setPaper('A4', 'portrait');

// Renderizar
$dompdf->render();

// Exibir PDF
if($_REQUEST['preco'] === 'normal'){
    $dompdf->stream("tabela_precos.pdf", ["Attachment" => false]);
}else{
    $dompdf->stream("tabela_precos_fornecedor.pdf", ["Attachment" => false]);
}

