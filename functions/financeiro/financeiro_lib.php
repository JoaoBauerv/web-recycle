<?php
/**
 * Utilidades compartilhadas do módulo financeiro.
 *
 * Regras de situação de parcela e formatação ficam aqui para a tela, o
 * relatório e a exportação não divergirem entre si.
 */

/** Formas de pagamento aceitas. */
const FINANCEIRO_FORMAS = [
    'pix'         => 'PIX',
    'dinheiro'    => 'Dinheiro',
    'transferencia' => 'Transferência',
    'boleto'      => 'Boleto',
    'cartao'      => 'Cartão',
    'cheque'      => 'Cheque',
    'debito_auto' => 'Débito automático',
    'outro'       => 'Outro',
];

/** Periodicidades de despesa recorrente. */
const FINANCEIRO_PERIODOS = [
    'semanal'   => 'Semanal',
    'quinzenal' => 'Quinzenal',
    'mensal'    => 'Mensal',
    'anual'     => 'Anual',
];

/**
 * Situação real da parcela.
 *
 * 'vencida' não é gravada no banco: ela depende da data de hoje, e uma coluna
 * precisaria de rotina diária para não mentir. Derivar na leitura mantém a
 * informação sempre correta sem processo em segundo plano.
 */
function financeiroSituacaoParcela(array $parcela, ?string $hoje = null): array
{
    $hoje = $hoje ?? date('Y-m-d');

    if ($parcela['status'] === 'cancelada') {
        return ['chave' => 'cancelada', 'rotulo' => 'Cancelada',
                'classe' => 'bg-secondary-subtle text-secondary', 'icone' => 'bi-slash-circle'];
    }
    if ($parcela['status'] === 'paga') {
        return ['chave' => 'paga', 'rotulo' => 'Paga',
                'classe' => 'bg-success-subtle text-success', 'icone' => 'bi-check-circle'];
    }
    if ($parcela['data_vencimento'] < $hoje) {
        return ['chave' => 'vencida', 'rotulo' => 'Vencida',
                'classe' => 'bg-danger-subtle text-danger', 'icone' => 'bi-exclamation-octagon'];
    }
    return ['chave' => 'pendente', 'rotulo' => 'Pendente',
            'classe' => 'bg-warning-subtle text-warning-emphasis', 'icone' => 'bi-clock'];
}

/**
 * Trecho SQL que devolve a situação derivada, para filtrar e agregar no banco.
 * Mantém a MESMA regra de financeiroSituacaoParcela().
 */
function financeiroSituacaoSql(string $alias = 'pa'): string
{
    return "CASE
                WHEN $alias.status = 'cancelada' THEN 'cancelada'
                WHEN $alias.status = 'paga'      THEN 'paga'
                WHEN $alias.data_vencimento < CURRENT_DATE THEN 'vencida'
                ELSE 'pendente'
            END";
}

/** Aceita "1.234,56" e "1234.56". Devolve null quando não é número. */
function financeiroNumero(string $valor): ?float
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

/** Converte 'Y-m-d' ou 'd/m/Y' em 'Y-m-d', ou null. */
function financeiroData(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    foreach (['!Y-m-d', '!d/m/Y'] as $formato) {
        $d = DateTimeImmutable::createFromFormat($formato, $valor);
        if ($d !== false) {
            return $d->format('Y-m-d');
        }
    }
    return null;
}

/** R$ 1.234,56 */
function financeiroMoeda(?float $valor): string
{
    return $valor === null ? '—' : 'R$ ' . number_format($valor, 2, ',', '.');
}

/** 1.234,56 (sem símbolo) */
function financeiroNum(?float $valor, int $casas = 2): string
{
    return $valor === null ? '—' : number_format($valor, $casas, ',', '.');
}

/** 42,86% */
function financeiroPercentual(?float $valor): string
{
    return $valor === null ? '—' : number_format($valor, 2, ',', '.') . '%';
}

/**
 * Distribui o valor total entre as parcelas sem perder centavos.
 *
 * R$ 1.000,00 em 3 não é 333,33 três vezes: sobra 1 centavo. A diferença vai
 * para a primeira parcela, que é a convenção usual e a que o usuário confere.
 *
 * @return float[] valores na ordem das parcelas
 */
function financeiroDividirParcelas(float $total, int $quantidade): array
{
    $total_centavos = (int) round($total * 100);
    $base = intdiv($total_centavos, $quantidade);
    $sobra = $total_centavos - ($base * $quantidade);

    $valores = array_fill(0, $quantidade, $base / 100);
    if ($sobra > 0) {
        $valores[0] = ($base + $sobra) / 100;
    }

    return $valores;
}

/**
 * Datas de vencimento das parcelas a partir da primeira.
 *
 * Usa "+N months" sobre o primeiro vencimento em vez de somar mês a mês, para
 * 31/01 + 1 mês não virar 03/03 por causa do transbordo de fevereiro: quando o
 * dia não existe no mês, cai no último dia dele.
 */
function financeiroVencimentos(string $primeiro, int $quantidade, string $periodo = 'mensal'): array
{
    $base = new DateTimeImmutable($primeiro);
    $dia = (int) $base->format('d');
    $datas = [];

    for ($i = 0; $i < $quantidade; $i++) {
        if ($periodo === 'semanal') {
            $datas[] = $base->modify("+" . ($i * 7) . " days")->format('Y-m-d');
            continue;
        }
        if ($periodo === 'quinzenal') {
            $datas[] = $base->modify("+" . ($i * 15) . " days")->format('Y-m-d');
            continue;
        }

        $meses = $periodo === 'anual' ? $i * 12 : $i;
        $alvo = $base->modify('first day of this month')->modify("+$meses months");
        $ultimo_dia = (int) $alvo->format('t');
        $datas[] = $alvo->setDate((int) $alvo->format('Y'), (int) $alvo->format('m'), min($dia, $ultimo_dia))
                        ->format('Y-m-d');
    }

    return $datas;
}
