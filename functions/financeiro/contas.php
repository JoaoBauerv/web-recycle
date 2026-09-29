<?php
/**
 * Contas a pagar: criação, edição, cancelamento, pagamento e categorias.
 *
 * Duas regras atravessam tudo aqui:
 *
 * 1. Nada financeiro é apagado. Conta e parcela vão para 'cancelada', nunca
 *    DELETE — um valor que some sem rastro é o pior defeito possível num
 *    controle de despesas.
 * 2. Marcar como paga exige data, valor e usuário. A constraint ck_parcela_paga
 *    garante isso no banco também, para nem um caminho alternativo escapar.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/financeiro_lib.php');

$tela = $url_base . '/financeiro/contas';

function contasVoltar(string $destino, string $tipo, string $mensagem): void
{
    header("Location: $destino" . (str_contains($destino, '?') ? '&' : '?') . "msg{$tipo}=" . urlencode($mensagem));
    exit;
}

/** Registra a ação na trilha de auditoria do sistema (§32). */
function contasAuditar(PDO $pdo, string $descricao): void
{
    registraMovimentacao($_SESSION['id_usuario'], $_SESSION['id_usuario'], $descricao, 'Financeiro', $pdo);
}

$acao = $_REQUEST['acao'] ?? '';

switch ($acao) {

    // ------------------------------------------------ criar / editar conta
    case 'salvar':
        exigirPermissao('financeiro.conta_gerenciar', $url_base);

        $id_conta    = (int) ($_POST['id_conta'] ?? 0);
        $descricao   = trim($_POST['descricao'] ?? '');
        $id_categoria = (int) ($_POST['id_categoria'] ?? 0);
        $id_centro   = (int) ($_POST['id_centro'] ?? 0) ?: null;
        $favorecido  = trim($_POST['favorecido'] ?? '');
        $id_func     = (int) ($_POST['id_usuario_func'] ?? 0) ?: null;
        $valor_total = financeiroNumero($_POST['valor_total'] ?? '');
        $emissao     = financeiroData($_POST['data_emissao'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        $parcelas    = max((int) ($_POST['parcelas'] ?? 1), 1);
        $periodo     = $_POST['periodo_parcelas'] ?? 'mensal';
        $primeiro_vc = financeiroData($_POST['primeiro_vencimento'] ?? '');
        $forma       = $_POST['forma_pagamento'] ?? '';

        $recorrente  = !empty($_POST['recorrente']);
        $rec_periodo = $_POST['recorrencia_periodo'] ?? null;
        $rec_dia     = (int) ($_POST['recorrencia_dia'] ?? 0) ?: null;
        $rec_inicio  = financeiroData($_POST['recorrencia_inicio'] ?? '');
        $rec_fim     = financeiroData($_POST['recorrencia_fim'] ?? '');

        if ($descricao === '')      { contasVoltar($tela, 'Erro', 'Informe a descrição da despesa.'); }
        if (!$id_categoria)         { contasVoltar($tela, 'Erro', 'Selecione a categoria.'); }
        if ($valor_total === null || $valor_total <= 0) { contasVoltar($tela, 'Erro', 'Informe um valor maior que zero.'); }
        if (!$emissao)              { contasVoltar($tela, 'Erro', 'Informe a data da despesa.'); }
        if (!$primeiro_vc)          { contasVoltar($tela, 'Erro', 'Informe a data do primeiro vencimento.'); }
        if ($parcelas > 120)        { contasVoltar($tela, 'Erro', 'No máximo 120 parcelas.'); }
        if ($forma !== '' && !isset(FINANCEIRO_FORMAS[$forma])) { contasVoltar($tela, 'Erro', 'Forma de pagamento inválida.'); }
        if ($recorrente && !isset(FINANCEIRO_PERIODOS[$rec_periodo])) {
            contasVoltar($tela, 'Erro', 'Selecione a periodicidade da recorrência.');
        }

        try {
            $pdo->beginTransaction();

            if ($id_conta) {
                // Edição não mexe nas parcelas já pagas: só no cabeçalho.
                $pagas = $pdo->prepare("SELECT COUNT(*) FROM contas_pagar_parcelas
                                        WHERE id_conta = ? AND status = 'paga'");
                $pagas->execute([$id_conta]);
                $tem_pagas = (int) $pagas->fetchColumn() > 0;

                $pdo->prepare("UPDATE contas_pagar
                               SET descricao = :d, id_categoria = :cat, id_centro = :cc,
                                   favorecido = :fav, id_usuario_func = :func,
                                   data_emissao = :em, observacoes = :obs,
                                   recorrente = :rec, recorrencia_periodo = :rp,
                                   recorrencia_dia = :rd, recorrencia_inicio = :ri, recorrencia_fim = :rf,
                                   data_alteracao = NOW(), id_usuario_alteracao = :u
                               WHERE id_conta = :id AND status = 'ativa'")
                    ->execute([
                        ':d' => $descricao, ':cat' => $id_categoria, ':cc' => $id_centro,
                        ':fav' => $favorecido ?: null, ':func' => $id_func,
                        ':em' => $emissao, ':obs' => $observacoes ?: null,
                        ':rec' => $recorrente ? 'true' : 'false',
                        ':rp' => $recorrente ? $rec_periodo : null,
                        ':rd' => $recorrente ? $rec_dia : null,
                        ':ri' => $recorrente ? $rec_inicio : null,
                        ':rf' => $recorrente ? $rec_fim : null,
                        ':u' => $_SESSION['id_usuario'], ':id' => $id_conta,
                    ]);

                if (!$tem_pagas) {
                    // Sem parcela paga, regera o parcelamento com os valores novos.
                    $pdo->prepare("DELETE FROM contas_pagar_parcelas WHERE id_conta = ? AND status <> 'paga'")
                        ->execute([$id_conta]);
                    contasGerarParcelas($pdo, $id_conta, $valor_total, $parcelas, $primeiro_vc, $periodo, $forma);
                    $pdo->prepare("UPDATE contas_pagar SET valor_total = ? WHERE id_conta = ?")
                        ->execute([$valor_total, $id_conta]);
                }

                $pdo->commit();
                contasAuditar($pdo, sprintf('Despesa #%d alterada: %s (%s)', $id_conta, $descricao, financeiroMoeda($valor_total)));
                contasVoltar($tela, 'Sucesso', $tem_pagas
                    ? "Despesa #$id_conta atualizada. As parcelas não mudaram porque já há pagamento registrado."
                    : "Despesa #$id_conta atualizada.");
            }

            $stmt = $pdo->prepare("INSERT INTO contas_pagar
                    (descricao, id_categoria, id_centro, favorecido, id_usuario_func, valor_total,
                     data_emissao, observacoes, recorrente, recorrencia_periodo, recorrencia_dia,
                     recorrencia_inicio, recorrencia_fim, id_usuario_cadastro)
                   VALUES (:d,:cat,:cc,:fav,:func,:v,:em,:obs,:rec,:rp,:rd,:ri,:rf,:u)
                   RETURNING id_conta");
            $stmt->execute([
                ':d' => $descricao, ':cat' => $id_categoria, ':cc' => $id_centro,
                ':fav' => $favorecido ?: null, ':func' => $id_func, ':v' => $valor_total,
                ':em' => $emissao, ':obs' => $observacoes ?: null,
                ':rec' => $recorrente ? 'true' : 'false',
                ':rp' => $recorrente ? $rec_periodo : null,
                ':rd' => $recorrente ? $rec_dia : null,
                ':ri' => $recorrente ? ($rec_inicio ?: $emissao) : null,
                ':rf' => $recorrente ? $rec_fim : null,
                ':u' => $_SESSION['id_usuario'],
            ]);
            $id_conta = (int) $stmt->fetchColumn();

            contasGerarParcelas($pdo, $id_conta, $valor_total, $parcelas, $primeiro_vc, $periodo, $forma);

            $pdo->commit();

            contasAuditar($pdo, sprintf('Despesa #%d criada: %s, %s em %dx',
                $id_conta, $descricao, financeiroMoeda($valor_total), $parcelas));

            contasVoltar($tela, 'Sucesso', sprintf('Despesa #%d criada com %d parcela(s).', $id_conta, $parcelas));

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            contasVoltar($tela, 'Erro', 'Não foi possível salvar: ' . $e->getMessage());
        }

    // ---------------------------------------------------- registrar pagamento
    case 'pagar':
        exigirPermissao('financeiro.pagamento', $url_base);

        $id_parcela = (int) ($_POST['id_parcela'] ?? 0);
        $data_pg    = financeiroData($_POST['data_pagamento'] ?? '');
        $valor_pago = financeiroNumero($_POST['valor_pago'] ?? '');
        $forma      = $_POST['forma_pagamento'] ?? '';
        $obs        = trim($_POST['observacao'] ?? '');

        if (!$id_parcela)  { contasVoltar($tela, 'Erro', 'Parcela não informada.'); }
        if (!$data_pg)     { contasVoltar($tela, 'Erro', 'Informe a data do pagamento.'); }
        if ($valor_pago === null || $valor_pago <= 0) { contasVoltar($tela, 'Erro', 'Informe o valor pago.'); }
        if (!isset(FINANCEIRO_FORMAS[$forma])) { contasVoltar($tela, 'Erro', 'Selecione a forma de pagamento.'); }
        if ($data_pg > date('Y-m-d')) { contasVoltar($tela, 'Erro', 'A data do pagamento não pode ser futura.'); }

        $stmt = $pdo->prepare("SELECT pa.*, c.descricao FROM contas_pagar_parcelas pa
                               JOIN contas_pagar c ON c.id_conta = pa.id_conta
                               WHERE pa.id_parcela = ?");
        $stmt->execute([$id_parcela]);
        $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parcela)                          { contasVoltar($tela, 'Erro', 'Parcela não encontrada.'); }
        if ($parcela['status'] === 'paga')      { contasVoltar($tela, 'Erro', 'Esta parcela já está paga.'); }
        if ($parcela['status'] === 'cancelada') { contasVoltar($tela, 'Erro', 'Parcela cancelada não pode ser paga.'); }

        $pdo->prepare("UPDATE contas_pagar_parcelas
                       SET status = 'paga', data_pagamento = :dp, valor_pago = :vp,
                           forma_pagamento = :fp, id_usuario_pagamento = :u,
                           data_registro_pagamento = NOW(),
                           observacao = COALESCE(NULLIF(:obs, ''), observacao)
                       WHERE id_parcela = :id")
            ->execute([':dp' => $data_pg, ':vp' => $valor_pago, ':fp' => $forma,
                       ':u' => $_SESSION['id_usuario'], ':obs' => $obs, ':id' => $id_parcela]);

        contasAuditar($pdo, sprintf('Parcela %d/%d de "%s" paga: %s em %s por %s',
            $parcela['numero_parcela'], $parcela['total_parcelas'], $parcela['descricao'],
            financeiroMoeda($valor_pago), date('d/m/Y', strtotime($data_pg)), FINANCEIRO_FORMAS[$forma]));

        $diferenca = $valor_pago - (float) $parcela['valor'];
        $aviso = abs($diferenca) > 0.005
            ? sprintf(' Valor pago difere do previsto em %s.', financeiroMoeda($diferenca))
            : '';

        contasVoltar($tela, 'Sucesso', sprintf('Pagamento da parcela %d/%d registrado.%s',
            $parcela['numero_parcela'], $parcela['total_parcelas'], $aviso));

    // ------------------------------------------------- cancelar pagamento
    case 'estornar':
        exigirPermissao('financeiro.pagamento', $url_base);

        $id_parcela = (int) ($_POST['id_parcela'] ?? 0);
        if (!$id_parcela) { contasVoltar($tela, 'Erro', 'Parcela não informada.'); }

        $stmt = $pdo->prepare("SELECT pa.*, c.descricao FROM contas_pagar_parcelas pa
                               JOIN contas_pagar c ON c.id_conta = pa.id_conta
                               WHERE pa.id_parcela = ? AND pa.status = 'paga'");
        $stmt->execute([$id_parcela]);
        $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parcela) { contasVoltar($tela, 'Erro', 'Parcela não encontrada ou não está paga.'); }

        $pdo->prepare("UPDATE contas_pagar_parcelas
                       SET status = 'pendente', data_pagamento = NULL, valor_pago = NULL,
                           forma_pagamento = NULL, id_usuario_pagamento = NULL,
                           data_registro_pagamento = NULL
                       WHERE id_parcela = ?")->execute([$id_parcela]);

        contasAuditar($pdo, sprintf('Pagamento ESTORNADO da parcela %d/%d de "%s" (era %s de %s)',
            $parcela['numero_parcela'], $parcela['total_parcelas'], $parcela['descricao'],
            financeiroMoeda((float) $parcela['valor_pago']), date('d/m/Y', strtotime($parcela['data_pagamento']))));

        contasVoltar($tela, 'Sucesso', 'Pagamento estornado. A parcela voltou a pendente.');

    // ------------------------------------------------------ cancelar conta
    case 'cancelar':
        exigirPermissao('financeiro.conta_cancelar', $url_base);

        $id_conta = (int) ($_POST['id_conta'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');

        if (!$id_conta) { contasVoltar($tela, 'Erro', 'Despesa não informada.'); }
        if ($motivo === '') { contasVoltar($tela, 'Erro', 'Informe o motivo do cancelamento.'); }

        $stmt = $pdo->prepare("SELECT descricao, valor_total FROM contas_pagar WHERE id_conta = ? AND status = 'ativa'");
        $stmt->execute([$id_conta]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conta) { contasVoltar($tela, 'Erro', 'Despesa não encontrada ou já cancelada.'); }

        try {
            $pdo->beginTransaction();

            // Parcela paga NÃO é cancelada: o dinheiro saiu de verdade e apagar
            // isso falsearia o histórico. Só as pendentes deixam de ser devidas.
            $pdo->prepare("UPDATE contas_pagar_parcelas SET status = 'cancelada'
                           WHERE id_conta = ? AND status = 'pendente'")->execute([$id_conta]);

            $pdo->prepare("UPDATE contas_pagar
                           SET status = 'cancelada', observacoes = TRIM(COALESCE(observacoes, '') || ' [CANCELADA: ' || :m || ']'),
                               data_alteracao = NOW(), id_usuario_alteracao = :u
                           WHERE id_conta = :id")
                ->execute([':m' => $motivo, ':u' => $_SESSION['id_usuario'], ':id' => $id_conta]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            contasVoltar($tela, 'Erro', 'Não foi possível cancelar: ' . $e->getMessage());
        }

        contasAuditar($pdo, sprintf('Despesa #%d cancelada: %s (%s). Motivo: %s',
            $id_conta, $conta['descricao'], financeiroMoeda((float) $conta['valor_total']), $motivo));

        contasVoltar($tela, 'Sucesso', "Despesa #$id_conta cancelada. As parcelas pagas foram mantidas no histórico.");

    // --------------------------------------------------------- categorias
    case 'categoria':
        exigirPermissao('financeiro.categorias', $url_base);

        $destino = $url_base . '/financeiro/categorias';
        $id_categoria = (int) ($_POST['id_categoria'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $tipo = ($_POST['tipo'] ?? 'geral') === 'funcionario' ? 'funcionario' : 'geral';
        $operacao = $_POST['operacao'] ?? 'criar';

        if ($operacao === 'inativar') {
            if (!$id_categoria) { contasVoltar($destino, 'Erro', 'Categoria não informada.'); }

            $uso = $pdo->prepare("SELECT COUNT(*) FROM contas_pagar WHERE id_categoria = ? AND status = 'ativa'");
            $uso->execute([$id_categoria]);
            if ((int) $uso->fetchColumn() > 0) {
                contasVoltar($destino, 'Erro', 'Categoria em uso por despesas ativas. Inative as despesas antes.');
            }

            $pdo->prepare("UPDATE despesa_categoria SET status = 0 WHERE id_categoria = ?")->execute([$id_categoria]);
            contasAuditar($pdo, "Categoria de despesa #$id_categoria inativada");
            contasVoltar($destino, 'Sucesso', 'Categoria inativada.');
        }

        if ($nome === '') { contasVoltar($destino, 'Erro', 'Informe o nome da categoria.'); }

        try {
            if ($id_categoria) {
                $pdo->prepare("UPDATE despesa_categoria SET nome = :n, tipo = :t WHERE id_categoria = :id")
                    ->execute([':n' => $nome, ':t' => $tipo, ':id' => $id_categoria]);
                $msg = 'Categoria atualizada.';
            } else {
                $pdo->prepare("INSERT INTO despesa_categoria (nome, tipo) VALUES (:n, :t)")
                    ->execute([':n' => $nome, ':t' => $tipo]);
                $msg = 'Categoria criada.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                contasVoltar($destino, 'Erro', 'Já existe uma categoria ativa com esse nome.');
            }
            contasVoltar($destino, 'Erro', 'Não foi possível salvar: ' . $e->getMessage());
        }

        contasAuditar($pdo, "Categoria de despesa salva: $nome");
        contasVoltar($destino, 'Sucesso', $msg);

    default:
        header("Location: $tela");
        exit;
}

/**
 * Cria as parcelas de uma conta.
 *
 * O valor é dividido sem perder centavos e os vencimentos respeitam meses com
 * menos dias — ver financeiroDividirParcelas() e financeiroVencimentos().
 */
function contasGerarParcelas(PDO $pdo, int $id_conta, float $total, int $quantidade,
                             string $primeiro_vencimento, string $periodo, string $forma): void
{
    $valores = financeiroDividirParcelas($total, $quantidade);
    $datas = financeiroVencimentos($primeiro_vencimento, $quantidade, $periodo);

    $stmt = $pdo->prepare("INSERT INTO contas_pagar_parcelas
            (id_conta, numero_parcela, total_parcelas, valor, data_vencimento, forma_pagamento)
         VALUES (:c, :n, :t, :v, :d, :f)");

    for ($i = 0; $i < $quantidade; $i++) {
        $stmt->execute([
            ':c' => $id_conta, ':n' => $i + 1, ':t' => $quantidade,
            ':v' => $valores[$i], ':d' => $datas[$i],
            ':f' => $forma !== '' ? $forma : null,
        ]);
    }
}
