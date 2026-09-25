<?php
/**
 * Núcleo da importação de vendas por planilha.
 *
 * Responsabilidades: ler o arquivo, aplicar o mapeamento de colunas, identificar
 * o material oficial pelo de/para, conferir estoque e montar a pré-visualização.
 * Nada aqui grava venda ou mexe em estoque — isso só acontece na confirmação,
 * através de vendaCriar().
 */

require_once __DIR__ . '/planilha.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

const IMPORTACAO_TAMANHO_MAX = 5 * 1024 * 1024; // 5 MB
const IMPORTACAO_LINHAS_MAX  = 2000;
const IMPORTACAO_EXTENSOES   = ['xlsx', 'xls', 'csv', 'ods'];

/**
 * Lê a planilha e devolve a matriz de células.
 *
 * @throws Exception quando o arquivo não pode ser interpretado.
 */
function importacaoLerPlanilha(string $caminho): array
{
    $leitor = IOFactory::createReaderForFile($caminho);
    $leitor->setReadDataOnly(true);   // estilos e fórmulas não interessam e pesam

    $planilha = $leitor->load($caminho);
    $linhas = $planilha->getActiveSheet()->toArray(null, true, false, false);
    $planilha->disconnectWorksheets();

    if (count($linhas) > IMPORTACAO_LINHAS_MAX) {
        throw new Exception('A planilha tem mais de ' . IMPORTACAO_LINHAS_MAX . ' linhas.');
    }

    return $linhas;
}

/**
 * Nomes das colunas para a tela de mapeamento: "A — Código Produto".
 *
 * O usuário precisa reconhecer a coluna, então mostramos a letra do Excel junto
 * do título lido no cabeçalho (ou uma amostra, quando o cabeçalho é vazio).
 */
function importacaoColunasDisponiveis(array $linhas, int $linha_cabecalho): array
{
    $cabecalho = $linhas[$linha_cabecalho] ?? [];
    $largura = 0;
    foreach ($linhas as $linha) {
        $largura = max($largura, count($linha));
    }

    $colunas = [];
    for ($i = 0; $i < $largura; $i++) {
        $letra = importacaoLetraColuna($i);
        $titulo = trim((string) ($cabecalho[$i] ?? ''));

        if ($titulo === '') {
            // Sem título, mostra o primeiro valor preenchido abaixo como pista.
            foreach (array_slice($linhas, $linha_cabecalho + 1, 5) as $amostra) {
                $valor = trim((string) ($amostra[$i] ?? ''));
                if ($valor !== '') {
                    $titulo = '(ex.: ' . mb_strimwidth($valor, 0, 22, '…') . ')';
                    break;
                }
            }
        }

        $colunas[$i] = $titulo !== '' ? "$letra — $titulo" : $letra;
    }

    return $colunas;
}

/** Índice 0 => "A", 25 => "Z", 26 => "AA". */
function importacaoLetraColuna(int $indice): string
{
    $letra = '';
    $indice++;
    while ($indice > 0) {
        $resto = ($indice - 1) % 26;
        $letra = chr(65 + $resto) . $letra;
        $indice = intdiv($indice - 1, 26);
    }
    return $letra;
}

/**
 * Carrega materiais oficiais e o de/para aplicável, já indexados para busca direta.
 *
 * Tudo vem em duas consultas e a resolução acontece em memória: são poucas dezenas
 * de materiais, e assim uma planilha de 500 linhas não vira 500 idas ao banco.
 *
 * O de/para específico do modelo tem precedência sobre o global, porque o mesmo
 * "PET B" pode significar materiais diferentes em parceiros diferentes. Relação
 * de OUTRO modelo nunca é considerada.
 */
function importacaoCarregarCatalogo(PDO $pdo, ?int $id_modelo): array
{
    $materiais = [];
    $por_nome = [];
    $por_codigo = [];

    $sql = "SELECT id_material, nm_material, codigo, tipo, unidade_medida,
                   COALESCE(qt_estoque, 0) AS qt_estoque
            FROM tb_material
            WHERE status = 1
            ORDER BY nm_material";

    foreach ($pdo->query($sql) as $m) {
        $id = (int) $m['id_material'];
        $materiais[$id] = [
            'id_material'    => $id,
            'nm_material'    => $m['nm_material'],
            'codigo'         => $m['codigo'],
            'tipo'           => $m['tipo'],
            'unidade_medida' => $m['unidade_medida'],
            'qt_estoque'     => (float) $m['qt_estoque'],
        ];

        $chave_nome = planilhaNormalizar($m['nm_material']);
        if ($chave_nome !== '' && !isset($por_nome[$chave_nome])) {
            $por_nome[$chave_nome] = $id;
        }

        if (!empty($m['codigo'])) {
            $chave_codigo = planilhaChave($m['codigo']);
            if ($chave_codigo !== '' && !isset($por_codigo[$chave_codigo])) {
                $por_codigo[$chave_codigo] = $id;
            }
        }
    }

    // Globais primeiro, específicas depois: assim a específica sobrescreve.
    $stmt = $pdo->prepare("SELECT id_material, nome_normalizado, codigo_normalizado, id_modelo
                           FROM material_relacao
                           WHERE status = 1
                             AND (id_modelo IS NULL OR id_modelo = :id_modelo)
                           ORDER BY (id_modelo IS NOT NULL)");
    $stmt->execute([':id_modelo' => $id_modelo]);

    $rel_nome = [];
    $rel_codigo = [];

    foreach ($stmt as $r) {
        $id = (int) $r['id_material'];
        if (!isset($materiais[$id])) {
            continue;   // relação apontando para material inativado
        }
        if (!empty($r['nome_normalizado'])) {
            $rel_nome[$r['nome_normalizado']] = $id;
        }
        if (!empty($r['codigo_normalizado'])) {
            $rel_codigo[$r['codigo_normalizado']] = $id;
        }
    }

    return compact('materiais', 'por_nome', 'por_codigo', 'rel_nome', 'rel_codigo');
}

/**
 * Identifica o material oficial a partir do nome e/ou código da planilha.
 *
 * A ordem é do mais confiável para o menos: código bate exato e é curto, então
 * vem antes do nome. Só reconhece por correspondência EXATA (depois de
 * normalizar) — semelhança nunca confirma nada aqui, só vira sugestão.
 *
 * @return array{id_material:int,via:string}|null
 */
function importacaoResolverMaterial(array $catalogo, string $nome, string $codigo): ?array
{
    $chave_codigo = $codigo !== '' ? planilhaChave($codigo) : '';
    $chave_nome   = $nome !== '' ? planilhaNormalizar($nome) : '';

    if ($chave_codigo !== '') {
        if (isset($catalogo['por_codigo'][$chave_codigo])) {
            return ['id_material' => $catalogo['por_codigo'][$chave_codigo], 'via' => 'codigo_oficial'];
        }
        if (isset($catalogo['rel_codigo'][$chave_codigo])) {
            return ['id_material' => $catalogo['rel_codigo'][$chave_codigo], 'via' => 'relacao_codigo'];
        }
    }

    if ($chave_nome !== '') {
        if (isset($catalogo['por_nome'][$chave_nome])) {
            return ['id_material' => $catalogo['por_nome'][$chave_nome], 'via' => 'nome_oficial'];
        }
        if (isset($catalogo['rel_nome'][$chave_nome])) {
            return ['id_material' => $catalogo['rel_nome'][$chave_nome], 'via' => 'relacao_nome'];
        }
    }

    return null;
}

/** Como cada forma de identificação aparece na tela. */
function importacaoRotuloVia(?string $via): string
{
    return [
        'codigo_oficial' => 'Código do cadastro',
        'nome_oficial'   => 'Nome do cadastro',
        'relacao_codigo' => 'De/para por código',
        'relacao_nome'   => 'De/para por nome',
    ][$via] ?? '—';
}

/**
 * Sugere materiais parecidos para o usuário confirmar.
 *
 * Isso é só um atalho para não obrigar a procurar na lista inteira: a sugestão
 * nunca é aplicada sozinha, porque associar o material errado significa baixar
 * estoque do produto errado.
 *
 * @return array Até 3 candidatos, do mais parecido para o menos.
 */
function importacaoSugestoes(array $catalogo, string $nome, string $codigo, int $limite = 3): array
{
    $alvo = planilhaNormalizar($nome !== '' ? $nome : $codigo);
    if ($alvo === '') {
        return [];
    }

    $candidatos = [];

    foreach ($catalogo['materiais'] as $m) {
        $score = 0.0;

        foreach (array_filter([$m['nm_material'], $m['codigo']]) as $referencia) {
            $comparado = planilhaNormalizar($referencia);
            if ($comparado === '') {
                continue;
            }

            similar_text($alvo, $comparado, $percentual);

            // Prefixo em comum conta muito: "PET B" contra "PET BRANCA" é
            // exatamente o caso que queremos sugerir.
            if (str_starts_with($comparado, $alvo) || str_starts_with($alvo, $comparado)) {
                $percentual = max($percentual, 85);
            }

            $score = max($score, $percentual);
        }

        if ($score >= 55) {
            $candidatos[] = [
                'id_material' => $m['id_material'],
                'nm_material' => $m['nm_material'],
                'codigo'      => $m['codigo'],
                'score'       => round($score),
            ];
        }
    }

    usort($candidatos, fn($a, $b) => $b['score'] <=> $a['score']);

    return array_slice($candidatos, 0, $limite);
}

/**
 * Localiza o comprador no cadastro de fornecedores.
 *
 * A venda é sempre PARA um fornecedor. Algumas planilhas de parceiro chamam esse
 * campo de "cliente" (a ótica é a deles), por isso os dois cabeçalhos caem aqui —
 * mas no sistema o registro é sempre o fornecedor.
 *
 * @return array{id_fornecedor:int,nome:string}|null
 */
function importacaoResolverFornecedor(PDO $pdo, string $texto): ?array
{
    $texto = trim($texto);
    if ($texto === '') {
        return null;
    }

    $digitos = preg_replace('/\D/', '', $texto);

    $stmt = $pdo->prepare("SELECT id_fornecedor, nome_razao_social
                           FROM fornecedores
                           WHERE status = 1
                             AND (LOWER(nome_razao_social) = LOWER(:nome)
                                  OR (:digitos <> '' AND regexp_replace(COALESCE(cnpj_cpf, ''), '\D', '', 'g') = :digitos))
                           LIMIT 2");
    $stmt->execute([':nome' => $texto, ':digitos' => $digitos]);
    $achados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dois com o mesmo nome: não dá para escolher sozinho sem arriscar a venda errada.
    if (count($achados) !== 1) {
        return null;
    }

    return [
        'id_fornecedor' => (int) $achados[0]['id_fornecedor'],
        'nome'          => $achados[0]['nome_razao_social'],
    ];
}

/**
 * Converte a célula de data em 'Y-m-d H:i:s'.
 *
 * Com readDataOnly o Excel entrega data como número de série, mas CSV entrega
 * texto — por isso os dois caminhos.
 */
function importacaoData($valor): ?string
{
    if ($valor === null || $valor === '' ) {
        return null;
    }

    if (is_numeric($valor)) {
        try {
            return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    $texto = trim((string) $valor);

    // O "!" zera os campos que o formato não informa. Sem ele, uma data sem hora
    // herdaria a hora atual, e a venda ficaria carimbada com o horário do upload
    // em vez de 00:00 — divergindo do que vem como número de série do Excel.
    foreach (['!d/m/Y H:i:s', '!d/m/Y H:i', '!d/m/Y', '!Y-m-d H:i:s', '!Y-m-d', '!d-m-Y'] as $formato) {
        $data = DateTime::createFromFormat($formato, $texto);
        if ($data !== false) {
            return $data->format('Y-m-d H:i:s');
        }
    }

    return null;
}

/**
 * Normaliza o que veio do formulário de conferência em edições por linha.
 *
 * Campo em branco significa "não mexi", e não "zero" — por isso o null: só
 * sobrescreve o valor lido da planilha quando o usuário realmente digitou algo.
 * A tara é a exceção: nela o zero digitado é uma informação legítima.
 *
 * @param array $post  $_POST['itens'] — linha => campos editados
 */
function importacaoNormalizarEdicoes(array $post): array
{
    $edicoes = [];

    foreach ($post as $linha => $campos) {
        $linha = (int) $linha;
        if (!$linha || !is_array($campos)) {
            continue;
        }

        $numero = function (string $campo) use ($campos) {
            $valor = trim((string) ($campos[$campo] ?? ''));
            return $valor === '' ? null : planilhaNumero($valor);
        };

        $edicoes[$linha] = [
            'id_material'      => isset($campos['id_material']) && $campos['id_material'] !== ''
                                    ? (int) $campos['id_material'] : null,
            'quantidade_bruta' => $numero('quantidade_bruta'),
            'tara'             => $numero('tara'),
            'preco_un'         => $numero('preco_un'),
            'ignorar'          => !empty($campos['ignorar']),
        ];
    }

    return $edicoes;
}

/**
 * Transforma as linhas da planilha na pré-visualização da venda.
 *
 * Nenhuma escrita acontece aqui: o resultado é o que a tela mostra para o usuário
 * conferir — e editar — antes de confirmar.
 *
 * As edições sobrepõem o que veio da planilha, mas continuam passando por toda a
 * validação (quantidade positiva, tara menor que o bruto, estoque suficiente):
 * o formulário muda os números, não as regras.
 *
 * @param array $mapa     campo do sistema => índice da coluna
 * @param array $edicoes  linha => ['id_material','quantidade_bruta','tara','preco_un','ignorar']
 */
function importacaoMontarPreview(
    PDO $pdo,
    array $linhas,
    int $linha_cabecalho,
    array $mapa,
    ?int $id_modelo,
    array $edicoes = []
): array {
    $catalogo = importacaoCarregarCatalogo($pdo, $id_modelo);

    $celula = function (array $linha, string $campo) use ($mapa) {
        if (!isset($mapa[$campo]) || $mapa[$campo] === '' || $mapa[$campo] === null) {
            return '';
        }
        return trim((string) ($linha[(int) $mapa[$campo]] ?? ''));
    };

    $itens = [];
    $consumido = [];        // id_material => quantidade já comprometida nas linhas acima
    $fornecedor_texto = '';
    $pedido = '';
    $data_venda = null;

    foreach ($linhas as $indice => $linha) {
        if ($indice <= $linha_cabecalho) {
            continue;
        }
        if (implode('', array_map('strval', $linha)) === '') {
            continue;   // linha em branco é separador, não erro
        }

        $numero = $indice + 1;   // como aparece no Excel

        if ($fornecedor_texto === '') {
            $fornecedor_texto = $celula($linha, 'fornecedor');
        }
        if ($pedido === '') {
            $pedido = $celula($linha, 'pedido');
        }
        if ($data_venda === null) {
            $data_venda = importacaoData($celula($linha, 'data'));
        }

        $edicao = $edicoes[$numero] ?? [];

        $nome   = $celula($linha, 'material');
        $codigo = $celula($linha, 'codigo');

        // Valores da planilha; a edição do usuário, quando existe, substitui.
        // A tara vale 0 quando a planilha não tem a coluna — ela é opcional.
        $bruto_planilha = planilhaNumero($celula($linha, 'peso_bruto'));
        $tara_planilha  = planilhaNumero($celula($linha, 'tara')) ?? 0.0;
        $preco_planilha = planilhaNumero($celula($linha, 'preco_un'));

        $bruto = $edicao['quantidade_bruta'] ?? $bruto_planilha;
        $tara  = $edicao['tara'] ?? $tara_planilha;
        $preco = $edicao['preco_un'] ?? $preco_planilha;

        $editado = ($edicao['quantidade_bruta'] ?? null) !== null
                || ($edicao['tara'] ?? null) !== null
                || ($edicao['preco_un'] ?? null) !== null
                || !empty($edicao['id_material']);

        $item = [
            'linha'            => $numero,
            'planilha_nome'    => $nome,
            'planilha_codigo'  => $codigo,
            'id_material'      => null,
            'material_nome'    => null,
            'material_codigo'  => null,
            'unidade'          => 'kg',
            'via'              => null,
            'quantidade_bruta' => $bruto,
            'tara'             => $tara,
            'quantidade'       => null,
            'preco_un'         => $preco,
            'valor_total'      => null,
            'estoque_antes'    => null,
            'estoque_depois'   => null,
            'situacao'         => 'ok',
            'motivo'           => '',
            'sugestoes'        => [],
            'editado'          => $editado,
        ];

        if (!empty($edicao['ignorar'])) {
            $item['situacao'] = 'ignorado';
            $item['motivo'] = 'Item removido da venda pelo usuário.';
            $itens[] = $item;
            continue;
        }

        $do_arquivo = ($nome !== '' || $codigo !== '')
            ? importacaoResolverMaterial($catalogo, $nome, $codigo)
            : null;

        $escolha = $edicao['id_material'] ?? null;

        if ($escolha && isset($catalogo['materiais'][$escolha])) {
            // Só conta como escolha do usuário quando muda o que o arquivo já
            // resolveria. Sem isso, toda linha reenviada pelo formulário viraria
            // uma relação de/para nova, poluindo o cadastro.
            $resolvido = ($do_arquivo !== null && $do_arquivo['id_material'] === $escolha)
                ? $do_arquivo
                : ['id_material' => $escolha, 'via' => 'escolha_usuario'];
        } elseif ($do_arquivo === null && $nome === '' && $codigo === '') {
            $item['situacao'] = 'erro';
            $item['motivo'] = 'Linha sem material nem código. Escolha o material para aproveitá-la.';
            $itens[] = $item;
            continue;
        } else {
            $resolvido = $do_arquivo;
        }

        if ($resolvido === null) {
            $item['situacao'] = 'nao_reconhecido';
            $item['motivo'] = 'Não há relação cadastrada para este material.';
            $item['sugestoes'] = importacaoSugestoes($catalogo, $nome, $codigo);
            $itens[] = $item;
            continue;
        }

        $material = $catalogo['materiais'][$resolvido['id_material']];

        $item['id_material']     = $material['id_material'];
        $item['material_nome']   = $material['nm_material'];
        $item['material_codigo'] = $material['codigo'];
        $item['unidade']         = $material['unidade_medida'];
        $item['via']             = $resolvido['via'];

        if ($bruto === null || $bruto <= 0) {
            $item['situacao'] = 'erro';
            $item['motivo'] = 'Quantidade ausente ou menor/igual a zero.';
            $itens[] = $item;
            continue;
        }
        if ($tara < 0) {
            $item['situacao'] = 'erro';
            $item['motivo'] = 'Tara negativa.';
            $itens[] = $item;
            continue;
        }

        $liquido = round($bruto - $tara, 2);
        if ($liquido <= 0) {
            $item['situacao'] = 'erro';
            $item['motivo'] = 'A tara é maior ou igual à quantidade.';
            $itens[] = $item;
            continue;
        }
        if ($preco === null || $preco <= 0) {
            $item['situacao'] = 'erro';
            $item['motivo'] = 'Valor unitário ausente ou menor/igual a zero.';
            $itens[] = $item;
            continue;
        }

        $item['quantidade']  = $liquido;
        $item['valor_total'] = round($liquido * $preco, 2);

        // O saldo mostrado desconta o que as linhas anteriores já consumiram,
        // senão duas linhas do mesmo material pareceriam caber quando não cabem.
        $ja_usado = $consumido[$material['id_material']] ?? 0;
        $item['estoque_antes']  = $material['qt_estoque'] - $ja_usado;
        $item['estoque_depois'] = $item['estoque_antes'] - $liquido;

        if ($item['estoque_depois'] < 0) {
            $item['situacao'] = 'estoque';
            $item['motivo'] = sprintf(
                'Estoque insuficiente: necessário %s %s, disponível %s %s (diferença %s %s).',
                number_format($liquido, 2, ',', '.'), $material['unidade_medida'],
                number_format(max($item['estoque_antes'], 0), 2, ',', '.'), $material['unidade_medida'],
                number_format($liquido - max($item['estoque_antes'], 0), 2, ',', '.'), $material['unidade_medida']
            );
            $itens[] = $item;
            continue;
        }

        $consumido[$material['id_material']] = $ja_usado + $liquido;
        $itens[] = $item;
    }

    $fornecedor = $fornecedor_texto !== '' ? importacaoResolverFornecedor($pdo, $fornecedor_texto) : null;

    $contagem = ['ok' => 0, 'nao_reconhecido' => 0, 'erro' => 0, 'estoque' => 0, 'ignorado' => 0];
    $total_peso = 0.0;
    $total_valor = 0.0;

    foreach ($itens as $item) {
        $contagem[$item['situacao']]++;
        if ($item['situacao'] === 'ok') {
            $total_peso  += $item['quantidade'];
            $total_valor += $item['valor_total'];
        }
    }

    return [
        'itens'            => $itens,
        'contagem'         => $contagem,
        'total_peso'       => $total_peso,
        'total_valor'      => $total_valor,
        'fornecedor'       => $fornecedor,
        'fornecedor_texto' => $fornecedor_texto,
        'pedido'           => $pedido,
        'data_venda'       => $data_venda,
    ];
}

/**
 * Grava (ou reativa) uma relação de/para.
 *
 * Usa ON CONFLICT sobre os índices únicos para que confirmar a mesma sugestão
 * duas vezes não exploda nem duplique.
 */
function importacaoSalvarRelacao(
    PDO $pdo,
    int $id_material,
    string $nome_externo,
    string $codigo_externo,
    ?int $id_modelo,
    string $origem = 'Importação'
): void {
    $nome_normalizado   = $nome_externo !== '' ? planilhaNormalizar($nome_externo) : null;
    $codigo_normalizado = $codigo_externo !== '' ? planilhaChave($codigo_externo) : null;

    if ($nome_normalizado === null && $codigo_normalizado === null) {
        return;
    }

    // Nome e código viram relações separadas: a próxima planilha pode trazer só um dos dois.
    $gravar = function (?string $nome, ?string $nome_norm, ?string $codigo, ?string $codigo_norm)
        use ($pdo, $id_material, $id_modelo, $origem) {

        $stmt = $pdo->prepare("INSERT INTO material_relacao
                                  (id_material, nome_externo, nome_normalizado,
                                   codigo_externo, codigo_normalizado, id_modelo, origem)
                               VALUES
                                  (:id_material, :nome, :nome_norm, :codigo, :codigo_norm, :id_modelo, :origem)
                               ON CONFLICT DO NOTHING");
        $stmt->execute([
            ':id_material' => $id_material,
            ':nome'        => $nome,
            ':nome_norm'   => $nome_norm,
            ':codigo'      => $codigo,
            ':codigo_norm' => $codigo_norm,
            ':id_modelo'   => $id_modelo,
            ':origem'      => $origem,
        ]);
    };

    if ($nome_normalizado !== null) {
        $gravar($nome_externo, $nome_normalizado, null, null);
    }
    if ($codigo_normalizado !== null) {
        $gravar(null, null, $codigo_externo, $codigo_normalizado);
    }
}
