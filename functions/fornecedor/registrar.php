<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/../../components/endereco.php');

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

            // O endereço é gravado em 'endereco' e o fornecedor guarda só a FK.
            $id_endereco = enderecoSalvar($pdo, null, $_REQUEST);

            $sql = "INSERT INTO fornecedores
                        (nome_razao_social, cpf_cnpj, telefone, email, id_endereco, status, data_cadastro, observacoes)
                    VALUES
                        (:nome, :cpf_cnpj, :telefone, :email, :id_endereco, 1, NOW(), :observacoes)
                    RETURNING id_fornecedor";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cpf_cnpj' => validarCpfCnpj($_REQUEST['cpf_cnpj'] ?? '') ?: null,
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':id_endereco' => $id_endereco,
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

            // Reaproveita a linha de endereço que o fornecedor já apontava, se houver.
            $atual = $pdo->prepare("SELECT id_endereco FROM fornecedores WHERE id_fornecedor = :id");
            $atual->execute([':id' => $id]);
            $id_endereco_atual = $atual->fetchColumn();

            $id_endereco = enderecoSalvar(
                $pdo,
                $id_endereco_atual ? (int) $id_endereco_atual : null,
                $_REQUEST
            );

            $sql = "UPDATE fornecedores SET
                        nome_razao_social = :nome,
                        cpf_cnpj = :cpf_cnpj,
                        telefone = :telefone,
                        email = :email,
                        id_endereco = :id_endereco,
                        observacoes = :observacoes
                    WHERE id_fornecedor = :id";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':cpf_cnpj' => validarCpfCnpj($_REQUEST['cpf_cnpj'] ?? '') ?: null,
                ':telefone' => preg_replace('/[^0-9]/', '', $_REQUEST['telefone'] ?? ''),
                ':email' => trim($_REQUEST['email'] ?? ''),
                ':id_endereco' => $id_endereco,
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
