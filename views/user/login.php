<?php 
require '../../banco.php';

function post_data($field){
  $_POST[$field] ??= '';
  
  return htmlspecialchars(stripslashes($_POST[$field]));
}

define('REQUIRED_FIELD_ERROR', 'É necessario preencher esse campo!');
$errors = [];

$user = '';
$senha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = post_data('usuario');
  $senha = post_data('senha');

  // Validações
  if (!$user) {
    $errors['usuario'] = REQUIRED_FIELD_ERROR;
  } 

  if (!$senha) {
    $errors['senha'] = REQUIRED_FIELD_ERROR;
  }

  // Se não houver erros, redireciona para o login.php
  if (empty($errors)) {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    $_SESSION['usuario'] = $user;
    $_SESSION['senha'] = $senha;
    
    header('Location: '.$url_base.'/functions/user/login.php');
    exit;
  }
}

include '../../components/sidebar.php'; 


if(empty($_SESSION['usuario'])){
?>

<!-- Página escura de fundo -->
<div class="container-fluid min-vh-100 d-flex justify-content-center align-items-center">
  <div class="d-flex flex-column align-items-center gap-3" style="width: 100%; max-width: 500px;">

    <div class="w-100">
      <?php require_once '../../components/alert.php'; ?>
    </div>

    <div class="card shadow-lg bg-dark p-4" style="width: 100%; max-width: 400px;">
      
      <div class="text-center mb-4">
        <img src="../../images/logo.jpg" alt="" style="max-height: 80px;" class="rounded-circle me-2">
        <h3 class="mt-2 text-white">Login</h3>
      </div>

      <form action="" method="POST">
        <div class="mb-3">
          <label for="usuario" class="form-label text-white">Usuário</label>
        <input type="text" class="form-control <?php echo isset($errors['usuario']) ? 'is-invalid' : '' ?>" id="usuario" name="usuario" value="<?php echo $user ?>" >
          <div class="invalid-feedback"> 
        <?php echo $errors['usuario'] ?>
          </div>
        </div>

        <div class="mb-3">
          <label for="senha" class="form-label text-white">Senha</label>

        <input type="password"  class="form-control <?php echo isset($errors['senha']) ? 'is-invalid' : '' ?>" id="senha" name="senha" >

          <div class="invalid-feedback"> 
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