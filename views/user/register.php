<!-- Sidebar -->
<?php 
require __DIR__ . '/../../functions/funcoes.php';
require __DIR__ . '/../../banco.php';



session_start();

// Verificação de permissão no início
if ($_SESSION['permissao'] !== 'Admin') {

    // Redireciona para página de acesso negado ou index
    header('Location: '.$url_base.'/index2.php?error=access_denied');
    exit;
}

function post_data($field) {
    $_POST[$field] ??= '';
    return htmlspecialchars(stripslashes($_POST[$field]));
}

date_default_timezone_set('America/Sao_Paulo');

define('REQUIRED_FIELD_ERROR', 'É necessario preencher esse campo!');
$errors = [];
$admin = '';
$nome = '';
$sobrenome = '';
$email = '';
$senha = '';
//$senha2 = '123456';
$data = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = post_data('nome');
    $sobrenome = post_data('sobrenome');
    $email = post_data('email');
    $senha = post_data('senha');
    // $senha2 = post_data('senha2');
    $data = post_data('data');
    $admin = post_data('admin'); 
    
    $dataObj = null;
    if ($data) {
        $dataObj = DateTime::createFromFormat('Y-m-d', $data);
        $hoje = new DateTime(date('Y-m-d'));
    }

     
    // Verificar se email já existe para outro usuário
    if (!verificarEmailUnico($pdo, $email, 0)) {
        $errors['email'] = ('Este email já está sendo usado por outro usuário.');
    }

    // Validações
    if (!$nome) {
        $errors['nome'] = REQUIRED_FIELD_ERROR;
    } elseif (strlen($nome) < 3 || strlen($nome) > 16) {
        $errors['nome'] = 'O nome precisa ser entre 3 e 16 caracteres!';
    }
  
    if (!$sobrenome) {
        $errors['sobrenome'] = REQUIRED_FIELD_ERROR;
    } elseif (strlen($sobrenome) < 3 || strlen($sobrenome) > 16) {
        $errors['sobrenome'] = 'O sobrenome precisa ser entre 3 e 16 caracteres!';

    }

    if (!$data) {
        $errors['data'] = REQUIRED_FIELD_ERROR;
    } elseif (!$dataObj) {
        $errors['data'] = 'Data de nascimento inválida';
    } elseif ($dataObj > $hoje) {
        $errors['data'] = 'Data de nascimento inválida';
    } elseif ($dataObj->diff($hoje)->y < 18) {
        $errors['data'] = 'É necessário ter no mínimo 18 anos';
    }

    if (!$email) {
        $errors['email'] = REQUIRED_FIELD_ERROR;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Esse campo precisa ser um email válido!';
    }

    // if (!$senha) {
    //     $errors['senha'] = REQUIRED_FIELD_ERROR;
    // } 
    // elseif (strlen($senha) < 8) {
    //     $errors['senha'] = 'A senha deve ter no mínimo 8 caracteres';
    // } 
    // elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $senha)) {
    //     $errors['senha'] = 'A senha deve conter ao menos: 1 maiúscula, 1 minúscula e 1 número';
    // }

    // if (!$senha2) {
    //     $errors['senha2'] = REQUIRED_FIELD_ERROR;
    // }

    // if ($senha && $senha2 && strcmp($senha, $senha2) !== 0) {
    //     $errors['senha2'] = 'As senhas precisam ser iguais!';
    // }

    // Gera o nome de usuário
    $usuario = '';
    if (!empty($nome) && !empty($sobrenome)) {
        $nomeCompleto = trim($nome . ' ' . $sobrenome);
        $partes = preg_split('/\s+/', $nomeCompleto);

        if (count($partes) > 1) {
            $usuario = removerAcentos($partes[0]) . '-' . removerAcentos($partes[count($partes) - 1]);
        } else {
            $usuario = removerAcentos($nomeCompleto);
        }
    }

    // Se não houver erros, processa o cadastro
    if (empty($errors)) {
        $foto_nome = null;
        
        // Processa upload de arquivo
        if (isset($_FILES['arquivos']['name'][0]) && !empty($_FILES['arquivos']['name'][0])) {
            $file_name = $_FILES['arquivos']['name'][0];
            $tmp_name = $_FILES['arquivos']['tmp_name'][0];
            
            $url = $usuario . '_' . $file_name;
            $uploadDir = realpath(__DIR__ . '/../../images/user/') . DIRECTORY_SEPARATOR;
            $destino = $uploadDir . $url;

            if (move_uploaded_file($tmp_name, $destino)) {
                $foto_nome = $url;
            }
        }

        $dados = [
            'nome_completo' => $nomeCompleto,
            'usuario' => $usuario,
            'email' => $email,
            'senha' => $senha,
            'foto_nome' => $foto_nome,
            'data' => $data,
            'admin' => $admin
        ];

        $query = http_build_query($dados);
        header('Location: '.$url_base.'/functions/user/registrar.php?' . $query);
        exit;
    }
}

include '../../components/sidebar.php';
require_once (__DIR__ . '/../../components/middleware.php');

?>

<!-- Página escura de fundo -->
<div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center">
    <div class="card shadow-lg bg-dark p-4" style="width: 100%; max-width: 800px;">
        <div class="text-center mb-4">
            <img src="../../images/logo.jpg" alt="" style="max-height: 80px;" class="rounded-circle me-2">
            <h3 class="mt-2 text-white">Registrar Usuário</h3>
        </div>

        <form action="" method="POST" enctype="multipart/form-data">
            
            <div class="row">
                <div class="col mb-3">
                    <label for="nome" class="form-label text-white">Nome</label>
                    <input type="text" class="form-control <?php echo isset($errors['nome']) ? 'is-invalid' : '' ?>" 
                           id="nome" name="nome" value="<?php echo $nome ?>" >
                    <div class="invalid-feedback"> 
                        <?php echo $errors['nome'] ?? '' ?>
                    </div>
                </div>


                <div class="col mb-3">
                    <label for="sobrenome" class="form-label text-white">Sobrenome</label>
                    <input type="text" class="form-control <?php echo isset($errors['sobrenome']) ? 'is-invalid' : '' ?>" 
                           id="sobrenome" name="sobrenome" value="<?php echo $sobrenome ?>" >
                    <div class="invalid-feedback"> 
                        <?php echo $errors['sobrenome'] ?? '' ?>
                    </div>

                </div>
            </div>

            <div class="mb-3">

                <label for="email" class="form-label text-white">Email</label>
                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : '' ?>" 
                       id="email" name="email" value="<?php echo $email ?>" >
                <div class="invalid-feedback"> 
                    <?php echo $errors['email'] ?? '' ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="data" class="form-label text-white">Data Nascimento</label>
                <input type="date" class="form-control <?php echo isset($errors['data']) ? 'is-invalid' : '' ?>" 
                       id="data" name="data" value="<?php echo $data ?>" >
                <div class="invalid-feedback"> 
                    <?php echo $errors['data'] ?? '' ?>
                </div>
            </div>

            <!-- <div class="row">
                <div class="col mb-3">
                    <label-- for="senha" class="form-label text-white">Senha</label-->
                    <input type="hidden" class="form-control <?php // echo isset($errors['senha']) ? 'is-invalid' : '' ?>" 
                           id="senha" name="senha" value="123456"<?php //echo $senha ?> >
                    <!--div class="invalid-feedback"> 
                        <?php //echo $errors['senha'] ?? '' ?>
                    </div>
                </div>

                <div class="col mb-3">
                    <label for="senha2" class="form-label text-white">Confirma senha</label>
                    <input type="password" class="form-control <?php //echo isset($errors['senha2']) ? 'is-invalid' : '' ?>" 
                           id="senha2" name="senha2" value="<?php // echo $senha2 ?>" >
                    <div class="invalid-feedback"> 
                        <?php // echo $errors['senha2'] ?? '' ?>
                    </div>
                </div>
            </div> -->

            <div class="mb-3 centralizar-filepond" style="text-align: center;">
                <label for="file" class="form-label text-white">Escolha uma foto de perfil</label>
                <input type="file" id="file" name="arquivos[]" class="filepond" accept="image/*" />
                <?php if (isset($errors['arquivo'])): ?>
                    <div class="invalid-feedback d-block text-danger">
                        <?php echo $errors['arquivo']; ?>
                    </div>
                <?php endif; ?>
            </div>

            <script>
                FilePond.registerPlugin(
                    FilePondPluginFileEncode,
                    FilePondPluginFileValidateType,
                    FilePondPluginImageExifOrientation,
                    FilePondPluginImagePreview,
                    FilePondPluginImageCrop,
                    FilePondPluginImageResize,
                    FilePondPluginImageTransform
                );

                const pond = FilePond.create(document.querySelector('input[type="file"].filepond'), {
                    labelIdle: 'Arraste e solte a imagem ou <span class="filepond--label-action">Navegue</span>',
                    acceptedFileTypes: ['image/*'],
                    allowImagePreview: true,
                    imagePreviewHeight: 100,
                    imageCropAspectRatio: '1:1',
                    imageResizeTargetWidth: 100,
                    imageResizeTargetHeight: 100,
                    stylePanelLayout: 'compact circle',
                    styleLoadIndicatorPosition: 'center bottom',
                    styleButtonRemoveItemPosition: 'center bottom',
                    storeAsFile: true
                });
            </script>

            <input type="hidden" name="admin" id="admin" value="<?= $dados_usuario['id_usuario'] ?? '' ?>">
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-warning">Cadastrar</button>
            </div>
        </form>
    </div>
</div>