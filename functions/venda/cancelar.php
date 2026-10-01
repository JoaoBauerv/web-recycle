<?php
/**
 * Cancela uma venda e devolve ao estoque o material que ela baixou.
 *
 * Cancelar venda SOMA estoque, então nunca é recusado por saldo — é o espelho
 * do cancelamento de compra, que subtrai e pode ser recusado.
 *
 * Depois do estorno, tb_material.preco_venda é recalculado ignorando vendas
 * canceladas: aquela coluna guarda o último preço praticado, e o preço de uma
 * venda que não aconteceu não serve de referência para a próxima.
 *
 * Decisão de 01/10/2026: o pedido_externo NÃO é liberado. A venda cancelada
 * continua dona daquele número, e reimportar o mesmo pedido segue bloqueado.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/../estoque/estoque_lib.php');
require_once(__DIR__ . '/venda_lib.php');

exigirPermissao('venda.cancelar', $url_base);
csrfExigir($url_base . '/vendas/listar');

$id_venda = (int) ($_POST['id_venda'] ?? 0);
$motivo   = trim((string) ($_POST['motivo'] ?? ''));

$voltar = $id_venda
    ? $url_base . '/vendas/detalhe?id=' . $id_venda
    : $url_base . '/vendas/listar';

/**
 * Volta para a tela de origem com a mensagem no padrão do components/alert.php.
 *
 * O separador é escolhido na hora: a URL de volta já leva ?id=, e grudar outro
 * '?' produziria ...detalhe?id=5?msgErro=..., que o PHP lê como id = "5?msgErro".
 */
function vendaCancelarVoltar(string $url, string $chave, string $mensagem): void
{
    $separador = str_contains($url, '?') ? '&' : '?';
    header("Location: $url$separador$chave=" . urlencode($mensagem));
    exit;
}

if (!$id_venda) {
    vendaCancelarVoltar($url_base . '/vendas/listar', 'msgErro', 'Venda não informada.');
}

if ($motivo === '') {
    vendaCancelarVoltar($voltar, 'msgErro', 'Informe o motivo do cancelamento.');
}
if (mb_strlen($motivo) > 500) {
    vendaCancelarVoltar($voltar, 'msgErro', 'O motivo deve ter no máximo 500 caracteres.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id_venda, status, total_valor, total_peso, data_venda, pedido_externo
                           FROM vendas
                           WHERE id_venda = :id
                           FOR UPDATE");
    $stmt->execute([':id' => $id_venda]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venda) {
        throw new Exception('Venda não encontrada.');
    }
    if ($venda['status'] === 'cancelada') {
        throw new Exception('Esta venda já está cancelada.');
    }

    $stmt_itens = $pdo->prepare("SELECT id_material, SUM(quantidade) AS quantidade_total
                                 FROM vendas_itens
                                 WHERE id_venda = :id
                                 GROUP BY id_material");
    $stmt_itens->execute([':id' => $id_venda]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        throw new Exception('Esta venda não tem itens para estornar.');
    }

    // Estorno: a venda deu 'saida', o cancelamento dá 'entrada' da mesma
    // quantidade. Entrada nunca deixa saldo negativo, então aqui não há o risco
    // que existe no cancelamento de compra.
    $estornados = [];
    $materiais  = [];
    foreach ($itens as $item) {
        $quantidade = (float) $item['quantidade_total'];
        if ($quantidade <= 0) {
            continue;
        }

        $resultado = estoqueMovimentar(
            $pdo,
            (int) $item['id_material'],
            'entrada',
            $quantidade,
            'estorno_venda',
            $id_venda,
            (int) $_SESSION['id_usuario'],
            // observacoes é varchar(255) e o motivo pode ter até 500: corta aqui.
            // O motivo completo fica em vendas.motivo_cancelamento, que é para
            // onde o origem_id desta linha aponta.
            mb_substr("Estorno do cancelamento da venda #{$id_venda}: {$motivo}", 0, 255)
        );

        $estornados[] = $resultado['nm_material'];
        $materiais[]  = (int) $item['id_material'];
    }

    $pdo->prepare("UPDATE vendas
                   SET status = 'cancelada',
                       cancelada_em = NOW(),
                       id_usuario_cancelou = :id_usuario,
                       motivo_cancelamento = :motivo
                   WHERE id_venda = :id")
        ->execute([
            ':id_usuario' => $_SESSION['id_usuario'],
            ':motivo'     => $motivo,
            ':id'         => $id_venda,
        ]);

    // Recalcula depois do UPDATE: a função já filtra vendas canceladas, então
    // precisa ver esta venda com o status novo para desconsiderá-la.
    vendaAtualizarUltimoPreco($pdo, $materiais);

    // descricao é varchar(100), então a mensagem é curta e NÃO repete o motivo:
    // ele fica em vendas.motivo_cancelamento, junto com a data e o autor.
    registraMovimentacao(
        $_SESSION['id_usuario'],
        $_SESSION['id_usuario'],
        sprintf('Venda #%d cancelada: R$ %s, %s kg devolvidos',
            $id_venda,
            number_format((float) $venda['total_valor'], 2, ',', '.'),
            number_format((float) $venda['total_peso'], 2, ',', '.')
        ),
        'Cancelamento Venda',
        $pdo
    );

    $pdo->commit();

    vendaCancelarVoltar($voltar, 'msgSucesso', sprintf(
        'Venda #%d cancelada. Estoque devolvido: %s.',
        $id_venda,
        implode(', ', $estornados)
    ));

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    vendaCancelarVoltar($voltar, 'msgErro', $e->getMessage());
}
