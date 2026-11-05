<?php
/**
 * Senha temporária: geração e envio por e-mail.
 *
 * Usada em dois lugares que precisam da mesma coisa — dar a alguém uma senha
 * que só ele vai conhecer e que precisa ser trocada no primeiro acesso:
 *   - functions/user/registrar.php   (usuário novo)
 *   - functions/user/resetarsenha.php (reset feito pelo Admin)
 *
 * Espera vendor/autoload.php já carregado (banco.php faz isso).
 */

/**
 * Lê uma variável do .env com valor padrão.
 *
 * `$_ENV[x] ?? getenv(x) ?? padrao` não funciona: getenv() devolve false, não
 * null, quando a variável não existe, e o ?? nunca chega no padrão.
 */
function senhaEnv(string $chave, string $padrao = ''): string
{
    $valor = $_ENV[$chave] ?? getenv($chave);
    return ($valor === false || $valor === null || $valor === '') ? $padrao : (string) $valor;
}

/** E-mail está configurado neste ambiente? */
function senhaEmailConfigurado(): bool
{
    return senhaEnv('MAIL_HOST') !== '';
}

/**
 * Senha aleatória com ao menos 1 maiúscula, 1 minúscula e 1 número.
 *
 * random_int() usa o gerador criptográfico do sistema. rand() é previsível:
 * quem souber a hora aproximada da geração consegue reproduzir a sequência.
 */
function gerarSenhaAleatoria(int $tamanho = 12): string
{
    $caracteres = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
    $limite = strlen($caracteres) - 1;

    do {
        $senha = '';
        for ($i = 0; $i < $tamanho; $i++) {
            $senha .= $caracteres[random_int(0, $limite)];
        }
    } while (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $senha));

    return $senha;
}

/**
 * Envia a senha temporária por SMTP (PHPMailer). Devolve true se saiu.
 *
 * @param string $motivo 'novo' para conta criada, 'reset' para senha redefinida.
 */
function enviarEmailSenha(string $email, string $nome, string $usuario, string $senha, string $motivo = 'reset'): bool
{
    if (!senhaEmailConfigurado() || !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return false;
    }

    $remetente = senhaEnv('MAIL_FROM_NAME', senhaEnv('APP_NAME', 'Sistema'));
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    [$assunto, $titulo, $abertura] = $motivo === 'novo'
        ? ['Sua conta foi criada', 'Bem-vindo(a) ao sistema', 'Uma conta foi criada para você. Seu usuário é']
        : ['Nova senha', 'Nova senha gerada', 'Uma nova senha foi gerada para o seu usuário'];

    $corpo = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background-color: #007bff; color: white; padding: 20px; text-align: center;'>
                <h2>{$h($titulo)}</h2>
            </div>
            <div style='background-color: #f8f9fa; padding: 20px;'>
                <p>Olá <strong>{$h($nome)}</strong>,</p>
                <p>{$h($abertura)}: <strong>{$h($usuario)}</strong></p>
                <div style='background-color: #e9ecef; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;'>
                    <h4>Sua senha temporária é:</h4>
                    <h3 style='color: #007bff; letter-spacing: 2px;'>{$h($senha)}</h3>
                </div>
                <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                    <p style='color: #856404; margin: 0;'><strong>Você será obrigado a trocar esta senha no primeiro acesso.</strong></p>
                </div>
                <ul>
                    <li>Entre com o seu e-mail e a senha acima.</li>
                    <li>Não compartilhe esta senha com outras pessoas.</li>
                    <li>Escolha uma senha forte e pessoal na troca.</li>
                </ul>
            </div>
            <div style='text-align: center; padding: 20px; color: #6c757d; font-size: 12px;'>
                <p>Este é um e-mail automático, não responda.</p>
                <p>{$h($remetente)} - " . date('Y') . "</p>
            </div>
        </div>
    </body>
    </html>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = senhaEnv('MAIL_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = senhaEnv('MAIL_USERNAME');
        $mail->Password   = senhaEnv('MAIL_PASSWORD');
        $mail->SMTPSecure = senhaEnv('MAIL_ENCRYPTION', 'tls');
        $mail->Port       = (int) senhaEnv('MAIL_PORT', '587');
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(senhaEnv('MAIL_FROM_ADDRESS', 'noreply@localhost'), $remetente);
        $mail->addAddress($email, $nome);

        $mail->isHTML(true);
        $mail->Subject = $assunto . ' - ' . $remetente;
        $mail->Body    = $corpo;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail de senha: ' . $mail->ErrorInfo);
        return false;
    }
}
