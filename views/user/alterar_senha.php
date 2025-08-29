<?php
session_start();
require_once(__DIR__ . '/../../banco.php');
require_once(__DIR__ . '/../../functions/funcoes.php');

// Verificar se realmente precisa alterar a senha
if (!isset($_SESSION['precisa_alterar_senha']) || !$_SESSION['precisa_alterar_senha']) {
    header('Location: '.$url_base.'/index2.php');
    exit;
}

function post_data($field) {
    $_POST[$field] ??= '';
    return htmlspecialchars(stripslashes($_POST[$field]));
}

define('REQUIRED_FIELD_ERROR', 'É necessário preencher esse campo!');
$errors = [];
$senha_atual = '';
$nova_senha = '';
$confirma_senha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual = post_data('senha_atual');
    $nova_senha = post_data('nova_senha');
    $confirma_senha = post_data('confirma_senha');

    // Buscar senha atual do banco
    $stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE id_usuario = :id");
    $stmt->bindParam(':id', $_SESSION['id_usuario']);
    $stmt->execute();
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validações
    if (!$senha_atual) {
        $errors['senha_atual'] = REQUIRED_FIELD_ERROR;
    } elseif (!password_verify($senha_atual, $dados['senha'])) {
        $errors['senha_atual'] = 'Senha atual incorreta!';
    }

    if (!$nova_senha) {
        $errors['nova_senha'] = REQUIRED_FIELD_ERROR;
    } elseif (strlen($nova_senha) < 8) {
        $errors['nova_senha'] = 'A nova senha deve ter no mínimo 8 caracteres';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $nova_senha)) {
        $errors['nova_senha'] = 'A senha deve conter ao menos: 1 maiúscula, 1 minúscula e 1 número';
    }

    if (!$confirma_senha) {
        $errors['confirma_senha'] = REQUIRED_FIELD_ERROR;
    } elseif ($nova_senha !== $confirma_senha) {
        $errors['confirma_senha'] = 'As senhas não coincidem!';
    }

    // Verificar se a nova senha é diferente da atual
    if ($nova_senha && password_verify($nova_senha, $dados['senha'])) {
        $errors['nova_senha'] = 'A nova senha deve ser diferente da senha atual!';
    }

    // Se não há erros, atualizar a senha
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE tb_usuario SET senha = :senha, precisa_alterar_senha = 0 WHERE id_usuario = :id");
            $stmt->bindValue(':senha', password_hash($nova_senha, PASSWORD_DEFAULT));
            $stmt->bindValue(':id', $_SESSION['id_usuario']);
            
            if ($stmt->execute()) {
                // Registrar a alteração
                registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'], 'Senha alterada pelo próprio usuário (obrigatória)', 'Alteração de senha', $pdo);
                
                // Remover flag da sessão
                unset($_SESSION['precisa_alterar_senha']);
                unset($_SESSION['msg_aviso']);
                $_SESSION['msg_sucesso'] = 'Senha alterada com sucesso!';
                
               
                header('Location: '.$url_base.'/index2.php');
                
                exit;
            } else {
                $errors['geral'] = 'Erro ao alterar senha. Tente novamente.';
            }
        } catch (Exception $e) {
            error_log("Erro ao alterar senha: " . $e->getMessage());
            $errors['geral'] = 'Erro interno do sistema!';
        }
    }
}

include '../../components/sidebar.php';
?>



<div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center">
    <div class="card shadow-lg bg-dark text-white p-4" style="width: 100%; max-width: 500px;">
        
        <!-- Cabeçalho com alerta -->
        <div class="text-center mb-4">
            <div class="alert alert-warning text-dark mb-3">
                <h5 class="alert-heading">⚠️ Alteração Obrigatória</h5>
                <p class="mb-0">Por motivos de segurança, você deve alterar sua senha antes de continuar.</p>
            </div>
            <img src="../../images/logo.jpg" alt="Logo" style="max-height: 80px;" class="rounded-circle me-2">
            <h3 class="mt-2">Alterar Senha</h3>
        </div>

        <!-- Mensagem de erro geral -->
        <?php if (isset($errors['geral'])): ?>
            <div class="alert alert-danger">
                <?php echo $errors['geral']; ?>
            </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form action="" method="POST">
            
            <div class="mb-3">
                <label for="senha_atual" class="form-label">Senha Atual (Temporária)</label>
                <input type="password" class="form-control <?php echo isset($errors['senha_atual']) ? 'is-invalid' : '' ?>" 
                       id="senha_atual" name="senha_atual" value="<?php echo $senha_atual ?>" >
                <div class="invalid-feedback">
                    <?php echo $errors['senha_atual'] ?? '' ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="nova_senha" class="form-label">Nova Senha</label>
                <input type="password" class="form-control <?php echo isset($errors['nova_senha']) ? 'is-invalid' : '' ?>" 
                       id="nova_senha" name="nova_senha" value="<?php echo $nova_senha ?>" >
                <div class="invalid-feedback">
                    <?php echo $errors['nova_senha'] ?? '' ?>
                </div>
                <small class="form-text" style="color: red;">Mínimo 8 caracteres, com ao menos: 1 maiúscula, 1 minúscula e 1 número</small>
            </div>

            <div class="mb-3">
                <label for="confirma_senha" class="form-label">Confirmar Nova Senha</label>
                <input type="password" class="form-control <?php echo isset($errors['confirma_senha']) ? 'is-invalid' : '' ?>" 
                       id="confirma_senha" name="confirma_senha" value="<?php echo $confirma_senha ?>" >
                <div class="invalid-feedback">
                    <?php echo $errors['confirma_senha'] ?? '' ?>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-warning btn-lg">Alterar Senha</button>
            </div>
        </form>

        <!-- Informações importantes -->
        <div class="mt-4">
            <small class="" style="color: white;">
                <strong>Importante:</strong> Você não poderá acessar o sistema até alterar sua senha. 
                Escolha uma senha forte e pessoal.
            </small>
        </div>
    </div>
</div>

