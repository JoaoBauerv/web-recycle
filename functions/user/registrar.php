<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');

exigirPermissao('usuario.gerenciar', $url_base);

// A tela de cadastro entrega os dados pela sessão (a senha não pode trafegar na
// URL) e já validou o token CSRF antes de redirecionar para cá. Nada é lido de
// $_REQUEST: o fallback que existia aqui deixava qualquer requisição escolher o
// próprio valor de 'permissao' e se cadastrar como Admin.
$entrada = $_SESSION['novo_usuario'] ?? [];
unset($_SESSION['novo_usuario']);

if (!$entrada) {
    header("Location: $url_base/usuarios/novo?msgErro=" . urlencode('Sessão expirada. Preencha o cadastro novamente.'));
    exit;
}

function dadoCadastro(array $entrada, string $campo, string $padrao = '')
{
    return $entrada[$campo] ?? $padrao;
}

$nomeCompleto = ucwords(strtolower(dadoCadastro($entrada, 'nome_completo')));
$usuario = dadoCadastro($entrada, 'usuario');
$email = dadoCadastro($entrada, 'email');
$senha = dadoCadastro($entrada, 'senha');
$foto = dadoCadastro($entrada, 'foto_nome');
$data = dadoCadastro($entrada, 'data');

// Quem cria é sempre o Admin logado, conferido acima.
$admin = $_SESSION['id_usuario'];

// Perfil não é escolhido no cadastro: todo usuário nasce como 'Usuario' e a
// promoção para Admin passa pela tela de edição, que é auditada à parte.
$permissao = 'Usuario';
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
        
        registraMovimentacao($admin, $id_cadastrado['id_usuario'], 'Usuario criado por admin: ' . $admin, 'Cadastro Usuario', $pdo);

        header("Location: $url_base/usuarios?msgSucesso=Cadastro realizado com sucesso!");
    } else {
        header("Location: $url_base/usuarios/novo?msgErro=Erro ao executar o cadastro.");
    }

} catch (Exception $e) {
    // Rollback da transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log do erro real
    $_SESSION['msg_erro'] = 'Erro ao cadastrar usuário' . $e->getMessage();
    
    // Mensagem genérica para o usuário
    
    header("Location: $url_base/usuarios/novo?");
    exit;
}

exit;

?>
