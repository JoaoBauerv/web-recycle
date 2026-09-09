<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');

unset($_SESSION['msg_erro']);
unset($_SESSION['msg_sucesso']);

switch ($_REQUEST['acao']) {

    case 'cadastrar':

        try {
            $nome = ucwords(strtolower(trim($_REQUEST['nome_razao_social'])));

            if (empty($nome)) {
                header("Location: $url_base/fornecedores/novo?msgErro=Nome/Razão Social é obrigatório.");
                exit;
            }

            $endereco = validarDadosEndereco($_REQUEST);

            $sql = "INSERT INTO fornecedores
                        (nome_razao_social, cnpj_cpf, telefone, email, cep, logradouro, numero, complemento, bairro, cidade, estado, status, data_cadastro, observacoes)
                    VALUES
                        (:nome, :cnpj_cpf, :telefone, :email, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, 1, NOW(), :observacoes)
                    RETURNING id_fornecedor";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cnpj_cpf' => preg_replace('/[^0-9]/', '', $_REQUEST['cnpj_cpf'] ?? ''),
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':cep' => $endereco['cep'],
                ':logradouro' => $endereco['logradouro'],
                ':numero' => $endereco['numero'],
                ':complemento' => $endereco['complemento'],
                ':bairro' => $endereco['bairro'],
                ':cidade' => $endereco['cidade'],
                ':estado' => strtoupper(trim($_REQUEST['estado'] ?? '')),
                ':observacoes' => htmlspecialchars(trim($_REQUEST['observacoes'] ?? ''), ENT_QUOTES, 'UTF-8')
            );

            if ($stmt->execute($dados)) {
                $id_fornecedor = $stmt->fetchColumn();

                registraMovimentacao(
                    $_SESSION['id_usuario'],
                    $_SESSION['id_usuario'],
                    "Fornecedor cadastrado: {$nome} (#{$id_fornecedor})",
                    'Cadastro Fornecedor',
                    $pdo
                );

                header("Location: $url_base/fornecedores?msgSucesso=Fornecedor cadastrado com sucesso!");
            } else {
                header("Location: $url_base/fornecedores/novo?msgErro=Erro ao executar o cadastro.");
            }

        } catch (Exception $e) {
            $_SESSION['msg_erro'] = 'Erro ao cadastrar fornecedor: ' . $e->getMessage();
            header("Location: $url_base/fornecedores/novo?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    case 'editar':

        try {
            $id = (int) $_REQUEST['id'];
            $nome = ucwords(strtolower(trim($_REQUEST['nome_razao_social'])));

            $endereco = validarDadosEndereco($_REQUEST);

            $sql = "UPDATE fornecedores SET
                        nome_razao_social = :nome,
                        cnpj_cpf = :cnpj_cpf,
                        telefone = :telefone,
                        email = :email,
                        cep = :cep,
                        logradouro = :logradouro,
                        numero = :numero,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        estado = :estado,
                        observacoes = :observacoes
                    WHERE id_fornecedor = :id";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cnpj_cpf' => preg_replace('/[^0-9]/', '', $_REQUEST['cnpj_cpf'] ?? ''),
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':cep' => $endereco['cep'],
                ':logradouro' => $endereco['logradouro'],
                ':numero' => $endereco['numero'],
                ':complemento' => $endereco['complemento'],
                ':bairro' => $endereco['bairro'],
                ':cidade' => $endereco['cidade'],
                ':estado' => strtoupper(trim($_REQUEST['estado'] ?? '')),
                ':observacoes' => htmlspecialchars(trim($_REQUEST['observacoes'] ?? ''), ENT_QUOTES, 'UTF-8'),
                ':id' => $id
            );

            if ($stmt->execute($dados)) {
                registraMovimentacao(
                    $_SESSION['id_usuario'],
                    $_SESSION['id_usuario'],
                    "Fornecedor editado: {$nome} (#{$id})",
                    'Edição Fornecedor',
                    $pdo
                );

                header("Location: $url_base/fornecedores?msgSucesso=Fornecedor atualizado com sucesso!");
            } else {
                header("Location: $url_base/fornecedores?msgErro=Erro ao executar edição.");
            }

        } catch (Exception $e) {
            $_SESSION['msg_erro'] = 'Erro ao editar fornecedor: ' . $e->getMessage();
            header("Location: $url_base/fornecedores?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    case 'excluir':

        try {
            $id = (int) $_REQUEST['id'];

            $sql = "UPDATE fornecedores SET status = 0 WHERE id_fornecedor = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            registraMovimentacao(
                $_SESSION['id_usuario'],
                $_SESSION['id_usuario'],
                "Fornecedor excluído (#{$id})",
                'Exclusão Fornecedor',
                $pdo
            );

            header("Location: $url_base/fornecedores?msgSucesso=Fornecedor excluído com sucesso!");

        } catch (Exception $e) {
            $_SESSION['msg_erro'] = 'Erro ao excluir fornecedor: ' . $e->getMessage();
            header("Location: $url_base/fornecedores?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    default:
        header("Location: $url_base/fornecedores?msgErro=Ação inválida.");
    break;
}
?>
