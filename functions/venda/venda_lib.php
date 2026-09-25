<?php
/**
 * Criação de venda — caminho único para a venda manual e a importada.
 *
 * Toda venda passa por aqui, então só existe uma lógica de estoque no sistema:
 * a validação com FOR UPDATE, a baixa em tb_material e o registro em
 * estoque_movimentacoes acontecem sempre do mesmo jeito, venha a venda da tela
 * ou de uma planilha.
 */

/**
 * Recalcula `tb_material.preco_venda` como o preço da ÚLTIMA venda do material.
 *
 * A coluna não é tabela de preço: cada venda é negociada, então ela guarda só o
 * último valor praticado, para a tela sugerir por onde começar. Por isso o valor
 * é derivado das vendas existentes em vez de simplesmente receber o preço da
 * venda recém-gravada — uma venda lançada com data retroativa (caso comum na
 * importação) não pode sobrescrever um preço mais recente.
 *
 * Material sem nenhuma venda volta a NULL: sem histórico, não há o que sugerir.
 *
 * @param int[] $ids_material Materiais a recalcular.
 */
function vendaAtualizarUltimoPreco(PDO $pdo, array $ids_material): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids_material))));
    if (!$ids) {
        return;
    }

    // Inteiros já saneados acima; interpolar aqui evita montar arrays do Postgres.
    $lista = implode(',', $ids);

    // DISTINCT ON pega a primeira linha de cada material na ordenação, que aqui
    // é a venda mais recente (data, depois id, para empate no mesmo instante).
    $pdo->exec("
        UPDATE tb_material m
        SET preco_venda = u.preco_un
        FROM (
            SELECT DISTINCT ON (vi.id_material) vi.id_material, vi.preco_un
            FROM vendas_itens vi
            JOIN vendas v ON v.id_venda = vi.id_venda
            WHERE vi.id_material IN ($lista)
            ORDER BY vi.id_material, v.data_venda DESC, v.id_venda DESC, vi.id_venda_item DESC
        ) u
        WHERE m.id_material = u.id_material
    ");

    $pdo->exec("
        UPDATE tb_material
        SET preco_venda = NULL
        WHERE id_material IN ($lista)
          AND NOT EXISTS (SELECT 1 FROM vendas_itens WHERE id_material = tb_material.id_material)
    ");
}

/**
 * Grava a venda, seus itens, a baixa de estoque e as movimentações.
 *
 * O chamador é quem abre e fecha a transação: a importação precisa gravar o
 * histórico no mesmo commit da venda, e transação aninhada em PDO não existe.
 * Lançar exceção aqui faz o chamador dar rollback de tudo.
 *
 * @param array $itens    Cada item: id_material, quantidade (líquida), preco_un,
 *                        valor_total e, opcionalmente, quantidade_bruta e tara.
 * @param array $opcoes   id_usuario (obrigatório), observacoes, origem,
 *                        pedido_externo, id_importacao.
 * @return int            id_venda gerado.
 * @throws Exception      Material indisponível ou estoque insuficiente.
 */
function vendaCriar(PDO $pdo, int $id_fornecedor, array $itens, array $opcoes): int
{
    if (!$pdo->inTransaction()) {
        throw new Exception('vendaCriar() exige uma transação aberta pelo chamador.');
    }
    if (!$id_fornecedor) {
        throw new Exception('Venda sem fornecedor.');
    }
    if (empty($itens)) {
        throw new Exception('Venda sem itens.');
    }

    $id_usuario = (int) ($opcoes['id_usuario'] ?? 0);
    if (!$id_usuario) {
        throw new Exception('Venda sem usuário responsável.');
    }

    // Agrupa por material: o mesmo material pode vir em várias linhas (pesagens
    // diferentes), e o estoque tem de ser validado pelo total, não por linha.
    $por_material = [];
    foreach ($itens as $item) {
        $id_material = (int) $item['id_material'];
        $por_material[$id_material] = ($por_material[$id_material] ?? 0) + (float) $item['quantidade'];
    }

    // FOR UPDATE trava a linha até o commit: sem isso, duas vendas simultâneas
    // conseguiriam vender o mesmo saldo, porque ambas leriam o valor antigo.
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

    $observacoes = trim((string) ($opcoes['observacoes'] ?? ''));

    $stmt_venda = $pdo->prepare("INSERT INTO vendas
                                    (id_fornecedor, id_usuario, total_peso, total_valor, status,
                                     data_venda, observacoes, origem, pedido_externo, id_importacao)
                                 VALUES
                                    (:id_fornecedor, :id_usuario, :total_peso, :total_valor, 'concluida',
                                     COALESCE(:data_venda, NOW()), :observacoes, :origem, :pedido_externo, :id_importacao)
                                 RETURNING id_venda");
    $stmt_venda->execute([
        ':id_fornecedor'  => $id_fornecedor,
        ':id_usuario'     => $id_usuario,
        ':total_peso'     => $total_peso,
        ':total_valor'    => $total_valor,
        ':data_venda'     => $opcoes['data_venda'] ?? null,
        ':observacoes'    => $observacoes !== '' ? htmlspecialchars($observacoes, ENT_QUOTES, 'UTF-8') : null,
        ':origem'         => $opcoes['origem'] ?? 'manual',
        ':pedido_externo' => $opcoes['pedido_externo'] ?? null,
        ':id_importacao'  => $opcoes['id_importacao'] ?? null,
    ]);
    $id_venda = (int) $stmt_venda->fetchColumn();

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
            ':id_usuario'  => $id_usuario,
            ':observacoes' => "Venda #{$id_venda} - " . $baixa['nm_material'],
        ]);
    }

    // Guarda o preço praticado para a próxima venda ter de onde partir.
    vendaAtualizarUltimoPreco($pdo, array_keys($por_material));

    return $id_venda;
}
