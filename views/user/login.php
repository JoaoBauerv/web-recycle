<?php 
require '../../banco.php';

function post_data($field){
  $_POST[$field] ??= '';
  
  return htmlspecialchars(stripslashes($_POST[$field]));
}

define('REQUIRED_FIELD_ERROR', 'É necessario preencher esse campo!');
$errors = [];

$email = '';
$senha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim(post_data('email'));
  $senha = $_POST['senha'];

  // Validações
  if (!$email) {
    $errors['email'] = REQUIRED_FIELD_ERROR;
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Informe um e-mail válido.';
  }

  if (!$senha) {
    $errors['senha'] = REQUIRED_FIELD_ERROR;
  }

  // Se não houver erros, redireciona para o login.php
  if (empty($errors)) {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $_SESSION['login_email'] = $email;
    $_SESSION['senha'] = $senha;

    header('Location: '.$url_base.'/functions/user/login.php');
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reciclagem</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
    <?php include __DIR__ . '/../../components/head-fonts.php'; ?>
    <link href="<?=$url_base?>/css/theme.css" rel="stylesheet">

    <style>
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 1rem;
        }

        .content {
            margin-left: 250px;
            flex: 1;
            padding: 1rem;
        }

        html, body {
            height: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
        }
    </style>
</head>

<body>
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none"></svg>

    <main class="d-flex flex-nowrap">
<?php
include '../../components/sidebar.php'; 


if(empty($_SESSION['logado'])){
?>

<!-- Página escura de fundo -->
<div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center">
  <div class="d-flex flex-column align-items-center gap-3" style="width: 100%; max-width: 500px;">

    <div class="w-100">
      <?php require_once '../../components/alert.php'; ?>
    </div>

    <div class="card shadow-lg p-4" style="width: 100%; max-width: 400px;">

      <div class="text-center mb-4">
        <img src="../../images/logo.jpg" alt="Logo Web Recycle" style="max-height: 80px;" class="rounded-circle me-2">
        <h3 class="mt-2">Login</h3>
      </div>

      <form action="" method="POST" novalidate>
        <div class="mb-3">
          <label for="email" class="form-label">E-mail</label>
          <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : '' ?>"
                 id="email" name="email" value="<?php echo $email ?>"
                 autocomplete="username" placeholder="voce@exemplo.com"
                 aria-describedby="email-erro">
          <div class="invalid-feedback" id="email-erro">
            <?php echo $errors['email'] ?? '' ?>
          </div>
        </div>

        <div class="mb-3">
          <label for="senha" class="form-label">Senha</label>

        <input type="password"  class="form-control <?php echo isset($errors['senha']) ? 'is-invalid' : '' ?>" id="senha" name="senha" aria-describedby="senha-erro">

          <div class="invalid-feedback" id="senha-erro">
        <?php echo $errors['senha'] ?>
          </div>
        </div>

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary">Acessar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php }
else{ ?>

  <div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center bg-light">
    <div class="text-center bg-white p-5 shadow rounded" style="max-width: 500px;">
      <h4 class="text-danger mb-3"><i class="bi bi-shield-lock-fill"></i> Página Indisponível</h4>
      <p class="text-muted">Você já está logado!</p>
      <a href="<?=$url_base?>/index2.php" class="btn btn-primary mt-3">Voltar</a>
    </div>
  </div>

<?php }?>