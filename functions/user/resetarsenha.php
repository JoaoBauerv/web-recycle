<?php 
session_start();
require_once(__DIR__ . '/../../banco.php');
require_once(__DIR__ . '/../funcoes.php');

// Função para gerar senha aleatória
function gerarSenhaAleatoria($tamanho = 12) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $senha = '';
    $caracteresLength = strlen($caracteres);
    
    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $caracteres[rand(0, $caracteresLength - 1)];
    }
    
    // Garantir que tenha pelo menos: 1 maiúscula, 1 minúscula, 1 número
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $senha)) {
        return gerarSenhaAleatoria($tamanho); // Regenera se não atender aos critérios
    }
    
    return $senha;
}

// Função para enviar email usando configurações do .env
function enviarEmailSenha($email, $nome, $usuario, $novaSenha) {
    // Buscar configurações do .env usando $_ENV ou getenv()
    $smtpHost = $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST') ?? 'localhost';
    $smtpPort = $_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?? '587';
    $smtpUser = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?? '';
    $smtpPass = $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?? '';
    $mailFrom = $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?? 'noreply@localhost';
    $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?? 'Sistema de Login';
    $mailEncryption = $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?? 'tls';
    
    // Se PHPMailer estiver disponível, usar SMTP
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return enviarEmailSMTP($email, $nome, $usuario, $novaSenha);
    }
    
    // Fallback para mail() nativo do PHP
    $assunto = "Nova Senha - " . $mailFromName;
    
    $mensagem = "
    <html>
    <head>
        <title>Nova Senha</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f8f9fa; padding: 20px; }
            .senha-box { background-color: #e9ecef; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
            .alert-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Nova Senha Gerada</h2>
            </div>
            <div class='content'>
                <p>Olá <strong>{$nome}</strong>,</p>
                <p>Uma nova senha foi gerada para seu usuário: <strong>{$usuario}</strong></p>
                
                <div class='senha-box'>
                    <h4>Sua nova senha é:</h4>
                    <h3 style='color: #007bff; letter-spacing: 2px;'>{$novaSenha}</h3>
                </div>
                
                <div class='alert-box'>
                    <h4 style='color: #856404;'>⚠️ ATENÇÃO IMPORTANTE!</h4>
                    <p style='color: #856404; margin: 0;'><strong>Você será obrigado a alterar esta senha no próximo login por motivos de segurança.</strong></p>
                </div>
                
                <p><strong>Importante:</strong></p>
                <ul>
                    <li>Esta senha é temporária e deve ser alterada no primeiro acesso</li>
                    <li>Mantenha esta senha em local seguro até a alteração</li>
                    <li>Não compartilhe esta senha com outras pessoas</li>
                    <li>Escolha uma senha forte e pessoal na próxima alteração</li>
                </ul>
            </div>
            <div class='footer'>
                <p>Este é um email automático, não responda.</p>
                <p>{$mailFromName} - " . date('Y') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: {$mailFromName} <{$mailFrom}>" . "\r\n";
    $headers .= "Reply-To: {$mailFrom}" . "\r\n";

    return mail($email, $assunto, $mensagem, $headers);
}

// Função para enviar email via SMTP (se PHPMailer estiver disponível)
function enviarEmailSMTP($email, $nome, $usuario, $novaSenha) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configurações do servidor SMTP do .env
        $mail->isSMTP();
        $mail->Host = $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME');
        $mail->Password = $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD');
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?? 'tls';
        $mail->Port = $_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?? 587;
        
        // Configurações do email
        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS'), 
            $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?? 'Sistema de Login'
        );
        $mail->addAddress($email, $nome);
        
        // Conteúdo do email
        $mail->isHTML(true);
        $mail->Subject = 'Nova Senha - ' . ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?? 'Sistema de Login');
        $mail->CharSet = 'UTF-8';
        
        $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?? 'Sistema de Login';
        
        $mail->Body = "
        <html>
        <head>
            <title>Nova Senha</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f8f9fa; padding: 20px; }
                .senha-box { background-color: #e9ecef; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
                .alert-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Nova Senha Gerada</h2>
                </div>
                <div class='content'>
                    <p>Olá <strong>{$nome}</strong>,</p>
                    <p>Uma nova senha foi gerada para seu usuário: <strong>{$usuario}</strong></p>
                    
                    <div class='senha-box'>
                        <h4>Sua nova senha é:</h4>
                        <h3 style='color: #007bff; letter-spacing: 2px;'>{$novaSenha}</h3>
                    </div>
                    
                    <div class='alert-box'>
                        <h4 style='color: #856404;'>⚠️ ATENÇÃO IMPORTANTE!</h4>
                        <p style='color: #856404; margin: 0;'><strong>Você será obrigado a alterar esta senha no próximo login por motivos de segurança.</strong></p>
                    </div>
                    
                    <p><strong>Importante:</strong></p>
                    <ul>
                        <li>Esta senha é temporária e deve ser alterada no primeiro acesso</li>
                        <li>Mantenha esta senha em local seguro até a alteração</li>
                        <li>Não compartilhe esta senha com outras pessoas</li>
                        <li>Escolha uma senha forte e pessoal na próxima alteração</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>Este é um email automático, não responda.</p>
                    <p>{$mailFromName} - " . date('Y') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email via SMTP: " . $mail->ErrorInfo);
        return false;
    }
}

// Buscar informações do usuário logado
$stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE id_usuario = :id");
$stmt->bindParam(':id', $_SESSION['id_usuario']);
$stmt->execute();
$dados_usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!empty($_REQUEST['id'])) {
    try {
        // Buscar dados do usuário que terá a senha resetada
        $sql = "SELECT id_usuario, usuario, nome, email, data_nascimento FROM tb_usuario WHERE id_usuario = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $_REQUEST['id'], PDO::PARAM_INT);
        $stmt->execute();

        $usuario_reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_reset) {
            header("Location: ../../index2.php?msgErro=Usuário não encontrado!");
            exit;
        }

        // Gerar nova senha aleatória
        $novaSenha = gerarSenhaAleatoria(12);

        // Atualizar senha no banco E marcar para alterar senha
        $sql = "UPDATE tb_usuario SET senha = :senha, precisa_alterar_senha = 1 WHERE id_usuario = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $_REQUEST['id'], PDO::PARAM_INT);
        $stmt->bindValue(':senha', password_hash($novaSenha, PASSWORD_DEFAULT));
        
        if ($stmt->execute()) {
            // Registrar movimentação
            registraMovimentacao(
                $_SESSION['id_usuario'], 
                $_REQUEST['id'], 
                'Senha resetada por admin: ' . $_SESSION['id_usuario'] . ' - Obrigatório alterar no próximo login', 
                'Senha resetada', 
                $pdo
            );

            // Enviar email com a nova senha
            $emailEnviado = enviarEmailSenha(
                $usuario_reset['email'],
                $usuario_reset['nome'],
                $usuario_reset['usuario'],
                $novaSenha
            );

            if ($emailEnviado) {
                $mensagem = "Senha do usuário " . $usuario_reset['usuario'] . " resetada e enviada por email! O usuário será obrigado a alterar a senha no próximo login.";
                header("Location: ../../views/user/admin.php?msgSucesso=" . urlencode($mensagem));
            } else {
                // Se falhou o envio do email, ainda informa que a senha foi resetada
                $mensagem = "Senha resetada, mas houve problema no envio do email. Nova senha: " . $novaSenha . " - Usuário deve alterar no próximo login.";
                header("Location: ../../views/user/admin.php?msgAviso=" . urlencode($mensagem));
            }
        } else {
            header("Location: ../../views/user/admin.php?msgErro=Erro ao resetar senha!");
        }

    } catch (Exception $e) {
        error_log("Erro ao resetar senha: " . $e->getMessage());
        header("Location: ../../views/user/admin.php?msgErro=Erro interno do sistema!");
    }

} else {
    header("Location: ../../index2.php?msgErro=ID do usuário não fornecido!");
}
?>