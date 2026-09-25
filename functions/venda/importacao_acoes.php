<?php
/**
 * Controlador do fluxo de importação de vendas.
 *
 * Etapas: enviar arquivo -> mapear colunas -> resolver pendências -> confirmar.
 * O estado vive em $_SESSION['importacao'] e o arquivo fica no diretório
 * temporário do sistema, FORA da pasta pública: o navegador nunca consegue
 * pedir esse arquivo de volta, mesmo adivinhando o nome.
 *
 * A baixa de estoque só acontece no case 'confirmar'.
 */
require_once(__DIR__ . '/../../components/middleware.php');
require_once(__DIR__ . '/../../components/permissoes.php');
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../funcoes.php');
require_once(__DIR__ . '/importacao_lib.php');
require_once(__DIR__ . '/venda_lib.php');

exigirPermissao('venda.importar', $url_base);

$tela = $url_base . '/vendas/importar';

function importacaoVoltar(string $tela, string $tipo, string $mensagem): void
{
    header("Location: $tela?msg{$tipo}=" . urlencode($mensagem));
    exit;
}

/** Apaga o arquivo temporário e zera o estado da importação em andamento. */
function importacaoLimpar(): void
{
    $arquivo = $_SESSION['importacao']['arquivo_tmp'] ?? null;
    if ($arquivo && is_file($arquivo)) {
        unlink($arquivo);
    }
    unset($_SESSION['importacao']);
}

$acao = $_REQUEST['acao'] ?? '';

switch ($acao) {

    // -------------------------------------------------------------- envio
    case 'enviar':
        $arquivo = $_FILES['planilha'] ?? null;

        if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            importacaoVoltar($tela, 'Erro', 'Selecione a planilha.');
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            importacaoVoltar($tela, 'Erro', 'Falha no envio do arquivo (código ' . $arquivo['error'] . ').');
        }
        if ($arquivo['size'] > IMPORTACAO_TAMANHO_MAX) {
            importacaoVoltar($tela, 'Erro', 'Planilha muito grande. O limite é 5 MB.');
        }

        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extensao, IMPORTACAO_EXTENSOES, true)) {
            importacaoVoltar($tela, 'Erro', 'Formato não aceito. Envie .xlsx, .xls, .ods ou .csv.');
        }

        // Importação anterior abandonada não pode deixar arquivo para trás.
        importacaoLimpar();

        $destino = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                 . 'imp_venda_' . bin2hex(random_bytes(8)) . '.' . $extensao;

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            importacaoVoltar($tela, 'Erro', 'Não foi possível guardar o arquivo para leitura.');
        }

        try {
            $linhas = importacaoLerPlanilha($destino);
        } catch (Throwable $e) {
            unlink($destino);
            importacaoVoltar($tela, 'Erro', 'Não consegui ler a planilha: ' . $e->getMessage());
        }

        if (count($linhas) < 2) {
            unlink($destino);
            importacaoVoltar($tela, 'Erro', 'A planilha não tem linhas de dados.');
        }

        $id_modelo = (int) ($_POST['id_modelo'] ?? 0) ?: null;
        $mapa = [];
        $linha_cabecalho = 0;

        if ($id_modelo) {
            $stmt = $pdo->prepare("SELECT mapeamento, linha_cabecalho FROM importacao_modelo
                                   WHERE id_modelo = ? AND status = 1");
            $stmt->execute([$id_modelo]);
            $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$modelo) {
                unlink($destino);
                importacaoVoltar($tela, 'Erro', 'Modelo de importação não encontrado.');
            }

            $mapa = json_decode($modelo['mapeamento'], true) ?: [];
            $linha_cabecalho = max((int) $modelo['linha_cabecalho'] - 1, 0);
        } else {
            // Sem modelo, tenta adivinhar pelos títulos — o usuário confere depois.
            $detectado = planilhaLocalizarCabecalho($linhas);
            if ($detectado) {
                $mapa = $detectado['mapa'];
                $linha_cabecalho = $detectado['linha'];
            }
        }

        $stmt = $pdo->prepare("INSERT INTO importacao_historico (arquivo, id_modelo, id_usuario, status)
                               VALUES (:arquivo, :id_modelo, :id_usuario, 'processando')
                               RETURNING id_importacao");
        $stmt->execute([
            ':arquivo'    => $arquivo['name'],
            ':id_modelo'  => $id_modelo,
            ':id_usuario' => $_SESSION['id_usuario'],
        ]);

        $_SESSION['importacao'] = [
            'id_importacao'   => (int) $stmt->fetchColumn(),
            'arquivo_nome'    => $arquivo['name'],
            'arquivo_tmp'     => $destino,
            'id_modelo'       => $id_modelo,
            'linha_cabecalho' => $linha_cabecalho,
            'mapa'            => $mapa,
            'edicoes'         => [],
            'etapa'           => $mapa ? 'preview' : 'mapear',
        ];

        header("Location: $tela");
        exit;

    // ---------------------------------------------------------- mapeamento
    case 'mapear':
        if (empty($_SESSION['importacao'])) {
            importacaoVoltar($tela, 'Erro', 'Nenhuma importação em andamento.');
        }

        $mapa = [];
        foreach (array_keys(VENDA_PLANILHA_ROTULOS) as $campo) {
            $valor = $_POST['campo'][$campo] ?? '';
            if ($valor !== '') {
                $mapa[$campo] = (int) $valor;
            }
        }

        if (!isset($mapa['material']) && !isset($mapa['codigo'])) {
            importacaoVoltar($tela, 'Erro', 'Indique a coluna do material ou a do código — pelo menos uma das duas.');
        }
        foreach (VENDA_PLANILHA_OBRIGATORIAS as $campo) {
            if (!isset($mapa[$campo])) {
                importacaoVoltar($tela, 'Erro', 'Falta mapear: ' . VENDA_PLANILHA_ROTULOS[$campo] . '.');
            }
        }

        $_SESSION['importacao']['mapa'] = $mapa;
        $_SESSION['importacao']['linha_cabecalho'] = max((int) ($_POST['linha_cabecalho'] ?? 1) - 1, 0);
        $_SESSION['importacao']['edicoes'] = [];   // mapa novo invalida edições antigas
        $_SESSION['importacao']['etapa'] = 'preview';

        // Salvar o mapeamento como modelo é opcional e só acontece se pedido.
        $nome_modelo = trim($_POST['nome_modelo'] ?? '');
        if ($nome_modelo !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO importacao_modelo
                                          (nome, id_fornecedor, linha_cabecalho, mapeamento)
                                       VALUES (:nome, :id_fornecedor, :linha, :mapeamento)
                                       RETURNING id_modelo");
                $stmt->execute([
                    ':nome'          => $nome_modelo,
                    ':id_fornecedor' => (int) ($_POST['id_fornecedor_modelo'] ?? 0) ?: null,
                    ':linha'         => $_SESSION['importacao']['linha_cabecalho'] + 1,
                    ':mapeamento'    => json_encode($mapa),
                ]);
                $_SESSION['importacao']['id_modelo'] = (int) $stmt->fetchColumn();

                $pdo->prepare("UPDATE importacao_historico SET id_modelo = ? WHERE id_importacao = ?")
                    ->execute([$_SESSION['importacao']['id_modelo'], $_SESSION['importacao']['id_importacao']]);

                importacaoVoltar($tela, 'Sucesso', 'Modelo "' . $nome_modelo . '" salvo.');
            } catch (PDOException $e) {
                importacaoVoltar($tela, 'Erro', 'Já existe um modelo com esse nome. O mapeamento foi aplicado mesmo assim.');
            }
        }

        header("Location: $tela");
        exit;

    // ---------------------------------------- recalcular com as edições
    case 'recalcular':
        if (empty($_SESSION['importacao'])) {
            importacaoVoltar($tela, 'Erro', 'Nenhuma importação em andamento.');
        }

        $_SESSION['importacao']['edicoes'] = importacaoNormalizarEdicoes($_POST['itens'] ?? []);
        $_SESSION['importacao']['cabecalho'] = [
            'id_fornecedor'  => (int) ($_POST['id_fornecedor'] ?? 0),
            'pedido_externo' => trim($_POST['pedido_externo'] ?? ''),
            'data_venda'     => trim($_POST['data_venda'] ?? ''),
            'observacoes'    => trim($_POST['observacoes'] ?? ''),
        ];

        importacaoVoltar($tela, 'Sucesso', 'Valores recalculados. Confira o estoque antes de confirmar.');

    // ------------------------------------------------------- confirmação
    case 'confirmar':
        if (empty($_SESSION['importacao'])) {
            importacaoVoltar($tela, 'Erro', 'Nenhuma importação em andamento.');
        }

        $estado = $_SESSION['importacao'];
        $id_fornecedor = (int) ($_POST['id_fornecedor'] ?? 0);

        // Guarda o que foi digitado antes de qualquer validação: se a confirmação
        // falhar, o usuário volta para a tela com as edições preservadas.
        $edicoes = importacaoNormalizarEdicoes($_POST['itens'] ?? []);
        $_SESSION['importacao']['edicoes'] = $edicoes;
        $_SESSION['importacao']['cabecalho'] = [
            'id_fornecedor'  => $id_fornecedor,
            'pedido_externo' => trim($_POST['pedido_externo'] ?? ''),
            'data_venda'     => trim($_POST['data_venda'] ?? ''),
            'observacoes'    => trim($_POST['observacoes'] ?? ''),
        ];

        if (!$id_fornecedor) {
            importacaoVoltar($tela, 'Erro', 'Selecione o fornecedor da venda antes de confirmar.');
        }

        try {
            $linhas = importacaoLerPlanilha($estado['arquivo_tmp']);
        } catch (Throwable $e) {
            importacaoVoltar($tela, 'Erro', 'O arquivo da importação não está mais disponível. Envie a planilha de novo.');
        }

        // A prévia é remontada a partir do ARQUIVO, aplicando as edições por cima.
        // O formulário pode mudar quantidade, tara, valor e material; não pode
        // burlar a validação: estoque, positividade e tara < bruto são conferidos
        // aqui de novo, do lado do servidor.
        $previa = importacaoMontarPreview(
            $pdo,
            $linhas,
            $estado['linha_cabecalho'],
            $estado['mapa'],
            $estado['id_modelo'],
            $edicoes
        );

        $itens_ok = array_values(array_filter($previa['itens'], fn($i) => $i['situacao'] === 'ok'));

        if (empty($itens_ok)) {
            importacaoVoltar($tela, 'Erro', 'Nenhum item válido para importar.');
        }

        $pedido = trim($_POST['pedido_externo'] ?? $previa['pedido'] ?? '');
        $pedido = $pedido !== '' ? $pedido : null;

        if ($pedido !== null) {
            $dup = $pdo->prepare("SELECT id_venda, data_venda FROM vendas WHERE LOWER(pedido_externo) = LOWER(?)");
            $dup->execute([$pedido]);
            if ($ja = $dup->fetch(PDO::FETCH_ASSOC)) {
                importacaoVoltar($tela, 'Erro', sprintf(
                    'Pedido %s já foi importado na venda #%d em %s.',
                    $pedido, $ja['id_venda'], date('d/m/Y', strtotime($ja['data_venda']))
                ));
            }
        }

        try {
            $pdo->beginTransaction();

            // Data digitada na tela vence a lida da planilha; em branco, NOW().
            $data_digitada = trim($_POST['data_venda'] ?? '');
            $data_venda = $data_digitada !== ''
                ? (importacaoData($data_digitada) ?? $previa['data_venda'])
                : $previa['data_venda'];

            $id_venda = vendaCriar($pdo, $id_fornecedor, $itens_ok, [
                'id_usuario'     => $_SESSION['id_usuario'],
                'observacoes'    => trim($_POST['observacoes'] ?? ''),
                'origem'         => 'importacao',
                'pedido_externo' => $pedido,
                'id_importacao'  => $estado['id_importacao'],
                'data_venda'     => $data_venda,
            ]);

            // As escolhas manuais viram de/para para a próxima planilha do mesmo parceiro.
            foreach ($previa['itens'] as $item) {
                if ($item['situacao'] === 'ok' && $item['via'] === 'escolha_usuario') {
                    importacaoSalvarRelacao(
                        $pdo,
                        $item['id_material'],
                        $item['planilha_nome'],
                        $item['planilha_codigo'],
                        $estado['id_modelo']
                    );
                }
            }

            $problemas = array_values(array_filter(
                $previa['itens'],
                fn($i) => in_array($i['situacao'], ['nao_reconhecido', 'erro', 'estoque', 'ignorado'], true)
            ));

            $erros_log = array_map(fn($i) => [
                'linha'    => $i['linha'],
                'material' => $i['planilha_nome'] ?: $i['planilha_codigo'],
                'motivo'   => $i['motivo'],
            ], $problemas);

            $pdo->prepare("UPDATE importacao_historico
                           SET status = :status, total_registros = :total,
                               registros_importados = :importados, registros_com_erro = :erros,
                               valor_total = :valor, pedido_externo = :pedido, erros = :log
                           WHERE id_importacao = :id")
                ->execute([
                    ':status'     => empty($problemas) ? 'concluido' : 'concluido_avisos',
                    ':total'      => count($previa['itens']),
                    ':importados' => count($itens_ok),
                    ':erros'      => count($problemas),
                    ':valor'      => $previa['total_valor'],
                    ':pedido'     => $pedido,
                    ':log'        => $erros_log ? json_encode($erros_log, JSON_UNESCAPED_UNICODE) : null,
                    ':id'         => $estado['id_importacao'],
                ]);

            $pdo->commit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // O histórico é gravado fora da transação que falhou, senão o
            // rollback levaria junto o registro do erro.
            $pdo->prepare("UPDATE importacao_historico SET status = 'erro', erros = :log WHERE id_importacao = :id")
                ->execute([
                    ':log' => json_encode([['linha' => 0, 'material' => '', 'motivo' => $e->getMessage()]], JSON_UNESCAPED_UNICODE),
                    ':id'  => $estado['id_importacao'],
                ]);

            importacaoVoltar($tela, 'Erro', 'A importação foi desfeita: ' . $e->getMessage());
        }

        registraMovimentacao(
            $_SESSION['id_usuario'],
            $_SESSION['id_usuario'],
            sprintf('Venda #%d importada de %s (%d itens, R$ %s)',
                $id_venda, $estado['arquivo_nome'], count($itens_ok),
                number_format($previa['total_valor'], 2, ',', '.')),
            'Importação Venda',
            $pdo
        );

        importacaoLimpar();

        header("Location: $url_base/vendas/detalhe?id={$id_venda}&msgSucesso="
            . urlencode(sprintf('Venda #%d importada: %d itens.', $id_venda, count($itens_ok))));
        exit;

    // ---------------------------------------------------------- cancelar
    case 'cancelar':
        if (!empty($_SESSION['importacao']['id_importacao'])) {
            $pdo->prepare("UPDATE importacao_historico SET status = 'cancelado' WHERE id_importacao = ? AND status = 'processando'")
                ->execute([$_SESSION['importacao']['id_importacao']]);
        }
        importacaoLimpar();
        importacaoVoltar($tela, 'Sucesso', 'Importação cancelada.');

    default:
        header("Location: $tela");
        exit;
}
