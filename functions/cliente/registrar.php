<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/../../components/endereco.php');

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

            $preco_especial = isset($_REQUEST['preco_especial']) && $_REQUEST['preco_especial'] === 'on';

            // O endereço é gravado em 'endereco' e o cliente guarda só a FK.
            $id_endereco = enderecoSalvar($pdo, null, $_REQUEST);

            $sql = "INSERT INTO clientes
                        (nome, cpf_cnpj, telefone, celular, email, id_endereco, preco_especial, status, data_cadastro)
                    VALUES
                        (:nome, :cpf_cnpj, :telefone, :celular, :email, :id_endereco, :preco_especial, 1, NOW())
                    RETURNING id_cliente";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cpf_cnpj' => validarCpfCnpj($_REQUEST['cpf_cnpj'] ?? '') ?: null,
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':celular' => preg_replace('/[^0-9]/', '', $_REQUEST['celular'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':id_endereco' => $id_endereco,
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

            $preco_especial = isset($_REQUEST['preco_especial']) && $_REQUEST['preco_especial'] === 'on';

            // Reaproveita a linha de endereço que o cliente já apontava, se houver.
            $atual = $pdo->prepare("SELECT id_endereco FROM clientes WHERE id_cliente = :id");
            $atual->execute([':id' => $id]);
            $id_endereco_atual = $atual->fetchColumn();

            $id_endereco = enderecoSalvar(
                $pdo,
                $id_endereco_atual ? (int) $id_endereco_atual : null,
                $_REQUEST
            );

            $sql = "UPDATE clientes SET
                        nome = :nome,
                        cpf_cnpj = :cpf_cnpj,
                        telefone = :telefone,
                        celular = :celular,
                        email = :email,
                        id_endereco = :id_endereco,
                        preco_especial = :preco_especial
                    WHERE id_cliente = :id";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cpf_cnpj' => validarCpfCnpj($_REQUEST['cpf_cnpj'] ?? '') ?: null,
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':celular' => preg_replace('/[^0-9]/', '', $_REQUEST['celular'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':id_endereco' => $id_endereco,
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
