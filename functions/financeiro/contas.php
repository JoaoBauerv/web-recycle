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
        $valor       = financeiroNumero($_POST['valor'] ?? '');
        $emissao     = financeiroData($_POST['data_emissao'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        $parcelas    = max((int) ($_POST['parcelas'] ?? 1), 1);
        $periodo     = $_POST['periodo_parcelas'] ?? 'mensal';
        $primeiro_vc = financeiroData($_POST['primeiro_vencimento'] ?? '');
        $forma       = $_POST['forma_pagamento'] ?? '';

        // Duas formas de informar o valor:
        //   'total'   — R$ 1.200 em 12x: o total é dividido (a sobra de centavos
        //               vai para a 1ª parcela, ver financeiroDividirParcelas);
        //   'parcela' — 12x de R$ 100: cada parcela tem o valor digitado e o
        //               total é a multiplicação.
        $modo_valor  = ($_POST['modo_valor'] ?? 'total') === 'parcela' ? 'parcela' : 'total';

        if ($descricao === '')      { contasVoltar($tela, 'Erro', 'Informe a descrição da despesa.'); }
        if (!$id_categoria)         { contasVoltar($tela, 'Erro', 'Selecione a categoria.'); }
        if ($valor === null || $valor <= 0) { contasVoltar($tela, 'Erro', 'Informe um valor maior que zero.'); }
        if (!$emissao)              { contasVoltar($tela, 'Erro', 'Informe a data da despesa.'); }
        if (!$primeiro_vc)          { contasVoltar($tela, 'Erro', 'Informe a data do primeiro vencimento.'); }
        if ($parcelas > 120)        { contasVoltar($tela, 'Erro', 'No máximo 120 parcelas.'); }
        if ($forma !== '' && !isset(FINANCEIRO_FORMAS[$forma])) { contasVoltar($tela, 'Erro', 'Forma de pagamento inválida.'); }
        if (!isset(FINANCEIRO_PERIODOS[$periodo])) { contasVoltar($tela, 'Erro', 'Selecione o intervalo entre as parcelas.'); }

        $valores = $modo_valor === 'parcela'
            ? array_fill(0, $parcelas, round($valor, 2))
            : financeiroDividirParcelas($valor, $parcelas);
        $valor_total = round(array_sum($valores), 2);

        if ($modo_valor === 'total' && $valores[$parcelas - 1] <= 0) {
            contasVoltar($tela, 'Erro', 'O valor total é pequeno demais para tantas parcelas.');
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
                                   data_emissao = :em, observacoes = :obs
                               WHERE id_conta = :id AND status = 'ativa'")
                    ->execute([
                        ':d' => $descricao, ':cat' => $id_categoria, ':cc' => $id_centro,
                        ':fav' => $favorecido ?: null, ':func' => $id_func,
                        ':em' => $emissao, ':obs' => $observacoes ?: null,
                        ':id' => $id_conta,
                    ]);

                if (!$tem_pagas) {
                    // Sem parcela paga, regera o parcelamento com os valores novos.
                    $pdo->prepare("DELETE FROM contas_pagar_parcelas WHERE id_conta = ? AND status <> 'paga'")
                        ->execute([$id_conta]);
                    contasGerarParcelas($pdo, $id_conta, $valores, $primeiro_vc, $periodo, $forma);
                    $pdo->prepare("UPDATE contas_pagar SET valor_total = ? WHERE id_conta = ?")
                        ->execute([$valor_total, $id_conta]);
                }

                $pdo->commit();
                contasAuditar($pdo, sprintf('Despesa #%d alterada: %s (%s)', $id_conta, $descricao, financeiroMoeda($valor_total)));
                contasVoltar($tela, 'Sucesso', $tem_pagas
                    ? "Despesa #$id_conta atualizada. As parcelas não mudaram porque já há pagamento registrado."
                    : "Despesa #$id_conta atualizada.");
            }

            // Quem criou e quando fica em registro_movimento, pelo contasAuditar()
            // logo abaixo: a conta não repete essa informação em coluna própria.
            $stmt = $pdo->prepare("INSERT INTO contas_pagar
                    (descricao, id_categoria, id_centro, favorecido, id_usuario_func, valor_total,
                     data_emissao, observacoes)
                   VALUES (:d,:cat,:cc,:fav,:func,:v,:em,:obs)
                   RETURNING id_conta");
            $stmt->execute([
                ':d' => $descricao, ':cat' => $id_categoria, ':cc' => $id_centro,
                ':fav' => $favorecido ?: null, ':func' => $id_func, ':v' => $valor_total,
                ':em' => $emissao, ':obs' => $observacoes ?: null,
            ]);
            $id_conta = (int) $stmt->fetchColumn();

            contasGerarParcelas($pdo, $id_conta, $valores, $primeiro_vc, $periodo, $forma);

            $pdo->commit();

            $resumo = $modo_valor === 'parcela'
                ? sprintf('%dx de %s (total %s)', $parcelas, financeiroMoeda($valores[0]), financeiroMoeda($valor_total))
                : sprintf('%s em %dx', financeiroMoeda($valor_total), $parcelas);

            contasAuditar($pdo, sprintf('Despesa #%d criada: %s, %s', $id_conta, $descricao, $resumo));

            contasVoltar($tela, 'Sucesso', sprintf('Despesa #%d criada: %s.', $id_conta, $resumo));

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Não foi possível salvar: ' . $e->getMessage());
            contasVoltar($tela, 'Erro', 'Não foi possível salvar. Confira os dados e tente novamente.');
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

        $stmt = $pdo->prepare("SELECT pa.*, c.descricao, " . financeiroTotalParcelasSql('pa') . "
                               FROM contas_pagar_parcelas pa
                               JOIN contas_pagar c ON c.id_conta = pa.id_conta
                               WHERE pa.id_parcela = ?");
        $stmt->execute([$id_parcela]);
        $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parcela)                          { contasVoltar($tela, 'Erro', 'Parcela não encontrada.'); }
        if ($parcela['status'] === 'paga')      { contasVoltar($tela, 'Erro', 'Esta parcela já está paga.'); }
        if ($parcela['status'] === 'cancelada') { contasVoltar($tela, 'Erro', 'Parcela cancelada não pode ser paga.'); }

        // A condição status = 'pendente' no próprio UPDATE é o que impede pagar
        // duas vezes: dois envios do formulário (duplo clique, duas abas) passam
        // juntos pela checagem acima, mas só o primeiro encontra a parcela pendente.
        $pago = $pdo->prepare("UPDATE contas_pagar_parcelas
                       SET status = 'paga', data_pagamento = :dp, valor_pago = :vp,
                           forma_pagamento = :fp, id_usuario_pagamento = :u,
                           observacao = LEFT(COALESCE(NULLIF(:obs, ''), observacao), 255)
                       WHERE id_parcela = :id AND status = 'pendente'");
        $pago->execute([':dp' => $data_pg, ':vp' => $valor_pago, ':fp' => $forma,
                        ':u' => $_SESSION['id_usuario'], ':obs' => $obs, ':id' => $id_parcela]);

        if ($pago->rowCount() === 0) {
            contasVoltar($tela, 'Erro', 'Esta parcela já foi paga ou cancelada por outra operação.');
        }

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

        $stmt = $pdo->prepare("SELECT pa.*, c.descricao, " . financeiroTotalParcelasSql('pa') . "
                               FROM contas_pagar_parcelas pa
                               JOIN contas_pagar c ON c.id_conta = pa.id_conta
                               WHERE pa.id_parcela = ? AND pa.status = 'paga'");
        $stmt->execute([$id_parcela]);
        $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parcela) { contasVoltar($tela, 'Erro', 'Parcela não encontrada ou não está paga.'); }

        $estorno = $pdo->prepare("UPDATE contas_pagar_parcelas
                       SET status = 'pendente', data_pagamento = NULL, valor_pago = NULL,
                           forma_pagamento = NULL, id_usuario_pagamento = NULL
                       WHERE id_parcela = ? AND status = 'paga'");
        $estorno->execute([$id_parcela]);

        if ($estorno->rowCount() === 0) {
            contasVoltar($tela, 'Erro', 'Este pagamento já foi estornado por outra operação.');
        }

        contasAuditar($pdo, sprintf('Pagamento ESTORNADO da parcela %d/%d de "%s" (era %s de %s)',
            $parcela['numero_parcela'], $parcela['total_parcelas'], $parcela['descricao'],
            financeiroMoeda((float) $parcela['valor_pago']), date('d/m/Y', strtotime($parcela['data_pagamento']))));

        contasVoltar($tela, 'Sucesso', 'Pagamento estornado. A parcela voltou a pendente.');

    // -------------------------------------------------- cancelar parcela(s)
    // Três alcances, escolhidos no modal a partir de uma parcela:
    //   'parcela'   — só ela;
    //   'seguintes' — ela e as pendentes depois dela (contrato encerrado no meio);
    //   'todas'     — todas as pendentes da despesa.
    //
    // Parcela paga NÃO é cancelada em nenhum dos três: o dinheiro saiu de verdade
    // e apagar isso falsearia o histórico. Para desfazer um pagamento existe o
    // estorno, que a devolve a pendente.
    //
    // A despesa só passa a 'cancelada' quando não sobra nenhuma parcela fora de
    // 'cancelada'. Se houver parcela paga ela continua 'ativa': os relatórios
    // filtram c.status = 'ativa', e cancelar a conta inteira (como era antes)
    // tirava das somas o que já tinha sido pago.
    case 'cancelar':
        exigirPermissao('financeiro.conta_cancelar', $url_base);

        $id_parcela = (int) ($_POST['id_parcela'] ?? 0);
        $escopo = $_POST['escopo'] ?? '';
        $motivo = trim($_POST['motivo'] ?? '');

        $escopos = ['parcela' => 'só esta parcela', 'seguintes' => 'esta e as próximas', 'todas' => 'todas as pendentes'];

        if (!$id_parcela)               { contasVoltar($tela, 'Erro', 'Parcela não informada.'); }
        if (!isset($escopos[$escopo]))  { contasVoltar($tela, 'Erro', 'Escolha o que cancelar.'); }
        if ($motivo === '')             { contasVoltar($tela, 'Erro', 'Informe o motivo do cancelamento.'); }
        if (mb_strlen($motivo) > 150)   { contasVoltar($tela, 'Erro', 'O motivo deve ter no máximo 150 caracteres.'); }

        try {
            $pdo->beginTransaction();

            // FOR UPDATE na conta: dois cancelamentos simultâneos da mesma
            // despesa decidiriam o status final a partir da mesma leitura.
            $stmt = $pdo->prepare("SELECT pa.id_conta, pa.numero_parcela, c.descricao
                                   FROM contas_pagar_parcelas pa
                                   JOIN contas_pagar c ON c.id_conta = pa.id_conta
                                   WHERE pa.id_parcela = ? AND c.status = 'ativa'
                                   FOR UPDATE OF c");
            $stmt->execute([$id_parcela]);
            $alvo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$alvo) {
                throw new DomainException('Despesa não encontrada ou já cancelada.');
            }

            $filtro = match ($escopo) {
                'parcela'   => 'id_parcela = :p',
                'seguintes' => 'id_conta = :c AND numero_parcela >= :n',
                'todas'     => 'id_conta = :c',
            };
            $params = match ($escopo) {
                'parcela'   => [':p' => $id_parcela],
                'seguintes' => [':c' => $alvo['id_conta'], ':n' => $alvo['numero_parcela']],
                'todas'     => [':c' => $alvo['id_conta']],
            };

            // observacao é varchar(255): o LEFT impede que o motivo derrube o UPDATE.
            $cancelar = $pdo->prepare("UPDATE contas_pagar_parcelas
                                       SET status = 'cancelada',
                                           observacao = LEFT(TRIM(COALESCE(observacao, '') || ' [CANCELADA: ' || :m || ']'), 255)
                                       WHERE $filtro AND status = 'pendente'");
            $cancelar->execute($params + [':m' => $motivo]);
            $qtd = $cancelar->rowCount();

            if ($qtd === 0) {
                throw new DomainException($escopo === 'parcela'
                    ? 'Esta parcela não está pendente. Parcela paga não se cancela: estorne o pagamento antes.'
                    : 'Não há parcelas pendentes para cancelar.');
            }

            // Sobrou alguma parcela que não esteja cancelada? Se não, a despesa
            // inteira deixa de existir para os relatórios.
            $vivas = $pdo->prepare("SELECT COUNT(*) FROM contas_pagar_parcelas
                                    WHERE id_conta = ? AND status <> 'cancelada'");
            $vivas->execute([$alvo['id_conta']]);
            $encerrada = (int) $vivas->fetchColumn() === 0;

            if ($encerrada) {
                $pdo->prepare("UPDATE contas_pagar
                               SET status = 'cancelada',
                                   observacoes = LEFT(TRIM(COALESCE(observacoes, '') || ' [CANCELADA: ' || :m || ']'), 255)
                               WHERE id_conta = :id")
                    ->execute([':m' => $motivo, ':id' => $alvo['id_conta']]);
            }

            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            contasVoltar($tela, 'Erro', $e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Não foi possível cancelar: ' . $e->getMessage());
            contasVoltar($tela, 'Erro', 'Não foi possível cancelar. Confira os dados e tente novamente.');
        }

        contasAuditar($pdo, sprintf('Despesa #%d (%s): %d parcela(s) cancelada(s) — %s, a partir da %dª%s. Motivo: %s',
            $alvo['id_conta'], $alvo['descricao'], $qtd, $escopos[$escopo], $alvo['numero_parcela'],
            $encerrada ? '; despesa encerrada' : '', $motivo));

        contasVoltar($tela, 'Sucesso', sprintf('%d parcela(s) cancelada(s).%s', $qtd, $encerrada
            ? ' Não restou parcela ativa, então a despesa foi cancelada.'
            : ' As demais parcelas continuam valendo.'));

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
            error_log('Não foi possível salvar: ' . $e->getMessage());
            contasVoltar($destino, 'Erro', 'Não foi possível salvar. Confira os dados e tente novamente.');
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
 * Recebe o valor de cada parcela já calculado — dividido do total ou repetido,
 * conforme o modo escolhido no formulário. Os vencimentos respeitam meses com
 * menos dias — ver financeiroVencimentos().
 *
 * @param float[] $valores um valor por parcela, na ordem
 */
function contasGerarParcelas(PDO $pdo, int $id_conta, array $valores,
                             string $primeiro_vencimento, string $periodo, string $forma): void
{
    $quantidade = count($valores);
    $datas = financeiroVencimentos($primeiro_vencimento, $quantidade, $periodo);

    // O total de parcelas não é gravado: é COUNT(*) por id_conta. Ver
    // contasTotalParcelas() e o JOIN da tela de parcelas.
    $stmt = $pdo->prepare("INSERT INTO contas_pagar_parcelas
            (id_conta, numero_parcela, valor, data_vencimento, forma_pagamento)
         VALUES (:c, :n, :v, :d, :f)");

    for ($i = 0; $i < $quantidade; $i++) {
        $stmt->execute([
            ':c' => $id_conta, ':n' => $i + 1,
            ':v' => $valores[$i], ':d' => $datas[$i],
            ':f' => $forma !== '' ? $forma : null,
        ]);
    }
}

