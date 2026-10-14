<?php
require_once (__DIR__ . '/../../components/middleware.php');
require_once (__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../../banco.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/../../components/endereco.php');

// Este endpoint grava usuario.permissao. Só exigir login não basta: a rota
// usuarios/editar pede Admin, mas o arquivo recebe POST direto, e sem esta
// checagem qualquer usuário comum se promovia a Admin mandando permissao=Admin
// com o próprio id_usuario — o que anularia todo o controle de acesso.
exigirPermissao('usuario.gerenciar', $url_base);
csrfExigir($url_base . '/usuarios');

unset($_SESSION['msg_erro']);
unset($_SESSION['msg_sucesso']);


// Verificar se foi enviado o ID
if (empty($_POST['id_usuario'])) {
    $_SESSION['msg'] = 'ID de usuário não especificado.';
    header('Location: '.$url_base.'/usuarios');
    exit;
}

$id = (int)$_POST['id_usuario']; // Cast para inteiro

try {
    // Verificar se usuário existe
    if (!verificarUsuarioExiste($pdo, $id)) {
        throw new Exception('Usuário não encontrado.');
    }
    
    // Validar dados do usuário
    $validacaoUsuario = validarDadosUsuario($_POST);
    if (!empty($validacaoUsuario['errors'])) {
        $errosString = implode(', ', $validacaoUsuario['errors']);
        throw new Exception("Dados inválidos: " . $errosString);
    }
    
    // Verificar se email já existe para outro usuário
    if (!verificarEmailUnico($pdo, $validacaoUsuario['dados']['email'], $id)) {
        throw new Exception('Este email já está sendo usado por outro usuário.');
    }
    
    // Iniciar transação
    $pdo->beginTransaction();

    // Buscar foto atual para possível remoção
    $sqlFotoAtual = "SELECT foto FROM usuario WHERE id_usuario = :id";
    $stmtFotoAtual = $pdo->prepare($sqlFotoAtual);
    $stmtFotoAtual->execute([':id' => $id]);
    $fotoAtual = $stmtFotoAtual->fetchColumn();

    // Endereço e CPF/CNPJ passaram a ser do próprio usuário: o endereço é uma
    // linha em 'endereco' apontada por usuario.id_endereco, e o documento é uma
    // coluna. A tabela 'documento' (cpf, rg, cnh) não existe mais.
    $stmtEnderecoAtual = $pdo->prepare("SELECT id_endereco FROM usuario WHERE id_usuario = :id");
    $stmtEnderecoAtual->execute([':id' => $id]);
    $idEnderecoAtual = $stmtEnderecoAtual->fetchColumn();

    $idEndereco = enderecoSalvar($pdo, $idEnderecoAtual ? (int) $idEnderecoAtual : null, $_POST);

    // Atualizar dados pessoais
    $sqlUsuario = "UPDATE usuario SET
        nome = :nome,
        email = :email,
        data_nascimento = :data_nascimento,
        telefone = :telefone,
        celular = :celular,
        cpf_cnpj = :cpf_cnpj,
        id_endereco = :id_endereco,
        status = :status,
        permissao = :permissao
        WHERE id_usuario = :id";
    $stmtUsuario = $pdo->prepare($sqlUsuario);
    $stmtUsuario->execute([
        ':nome' => $validacaoUsuario['dados']['nome'],
        ':email' => $validacaoUsuario['dados']['email'],
        ':data_nascimento' => $validacaoUsuario['dados']['data_nascimento'],
        ':telefone' => $validacaoUsuario['dados']['telefone'],
        ':celular' => $validacaoUsuario['dados']['celular'],
        ':cpf_cnpj' => validarCpfCnpj($_POST['cpf_cnpj'] ?? '') ?: null,
        ':id_endereco' => $idEndereco,
        ':status' => $validacaoUsuario['dados']['status'],
        ':permissao' => $validacaoUsuario['dados']['permissao'],
        ':id' => $id
    ]);

    // Processar upload de foto se enviada
    $novaFoto = null;

    $selecionaUsuario = "SELECT usuario FROM usuario WHERE id_usuario = :id";
    $stmt = $pdo->prepare($selecionaUsuario);
    $stmt->execute([':id' => $id]);

    // Buscar resultado como array associativo
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $resultadoUpload = processarUploadFoto($_FILES['foto'], $usuario['usuario']);
        
        if ($resultadoUpload['success']) {
            $novaFoto = $resultadoUpload['filename'];
            
            // Atualizar foto no banco
            $sqlFoto = "UPDATE usuario SET foto = :foto WHERE id_usuario = :id";
            $stmtFoto = $pdo->prepare($sqlFoto);
            $stmtFoto->execute([
                ':foto' => $novaFoto,
                ':id' => $id
            ]);
            
            // Remover foto antiga se existir
            if ($fotoAtual && $fotoAtual !== $novaFoto) {
                $caminhoFotoAntiga = __DIR__ . '/../../images/user/' . $fotoAtual;
                if (file_exists($caminhoFotoAntiga)) {
                    unlink($caminhoFotoAntiga);
                }
            }
        } else {
            throw new Exception('Erro no upload da foto: ' . $resultadoUpload['error']);
        }
    }
    
    // Commit da transação
    $pdo->commit();
    
    // Registrar movimentação
    
    registraMovimentacao($_SESSION['id_usuario'], $id, 'Usuário editado por admin: ' . $_SESSION['id_usuario'], 'Usuario editado', $pdo);
    $_SESSION['msg_sucesso'] = 'Usuário editado com sucesso!';
    header("Location: $url_base/usuarios/editar?id=$id");
    exit;
    
} catch (Exception $e) {
    // Rollback da transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log do erro real
    $_SESSION['msg_erro'] = 'Erro ao editar usuário '. $id . '. ' . $e->getMessage();
    
    // Mensagem genérica para o usuário
    
    header("Location: $url_base/usuarios/editar?id=$id");
    exit;
}
?>