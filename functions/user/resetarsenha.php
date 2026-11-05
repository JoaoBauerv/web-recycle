<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/senha_lib.php');

exigirPermissao('usuario.resetar_senha', $url_base);
csrfExigir($url_base . '/usuarios');

$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    header("Location: $url_base/usuarios?msgErro=" . urlencode('ID do usuário não fornecido!'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id_usuario, usuario, nome, email FROM usuario WHERE id_usuario = :id");
    $stmt->execute([':id' => $id]);
    $usuario_reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario_reset) {
        header("Location: $url_base/usuarios?msgErro=" . urlencode('Usuário não encontrado!'));
        exit;
    }

    $novaSenha = gerarSenhaAleatoria(12);

    // A senha nova só existe em dois lugares: o hash no banco e o corpo do
    // email. Se o email não sai, ninguém fica sabendo dela — então a troca é
    // desfeita e o usuário continua entrando com a senha antiga, em vez de
    // ficar trancado fora do sistema.
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE usuario SET senha = :senha, precisa_alterar_senha = 1 WHERE id_usuario = :id");
    $stmt->execute([':senha' => password_hash($novaSenha, PASSWORD_DEFAULT), ':id' => $id]);

    registraMovimentacao(
        $_SESSION['id_usuario'],
        $id,
        'Senha resetada por admin: ' . $_SESSION['id_usuario'] . ' - Obrigatório alterar no próximo login',
        'Senha resetada',
        $pdo
    );

    if (enviarEmailSenha($usuario_reset['email'], $usuario_reset['nome'], $usuario_reset['usuario'], $novaSenha, 'reset')) {
        $pdo->commit();
        $mensagem = "Senha do usuário " . $usuario_reset['usuario'] . " resetada e enviada por email! O usuário será obrigado a alterar a senha no próximo login.";
        header("Location: $url_base/usuarios?msgSucesso=" . urlencode($mensagem));
    } else {
        $pdo->rollBack();
        $mensagem = "Não foi possível enviar o email para " . $usuario_reset['email']
            . ". A senha NÃO foi alterada — o usuário continua com a senha atual. Verifique a configuração de email (MAIL_* no .env) e tente de novo.";
        header("Location: $url_base/usuarios?msgErro=" . urlencode($mensagem));
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao resetar senha: " . $e->getMessage());
    header("Location: $url_base/usuarios?msgErro=" . urlencode('Erro interno do sistema!'));
}
exit;
