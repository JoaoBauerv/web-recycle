<?php
session_start();
require_once (__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../banco.php');
require_once(__DIR__ . '/../funcoes.php');

unset($_SESSION['msg_erro']);
unset($_SESSION['msg_sucesso']);


// Verificar se foi enviado o ID
if (empty($_POST['id_usuario'])) {
    $_SESSION['msg'] = 'ID de usuário não especificado.';
    header('Location: '.$url_base.'/views/user/admin.php');
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
    
    // Validar outros dados
    $dadosEndereco = validarDadosEndereco($_POST);
    $dadosDocumento = validarDadosDocumento($_POST);
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Buscar foto atual para possível remoção
    $sqlFotoAtual = "SELECT foto FROM tb_usuario WHERE id_usuario = :id";
    $stmtFotoAtual = $pdo->prepare($sqlFotoAtual);
    $stmtFotoAtual->execute([':id' => $id]);
    $fotoAtual = $stmtFotoAtual->fetchColumn();
    
    // Atualizar dados pessoais
    $sqlUsuario = "UPDATE tb_usuario SET 
        nome = :nome, 
        email = :email, 
        data_nascimento = :data_nascimento, 
        telefone = :telefone,
        celular = :celular,
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
        ':status' => $validacaoUsuario['dados']['status'],
        ':permissao' => $validacaoUsuario['dados']['permissao'],
        ':id' => $id
    ]);
    
    // Verificar se endereço existe, senão criar
    $sqlVerificaEndereco = "SELECT COUNT(*) FROM tb_endereco WHERE id_usuario = :id";
    $stmtVerificaEndereco = $pdo->prepare($sqlVerificaEndereco);
    $stmtVerificaEndereco->execute([':id' => $id]);
    
    if ($stmtVerificaEndereco->fetchColumn() > 0) {
        // Atualizar endereço existente
        $sqlEndereco = "UPDATE tb_endereco SET 
            cep = :cep, 
            logradouro = :logradouro, 
            numero = :numero, 
            complemento = :complemento, 
            cidade = :cidade, 
            bairro = :bairro,
            referencia = :referencia
            WHERE id_usuario = :id";
    } else {
        // Inserir novo endereço
        $sqlEndereco = "INSERT INTO tb_endereco 
            (id_usuario, cep, logradouro, numero, complemento, cidade, bairro, referencia) 
            VALUES (:id, :cep, :logradouro, :numero, :complemento, :cidade, :bairro, :referencia)";
    }
    
    $stmtEndereco = $pdo->prepare($sqlEndereco);
    $stmtEndereco->execute([
        ':cep' => $dadosEndereco['cep'],
        ':logradouro' => $dadosEndereco['logradouro'],
        ':numero' => $dadosEndereco['numero'],
        ':complemento' => $dadosEndereco['complemento'],
        ':cidade' => $dadosEndereco['cidade'],
        ':bairro' => $dadosEndereco['bairro'],
        ':referencia' => $dadosEndereco['referencia'],
        ':id' => $id
    ]);
    
    // Mesma lógica para documentos
    $sqlVerificaDocumento = "SELECT COUNT(*) FROM tb_documento WHERE id_usuario = :id";
    $stmtVerificaDocumento = $pdo->prepare($sqlVerificaDocumento);
    $stmtVerificaDocumento->execute([':id' => $id]);
    
    if ($stmtVerificaDocumento->fetchColumn() > 0) {
        $sqlDocumento = "UPDATE tb_documento SET 
            cpf = :cpf, 
            rg = :rg, 
            cnh = :cnh 
            WHERE id_usuario = :id";
    } else {
        $sqlDocumento = "INSERT INTO tb_documento 
            (id_usuario, cpf, rg, cnh) 
            VALUES (:id, :cpf, :rg, :cnh)";
    }
    
    $stmtDocumento = $pdo->prepare($sqlDocumento);
    $stmtDocumento->execute([
        ':cpf' => $dadosDocumento['cpf'],
        ':rg' => $dadosDocumento['rg'],
        ':cnh' => $dadosDocumento['cnh'],
        ':id' => $id
    ]);
    
    // Processar upload de foto se enviada
    $novaFoto = null;

    $selecionaUsuario = "SELECT usuario FROM tb_usuario WHERE id_usuario = :id";
    $stmt = $pdo->prepare($selecionaUsuario);
    $stmt->execute([':id' => $id]);

    // Buscar resultado como array associativo
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $resultadoUpload = processarUploadFoto($_FILES['foto'], $usuario['usuario']);
        
        if ($resultadoUpload['success']) {
            $novaFoto = $resultadoUpload['filename'];
            
            // Atualizar foto no banco
            $sqlFoto = "UPDATE tb_usuario SET foto = :foto WHERE id_usuario = :id";
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
    exit;
    $_SESSION['msg_sucesso'] = 'Usuário editado com sucesso!';
    header("Location: $url_base/views/user/edit.php?id=$id");
    exit;
    
} catch (Exception $e) {
    // Rollback da transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log do erro real
    $_SESSION['msg_erro'] = 'Erro ao editar usuário '. $id . '. ' . $e->getMessage();
    
    // Mensagem genérica para o usuário
    
    header("Location: $url_base/views/user/edit.php?id=$id");
    exit;
}
?>