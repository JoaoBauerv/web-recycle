<?php
/**
 * Regras compartilhadas da planilha de venda (modelo e importação).
 *
 * O modelo gerado e o leitor precisam concordar sobre os nomes das colunas;
 * manter os dois lados aqui evita que um mude e o outro pare de reconhecer.
 */

/** Cabeçalhos do modelo, na ordem em que aparecem na planilha. */
const VENDA_PLANILHA_COLUNAS = [
    'fornecedor'  => 'Fornecedor',
    'codigo'      => 'Código',
    'material'    => 'Material',
    'peso_bruto'  => 'Quantidade (kg)',
    'tara'        => 'Tara (kg)',
    'preco_un'    => 'Valor unitário (R$)',
    'pedido'      => 'Pedido',
    'data'        => 'Data da venda',
];

/** Rótulo de cada campo na tela de mapeamento de colunas. */
const VENDA_PLANILHA_ROTULOS = [
    'fornecedor'  => 'Fornecedor',
    'codigo'      => 'Código do material',
    'material'    => 'Material (nome)',
    'peso_bruto'  => 'Quantidade',
    'tara'        => 'Tara (opcional)',
    'preco_un'    => 'Valor unitário',
    'pedido'      => 'Nº do pedido',
    'data'        => 'Data da venda',
];

/**
 * Sinônimos aceitos na detecção automática do cabeçalho.
 *
 * Quem monta a planilha raramente escreve o cabeçalho exatamente como o modelo,
 * então cada campo aceita as variações mais comuns. A comparação é feita sobre o
 * texto já normalizado (minúsculo, sem acento e sem pontuação). Quando nada casa,
 * o usuário mapeia as colunas na mão — a detecção é só um atalho.
 */
const VENDA_PLANILHA_SINONIMOS = [
    // A venda é PARA o fornecedor. Algumas planilhas chamam de "cliente" quem
    // está comprando, então os dois cabeçalhos caem no mesmo campo.
    'fornecedor' => ['fornecedor', 'cliente', 'comprador', 'razao social', 'empresa', 'destino'],
    'codigo'     => ['codigo', 'cod', 'codigo produto', 'codigo material', 'cod produto', 'cod material', 'sku', 'referencia'],
    'material'   => ['material', 'produto', 'descricao', 'item', 'mercadoria', 'nome'],
    'peso_bruto' => ['quantidade kg', 'quantidade', 'qtd', 'qtde', 'peso bruto kg', 'peso bruto', 'bruto', 'peso'],
    'tara'       => ['tara kg', 'tara', 'desconto', 'descontos'],
    'preco_un'   => ['valor unitario r', 'valor unitario', 'preco unitario', 'preco un', 'valor un', 'preco', 'valor', 'preco kg'],
    'pedido'     => ['pedido', 'n pedido', 'numero do pedido', 'numero pedido', 'ordem', 'nota', 'nf', 'id externo'],
    'data'       => ['data da venda', 'data venda', 'data', 'emissao', 'data emissao'],
];

/**
 * Campos sem os quais a planilha não pode ser interpretada.
 *
 * Material e código não entram aqui porque um supre o outro: a planilha pode
 * identificar o produto só pelo código. Isso é conferido à parte.
 */
const VENDA_PLANILHA_OBRIGATORIAS = ['peso_bruto', 'preco_un'];

/**
 * Mapa de acentos usado na normalização.
 *
 * Não dá para usar iconv('ASCII//TRANSLIT'): no Windows ele transforma "á" em "'a",
 * então "unitário" viraria "unit ario" e nenhum cabeçalho acentuado seria reconhecido.
 * Uma tabela explícita se comporta igual em qualquer servidor.
 */
const PLANILHA_ACENTOS = [
    'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','å'=>'a',
    'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
    'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
    'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
    'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
    'ç'=>'c','ñ'=>'n',
];

/**
 * Reduz um texto à forma usada nas comparações: sem acento, sem pontuação,
 * minúsculo e com espaços colapsados. "Preço Unitário (R$)" vira "preco unitario r".
 */
function planilhaNormalizar(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }

    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, PLANILHA_ACENTOS);
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);

    return trim(preg_replace('/\s+/', ' ', $texto));
}

/**
 * Chave de comparação de código: só letras e números.
 *
 * "PET-B", "PET B", "pet.b" e "petb" viram todos "petb", então o de/para não
 * depende de como o parceiro pontua o código. Diferente do nome, aqui o espaço
 * também some: em código ele nunca é significativo.
 */
function planilhaChave(string $texto): string
{
    return str_replace(' ', '', planilhaNormalizar($texto));
}

/**
 * Converte o conteúdo de uma célula em número.
 *
 * O Excel devolve float quando a célula é numérica, mas planilhas montadas à mão
 * costumam trazer texto: "1.234,56", "R$ 1,50", "12,5 kg". Aceitar os dois formatos
 * decimais (vírgula e ponto) evita rejeitar linha boa por detalhe de digitação.
 */
function planilhaNumero($valor): ?float
{
    if ($valor === null || $valor === '') {
        return null;
    }

    if (is_int($valor) || is_float($valor)) {
        return (float) $valor;
    }

    $texto = trim((string) $valor);
    $texto = preg_replace('/[^0-9,.\-]/', '', $texto);

    if ($texto === '' || $texto === '-') {
        return null;
    }

    $ultima_virgula = strrpos($texto, ',');
    $ultimo_ponto   = strrpos($texto, '.');

    if ($ultima_virgula !== false && $ultimo_ponto !== false) {
        // O separador decimal é o que aparece por último: "1.234,56" vs "1,234.56".
        if ($ultima_virgula > $ultimo_ponto) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }
    } elseif ($ultima_virgula !== false) {
        $texto = str_replace(',', '.', $texto);
    }

    return is_numeric($texto) ? (float) $texto : null;
}

/**
 * Localiza a linha de cabeçalho e devolve o índice de cada campo conhecido.
 *
 * A planilha pode ter título, logo ou linhas em branco antes da tabela, então a
 * busca varre as primeiras linhas até achar uma que contenha os campos obrigatórios.
 *
 * @param array $linhas Matriz da planilha (índice 0 = primeira linha).
 * @return array{linha:int, mapa:array<string,int>}|null
 */
function planilhaLocalizarCabecalho(array $linhas): ?array
{
    $limite = min(count($linhas), 15);

    for ($i = 0; $i < $limite; $i++) {
        $mapa = [];

        foreach ($linhas[$i] as $coluna => $celula) {
            $normalizado = planilhaNormalizar((string) $celula);
            if ($normalizado === '') {
                continue;
            }

            foreach (VENDA_PLANILHA_SINONIMOS as $campo => $sinonimos) {
                // Sem isset(): a primeira coluna que casa com o campo vence, para
                // uma planilha com "Peso" e "Peso bruto" não sobrescrever o certo.
                if (!isset($mapa[$campo]) && in_array($normalizado, $sinonimos, true)) {
                    $mapa[$campo] = $coluna;
                    break;
                }
            }
        }

        // Precisa dos obrigatórios e de ao menos uma forma de identificar o
        // produto — nome ou código, tanto faz qual.
        $faltando = array_diff(VENDA_PLANILHA_OBRIGATORIAS, array_keys($mapa));
        $identifica = isset($mapa['material']) || isset($mapa['codigo']);

        if (empty($faltando) && $identifica) {
            return ['linha' => $i, 'mapa' => $mapa];
        }
    }

    return null;
}
