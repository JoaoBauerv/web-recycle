<?php
date_default_timezone_set('America/Sao_Paulo');


function removerAcentos($string) {
  $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
  $string = preg_replace('`[^a-zA-Z0-9]`', '', $string); //Remove caracteres não alfanuméricos
  return $string;
}


function registraMovimentacao($id_cadastrou, $id_cadastrado, $descricao, $tipo, $pdo){

$sql = "INSERT INTO tb_registro_movimento (id_usuario_admin, id_usuario_modificado, descricao, tipo, data) VALUES (:id_cadastrou, :id_cadastrado, :descricao, :tipo, :data)";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id_cadastrou', $id_cadastrou, PDO::PARAM_STR);
$stmt->bindValue(':id_cadastrado', $id_cadastrado, PDO::PARAM_STR);
$stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
$stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
$stmt->bindValue(':data', date('Y-m-d H:i:s'), PDO::PARAM_STR);
$stmt->execute();
}


// Função para validar e sanitizar dados
function validarDadosUsuario($dados) {
    $errors = [];
    $dadosLimpos = [];

    // Nome
    $nome = trim($dados['nome'] ?? '');
    if (empty($nome)) {
        $errors['nome'] = 'Nome é obrigatório';
    } elseif (strlen($nome) < 2 || strlen($nome) > 100) {
        $errors['nome'] = 'Nome deve ter entre 2 e 100 caracteres';
    } else {
        $dadosLimpos['nome'] = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    }

    // Email
    $email = trim($dados['email'] ?? '');
    if (empty($email)) {
        $errors['email'] = 'Email é obrigatório';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email inválido';
    } else {
        $dadosLimpos['email'] = $email;
    }

    // Data de nascimento
    $dataNascimento = $dados['data_nascimento'] ?? '';
    if (empty($dataNascimento)) {
        $errors['data_nascimento'] = 'Data de nascimento é obrigatória';
    } else {
        $data = DateTime::createFromFormat('Y-m-d', $dataNascimento);
        $hoje = new DateTime();
        
        if (!$data || $data > $hoje) {
            $errors['data_nascimento'] = 'Data de nascimento inválida';
        } elseif ($data->diff($hoje)->y < 18) {
            $errors['data_nascimento'] = 'Usuário deve ter pelo menos 18 anos';
        } else {
            $dadosLimpos['data_nascimento'] = $dataNascimento;
        }
    }

    // Telefones (opcionais)
    $dadosLimpos['telefone'] = preg_replace('/[^0-9]/', '', $dados['telefone'] ?? '');
    $dadosLimpos['celular'] = preg_replace('/[^0-9]/', '', $dados['celular'] ?? '');

    // Status
    $status = $dados['status'] ?? '';
    if (!in_array($status, [ 1, 0 ])) {
        $errors['status'] = 'Status inválido';
    } else {
        $dadosLimpos['status'] = $status;
    }

    // Permissão
    $permissao = $dados['permissao'] ?? '';
    if (!in_array($permissao, ['Admin', 'Usuario', 'Gerente'])) {
        $errors['permissao'] = 'Permissão inválida';
    } else {
        $dadosLimpos['permissao'] = $permissao;
    }

    return ['dados' => $dadosLimpos, 'errors' => $errors];
}

function validarDadosEndereco($dados) {
    $dadosLimpos = [];
    
    // CEP
    $cep = preg_replace('/[^0-9]/', '', $dados['cep'] ?? '');
    if (strlen($cep) === 8) {
        $dadosLimpos['cep'] = $cep;
    } else {
        $dadosLimpos['cep'] = '';
    }
    
    $dadosLimpos['logradouro'] = htmlspecialchars(trim($dados['logradouro'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dadosLimpos['numero'] = htmlspecialchars(trim($dados['numero'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dadosLimpos['complemento'] = htmlspecialchars(trim($dados['complemento'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dadosLimpos['cidade'] = htmlspecialchars(trim($dados['cidade'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dadosLimpos['bairro'] = htmlspecialchars(trim($dados['bairro'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dadosLimpos['referencia'] = htmlspecialchars(trim($dados['referencia'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    return $dadosLimpos;
}

function validarDadosDocumento($dados) {
    $dadosLimpos = [];
    
    // CPF
    $cpf = preg_replace('/[^0-9]/', '', $dados['cpf'] ?? '');
    if (strlen($cpf) === 11 && validarCPF($cpf)) {
        $dadosLimpos['cpf'] = $cpf;
    } else {
        $dadosLimpos['cpf'] = '';
    }
    
    // RG
    $dadosLimpos['rg'] = preg_replace('/[^0-9X]/', '', strtoupper($dados['rg'] ?? ''));
    
    // CNH
    $dadosLimpos['cnh'] = preg_replace('/[^0-9]/', '', $dados['cnh'] ?? '');
    
    return $dadosLimpos;
}

function validarCPF($cpf) {
    // Implementação básica de validação de CPF
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

function processarUploadFoto($arquivo, $usuario) {
    // Usar a função melhorada do exemplo anterior
    $uploadDir = realpath(__DIR__ . '/../../images/user/') . DIRECTORY_SEPARATOR;
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png', 
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    try {
        if (!isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload do arquivo');
        }
        
        if ($arquivo['size'] > $maxSize) {
            throw new Exception('Arquivo muito grande. Máximo 5MB permitido');
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($arquivo['tmp_name']);
        
        if (!array_key_exists($mimeType, $allowedTypes)) {
            throw new Exception('Tipo de arquivo não permitido');
        }
        
        $imageInfo = getimagesize($arquivo['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Arquivo não é uma imagem válida');
        }
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Erro ao criar diretório de upload');
            }
        }
        
        if (!is_writable($uploadDir)) {
            throw new Exception('Diretório sem permissão de escrita');
        }
        
        $extensao = $allowedTypes[$mimeType];
        $nomeArquivo =  $usuario . '_' . time() . '_' . uniqid() . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new Exception('Erro ao salvar arquivo no servidor');
        }
        
        return [
            'success' => true,
            'filename' => $nomeArquivo,
            'path' => $caminhoCompleto
        ];
        
    } catch (Exception $e) {
        error_log("Erro no upload: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function verificarUsuarioExiste($pdo, $id) {
    $sql = "SELECT COUNT(*) FROM tb_usuario WHERE id_usuario = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

function verificarEmailUnico($pdo, $email, $idUsuario) {
    $sql = "SELECT COUNT(*) FROM tb_usuario WHERE email = :email AND id_usuario != :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email, ':id' => $idUsuario]);
    return $stmt->fetchColumn() == 0;
}

?>