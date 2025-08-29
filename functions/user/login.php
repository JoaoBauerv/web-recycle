<?php
session_start();
require_once(__DIR__ . '/../../banco.php');

if (!empty($_SESSION)) {
    // Pegando os dados enviados pelo formulário
    $usuario = $_SESSION['usuario'];
    $senha = $_SESSION['senha'];

    // Preparar a query (protege contra SQL Injection)
    $stmt = $pdo->prepare("SELECT * FROM tb_usuario WHERE usuario = :usuario AND status = 1");
    $stmt->bindParam(':usuario', $usuario);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        
    // Verifica se o usuário existe e a senha está correta
    if ($user && password_verify($senha, $user['senha'])) {
      $_SESSION['usuario'] = $_SESSION['usuario'];
      $_SESSION['id_usuario'] = $user['id_usuario'] ;
      $_SESSION['permissao'] = $user['permissao'];
      $_SESSION['logado'] = 1;
      unset($_SESSION['senha']);

       if ($user['precisa_alterar_senha'] == 1) {
          // Marcar na sessão que precisa alterar senha
          $_SESSION['precisa_alterar_senha'] = 1;
          $_SESSION['msg_aviso'] = 'Por motivos de segurança, você deve alterar sua senha antes de continuar.';
          header('Location: '.$url_base.'/views/user/alterar_senha.php');
          exit;
      }else{
          header("Location: ../../index2.php?msgSucesso=Login realizado com sucesso!");
          exit;
      }
    
      

    } else {
        session_destroy();
        header("Location: ../../views/user/login.php?msgErro=Usuario ou senha errados!");
        
    }
}

?>