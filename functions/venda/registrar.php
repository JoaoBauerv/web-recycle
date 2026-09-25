<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/venda_lib.php');

switch ($_REQUEST['acao'] ?? '') {

    case 'finalizar':

        $id_fornecedor = (int) ($_SESSION['venda_fornecedor'] ?? 0);
        $itens = $_SESSION['venda_itens'] ?? [];
        $observacoes = trim($_REQUEST['observacoes'] ?? '');

        if (!$id_fornecedor) {
            header("Location: $url_base/vendas?msgErro=" . urlencode('Selecione o fornecedor antes de finalizar a venda.'));
            exit;
        }

        if (empty($itens)) {
            header("Location: $url_base/vendas?msgErro=" . urlencode('Adicione pelo menos um material à venda.'));
            exit;
        }

        try {
            $pdo->beginTransaction();

            // A venda manual e a importada gravam pelo mesmo caminho, então o
            // estoque só tem uma lógica de baixa no sistema inteiro.
            $id_venda = vendaCriar($pdo, $id_fornecedor, $itens, [
                'id_usuario'  => $_SESSION['id_usuario'],
                'observacoes' => $observacoes,
                'origem'      => 'manual',
            ]);

            $pdo->commit();

            $total_peso  = array_sum(array_column($itens, 'quantidade'));
            $total_valor = array_sum(array_column($itens, 'valor_total'));

            registraMovimentacao(
                $_SESSION['id_usuario'],
                $_SESSION['id_usuario'],
                sprintf('Venda #%d registrada: R$ %s (%s kg)',
                    $id_venda,
                    number_format($total_valor, 2, ',', '.'),
                    number_format($total_peso, 2, ',', '.')
                ),
                'Registro Venda',
                $pdo
            );

            unset($_SESSION['venda_itens'], $_SESSION['venda_fornecedor']);

            header("Location: $url_base/vendas/detalhe?id={$id_venda}&msgSucesso=" . urlencode("Venda #{$id_venda} registrada com sucesso!"));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: $url_base/vendas?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    default:
        header("Location: $url_base/vendas?msgErro=" . urlencode('Ação inválida.'));
    break;
}
