<?php 
if (empty($router_managed)) {
    header('Location: ../../index2.php');
    exit;
}
?>
    
    <style>
        /* Segue os tokens do design system (css/theme.css) em vez de cores fixas. */
        .avatar-container {
            position: relative;
            display: inline-block;
        }

        .avatar-preview {
            width: 128px;
            height: 128px;
            object-fit: cover;
            border: 1px solid var(--color-border);
        }

        .upload-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.65);
            color: #fff;
            opacity: 0;
            transition: opacity 200ms ease;
            cursor: pointer;
        }

        .avatar-container:hover .upload-overlay,
        .avatar-container:focus-within .upload-overlay {
            opacity: 1;
        }

        .required {
            color: var(--color-destructive);
        }

        .field-error {
            border-color: var(--color-destructive) !important;
        }

        .field-success {
            border-color: var(--color-accent) !important;
        }

        /* Abas do cartão, no mesmo tom do restante do sistema */
        .nav-tabs-card {
            border-bottom: 1px solid var(--color-border);
        }

        .nav-tabs-card .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            background: none;
            color: var(--color-muted-foreground);
            font-weight: 500;
        }

        .nav-tabs-card .nav-link.active,
        .nav-tabs-card .nav-link:hover {
            color: var(--color-accent);
            border-bottom-color: var(--color-accent);
            background: none;
        }

        @media (max-width: 768px) {
            .avatar-preview { width: 96px; height: 96px; }
        }
    </style>


<?php 

// Consulta os dados do usuário
$sqlUsuario = "SELECT * FROM tb_usuario WHERE id_usuario = ?";
$stmtUsuario = $pdo->prepare($sqlUsuario);
$stmtUsuario->execute([$_REQUEST['id']]);
$usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);


// Consulta endereço
$sqlEndereco = "SELECT * FROM tb_endereco WHERE id_usuario = ?";
$stmtEndereco = $pdo->prepare($sqlEndereco);
$stmtEndereco->execute([$_REQUEST['id']]);
$endereco = $stmtEndereco->fetch(PDO::FETCH_ASSOC);

// Consulta documentos
$sqlDocumento = "SELECT * FROM tb_documento WHERE id_usuario = ?";
$stmtDocumento = $pdo->prepare($sqlDocumento);
$stmtDocumento->execute([$_REQUEST['id']]);
$documento = $stmtDocumento->fetch(PDO::FETCH_ASSOC);



// Calcular idade
$age = '';
if (!empty($usuario['data_nascimento'])) {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $age = DateTime::createFromFormat('Y-m-d', $usuario['data_nascimento'], $tz)
        ->diff(new DateTime('now', $tz))
        ->y;
}

// Simular mensagens de erro/sucesso
$errors = $_SESSION['msg_erro'] ?? [];
if (is_string($errors)) {
    // Se for string, transforma em array
    $errors = ['geral' => $errors];
} elseif (!is_array($errors)) {
    // Se não for nem string nem array, inicializa vazio
    $errors = [];
}
$success = $_SESSION['msg_sucesso'] ?? '';
?>


<div class="container-fluid py-4" style="max-width: 1400px;">
    <div class="row">
        <div class="col-12">

            <div class="pagina-cabecalho d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="<?= $url_base ?>/usuarios" class="text-decoration-none">
                                    <i class="bi bi-people me-1" aria-hidden="true"></i>Usuários
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Editar</li>
                        </ol>
                    </nav>
                    <h2 class="fw-bold mb-1"><?= htmlspecialchars($usuario['nome'] ?? 'Usuário') ?></h2>
                    <p>Editar informações do usuário</p>
                </div>
                <span class="badge <?= ($usuario['status'] == 1) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                    <i class="bi <?= ($usuario['status'] == 1) ? 'bi-check-circle' : 'bi-slash-circle' ?> me-1" aria-hidden="true"></i>
                    <?= ($usuario['status'] == 1) ? 'Ativo' : 'Inativo' ?>
                </span>
            </div>

            <!-- Alertas -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                        <div>
                            <strong>Corrija os seguintes erros:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $field => $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fechar"></button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulário -->
            <form method="POST" action="<?=$url_base?>/functions/user/editar.php" enctype="multipart/form-data" id="editUserForm" novalidate>
                <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($usuario['id_usuario']) ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)) ?>">

                <div class="row g-4">
                    <!-- Coluna Lateral - Foto e Configurações -->
                    <div class="col-xl-3 col-lg-4">
                        <!-- Foto do Usuário -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-bottom">
                                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-camera me-2" aria-hidden="true"></i>Foto do perfil</h3>
                            </div>
                            <div class="card-body text-center">
                                <div class="avatar-container mb-3">
                                    <?php
                                    $foto = !empty($usuario['foto']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $url_base . "/images/user/" . $usuario['foto'])
                                        ? $usuario['foto']
                                        : 'padrao.png';
                                    ?>
                                    <img src="<?= $url_base ?>/images/user/<?= htmlspecialchars($foto) ?>"
                                         alt="Foto de <?= htmlspecialchars($usuario['nome'] ?? 'usuário') ?>"
                                         class="rounded-circle avatar-preview"
                                         id="avatarPreview"
                                         onerror="this.src='<?= $url_base ?>/images/user/padrao.png'">
                                    <div class="upload-overlay rounded-circle" onclick="document.getElementById('fotoInput').click()">
                                        <div>
                                            <i class="bi bi-camera fs-3 d-block" aria-hidden="true"></i>
                                            <small>Alterar</small>
                                        </div>
                                    </div>
                                </div>

                                <input type="file"
                                       name="foto"
                                       id="fotoInput"
                                       class="d-none"
                                       accept="image/jpeg,image/png,image/gif,image/webp"
                                       onchange="previewImage(this)">

                                <div class="d-grid gap-2">
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            onclick="document.getElementById('fotoInput').click()">
                                        <i class="bi bi-upload me-1" aria-hidden="true"></i>Escolher arquivo
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="removePhoto()">
                                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Remover foto
                                    </button>
                                </div>

                                <small class="text-muted d-block mt-3">
                                    JPG, PNG, GIF ou WebP · até 5 MB
                                </small>
                            </div>
                        </div>

                        <!-- Permissões -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-bottom">
                                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Permissão</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-2">
                                    <input type="radio" class="form-check-input" name="permissao"
                                           id="permissaoAdmin" value="Admin"
                                           <?= ($usuario['permissao'] === 'Admin') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="permissaoAdmin">
                                        Administrador
                                        <small class="text-muted d-block">Acesso total, incluindo usuários</small>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="radio" class="form-check-input" name="permissao"
                                           id="permissaoGerente" value="Gerente"
                                           <?= ($usuario['permissao'] === 'Gerente') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="permissaoGerente">Gerente</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="permissao"
                                           id="permissaoUsuario" value="Usuario"
                                           <?= ($usuario['permissao'] === 'Usuario') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="permissaoUsuario">Usuário</label>
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-bottom">
                                <h3 class="h6 fw-semibold mb-0"><i class="bi bi-toggle-on me-2" aria-hidden="true"></i>Situação</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-2">
                                    <input type="radio" class="form-check-input" name="status"
                                           id="statusAtivo" value="1"
                                           <?= ($usuario['status'] == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="statusAtivo">
                                        Ativo
                                        <small class="text-muted d-block">Pode acessar o sistema</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="status"
                                           id="statusInativo" value="0"
                                           <?= ($usuario['status'] == 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="statusInativo">
                                        Inativo
                                        <small class="text-muted d-block">Acesso bloqueado</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conteúdo Principal -->
                    <div class="col-xl-9 col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <!-- Tabs -->
                            <div class="card-header bg-white p-0">
                                <ul class="nav nav-tabs nav-tabs-card" id="editTabs" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pessoais" type="button">
                                            <i class="bi bi-person-vcard me-2" aria-hidden="true"></i>Dados pessoais
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#endereco" type="button">
                                            <i class="bi bi-geo-alt me-2" aria-hidden="true"></i>Endereço
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documentos" type="button">
                                            <i class="bi bi-card-text me-2" aria-hidden="true"></i>Documentos
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Dados Pessoais -->
                                    <div class="tab-pane fade show active" id="pessoais">
                                        <div>
                                            <h4 class="h6 fw-semibold text-uppercase text-muted mb-3" style="letter-spacing:.05em;">Informações básicas</h4>
                                            
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Nome Completo <span class="required">*</span></label>
                                                    <input type="text" 
                                                           class="form-control <?= isset($errors['nome']) ? 'field-error' : '' ?>" 
                                                           name="nome" 
                                                           value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>"
                                                           required
                                                           minlength="2"
                                                           maxlength="100">
                                                    <div class="invalid-feedback">
                                                        <?= $errors['nome'] ?? 'Nome é obrigatório (2-100 caracteres)' ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label">Email <span class="required">*</span></label>
                                                    <input type="email" 
                                                           class="form-control <?= isset($errors['email']) ? 'field-error' : '' ?>" 
                                                           name="email" 
                                                           value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                                                           required>
                                                    <div class="invalid-feedback">
                                                        <?= $errors['email'] ?? 'Email válido é obrigatório' ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">Data de Nascimento <span class="required">*</span></label>
                                                    <input type="date" 
                                                           class="form-control <?= isset($errors['data_nascimento']) ? 'field-error' : '' ?>" 
                                                           name="data_nascimento" 
                                                           value="<?= $usuario['data_nascimento'] ?? '' ?>"
                                                           max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                                                           required>
                                                    <div class="invalid-feedback">
                                                        <?= $errors['data_nascimento'] ?? 'Data válida é obrigatória (mínimo 18 anos)' ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">Idade</label>
                                                    <input type="text" 
                                                           class="form-control bg-light" 
                                                           value="<?= $age ? $age . ' anos' : 'Não calculada' ?>" 
                                                           readonly>
                                                </div>
                                                
                                                <div class="col-md-4"></div> <!-- Espaçamento -->
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label">Telefone</label>
                                                    <input type="tel" 
                                                           class="form-control" 
                                                           name="telefone" 
                                                           value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>"
                                                           placeholder="(11) 3333-4444"
                                                           pattern="[0-9\s\(\)\-]+"
                                                           data-mask="(00) 0000-0000">
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label">Celular</label>
                                                    <input type="tel" 
                                                           class="form-control" 
                                                           name="celular" 
                                                           value="<?= htmlspecialchars($usuario['celular'] ?? '') ?>"
                                                           placeholder="(11) 99999-8888"
                                                           pattern="[0-9\s\(\)\-]+"
                                                           data-mask="(00) 00000-0000">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Endereço -->
                                    <div class="tab-pane fade" id="endereco">
                                        <div>
                                            <h4 class="h6 fw-semibold text-uppercase text-muted mb-3" style="letter-spacing:.05em;">Informações de endereço</h4>
                                            
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label">CEP</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="cep" 
                                                           value="<?= htmlspecialchars($endereco['cep'] ?? '') ?>"
                                                           placeholder="00000-000"
                                                           data-mask="00000-000"
                                                           id="cep">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="buscarCEP()">
                                                        <i class="bi bi-search me-1" aria-hidden="true"></i>Buscar
                                                    </button>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label">Logradouro</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="logradouro" 
                                                           value="<?= htmlspecialchars($endereco['logradouro'] ?? '') ?>"
                                                           id="logradouro">
                                                </div>
                                                
                                                <div class="col-md-3">
                                                    <label class="form-label">Número</label>
                                                    <input type="number" 
                                                           class="form-control" 
                                                           name="numero"
                                                           maxlength="5" 
                                                           value="<?= htmlspecialchars($endereco['numero'] ?? '') ?>">
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">Complemento</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="complemento" 
                                                           value="<?= htmlspecialchars($endereco['complemento'] ?? '') ?>">
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">Bairro</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="bairro" 
                                                           value="<?= htmlspecialchars($endereco['bairro'] ?? '') ?>"
                                                           id="bairro">
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">Cidade</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="cidade" 
                                                           value="<?= htmlspecialchars($endereco['cidade'] ?? '') ?>"
                                                           id="cidade">
                                                </div>
                                                
                                                <div class="col-md-12">
                                                    <label class="form-label">Ponto de Referência</label>
                                                    <textarea class="form-control" 
                                                              name="referencia" 
                                                              rows="2"><?= htmlspecialchars($endereco['referencia'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Documentos -->
                                    <div class="tab-pane fade" id="documentos">
                                        <div>
                                            <h4 class="h6 fw-semibold text-uppercase text-muted mb-3" style="letter-spacing:.05em;">Documentos pessoais</h4>
                                            
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">CPF</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="cpf" 
                                                           value="<?= htmlspecialchars($documento['cpf'] ?? '') ?>"
                                                           placeholder="000.000.000-00"
                                                           data-mask="000.000.000-00"
                                                           id="cpf">
                                                    <div class="form-text" id="cpfValidation"></div>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">RG</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="rg" 
                                                           value="<?= htmlspecialchars($documento['rg'] ?? '') ?>">
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label class="form-label">CNH</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="cnh" 
                                                           value="<?= htmlspecialchars($documento['cnh'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botões de Ação -->
                                <hr class="my-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            
                                            Campos marcados com <span class="required">*</span> são obrigatórios
                                        </small>
                                    </div>
                                    <div>
                                        <a href="<?=$url_base?>/usuarios" class="btn btn-outline-secondary me-2">
                                            <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Salvar alterações
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Máscaras para campos
$(document).ready(function() {
    $('[data-mask]').each(function() {
        $(this).mask($(this).data('mask'));
    });
});

// Preview da imagem
function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validações
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (file.size > maxSize) {
            alert('Arquivo muito grande! Máximo 5MB.');
            input.value = '';
            return;
        }
        
        if (!allowedTypes.includes(file.type)) {
            alert('Tipo de arquivo não permitido! Use JPG, PNG, GIF ou WebP.');
            input.value = '';
            return;
        }
        
        // Mostrar preview
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// Remover foto
function removePhoto() {
    if (confirm('Tem certeza que deseja remover a foto?')) {
        document.getElementById('avatarPreview').src = '../../images/user/padrao.png';
        document.getElementById('fotoInput').value = '';
        
        // Adicionar campo hidden para indicar remoção
        let removeInput = document.querySelector('input[name="remove_photo"]');
        if (!removeInput) {
            removeInput = document.createElement('input');
            removeInput.type = 'hidden';
            removeInput.name = 'remove_photo';
            removeInput.value = '1';
            document.getElementById('editUserForm').appendChild(removeInput);
        }
    }
}

// Buscar CEP
function buscarCEP() {
    const cep = document.getElementById('cep').value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('CEP deve ter 8 dígitos!');
        return;
    }
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                document.getElementById('logradouro').value = data.logradouro;
                document.getElementById('bairro').value = data.bairro;
                document.getElementById('cidade').value = data.localidade;
            } else {
                alert('CEP não encontrado!');
            }
        })
        .catch(() => {
            alert('Erro ao buscar CEP!');
        });
}

// Validação de CPF
function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]/g, '');
    
    if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) {
        return false;
    }
    
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(cpf.charAt(i)) * (10 - i);
    }
    let remainder = 11 - (sum % 11);
    if (remainder === 10 || remainder === 11) remainder = 0;
    if (remainder !== parseInt(cpf.charAt(9))) return false;
    
    sum = 0;
    for (let i = 0; i < 10; i++) {
        sum += parseInt(cpf.charAt(i)) * (11 - i);
    }
    remainder = 11 - (sum % 11);
    if (remainder === 10 || remainder === 11) remainder = 0;
    if (remainder !== parseInt(cpf.charAt(10))) return false;
    
    return true;
}

// Validação do CPF em tempo real
document.getElementById('cpf').addEventListener('blur', function() {
    const cpf = this.value;
    const validationDiv = document.getElementById('cpfValidation');
    
    if (cpf && !validarCPF(cpf)) {
        validationDiv.innerHTML = '<i class="bi bi-x-circle text-danger me-1"></i>CPF inválido';
        this.classList.add('field-error');
    } else if (cpf) {
        validationDiv.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>CPF válido';
        this.classList.remove('field-error');
        this.classList.add('field-success');
    } else {
        validationDiv.innerHTML = '';
        this.classList.remove('field-error', 'field-success');
    }
});

// Validação do formulário antes do envio
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    let isValid = true;
    const errors = [];
    
    // Validar nome
    const nome = document.querySelector('input[name="nome"]').value.trim();
    if (!nome || nome.length < 2) {
        errors.push('Nome deve ter pelo menos 2 caracteres');
        isValid = false;
    }
    
    // Validar email
    const email = document.querySelector('input[name="email"]').value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
        errors.push('Email deve ser válido');
        isValid = false;
    }
    
    // Validar data de nascimento
    const dataNasc = document.querySelector('input[name="data_nascimento"]').value;
    if (dataNasc) {
        const hoje = new Date();
        const nascimento = new Date(dataNasc);
        const idade = Math.floor((hoje - nascimento) / (365.25 * 24 * 60 * 60 * 1000));
        
        if (idade < 18) {
            errors.push('Usuário deve ter pelo menos 18 anos');
            isValid = false;
        }
    }
    
    // Validar CPF se preenchido
    const cpf = document.querySelector('input[name="cpf"]').value;
    if (cpf && !validarCPF(cpf)) {
        errors.push('CPF deve ser válido');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Corrija os seguintes erros:\n\n' + errors.join('\n'));
        return false;
    }
    
    // Mostrar loading
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Salvando...';
    
    // Restaurar botão após um tempo (caso haja erro)
    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }, 5000);
});

// Confirmação antes de sair com dados não salvos
let formChanged = false;
document.querySelectorAll('input, select, textarea').forEach(input => {
    input.addEventListener('change', () => {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'Você tem alterações não salvas. Deseja realmente sair?';
    }
});

// Remover aviso ao enviar o formulário
document.getElementById('editUserForm').addEventListener('submit', () => {
    formChanged = false;
});

// Auto-save (opcional - salvar rascunho a cada 30 segundos)
let autoSaveTimer;
function startAutoSave() {
    autoSaveTimer = setInterval(() => {
        if (formChanged) {
            saveFormData();
        }
    }, 30000); // 30 segundos
}

function saveFormData() {
    const formData = new FormData(document.getElementById('editUserForm'));
    const data = {};
    for (let [key, value] of formData.entries()) {
        if (key !== 'foto') { // Não salvar arquivo no localStorage
            data[key] = value;
        }
    }
    localStorage.setItem('editUserForm_' + <?= $usuario['id_usuario'] ?>, JSON.stringify(data));
    
    // Mostrar indicador de auto-save
    const indicator = document.createElement('div');
    indicator.className = 'alert alert-info alert-dismissible fade show position-fixed';
    indicator.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 250px;';
    indicator.innerHTML = `
        <i class="bi bi-check-lg me-1"></i>
        Rascunho salvo automaticamente
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        if (indicator.parentNode) {
            indicator.remove();
        }
    }, 3000);
}

function loadFormData() {
    const saved = localStorage.getItem('editUserForm_' + <?= $usuario['id_usuario'] ?>);
    if (saved) {
        const data = JSON.parse(saved);
        Object.keys(data).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input && input.type !== 'file') {
                if (input.type === 'radio') {
                    const radio = document.querySelector(`[name="${key}"][value="${data[key]}"]`);
                    if (radio) radio.checked = true;
                } else {
                    input.value = data[key];
                }
            }
        });
        
        // Mostrar aviso sobre dados salvos
        const alert = document.createElement('div');
        alert.className = 'alert alert-warning alert-dismissible fade show';
        alert.innerHTML = `
            <i class="bi bi-exclamation-triangle me-1"></i>
            Dados de rascunho foram restaurados. 
            <button type="button" class="btn btn-sm btn-outline-dark ms-2" onclick="clearSavedData()">
                Limpar Rascunho
            </button>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container-fluid');
        container.insertBefore(alert, container.firstChild);
    }
}

function clearSavedData() {
    localStorage.removeItem('editUserForm_' + <?= $usuario['id_usuario'] ?>);
    location.reload();
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    loadFormData();
    startAutoSave();
    
    // Focar no primeiro campo com erro
    const firstError = document.querySelector('.field-error');
    if (firstError) {
        firstError.focus();
    }
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl+S para salvar
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('editUserForm').requestSubmit();
    }
    
    // Ctrl+Z para limpar formulário
    if (e.ctrlKey && e.key === 'z' && e.shiftKey) {
        e.preventDefault();
        if (confirm('Deseja restaurar todos os campos para os valores originais?')) {
            location.reload();
        }
    }
});

// Animações e efeitos visuais
function animateSuccess(element) {
    element.style.transform = 'scale(1.05)';
    element.style.transition = 'transform 0.2s ease';
    setTimeout(() => {
        element.style.transform = 'scale(1)';
    }, 200);
}

// Aplicar animação nos campos válidos
document.querySelectorAll('input').forEach(input => {
    input.addEventListener('blur', function() {
        if (this.checkValidity() && this.value) {
            animateSuccess(this);
        }
    });
});

</script>


