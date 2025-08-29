<?php
session_start();
require_once(__DIR__ . '/../../banco.php');
require_once(__DIR__ . '/../funcoes.php');




$nomeCompleto = ucwords(strtolower($_REQUEST['nome_completo'] ?? ''));
$usuario = $_REQUEST['usuario'] ?? '';
$email = $_REQUEST['email'] ?? '';
$senha = $_REQUEST['senha'] ?? '';
$foto = $_REQUEST['foto_nome'] ?? '';
$data = $_REQUEST['data'] ?? '';
$admin = $_REQUEST['admin'] ?? '';
$permissao = $_REQUEST['permissao'] ?? 'Usuario';
$precisa_alterar_senha = 1;



// $nome_final_arquivo = $usuario . '_' . $foto;
// $url_arquivo =  $nome_final_arquivo;
// var_dump($_REQUEST['foto_nome']);
// exit;

// var_dump($usuario);
// var_dump($_SESSION['foto_nome']);
// var_dump($nome_final_arquivo);

// Grava no banco
try {
    $sql = "INSERT INTO tb_usuario (nome, email, senha, foto, usuario, data_nascimento, permissao, precisa_alterar_senha) 
            VALUES (:nome, :email, :senha, :foto, :usuario, :data, :permissao, :precisa_alterar_senha)";
    $stmt = $pdo->prepare($sql);

    $dados = array(
        ':nome' => $nomeCompleto,
        ':email' => $email,
        ':senha' => password_hash($senha, PASSWORD_DEFAULT),
        ':foto' => $foto,
        ':usuario' => $usuario,
        ':data' => $data,
        ':permissao' => $permissao,
        ':precisa_alterar_senha' => $precisa_alterar_senha
    );

    // Verifica se foi enviado via POST (admin logado cadastrando outro)

    if ($stmt->execute($dados)) {
        $sql = "SELECT * FROM tb_usuario ORDER BY id_usuario DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $id_cadastrado = $stmt->fetch(PDO::FETCH_ASSOC);

        $sql = "INSERT INTO tb_documento (id_usuario) VALUES (:id_cadastrado)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_cadastrado', $id_cadastrado['id_usuario'], PDO::PARAM_STR);
        $stmt->execute();

        
        $sql = "INSERT INTO tb_endereco (id_usuario) VALUES (:id_cadastrado)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_cadastrado', $id_cadastrado['id_usuario'], PDO::PARAM_STR);
        $stmt->execute();
        
        // if($id_cadastrado['id_usuario'] === 1){
        //     $sql = "UPDATE tb_usuario SET permissao = 'Admin' WHERE id_usuario = :id_cadastrado";
        //     $stmt = $pdo->prepare($sql);
        //     $stmt->bindValue(':id_cadastrado', $id_cadastrado['id_usuario'], PDO::PARAM_STR);
        //     $stmt->execute();
            
        // }
        
        if (empty($admin)) {
            // Cadastro por usuário comum
            header("Location: ../../views/user/login.php?msgSucesso=Cadastro realizado com sucesso! Realize o login agora!");
        } else {
            // Cadastro feito por admin logado
 

            registraMovimentacao($admin, $id_cadastrado['id_usuario'], 'Usuario criado por admin: ' . $admin, 'Cadastro Usuario', $pdo);

            header("Location: ../../views/user/admin.php?msgSucesso=Cadastro realizado com sucesso!");
        }
    } else {
        header("Location: ../../views/user/register.php?msgErro=Erro ao executar o cadastro.");
    }

} catch (Exception $e) {
    // Rollback da transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log do erro real
    $_SESSION['msg_erro'] = 'Erro ao cadastrar usuário' . $e->getMessage();
    
    // Mensagem genérica para o usuário
    
    header("Location: $url_base/views/user/register.php?");
    exit;
}

exit;

?>
