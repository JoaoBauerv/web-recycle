<?php
/**
 * Envia o comprovante de compra em PDF para o e-mail do cliente.
 *
 * Reaproveita o Dompdf (functions/compra/comprovante_pdf.php) e o PHPMailer com
 * as credenciais SMTP do .env, mesmo caminho já usado no reset de senha.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/comprovante_pdf.php';

use PHPMailer\PHPMailer\PHPMailer;

$id_pesagem = (int) ($_POST['id_pesagem'] ?? 0);
$destino = $url_base . '/compras/comprovante?id=' . $id_pesagem;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id_pesagem) {
    header("Location: $url_base/compras/listar");
    exit;
}

function comprovanteErro(string $destino, string $mensagem): void
{
    header("Location: $destino&msgErro=" . urlencode($mensagem));
    exit;
}

$dados = carregarCompraParaComprovante($pdo, $id_pesagem);
if ($dados === null) {
    comprovanteErro($destino, 'Compra não encontrada.');
}

// O e-mail digitado tem prioridade, mas o cadastro do cliente é o padrão.
$email = trim($_POST['email'] ?? '') ?: trim($dados['cliente']['email'] ?? '');

if ($email === '') {
    comprovanteErro($destino, 'Este cliente não tem e-mail cadastrado. Informe um endereço para enviar.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    comprovanteErro($destino, 'E-mail inválido: ' . $email);
}

$smtp_host = $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST');
if (empty($smtp_host)) {
    comprovanteErro($destino, 'Envio de e-mail não configurado no sistema (MAIL_HOST ausente no .env).');
}

$pdf = gerarComprovantePdf($pdo, $id_pesagem);
if ($pdf === null) {
    comprovanteErro($destino, 'Não foi possível gerar o PDF do comprovante.');
}

$cliente_nome = $dados['cliente']['nome'] ?? 'Cliente';
$empresa      = $_ENV['MAIL_FROM_NAME'] ?? $_ENV['APP_NAME'] ?? 'Sistema de Reciclagem';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME');
    $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD');
    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?? 'tls';
    $mail->Port       = $_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?? 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(
        $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS'),
        $empresa
    );
    $mail->addAddress($email, $cliente_nome);

    $mail->addStringAttachment($pdf, "comprovante_venda_{$id_pesagem}.pdf", 'base64', 'application/pdf');

    $mail->isHTML(true);
    $mail->Subject = "Comprovante de venda #{$id_pesagem} - {$empresa}";

    // Os dados da compra ficam só no PDF anexo — o corpo do e-mail é só o recado.
    $mail->Body = "
    <html>
    <body style='font-family: Arial, sans-serif; color:#333;'>
        <p>Olá, " . htmlspecialchars($cliente_nome) . "!</p>
        <p>Segue em anexo o comprovante da sua venda.</p>
        <p style='color:#666;font-size:12px;'>{$empresa} — e-mail automático, não é necessário responder.</p>
    </body>
    </html>";

    $mail->AltBody = "Olá, {$cliente_nome}!\n\n"
        . "Segue em anexo o comprovante da sua venda.\n\n"
        . "{$empresa} — e-mail automático, não é necessário responder.";

    $mail->send();

    registraMovimentacao(
        $_SESSION['id_usuario'],
        $_SESSION['id_usuario'],
        sprintf('Comprovante da compra #%d enviado para %s', $id_pesagem, $email),
        'Envio de comprovante',
        $pdo
    );

    header("Location: $destino&msgSucesso=" . urlencode("Comprovante enviado para $email"));
    exit;
} catch (Exception $e) {
    error_log("Erro ao enviar comprovante #{$id_pesagem}: " . $mail->ErrorInfo);
    comprovanteErro($destino, 'Não foi possível enviar o e-mail: ' . ($mail->ErrorInfo ?: $e->getMessage()));
}
