<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');

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

            // Agrupa por material: o mesmo material pode ter sido adicionado em várias linhas,
            // e o estoque precisa ser validado/baixado pelo total acumulado, não por linha.
            $por_material = [];
            foreach ($itens as $item) {
                $id_material = (int) $item['id_material'];
                $por_material[$id_material] = ($por_material[$id_material] ?? 0) + (float) $item['quantidade'];
            }

            // FOR UPDATE trava a linha até o commit: impede que duas vendas simultâneas
            // vendam o mesmo estoque (o valor da sessão pode estar desatualizado).
            $stmt_saldo = $pdo->prepare("SELECT nm_material, unidade_medida, COALESCE(qt_estoque, 0) AS qt_estoque
                                         FROM tb_material
                                         WHERE id_material = :id_material AND status = 1
                                         FOR UPDATE");

            foreach ($por_material as $id_material => $quantidade_total) {
                $stmt_saldo->execute([':id_material' => $id_material]);
                $material = $stmt_saldo->fetch(PDO::FETCH_ASSOC);

                if (!$material) {
                    throw new Exception("Material #{$id_material} não está mais disponível.");
                }

                if ($quantidade_total > (float) $material['qt_estoque']) {
                    throw new Exception(sprintf(
                        'Estoque insuficiente de %s: há %s %s disponíveis e a venda pede %s %s.',
                        $material['nm_material'],
                        number_format((float) $material['qt_estoque'], 2, ',', '.'),
                        $material['unidade_medida'],
                        number_format($quantidade_total, 2, ',', '.'),
                        $material['unidade_medida']
                    ));
                }
            }

            $total_peso  = array_sum(array_column($itens, 'quantidade'));
            $total_valor = array_sum(array_column($itens, 'valor_total'));

            $stmt_venda = $pdo->prepare("INSERT INTO vendas
                                            (id_fornecedor, id_usuario, total_peso, total_valor, status, data_venda, observacoes)
                                         VALUES
                                            (:id_fornecedor, :id_usuario, :total_peso, :total_valor, 'concluida', NOW(), :observacoes)
                                         RETURNING id_venda");
            $stmt_venda->execute([
                ':id_fornecedor' => $id_fornecedor,
                ':id_usuario'    => $_SESSION['id_usuario'],
                ':total_peso'    => $total_peso,
                ':total_valor'   => $total_valor,
                ':observacoes'   => $observacoes !== '' ? htmlspecialchars($observacoes, ENT_QUOTES, 'UTF-8') : null,
            ]);
            $id_venda = $stmt_venda->fetchColumn();

            $stmt_item = $pdo->prepare("INSERT INTO vendas_itens
                                            (id_venda, id_material, quantidade, preco_un, valor_total, peso_bruto, tara)
                                        VALUES
                                            (:id_venda, :id_material, :quantidade, :preco_un, :valor_total, :peso_bruto, :tara)");

            foreach ($itens as $item) {
                $stmt_item->execute([
                    ':id_venda'    => $id_venda,
                    ':id_material' => $item['id_material'],
                    ':quantidade'  => $item['quantidade'],
                    ':preco_un'    => $item['preco_un'],
                    ':valor_total' => $item['valor_total'],
                    ':peso_bruto'  => $item['quantidade_bruta'] ?? $item['quantidade'],
                    ':tara'        => $item['tara'] ?? 0,
                ]);
            }

            // Venda concluída gera saída de estoque (espelho da entrada gerada pela pesagem).
            $stmt_baixa = $pdo->prepare("UPDATE tb_material
                                         SET qt_estoque = COALESCE(qt_estoque, 0) - :quantidade
                                         WHERE id_material = :id_material
                                         RETURNING nm_material, qt_estoque");

            $stmt_mov = $pdo->prepare("INSERT INTO estoque_movimentacoes
                                          (id_material, tipo, quantidade, origem_tipo, origem_id, saldo_apos, id_usuario, observacoes)
                                       VALUES
                                          (:id_material, 'saida', :quantidade, 'venda', :origem_id, :saldo_apos, :id_usuario, :observacoes)");

            foreach ($por_material as $id_material => $quantidade_total) {
                $stmt_baixa->execute([
                    ':quantidade'  => $quantidade_total,
                    ':id_material' => $id_material,
                ]);
                $baixa = $stmt_baixa->fetch(PDO::FETCH_ASSOC);

                $stmt_mov->execute([
                    ':id_material' => $id_material,
                    ':quantidade'  => $quantidade_total,
                    ':origem_id'   => $id_venda,
                    ':saldo_apos'  => $baixa['qt_estoque'],
                    ':id_usuario'  => $_SESSION['id_usuario'],
                    ':observacoes' => "Venda #{$id_venda} - " . $baixa['nm_material'],
                ]);
            }

            $pdo->commit();

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

            header("Location: $url_base/vendas/listar?msgSucesso=" . urlencode("Venda #{$id_venda} registrada com sucesso!"));
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
