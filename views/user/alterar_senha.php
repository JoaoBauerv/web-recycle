<?php
session_start();
require_once(__DIR__ . '/../../banco.php');
require_once(__DIR__ . '/../../functions/funcoes.php');

// Verificar se realmente precisa alterar a senha
if (!isset($_SESSION['precisa_alterar_senha']) || !$_SESSION['precisa_alterar_senha']) {
    header('Location: '.$url_base.'/index2.php');
    exit;
}

define('REQUIRED_FIELD_ERROR', 'É necessário preencher esse campo!');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Senha não passa por htmlspecialchars/stripslashes: isso alteraria o valor
    // digitado antes de conferir o hash. Os valores também nunca voltam para o
    // HTML (ver os inputs abaixo, sem atributo value).
    $senha_atual   = $_POST['senha_atual'] ?? '';
    $nova_senha    = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';

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
                registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'], 'Senha alterada pelo próprio usuário (obrigatória)', 'Alteração de senha', $pdo);

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
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alterar Senha · <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Reciclagem') ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
    <?php include __DIR__ . '/../../components/head-fonts.php'; ?>
    <link href="<?=$url_base?>/css/theme.css" rel="stylesheet">
</head>

<body>
    <main class="container-fluid min-vh-100 d-flex justify-content-center align-items-center py-5">
        <div class="card border-0 shadow-sm p-4 p-md-5" style="width: 100%; max-width: 480px;">

            <div class="text-center mb-4">
                <img src="<?=$url_base?>/images/logo.png" alt="<?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Logo') ?>"
                     style="max-height: 64px;" class="mb-3">
                <h1 class="h4 fw-bold mb-2">Alterar senha</h1>
                <p class="text-muted small mb-0">
                    Por segurança, defina uma nova senha antes de continuar usando o sistema.
                </p>
            </div>

            <?php if (isset($errors['geral'])): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                    <div><?= htmlspecialchars($errors['geral']) ?></div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="formSenha" novalidate>

                <div class="mb-3">
                    <label for="senha_atual" class="form-label">Senha atual</label>
                    <input type="password" class="form-control <?= isset($errors['senha_atual']) ? 'is-invalid' : '' ?>"
                           id="senha_atual" name="senha_atual" autocomplete="current-password"
                           aria-describedby="senha_atual-erro" required>
                    <div class="invalid-feedback" id="senha_atual-erro">
                        <?= htmlspecialchars($errors['senha_atual'] ?? '') ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="nova_senha" class="form-label">Nova senha</label>
                    <input type="password" class="form-control <?= isset($errors['nova_senha']) ? 'is-invalid' : '' ?>"
                           id="nova_senha" name="nova_senha" autocomplete="new-password"
                           aria-describedby="nova_senha-ajuda nova_senha-erro forca-senha-texto" required>
                    <div class="invalid-feedback" id="nova_senha-erro">
                        <?= htmlspecialchars($errors['nova_senha'] ?? '') ?>
                    </div>
                    <div class="progress mt-2" style="height: 5px;" role="presentation">
                        <div class="progress-bar" id="forca-senha-barra" style="width: 0%;"></div>
                    </div>
                    <small class="form-text d-block" id="forca-senha-texto" aria-live="polite"></small>
                    <small class="form-text text-muted" id="nova_senha-ajuda">
                        Mínimo de 8 caracteres, com ao menos 1 maiúscula, 1 minúscula e 1 número.
                    </small>
                </div>

                <div class="mb-4">
                    <label for="confirma_senha" class="form-label">Confirmar nova senha</label>
                    <input type="password" class="form-control <?= isset($errors['confirma_senha']) ? 'is-invalid' : '' ?>"
                           id="confirma_senha" name="confirma_senha" autocomplete="new-password"
                           aria-describedby="confirma_senha-erro" required>
                    <div class="invalid-feedback" id="confirma_senha-erro">
                        <?= htmlspecialchars($errors['confirma_senha'] ?? '') ?>
                    </div>
                    <small class="form-text text-danger d-none" id="erro-confirma">
                        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>As senhas não coincidem.
                    </small>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Alterar senha
                    </button>
                </div>
            </form>

            <p class="text-muted small mt-4 mb-0">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                Você não conseguirá acessar o sistema até concluir esta alteração.
            </p>
        </div>
    </main>

<script>
(function () {
    var nova = document.getElementById('nova_senha');
    var confirma = document.getElementById('confirma_senha');
    var barra = document.getElementById('forca-senha-barra');
    var texto = document.getElementById('forca-senha-texto');
    var erroConfirma = document.getElementById('erro-confirma');
    var form = document.getElementById('formSenha');
    if (!nova || !form) return;

    // Níveis nomeados: a cor nunca é o único indicador da força.
    var niveis = [
        { largura: '25%',  classe: 'bg-danger',  rotulo: 'Fraca' },
        { largura: '50%',  classe: 'bg-warning', rotulo: 'Razoável' },
        { largura: '75%',  classe: 'bg-info',    rotulo: 'Boa' },
        { largura: '100%', classe: 'bg-success', rotulo: 'Forte' }
    ];

    function pontuar(senha) {
        var pontos = 0;
        if (senha.length >= 8) pontos++;
        if (senha.length >= 12) pontos++;
        if (/[a-z]/.test(senha) && /[A-Z]/.test(senha)) pontos++;
        if (/\d/.test(senha) && /[^a-zA-Z0-9]/.test(senha)) pontos++;
        return Math.min(pontos, 4);
    }

    nova.addEventListener('input', function () {
        if (!this.value) {
            barra.style.width = '0%';
            texto.textContent = '';
            return;
        }
        var nivel = niveis[Math.max(pontuar(this.value) - 1, 0)];
        barra.style.width = nivel.largura;
        barra.className = 'progress-bar ' + nivel.classe;
        texto.textContent = 'Força da senha: ' + nivel.rotulo;
    });

    function conferirConfirmacao() {
        var divergente = confirma.value !== '' && confirma.value !== nova.value;
        erroConfirma.classList.toggle('d-none', !divergente);
        confirma.classList.toggle('is-invalid', divergente);
        return !divergente;
    }

    confirma.addEventListener('input', conferirConfirmacao);

    form.addEventListener('submit', function (e) {
        if (!conferirConfirmacao()) {
            e.preventDefault();
            confirma.focus();
        }
    });
})();
</script>
</body>
</html>
