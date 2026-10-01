<?php
/**
 * Cancela uma compra (tb_pesagem) e estorna o estoque que ela deu entrada.
 *
 * Cancelar compra SUBTRAI estoque: o material nunca entrou de verdade. Se ele
 * já foi revendido ou baixado, o saldo ficaria negativo e a operação é recusada
 * — estoqueMovimentar() barra, e aqui a mensagem explica o porquê.
 *
 * O documento não é apagado: fica com status = 'cancelada', visível na listagem
 * e no detalhe, com motivo, data e autor. O que sai são as somas: painel,
 * relatório de compras e análise de margem passam a ignorá-lo.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/../estoque/estoque_lib.php');

exigirPermissao('compra.cancelar', $url_base);
csrfExigir($url_base . '/compras/listar');

$id_compra = (int) ($_POST['id_pesagem'] ?? 0);
$motivo    = trim((string) ($_POST['motivo'] ?? ''));

$voltar = $id_compra
    ? $url_base . '/compras/detalhe?id=' . $id_compra
    : $url_base . '/compras/listar';

/**
 * Volta para a tela de origem com a mensagem no padrão do components/alert.php.
 *
 * O separador é escolhido na hora: a URL de volta já leva ?id=, e grudar outro
 * '?' produziria ...detalhe?id=5?msgErro=..., que o PHP lê como id = "5?msgErro".
 */
function compraCancelarVoltar(string $url, string $chave, string $mensagem): void
{
    $separador = str_contains($url, '?') ? '&' : '?';
    header("Location: $url$separador$chave=" . urlencode($mensagem));
    exit;
}

if (!$id_compra) {
    compraCancelarVoltar($url_base . '/compras/listar', 'msgErro', 'Compra não informada.');
}

// O motivo é o que justifica o estorno para quem auditar depois; sem ele o
// cancelamento não conta história nenhuma. A constraint ck_pesagem_cancelamento
// exige o mesmo no banco.
if ($motivo === '') {
    compraCancelarVoltar($voltar, 'msgErro', 'Informe o motivo do cancelamento.');
}
if (mb_strlen($motivo) > 500) {
    compraCancelarVoltar($voltar, 'msgErro', 'O motivo deve ter no máximo 500 caracteres.');
}

try {
    $pdo->beginTransaction();

    // FOR UPDATE trava o documento: sem isso dois cancelamentos simultâneos do
    // mesmo documento estornariam o estoque duas vezes.
    $stmt = $pdo->prepare("SELECT id_pesagem, status, total_valor, total_peso, data_pesagem
                           FROM tb_pesagem
                           WHERE id_pesagem = :id
                           FOR UPDATE");
    $stmt->execute([':id' => $id_compra]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$compra) {
        throw new Exception('Compra não encontrada.');
    }
    if ($compra['status'] === 'cancelada') {
        throw new Exception('Esta compra já está cancelada.');
    }

    // Agrupa por material, igual ao registro da compra: o mesmo material pode
    // ter sido pesado em várias linhas, e o estorno é pelo total.
    $stmt_itens = $pdo->prepare("SELECT id_material, SUM(peso_material) AS peso_total
                                 FROM tb_pesagem_material
                                 WHERE id_pesagem = :id
                                 GROUP BY id_material");
    $stmt_itens->execute([':id' => $id_compra]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        throw new Exception('Esta compra não tem itens para estornar.');
    }

    // Estorno: a compra deu 'entrada', o cancelamento dá 'saida' do mesmo peso.
    $estornados = [];
    foreach ($itens as $item) {
        $peso = (float) $item['peso_total'];
        if ($peso <= 0) {
            continue;
        }

        $resultado = estoqueMovimentar(
            $pdo,
            (int) $item['id_material'],
            'saida',
            $peso,
            'estorno_compra',
            $id_compra,
            (int) $_SESSION['id_usuario'],
            // observacoes é varchar(255) e o motivo pode ter até 500: corta aqui.
            // O motivo completo fica em tb_pesagem.motivo_cancelamento, que é
            // para onde o origem_id desta linha aponta.
            mb_substr("Estorno do cancelamento da compra #{$id_compra}: {$motivo}", 0, 255)
        );

        $estornados[] = $resultado['nm_material'];
    }

    $pdo->prepare("UPDATE tb_pesagem
                   SET status = 'cancelada',
                       cancelada_em = NOW(),
                       id_usuario_cancelou = :id_usuario,
                       motivo_cancelamento = :motivo
                   WHERE id_pesagem = :id")
        ->execute([
            ':id_usuario' => $_SESSION['id_usuario'],
            ':motivo'     => $motivo,
            ':id'         => $id_compra,
        ]);

    // descricao é varchar(100), então a mensagem é curta e NÃO repete o motivo:
    // ele fica em tb_pesagem.motivo_cancelamento, junto com a data e o autor.
    registraMovimentacao(
        $_SESSION['id_usuario'],
        $_SESSION['id_usuario'],
        sprintf('Compra #%d cancelada: R$ %s, %s kg estornados',
            $id_compra,
            number_format((float) $compra['total_valor'], 2, ',', '.'),
            number_format((float) $compra['total_peso'], 2, ',', '.')
        ),
        'Cancelamento Compra',
        $pdo
    );

    $pdo->commit();

    compraCancelarVoltar($voltar, 'msgSucesso', sprintf(
        'Compra #%d cancelada. Estoque estornado: %s.',
        $id_compra,
        implode(', ', $estornados)
    ));

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    compraCancelarVoltar($voltar, 'msgErro', $e->getMessage());
}
