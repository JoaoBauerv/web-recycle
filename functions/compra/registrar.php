<?php
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../funcoes.php');

switch ($_REQUEST['acao'] ?? '') {

    case 'finalizar':

        $id_cliente = (int) ($_SESSION['compra_cliente'] ?? 0);
        $itens = $_SESSION['compra_itens'] ?? [];

        if (!$id_cliente) {
            header("Location: $url_base/compras?msgErro=" . urlencode('Selecione o cliente antes de finalizar a compra.'));
            exit;
        }

        if (empty($itens)) {
            header("Location: $url_base/compras?msgErro=" . urlencode('Pese pelo menos um material antes de finalizar.'));
            exit;
        }

        try {
            $pdo->beginTransaction();

            $total_peso  = array_sum(array_column($itens, 'peso'));
            $total_valor = array_sum(array_column($itens, 'valor_total'));

            $stmt_compra = $pdo->prepare("INSERT INTO tb_pesagem (id_cliente, total_valor, total_peso, data_pesagem)
                                          VALUES (:id_cliente, :total_valor, :total_peso, NOW())
                                          RETURNING id_pesagem");
            $stmt_compra->execute([
                ':id_cliente'  => $id_cliente,
                ':total_valor' => $total_valor,
                ':total_peso'  => $total_peso,
            ]);
            $id_compra = $stmt_compra->fetchColumn();

            $stmt_item = $pdo->prepare("INSERT INTO tb_pesagem_material (id_pesagem, id_material, preco_un, peso_material, peso_bruto, tara, obs)
                                        VALUES (:id_pesagem, :id_material, :preco_un, :peso_material, :peso_bruto, :tara, :obs)");

            foreach ($itens as $item) {
                $stmt_item->execute([
                    ':id_pesagem'    => $id_compra,
                    ':id_material'   => $item['id_material'],
                    ':preco_un'      => $item['preco_un'],
                    ':peso_material' => $item['peso'],
                    ':peso_bruto'    => $item['peso_bruto'] ?? $item['peso'],
                    ':tara'          => $item['tara'] ?? 0,
                    ':obs'           => $item['observacoes'] !== '' ? $item['observacoes'] : null,
                ]);
            }

            // Agrupa por material para gerar uma única movimentação de estoque por material,
            // mesmo que ele tenha sido pesado em várias etapas da mesma compra.
            $por_material = [];
            foreach ($itens as $item) {
                $id_material = (int) $item['id_material'];
                $por_material[$id_material] = ($por_material[$id_material] ?? 0) + (float) $item['peso'];
            }

            // Compra concluída gera entrada no estoque (espelho da saída gerada pela venda).
            $stmt_entrada = $pdo->prepare("UPDATE tb_material
                                           SET qt_estoque = COALESCE(qt_estoque, 0) + :peso
                                           WHERE id_material = :id_material
                                           RETURNING nm_material, qt_estoque");

            // origem_tipo continua 'pesagem': é o valor aceito pela constraint ck_mov_origem no banco.
            $stmt_mov = $pdo->prepare("INSERT INTO estoque_movimentacoes
                                          (id_material, tipo, quantidade, origem_tipo, origem_id, saldo_apos, id_usuario, observacoes)
                                       VALUES
                                          (:id_material, 'entrada', :quantidade, 'pesagem', :origem_id, :saldo_apos, :id_usuario, :observacoes)");

            foreach ($por_material as $id_material => $peso_total) {
                $stmt_entrada->execute([
                    ':peso'        => $peso_total,
                    ':id_material' => $id_material,
                ]);
                $entrada = $stmt_entrada->fetch(PDO::FETCH_ASSOC);

                $stmt_mov->execute([
                    ':id_material' => $id_material,
                    ':quantidade'  => $peso_total,
                    ':origem_id'   => $id_compra,
                    ':saldo_apos'  => $entrada['qt_estoque'],
                    ':id_usuario'  => $_SESSION['id_usuario'],
                    ':observacoes' => "Compra #{$id_compra} - " . $entrada['nm_material'],
                ]);
            }

            $pdo->commit();

            registraMovimentacao(
                $_SESSION['id_usuario'],
                $_SESSION['id_usuario'],
                sprintf('Compra #%d registrada: R$ %s (%s kg)',
                    $id_compra,
                    number_format($total_valor, 2, ',', '.'),
                    number_format($total_peso, 2, ',', '.')
                ),
                'Registro Compra',
                $pdo
            );

            unset($_SESSION['compra_itens'], $_SESSION['compra_cliente']);

            // Leva direto para o comprovante da compra recém-salva (dados buscados do banco pelo ID,
            // não reaproveitados da sessão) em vez de voltar para a listagem.
            header("Location: $url_base/compras/comprovante?id={$id_compra}&novo=1");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header("Location: $url_base/compras?msgErro=" . urlencode($e->getMessage()));
            exit;
        }

    break;

    default:
        header("Location: $url_base/compras?msgErro=" . urlencode('Ação inválida.'));
    break;
}
