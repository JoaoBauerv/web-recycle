<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');

unset($_SESSION['msg_erro']);
unset($_SESSION['msg_sucesso']);

switch ($_REQUEST['acao']) {

    case 'cadastrar':

        try {
            $nome = ucwords(strtolower(trim($_REQUEST['nome'])));

            if (empty($nome)) {
                header("Location: $url_base/clientes/novo?msgErro=Nome é obrigatório.");
                exit;
            }

            $endereco = validarDadosEndereco($_REQUEST);
            $documento = validarDadosDocumento($_REQUEST);
            $preco_especial = isset($_REQUEST['preco_especial']) && $_REQUEST['preco_especial'] === 'on';

            $sql = "INSERT INTO clientes
                        (nome, cpf_cnpj, telefone, celular, email, cep, logradouro, numero, complemento, bairro, cidade, estado, preco_especial, status, data_cadastro)
                    VALUES
                        (:nome, :cpf_cnpj, :telefone, :celular, :email, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :preco_especial, 1, NOW())
                    RETURNING id_cliente";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cpf_cnpj' => !empty($documento['cpf']) ? $documento['cpf'] : ($_REQUEST['cpf_cnpj'] ?? null),
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':celular' => preg_replace('/[^0-9]/', '', $_REQUEST['celular'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':cep' => $endereco['cep'],
                ':logradouro' => $endereco['logradouro'],
                ':numero' => $endereco['numero'],
                ':complemento' => $endereco['complemento'],
                ':bairro' => $endereco['bairro'],
                ':cidade' => $endereco['cidade'],
                ':estado' => strtoupper(trim($_REQUEST['estado'] ?? '')),
                ':preco_especial' => $preco_especial ? 't' : 'f'
            );

            if ($stmt->execute($dados)) {
                $id_cliente = $stmt->fetchColumn();

                registraMovimentacao(
                    $_SESSION['id_usuario'],
                    $_SESSION['id_usuario'],
                    "Cliente cadastrado: {$nome} (#{$id_cliente})",
                    'Cadastro Cliente',
                    $pdo
                );

                header("Location: $url_base/clientes?msgSucesso=Cliente cadastrado com sucesso!");
            } else {
                header("Location: $url_base/clientes/novo?msgErro=Erro ao executar o cadastro.");
            }

        } catch (Exception $e) {
            $_SESSION['msg_erro'] = 'Erro ao cadastrar cliente: ' . $e->getMessage();
            header("Location: $url_base/clientes/novo?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    case 'editar':

        try {
            $id = (int) $_REQUEST['id'];
            $nome = ucwords(strtolower(trim($_REQUEST['nome'])));

            $endereco = validarDadosEndereco($_REQUEST);
            $documento = validarDadosDocumento($_REQUEST);
            $preco_especial = isset($_REQUEST['preco_especial']) && $_REQUEST['preco_especial'] === 'on';

            $sql = "UPDATE clientes SET
                        nome = :nome,
                        cpf_cnpj = :cpf_cnpj,
                        telefone = :telefone,
                        celular = :celular,
                        email = :email,
                        cep = :cep,
                        logradouro = :logradouro,
                        numero = :numero,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        estado = :estado,
                        preco_especial = :preco_especial
                    WHERE id_cliente = :id";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cpf_cnpj' => !empty($documento['cpf']) ? $documento['cpf'] : ($_REQUEST['cpf_cnpj'] ?? null),
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':celular' => preg_replace('/[^0-9]/', '', $_REQUEST['celular'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':cep' => $endereco['cep'],
                ':logradouro' => $endereco['logradouro'],
                ':numero' => $endereco['numero'],
                ':complemento' => $endereco['complemento'],
                ':bairro' => $endereco['bairro'],
                ':cidade' => $endereco['cidade'],
                ':estado' => strtoupper(trim($_REQUEST['estado'] ?? '')),
                ':preco_especial' => $preco_especial ? 't' : 'f',
                ':id' => $id
            );

            if ($stmt->execute($dados)) {
                registraMovimentacao(
                    $_SESSION['id_usuario'],
                    $_SESSION['id_usuario'],
                    "Cliente editado: {$nome} (#{$id})",
                    'Edição Cliente',
                    $pdo
                );

                header("Location: $url_base/clientes?msgSucesso=Cliente atualizado com sucesso!");
            } else {
                header("Location: $url_base/clientes?msgErro=Erro ao executar edição.");
            }

        } catch (Exception $e) {
            $_SESSION['msg_erro'] = 'Erro ao editar cliente: ' . $e->getMessage();
            header("Location: $url_base/clientes?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    case 'excluir':

        try {
            $id = (int) $_REQUEST['id'];

            $sql = "UPDATE clientes SET status = 0 WHERE id_cliente = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            registraMovimentacao(
                $_SESSION['id_usuario'],
                $_SESSION['id_usuario'],
                "Cliente excluído (#{$id})",
                'Exclusão Cliente',
                $pdo
            );

            header("Location: $url_base/clientes?msgSucesso=Cliente excluído com sucesso!");

        } catch (Exception $e) {
            $_SESSION['msg_erro'] = 'Erro ao excluir cliente: ' . $e->getMessage();
            header("Location: $url_base/clientes?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    default:
        header("Location: $url_base/clientes?msgErro=Ação inválida.");
    break;
}
?>
