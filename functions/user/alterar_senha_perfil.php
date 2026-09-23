<?php
/**
 * Troca de senha voluntária, feita pelo próprio usuário a partir do perfil.
 *
 * A tela alterar_senha.php cobre apenas a troca OBRIGATÓRIA (flag
 * precisa_alterar_senha) — ela redireciona quem não tem a flag. Este endpoint
 * atende o caso normal, usando as mesmas regras de validação e o mesmo
 * mecanismo de autenticação (password_verify / password_hash).
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');

$destino = $url_base . '/perfil';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $destino");
    exit;
}

$senha_atual   = $_POST['senha_atual'] ?? '';
$nova_senha    = $_POST['nova_senha'] ?? '';
$confirma      = $_POST['confirma_senha'] ?? '';

$stmt = $pdo->prepare("SELECT senha FROM tb_usuario WHERE id_usuario = :id");
$stmt->execute([':id' => $_SESSION['id_usuario']]);
$hash_atual = $stmt->fetchColumn();

$erro = null;

if (!$hash_atual) {
    $erro = 'Usuário não encontrado.';
} elseif ($senha_atual === '') {
    $erro = 'Informe sua senha atual.';
} elseif (!password_verify($senha_atual, $hash_atual)) {
    $erro = 'A senha atual está incorreta.';
} elseif ($nova_senha === '') {
    $erro = 'Informe a nova senha.';
} elseif (strlen($nova_senha) < 8) {
    $erro = 'A nova senha deve ter no mínimo 8 caracteres.';
} elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $nova_senha)) {
    $erro = 'A nova senha deve conter ao menos 1 letra maiúscula, 1 minúscula e 1 número.';
} elseif ($nova_senha !== $confirma) {
    $erro = 'A confirmação não confere com a nova senha.';
} elseif (password_verify($nova_senha, $hash_atual)) {
    $erro = 'A nova senha deve ser diferente da senha atual.';
}

if ($erro) {
    header("Location: $destino?aba=seguranca&msgErro=" . urlencode($erro));
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tb_usuario SET senha = :senha, precisa_alterar_senha = 0 WHERE id_usuario = :id");
    $stmt->execute([
        ':senha' => password_hash($nova_senha, PASSWORD_DEFAULT),
        ':id'    => $_SESSION['id_usuario'],
    ]);

    registraMovimentacao(
        $_SESSION['id_usuario'],
        $_SESSION['id_usuario'],
        'Senha alterada pelo próprio usuário',
        'Alteração de senha',
        $pdo
    );

    header("Location: $destino?aba=seguranca&msgSucesso=" . urlencode('Senha alterada com sucesso!'));
    exit;
} catch (Exception $e) {
    error_log('Erro ao alterar senha pelo perfil: ' . $e->getMessage());
    header("Location: $destino?aba=seguranca&msgErro=" . urlencode('Não foi possível alterar a senha. Tente novamente.'));
    exit;
}
