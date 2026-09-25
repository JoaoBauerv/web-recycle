<?php
/**
 * Operações de estoque que não vêm de compra nem de venda.
 *
 * Compra e venda já gravam estoque nos seus próprios controladores; aqui ficam
 * os lançamentos manuais (correção de contagem, perda) e a leitura da posição.
 * A conta de saldo é a mesma em todo o sistema — ver estoqueSinal().
 */

/**
 * Direção de cada tipo de movimentação no saldo.
 *
 * Esta é a definição canônica: qualquer consulta que reconstrua saldo a partir
 * de estoque_movimentacoes precisa usar exatamente estes sinais.
 *
 * Sobre o tipo 'ajuste', aceito pela constraint ck_mov_tipo: ele não é usado.
 * `quantidade` é sempre positiva, então 'ajuste' sozinho não diz se soma ou
 * subtrai. Correção de contagem entra como 'entrada' ou 'saida' conforme a
 * direção, sempre com origem_tipo = 'ajuste_manual', que é o que distingue um
 * lançamento manual de um gerado por documento.
 */
function estoqueSinal(string $tipo): int
{
    return $tipo === 'entrada' ? 1 : -1;
}

/** Tipos que o ajuste manual pode gravar, com rótulo e explicação. */
const ESTOQUE_TIPOS_MANUAIS = [
    'entrada' => ['rotulo' => 'Entrada manual', 'icone' => 'bi-plus-circle', 'ajuda' => 'Sobra na contagem, devolução, material encontrado'],
    'saida'   => ['rotulo' => 'Saída manual',   'icone' => 'bi-dash-circle', 'ajuda' => 'Falta na contagem, uso interno, transferência'],
    'perda'   => ['rotulo' => 'Perda',          'icone' => 'bi-exclamation-triangle', 'ajuda' => 'Quebra, umidade, contaminação, furto'],
];

/** Como cada origem aparece na tela, e para qual documento ela aponta. */
function estoqueRotuloOrigem(string $origem_tipo): array
{
    return [
        'pesagem'       => ['rotulo' => 'Compra',        'icone' => 'bi-cart-plus',  'rota' => 'compras/detalhe'],
        'venda'         => ['rotulo' => 'Venda',         'icone' => 'bi-cart-check', 'rota' => 'vendas/detalhe'],
        'ajuste_manual' => ['rotulo' => 'Ajuste manual', 'icone' => 'bi-pencil',     'rota' => null],
        'processamento' => ['rotulo' => 'Processamento', 'icone' => 'bi-gear',       'rota' => null],
    ][$origem_tipo] ?? ['rotulo' => $origem_tipo, 'icone' => 'bi-circle', 'rota' => null];
}

/**
 * Aplica uma movimentação de estoque e registra a trilha.
 *
 * O chamador abre e fecha a transação, para o lançamento poder participar de uma
 * operação maior sem transação aninhada.
 *
 * O saldo gravado em `saldo_apos` vem do RETURNING do próprio UPDATE, nunca de
 * uma conta em PHP: duas operações simultâneas no mesmo material leriam o mesmo
 * saldo antigo e gravariam uma trilha que não bate com a realidade.
 *
 * @param string $tipo  'entrada', 'saida' ou 'perda'
 * @return array{nm_material:string,unidade_medida:string,saldo_anterior:float,saldo_apos:float}
 * @throws Exception Material inexistente/inativo, quantidade inválida ou saldo negativo.
 */
function estoqueMovimentar(
    PDO $pdo,
    int $id_material,
    string $tipo,
    float $quantidade,
    string $origem_tipo,
    ?int $origem_id,
    int $id_usuario,
    ?string $observacoes = null
): array {
    if (!$pdo->inTransaction()) {
        throw new Exception('estoqueMovimentar() exige uma transação aberta pelo chamador.');
    }
    if (!isset(ESTOQUE_TIPOS_MANUAIS[$tipo]) && $tipo !== 'entrada' && $tipo !== 'saida') {
        throw new Exception("Tipo de movimentação inválido: $tipo");
    }
    if ($quantidade <= 0) {
        throw new Exception('A quantidade precisa ser maior que zero.');
    }

    // FOR UPDATE trava a linha até o commit, pelo mesmo motivo da venda:
    // sem isso dois ajustes simultâneos partem do mesmo saldo.
    $stmt = $pdo->prepare("SELECT nm_material, unidade_medida, COALESCE(qt_estoque, 0) AS qt_estoque
                           FROM tb_material
                           WHERE id_material = :id AND status = 1
                           FOR UPDATE");
    $stmt->execute([':id' => $id_material]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        throw new Exception("Material #{$id_material} não encontrado ou inativo.");
    }

    $saldo_anterior = (float) $material['qt_estoque'];
    $sinal = estoqueSinal($tipo);
    $novo_saldo = $saldo_anterior + ($sinal * $quantidade);

    if ($novo_saldo < 0) {
        throw new Exception(sprintf(
            'A operação deixaria %s com saldo negativo: há %s %s e a saída é de %s %s.',
            $material['nm_material'],
            number_format($saldo_anterior, 2, ',', '.'),
            $material['unidade_medida'],
            number_format($quantidade, 2, ',', '.'),
            $material['unidade_medida']
        ));
    }

    $stmt_saldo = $pdo->prepare("UPDATE tb_material
                                 SET qt_estoque = COALESCE(qt_estoque, 0) + :delta
                                 WHERE id_material = :id
                                 RETURNING qt_estoque");
    $stmt_saldo->execute([':delta' => $sinal * $quantidade, ':id' => $id_material]);
    $saldo_apos = (float) $stmt_saldo->fetchColumn();

    $pdo->prepare("INSERT INTO estoque_movimentacoes
                      (id_material, tipo, quantidade, origem_tipo, origem_id, saldo_apos, id_usuario, observacoes)
                   VALUES
                      (:id_material, :tipo, :quantidade, :origem_tipo, :origem_id, :saldo_apos, :id_usuario, :observacoes)")
        ->execute([
            ':id_material' => $id_material,
            ':tipo'        => $tipo,
            ':quantidade'  => $quantidade,
            ':origem_tipo' => $origem_tipo,
            ':origem_id'   => $origem_id,
            ':saldo_apos'  => $saldo_apos,
            ':id_usuario'  => $id_usuario,
            ':observacoes' => $observacoes !== null && $observacoes !== '' ? $observacoes : null,
        ]);

    return [
        'nm_material'    => $material['nm_material'],
        'unidade_medida' => $material['unidade_medida'],
        'saldo_anterior' => $saldo_anterior,
        'saldo_apos'     => $saldo_apos,
    ];
}

/**
 * Posição de estoque de todos os materiais ativos.
 *
 * A última movimentação sai por subconsulta em vez de JOIN + GROUP BY: agrupar
 * pela tabela de movimentações inflaria as somas se um dia a consulta ganhar
 * outra agregação, e a subconsulta deixa o total por material sempre correto.
 */
function estoquePosicao(PDO $pdo): array
{
    $sql = "SELECT m.id_material, m.nm_material, m.tipo, m.unidade_medida,
                   COALESCE(m.qt_estoque, 0)               AS saldo,
                   m.preco_compra, m.preco_venda, m.estoque_minimo,
                   COALESCE(m.qt_estoque, 0) * m.preco_compra AS valor_imobilizado,
                   (SELECT MAX(em.data_movimentacao)
                      FROM estoque_movimentacoes em
                     WHERE em.id_material = m.id_material) AS ultima_movimentacao,
                   (SELECT COUNT(*)
                      FROM estoque_movimentacoes em
                     WHERE em.id_material = m.id_material) AS total_movimentacoes
            FROM tb_material m
            WHERE m.status = 1
            ORDER BY m.nm_material";

    $materiais = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($materiais as &$m) {
        $m['saldo'] = (float) $m['saldo'];
        $m['valor_imobilizado'] = (float) $m['valor_imobilizado'];
        $m['estoque_minimo'] = $m['estoque_minimo'] !== null ? (float) $m['estoque_minimo'] : null;
        $m['situacao'] = estoqueSituacao($m['saldo'], $m['estoque_minimo']);
    }

    return $materiais;
}

/**
 * Classifica a linha sem precisar de coluna nova no banco.
 *
 * 'baixo' só existe quando há mínimo configurado; sem mínimo, saldo positivo é
 * simplesmente "em estoque" — o sistema não tem como adivinhar o que é pouco.
 */
function estoqueSituacao(float $saldo, ?float $minimo): string
{
    if ($saldo < 0) {
        return 'inconsistente';
    }
    if ($saldo == 0.0) {
        return 'zerado';
    }
    if ($minimo !== null && $saldo <= $minimo) {
        return 'baixo';
    }
    return 'normal';
}

/** Selo de cada situação: [classe css, ícone, rótulo]. */
const ESTOQUE_SELOS = [
    'normal'        => ['bg-success-subtle text-success', 'bi-check-circle', 'Em estoque'],
    'baixo'         => ['bg-warning-subtle text-warning-emphasis', 'bi-exclamation-triangle', 'Estoque baixo'],
    'zerado'        => ['bg-secondary-subtle text-secondary', 'bi-dash-circle', 'Sem estoque'],
    'inconsistente' => ['bg-danger-subtle text-danger', 'bi-x-octagon', 'Inconsistente'],
];

/**
 * Materiais abaixo do mínimo, para o alerta da tela de estoque e do painel inicial.
 */
function estoqueAbaixoDoMinimo(PDO $pdo, int $limite = 0): array
{
    $sql = "SELECT id_material, nm_material, unidade_medida,
                   COALESCE(qt_estoque, 0) AS saldo, estoque_minimo
            FROM tb_material
            WHERE status = 1
              AND estoque_minimo IS NOT NULL
              AND COALESCE(qt_estoque, 0) <= estoque_minimo
            ORDER BY COALESCE(qt_estoque, 0) / NULLIF(estoque_minimo, 0) NULLS FIRST, nm_material";

    if ($limite > 0) {
        $sql .= ' LIMIT ' . $limite;
    }

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
