<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/senha_lib.php');

exigirPermissao('usuario.gerenciar', $url_base);

// A tela de cadastro entrega os dados pela sessão e já validou o token CSRF
// antes de redirecionar para cá. Nada é lido de $_REQUEST: o fallback que
// existia aqui deixava qualquer requisição escolher o próprio valor de
// 'permissao' e se cadastrar como Admin.
$entrada = $_SESSION['novo_usuario'] ?? [];
unset($_SESSION['novo_usuario']);

if (!$entrada) {
    header("Location: $url_base/usuarios/novo?msgErro=" . urlencode('Sessão expirada. Preencha o cadastro novamente.'));
    exit;
}

$nomeCompleto = nomeProprio($entrada['nome_completo'] ?? '');
$usuario      = $entrada['usuario'] ?? '';
$email        = $entrada['email'] ?? '';
$foto         = $entrada['foto_nome'] ?? '';
$data         = $entrada['data'] ?? '';

// Perfil não é escolhido no cadastro: todo usuário nasce como 'Usuario' e a
// promoção para Admin passa pela tela de edição, que é auditada à parte.
//
// A senha é gerada aqui. Antes vinha de um campo escondido do formulário com o
// valor fixo 123456 — igual para todo usuário novo, conhecida por qualquer um,
// e válida até o primeiro login.
$senha = gerarSenhaAleatoria(12);

try {
    $stmt = $pdo->prepare("INSERT INTO usuario (nome, email, senha, foto, usuario, data_nascimento, permissao, precisa_alterar_senha)
                           VALUES (:nome, :email, :senha, :foto, :usuario, :data, 'Usuario', 1)
                           RETURNING id_usuario");
    $stmt->execute([
        ':nome'    => $nomeCompleto,
        ':email'   => $email,
        ':senha'   => password_hash($senha, PASSWORD_DEFAULT),
        ':foto'    => $foto,
        ':usuario' => $usuario,
        ':data'    => $data,
    ]);
    $id_cadastrado = (int) $stmt->fetchColumn();

    registraMovimentacao($_SESSION['id_usuario'], $id_cadastrado, 'Usuario criado por admin: ' . $_SESSION['id_usuario'], 'Cadastro Usuario', $pdo);

    if (enviarEmailSenha($email, $nomeCompleto, $usuario, $senha, 'novo')) {
        header("Location: $url_base/usuarios?msgSucesso=" . urlencode("Usuário cadastrado. A senha temporária foi enviada para $email."));
        exit;
    }

    // Sem e-mail configurado (ou com falha no envio), a senha só existe aqui.
    // Ela é mostrada uma vez ao Admin pela sessão — nunca pela URL, que fica no
    // histórico do navegador e no log do servidor — e some na próxima tela.
    $_SESSION['senha_temporaria'] = [
        'nome'  => $nomeCompleto,
        'email' => $email,
        'senha' => $senha,
    ];
    header("Location: $url_base/usuarios");
    exit;

} catch (Exception $e) {
    error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
    header("Location: $url_base/usuarios/novo?msgErro=" . urlencode('Não foi possível cadastrar o usuário. Confira se o e-mail já não está em uso.'));
    exit;
}
