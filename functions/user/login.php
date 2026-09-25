<?php
session_start();
require_once(__DIR__ . '/../../banco.php');

// A autenticação passou a ser pelo e-mail: ele é único, o usuário já o conhece
// e não depende do nome de login gerado automaticamente no cadastro.
$email = $_SESSION['login_email'] ?? '';
$senha = $_SESSION['senha'] ?? '';

// Limpa as credenciais da sessão logo no início: elas só servem para esta
// verificação e não devem sobreviver a ela, dando certo ou errado.
unset($_SESSION['login_email'], $_SESSION['senha']);

if ($email === '' || $senha === '') {
    header("Location: ../../views/user/login.php");
    exit;
}

// LOWER nos dois lados: e-mail não diferencia maiúsculas de minúsculas.
$stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE LOWER(email) = LOWER(:email) AND status = 1");
$stmt->bindParam(':email', $email);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($senha, $user['senha'])) {
    // A sessão identifica o usuário pela chave primária. A coluna `usuario`
    // continua existindo no banco (é NOT NULL e nomeia os arquivos de foto),
    // mas não é mais credencial nem chave de busca.
    $_SESSION['id_usuario'] = $user['id_usuario'];
    $_SESSION['permissao'] = $user['permissao'];
    $_SESSION['logado'] = 1;

    if ($user['precisa_alterar_senha'] == 1) {
        $_SESSION['precisa_alterar_senha'] = 1;
        $_SESSION['msg_aviso'] = 'Por motivos de segurança, você deve alterar sua senha antes de continuar.';
        header('Location: '.$url_base.'/views/user/alterar_senha.php');
        exit;
    }

    header("Location: ../../index2.php?msgSucesso=Login realizado com sucesso!");
    exit;
}

// Mensagem igual para e-mail inexistente e senha errada, para não revelar
// quais e-mails estão cadastrados.
session_destroy();
header("Location: ../../views/user/login.php?msgErro=E-mail ou senha incorretos!");
exit;
