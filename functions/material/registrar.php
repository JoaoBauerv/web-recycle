<?php
session_start();
require_once(__DIR__ . '/../../banco.php');
require_once(__DIR__ . '/../funcoes.php');

unset($_SESSION['msg_erro']);
unset($_SESSION['msg_sucesso']);

$nome = ucwords(strtolower($_REQUEST['nome']));

switch($_REQUEST['acao']){

    case 'cadastrar':

        try {
            $sql = "INSERT INTO tb_material (nm_material, tipo, qt_estoque, preco_compra, preco_especial) 
                    VALUES (:nome, :categoria, 0, :preco, :preco_especial)";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':categoria' => $_REQUEST['categoria'],
                ':medida' => $_REQUEST['medida'],
                ':preco' => $_REQUEST['preco_compra'],
                ':preco_especial' => $_REQUEST['preco_especial']
            );

            // Verifica se foi enviado via POST (admin logado cadastrando outro)

            if ($stmt->execute($dados)) {
                
                header("Location: ../../views/material/index.php?msgSucesso=Cadastro realizado com sucesso!");
                
            } else {
                header("Location: ../../views/material/create.php?msgErro=Erro ao executar o cadastro.");
            }

        } catch (Exception $e) {
            // Rollback da transação
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Log do erro real
            $_SESSION['msg_erro'] = 'Erro ao cadastrar usuário' . $e->getMessage();
            
            // Mensagem genérica para o usuário
            
            header("Location: $url_base/views/material/create.php?");
            exit;
        }

    break;

    case 'editar':

            try {
            $sql = "UPDATE tb_material SET nm_material = :nome , tipo = :categoria, preco_compra = :preco, preco_especial = :preco_especial WHERE
                    id_material = ".$_REQUEST['id']."";
            $stmt = $pdo->prepare($sql);

            $dados = array(
                ':nome' => $nome,
                ':categoria' => $_REQUEST['categoria'],
                ':preco' => $_REQUEST['preco_compra'],
                ':preco_especial' => $_REQUEST['preco_especial']
            );

            // Verifica se foi enviado via POST (admin logado cadastrando outro)

            if ($stmt->execute($dados)) {
                
                header("Location: ../../views/material/index.php?msgSucesso=Atualizado material com sucesso!");
                
            } else {
                header("Location: ../../views/material/index.php?msgErro=Erro ao executar edicao.");
            }

        } catch (Exception $e) {
            // Rollback da transação
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Log do erro real
            $_SESSION['msg_erro'] = 'Erro ao cadastrar usuário' . $e->getMessage();
            
            // Mensagem genérica para o usuário
            
            header("Location: $url_base/views/material/index.php?");
            exit;
        }


    break;

    case 'excluir':
        
        try {
            $sql = "UPDATE tb_material SET status = 0
                    WHERE id_material = ".$_REQUEST['id']."";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            header("Location: ../../views/material/index.php?msgErro=Material excluído com sucesso!");
            
        } catch (Exception $e) {
            // Rollback da transação
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Log do erro real
            $_SESSION['msg_erro'] = 'Erro ao cadastrar usuário' . $e->getMessage();
            
            // Mensagem genérica para o usuário
            
            header("Location: $url_base/views/material/index.php?");
            exit;
        }

    break;


    exit;
    
}



?>
