<?php
require_once (__DIR__ . '/../../components/middleware.php');
require_once __DIR__ . '/../../vendor/autoload.php';
require '../../banco.php';
require_once __DIR__ . '/../../functions/compra/comprovante_pdf.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID não informado.");
}
$id_pesagem = (int) $_GET['id'];

$pdf = gerarComprovantePdf($pdo, $id_pesagem);

if ($pdf === null) {
    die("Compra não encontrada.");
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="compra_' . $id_pesagem . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
