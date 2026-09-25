<?php
/**
 * Lançamentos manuais de estoque e configuração do estoque mínimo.
 *
 * Ajuste manual é o único caminho que altera saldo sem um documento por trás,
 * então tudo aqui passa por transação, exige motivo e fica registrado duas vezes:
 * em estoque_movimentacoes (a trilha do material) e em tb_registro_movimento
 * (a trilha de quem fez).
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/estoque_lib.php');

exigirPermissao('estoque.ajustar', $url_base);

$tela = $url_base . '/estoque';

function estoqueVoltar(string $tela, string $tipo, string $mensagem, string $extra = ''): void
{
    header("Location: $tela?msg{$tipo}=" . urlencode($mensagem) . $extra);
    exit;
}

/** Aceita "1.234,56" e "1234.56" — o separador decimal é o último que aparecer. */
function estoqueNumero(string $valor): ?float
{
    $texto = preg_replace('/[^0-9,.\-]/', '', trim($valor));
    if ($texto === '' || $texto === '-') {
        return null;
    }

    $virgula = strrpos($texto, ',');
    $ponto   = strrpos($texto, '.');

    if ($virgula !== false && $ponto !== false) {
        $texto = $virgula > $ponto
            ? str_replace(',', '.', str_replace('.', '', $texto))
            : str_replace(',', '', $texto);
    } elseif ($virgula !== false) {
        $texto = str_replace(',', '.', $texto);
    }

    return is_numeric($texto) ? (float) $texto : null;
}

switch ($_REQUEST['acao'] ?? '') {

    // --------------------------------------------------- lançamento manual
    case 'movimentar':
        $id_material = (int) ($_POST['id_material'] ?? 0);
        $tipo        = $_POST['tipo'] ?? '';
        $quantidade  = estoqueNumero($_POST['quantidade'] ?? '');
        $motivo      = trim($_POST['observacoes'] ?? '');

        $volta = $_POST['voltar_para'] === 'extrato' && $id_material
            ? $url_base . '/estoque/extrato?id=' . $id_material
            : $tela;

        if (!$id_material) {
            estoqueVoltar($volta, 'Erro', 'Selecione o material.');
        }
        if (!isset(ESTOQUE_TIPOS_MANUAIS[$tipo])) {
            estoqueVoltar($volta, 'Erro', 'Tipo de lançamento inválido.');
        }
        if ($quantidade === null || $quantidade <= 0) {
            estoqueVoltar($volta, 'Erro', 'Informe uma quantidade maior que zero.');
        }
        if ($motivo === '') {
            // Ajuste sem motivo vira um saldo que ninguém sabe explicar depois.
            estoqueVoltar($volta, 'Erro', 'Descreva o motivo do lançamento.');
        }

        try {
            $pdo->beginTransaction();

            $resultado = estoqueMovimentar(
                $pdo,
                $id_material,
                $tipo,
                $quantidade,
                'ajuste_manual',
                null,
                (int) $_SESSION['id_usuario'],
                ESTOQUE_TIPOS_MANUAIS[$tipo]['rotulo'] . ': ' . $motivo
            );

            $pdo->commit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            estoqueVoltar($volta, 'Erro', $e->getMessage());
        }

        registraMovimentacao(
            $_SESSION['id_usuario'],
            $_SESSION['id_usuario'],
            sprintf('%s de %s %s em %s (saldo %s -> %s). Motivo: %s',
                ESTOQUE_TIPOS_MANUAIS[$tipo]['rotulo'],
                number_format($quantidade, 2, ',', '.'),
                $resultado['unidade_medida'],
                $resultado['nm_material'],
                number_format($resultado['saldo_anterior'], 2, ',', '.'),
                number_format($resultado['saldo_apos'], 2, ',', '.'),
                $motivo
            ),
            'Ajuste Estoque',
            $pdo
        );

        estoqueVoltar($volta, 'Sucesso', sprintf(
            '%s registrada: %s agora tem %s %s.',
            ESTOQUE_TIPOS_MANUAIS[$tipo]['rotulo'],
            $resultado['nm_material'],
            number_format($resultado['saldo_apos'], 2, ',', '.'),
            $resultado['unidade_medida']
        ));

    // ------------------------------------------------- contagem/inventário
    case 'inventariar':
        $id_material = (int) ($_POST['id_material'] ?? 0);
        $contado     = estoqueNumero($_POST['saldo_contado'] ?? '');
        $motivo      = trim($_POST['observacoes'] ?? '');

        $volta = $_POST['voltar_para'] === 'extrato' && $id_material
            ? $url_base . '/estoque/extrato?id=' . $id_material
            : $tela;

        if (!$id_material) {
            estoqueVoltar($volta, 'Erro', 'Selecione o material.');
        }
        if ($contado === null || $contado < 0) {
            estoqueVoltar($volta, 'Erro', 'Informe o saldo contado (zero ou mais).');
        }

        try {
            $pdo->beginTransaction();

            $atual = $pdo->prepare("SELECT nm_material, unidade_medida, COALESCE(qt_estoque,0) AS qt_estoque
                                    FROM tb_material WHERE id_material = ? AND status = 1 FOR UPDATE");
            $atual->execute([$id_material]);
            $material = $atual->fetch(PDO::FETCH_ASSOC);

            if (!$material) {
                throw new Exception('Material não encontrado ou inativo.');
            }

            $diferenca = round($contado - (float) $material['qt_estoque'], 2);

            if (abs($diferenca) < 0.005) {
                $pdo->rollBack();
                estoqueVoltar($volta, 'Sucesso', sprintf(
                    'Contagem confere: %s já estava com %s %s. Nada foi lançado.',
                    $material['nm_material'],
                    number_format($contado, 2, ',', '.'),
                    $material['unidade_medida']
                ));
            }

            // A contagem vira entrada ou saída conforme a direção da diferença:
            // é assim que o saldo continua reconstruível a partir da trilha.
            $tipo = $diferenca > 0 ? 'entrada' : 'saida';

            $resultado = estoqueMovimentar(
                $pdo,
                $id_material,
                $tipo,
                abs($diferenca),
                'ajuste_manual',
                null,
                (int) $_SESSION['id_usuario'],
                sprintf('Contagem de inventário: sistema %s, contado %s%s',
                    number_format((float) $material['qt_estoque'], 2, ',', '.'),
                    number_format($contado, 2, ',', '.'),
                    $motivo !== '' ? ". $motivo" : '')
            );

            $pdo->commit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            estoqueVoltar($volta, 'Erro', $e->getMessage());
        }

        registraMovimentacao(
            $_SESSION['id_usuario'],
            $_SESSION['id_usuario'],
            sprintf('Inventário de %s: saldo %s -> %s (%s%s)',
                $resultado['nm_material'],
                number_format($resultado['saldo_anterior'], 2, ',', '.'),
                number_format($resultado['saldo_apos'], 2, ',', '.'),
                $diferenca > 0 ? '+' : '',
                number_format($diferenca, 2, ',', '.')
            ),
            'Ajuste Estoque',
            $pdo
        );

        estoqueVoltar($volta, 'Sucesso', sprintf(
            'Inventário lançado: %s ajustado em %s%s %s, saldo agora %s %s.',
            $resultado['nm_material'],
            $diferenca > 0 ? '+' : '',
            number_format($diferenca, 2, ',', '.'),
            $resultado['unidade_medida'],
            number_format($resultado['saldo_apos'], 2, ',', '.'),
            $resultado['unidade_medida']
        ));

    // ----------------------------------------------------- estoque mínimo
    case 'minimo':
        $id_material = (int) ($_POST['id_material'] ?? 0);
        $bruto = trim($_POST['estoque_minimo'] ?? '');

        if (!$id_material) {
            estoqueVoltar($tela, 'Erro', 'Material não informado.');
        }

        // Campo vazio desliga o alerta; zero é um mínimo legítimo ("avise ao zerar").
        $minimo = $bruto === '' ? null : estoqueNumero($bruto);

        if ($bruto !== '' && ($minimo === null || $minimo < 0)) {
            estoqueVoltar($tela, 'Erro', 'O estoque mínimo precisa ser um número igual ou maior que zero.');
        }

        $pdo->prepare("UPDATE tb_material SET estoque_minimo = :minimo WHERE id_material = :id AND status = 1")
            ->execute([':minimo' => $minimo, ':id' => $id_material]);

        $nome = $pdo->query("SELECT nm_material FROM tb_material WHERE id_material = $id_material")->fetchColumn();

        estoqueVoltar($tela, 'Sucesso', $minimo === null
            ? "Alerta de estoque mínimo desligado para {$nome}."
            : sprintf('Estoque mínimo de %s definido em %s.', $nome, number_format($minimo, 2, ',', '.')));

    default:
        header("Location: $tela");
        exit;
}
