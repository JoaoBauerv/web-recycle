<?php
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}

$id_usuario = (int) ($dados_usuario['id_usuario'] ?? 0);

// Consultas parametrizadas (antes os ids eram concatenados direto no SQL).
$stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE id_usuario = :id");
$stmt->execute([':id' => $id_usuario]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT * FROM tb_endereco WHERE id_usuario = :id");
$stmt->execute([':id' => $id_usuario]);
$endereco = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->prepare("SELECT * FROM tb_documento WHERE id_usuario = :id");
$stmt->execute([':id' => $id_usuario]);
$documento = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Idade só existe se a data de nascimento estiver preenchida.
$idade = null;
if (!empty($usuario['data_nascimento'])) {
    $nascimento = DateTime::createFromFormat('Y-m-d', $usuario['data_nascimento'], new DateTimeZone('America/Sao_Paulo'));
    if ($nascimento) {
        $idade = $nascimento->diff(new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->y;
    }
}

function perfilValor(?string $valor): string
{
    $valor = trim((string) $valor);
    return $valor !== '' ? htmlspecialchars($valor) : '—';
}

$aba_ativa = ($_GET['aba'] ?? 'dados') === 'seguranca' ? 'seguranca' : 'dados';
?>

<div class="container-fluid py-4" style="max-width: 1100px;">

    <div class="pagina-cabecalho">
        <h2 class="fw-bold">Meu Perfil</h2>
        <p>Consulte suas informações e gerencie o acesso à sua conta</p>
    </div>

    <?php require_once __DIR__ . '/../../components/alert.php'; ?>

    <div class="row g-4">

        <!-- Cartão de identificação -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <img src="<?= $url_base ?>/images/user/<?= htmlspecialchars($foto_usuario) ?>"
                         alt="Foto de <?= htmlspecialchars($usuario['nome'] ?? '') ?>"
                         class="rounded-circle mb-3" width="110" height="110" style="object-fit: cover;"
                         onerror="this.src='<?= $url_base ?>/images/user/padrao.png'">

                    <h3 class="h5 fw-bold mb-1"><?= htmlspecialchars($usuario['nome'] ?? '') ?></h3>
                    <p class="text-muted small mb-3 text-break"><?= htmlspecialchars($usuario['email'] ?? '') ?></p>

                    <span class="badge <?= ($usuario['permissao'] ?? '') === 'Admin' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' ?>">
                        <i class="bi <?= ($usuario['permissao'] ?? '') === 'Admin' ? 'bi-shield-check' : 'bi-person' ?> me-1" aria-hidden="true"></i>
                        <?= perfilValor($usuario['permissao'] ?? '') ?>
                    </span>

                    <hr class="my-4">

                    <ul class="list-unstyled text-start small mb-0">
                        <li class="d-flex align-items-start gap-2 mb-2">
                            <i class="bi bi-envelope text-muted mt-1" aria-hidden="true"></i>
                            <span class="text-break"><?= perfilValor($usuario['email'] ?? '') ?></span>
                        </li>
                        <li class="d-flex align-items-start gap-2 mb-2">
                            <i class="bi bi-telephone text-muted mt-1" aria-hidden="true"></i>
                            <span><?= perfilValor($usuario['telefone'] ?? '') ?></span>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-phone text-muted mt-1" aria-hidden="true"></i>
                            <span><?= perfilValor($usuario['celular'] ?? '') ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Abas -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom pb-0">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $aba_ativa === 'dados' ? 'active' : '' ?>" id="aba-dados"
                                    data-bs-toggle="tab" data-bs-target="#painel-dados" type="button" role="tab"
                                    aria-controls="painel-dados" aria-selected="<?= $aba_ativa === 'dados' ? 'true' : 'false' ?>">
                                <i class="bi bi-person-vcard me-1" aria-hidden="true"></i> Dados pessoais
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $aba_ativa === 'seguranca' ? 'active' : '' ?>" id="aba-seguranca"
                                    data-bs-toggle="tab" data-bs-target="#painel-seguranca" type="button" role="tab"
                                    aria-controls="painel-seguranca" aria-selected="<?= $aba_ativa === 'seguranca' ? 'true' : 'false' ?>">
                                <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> Segurança
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        <!-- Dados pessoais -->
                        <div class="tab-pane fade <?= $aba_ativa === 'dados' ? 'show active' : '' ?>"
                             id="painel-dados" role="tabpanel" aria-labelledby="aba-dados">

                            <h4 class="h6 fw-semibold text-uppercase text-muted mb-3" style="letter-spacing:.05em;">Informações pessoais</h4>
                            <dl class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <dt class="small text-muted fw-normal">Nome</dt>
                                    <dd class="mb-0 fw-medium"><?= perfilValor($usuario['nome'] ?? '') ?></dd>
                                </div>
                                <div class="col-sm-6">
                                    <dt class="small text-muted fw-normal">E-mail</dt>
                                    <dd class="mb-0 fw-medium text-break"><?= perfilValor($usuario['email'] ?? '') ?></dd>
                                </div>
                                <div class="col-sm-6">
                                    <dt class="small text-muted fw-normal">Data de nascimento</dt>
                                    <dd class="mb-0 fw-medium">
                                        <?php if (!empty($usuario['data_nascimento'])): ?>
                                            <?= date('d/m/Y', strtotime($usuario['data_nascimento'])) ?>
                                            <?php if ($idade !== null): ?>
                                                <span class="text-muted small">(<?= $idade ?> anos)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div class="col-sm-3">
                                    <dt class="small text-muted fw-normal">Telefone</dt>
                                    <dd class="mb-0 fw-medium"><?= perfilValor($usuario['telefone'] ?? '') ?></dd>
                                </div>
                                <div class="col-sm-3">
                                    <dt class="small text-muted fw-normal">Celular</dt>
                                    <dd class="mb-0 fw-medium"><?= perfilValor($usuario['celular'] ?? '') ?></dd>
                                </div>
                            </dl>

                            <h4 class="h6 fw-semibold text-uppercase text-muted mb-3" style="letter-spacing:.05em;">Endereço</h4>
                            <?php if (empty($endereco)): ?>
                                <p class="text-muted small mb-4">Nenhum endereço cadastrado.</p>
                            <?php else: ?>
                                <dl class="row g-3 mb-4">
                                    <div class="col-sm-3">
                                        <dt class="small text-muted fw-normal">CEP</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($endereco['cep'] ?? '') ?></dd>
                                    </div>
                                    <div class="col-sm-6">
                                        <dt class="small text-muted fw-normal">Logradouro</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($endereco['logradouro'] ?? '') ?></dd>
                                    </div>
                                    <div class="col-sm-3">
                                        <dt class="small text-muted fw-normal">Número</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($endereco['numero'] ?? '') ?></dd>
                                    </div>
                                    <div class="col-sm-4">
                                        <dt class="small text-muted fw-normal">Bairro</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($endereco['bairro'] ?? '') ?></dd>
                                    </div>
                                    <div class="col-sm-4">
                                        <dt class="small text-muted fw-normal">Cidade</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($endereco['cidade'] ?? '') ?></dd>
                                    </div>
                                    <div class="col-sm-4">
                                        <dt class="small text-muted fw-normal">Complemento</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($endereco['complemento'] ?? '') ?></dd>
                                    </div>
                                </dl>
                            <?php endif; ?>

                            <h4 class="h6 fw-semibold text-uppercase text-muted mb-3" style="letter-spacing:.05em;">Documentos</h4>
                            <?php if (empty($documento)): ?>
                                <p class="text-muted small mb-0">Nenhum documento cadastrado.</p>
                            <?php else: ?>
                                <dl class="row g-3 mb-0">
                                    <div class="col-sm-4">
                                        <dt class="small text-muted fw-normal">CPF</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($documento['cpf'] ?? '') ?></dd>
                                    </div>
                                    <div class="col-sm-4">
                                        <dt class="small text-muted fw-normal">RG</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($documento['rg'] ?? '') ?></dd>
                                    </div>
                                    <div class="col-sm-4">
                                        <dt class="small text-muted fw-normal">CNH</dt>
                                        <dd class="mb-0 fw-medium"><?= perfilValor($documento['cnh'] ?? '') ?></dd>
                                    </div>
                                </dl>
                            <?php endif; ?>

                            <div class="alert alert-light border mt-4 mb-0 small d-flex gap-2" role="note">
                                <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
                                <span>Para corrigir estes dados, procure um administrador do sistema.</span>
                            </div>
                        </div>

                        <!-- Segurança -->
                        <div class="tab-pane fade <?= $aba_ativa === 'seguranca' ? 'show active' : '' ?>"
                             id="painel-seguranca" role="tabpanel" aria-labelledby="aba-seguranca">

                            <h4 class="h6 fw-semibold text-uppercase text-muted mb-3" style="letter-spacing:.05em;">Alterar senha</h4>

                            <form method="POST" action="<?= $url_base ?>/functions/user/alterar_senha_perfil.php"
                                  id="formSenha" novalidate>

                                <div class="mb-3">
                                    <label for="senha_atual" class="form-label">Senha atual</label>
                                    <input type="password" class="form-control" id="senha_atual" name="senha_atual"
                                           autocomplete="current-password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="nova_senha" class="form-label">Nova senha</label>
                                    <input type="password" class="form-control" id="nova_senha" name="nova_senha"
                                           autocomplete="new-password" required
                                           aria-describedby="ajuda-senha forca-senha-texto">
                                    <div class="progress mt-2" style="height: 5px;" role="presentation">
                                        <div class="progress-bar" id="forca-senha-barra" style="width: 0%;"></div>
                                    </div>
                                    <small class="form-text d-block" id="forca-senha-texto" aria-live="polite"></small>
                                    <small class="form-text text-muted" id="ajuda-senha">
                                        Mínimo de 8 caracteres, com ao menos 1 maiúscula, 1 minúscula e 1 número.
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label for="confirma_senha" class="form-label">Confirmar nova senha</label>
                                    <input type="password" class="form-control" id="confirma_senha" name="confirma_senha"
                                           autocomplete="new-password" required aria-describedby="erro-confirma">
                                    <small class="form-text text-danger d-none" id="erro-confirma">
                                        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>As senhas não coincidem.
                                    </small>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Alterar senha
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
