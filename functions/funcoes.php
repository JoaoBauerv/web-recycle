<?php
date_default_timezone_set('America/Sao_Paulo');


function removerAcentos($string) {
  $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
  $string = preg_replace('`[^a-zA-Z0-9]`', '', $string); //Remove caracteres não alfanuméricos
  return $string;
}


function registraMovimentacao($id_cadastrou, $id_cadastrado, $descricao, $tipo, $pdo){

$sql = "INSERT INTO registro_movimento (id_usuario_admin, id_usuario_modificado, descricao, tipo, data) VALUES (:id_cadastrou, :id_cadastrado, :descricao, :tipo, :data)";
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
    if (!in_array($permissao, ['Admin', 'Usuario'], true)) {
        $errors['permissao'] = 'Permissão inválida';
    } else {
        $dadosLimpos['permissao'] = $permissao;
    }

    return ['dados' => $dadosLimpos, 'errors' => $errors];
}

// A sanitização de endereço mora em components/endereco.php, junto do SQL que
// grava na tabela: usuário, cliente e fornecedor compartilham a mesma tabela
// 'endereco' e portanto o mesmo tratamento de entrada.

/**
 * Normaliza um CPF ou CNPJ para gravação.
 *
 * Guarda só os dígitos. Devolve string vazia quando o que veio não é um CPF
 * nem um CNPJ válido — o campo é opcional nos três cadastros, e é melhor ficar
 * vazio do que guardar um documento que não existe.
 *
 * Substituiu validarDadosDocumento(), que também tratava rg e cnh: a tabela
 * 'documento' foi removida em 14/10/2026 e nenhum dos dois era usado.
 */
function validarCpfCnpj($valor) {
    $digitos = preg_replace('/[^0-9]/', '', (string) $valor);

    if (strlen($digitos) === 11) {
        return validarCPF($digitos) ? $digitos : '';
    }
    if (strlen($digitos) === 14) {
        return validarCNPJ($digitos) ? $digitos : '';
    }

    return '';
}

function validarCNPJ($cnpj) {
    if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }

    // Os dois dígitos verificadores usam pesos que vão de 2 a 9, ciclicamente.
    foreach ([12, 13] as $posicao) {
        $soma = 0;
        $peso = $posicao - 7;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += $cnpj[$i] * $peso;
            $peso = ($peso - 1) < 2 ? 9 : $peso - 1;
        }
        $digito = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);
        if ($cnpj[$posicao] != $digito) {
            return false;
        }
    }

    return true;
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
    // Este arquivo fica em functions/, então um único ".." chega na raiz do projeto.
    // Sem realpath(): se o diretório ainda não existir, realpath() devolve false e o
    // caminho degenera para a raiz do disco, fazendo o mkdir abaixo nunca ser usado.
    $uploadDir = __DIR__ . '/../images/user/';
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
    $sql = "SELECT COUNT(*) FROM usuario WHERE id_usuario = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() > 0;
}

function verificarEmailUnico($pdo, $email, $idUsuario) {
    // LOWER nos dois lados, igual ao login: sem isso seria possível cadastrar
    // "Joao@x.com" com "joao@x.com" já existente, e o login acharia dois registros.
    $sql = "SELECT COUNT(*) FROM usuario WHERE LOWER(email) = LOWER(:email) AND id_usuario != :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email, ':id' => $idUsuario]);
    return $stmt->fetchColumn() == 0;
}

?>