<?php
/**
 * Consulta compartilhada da análise de margem.
 *
 * Usada pela tela, pelo detalhe do material, pelo PDF e pela exportação, para
 * os quatro mostrarem exatamente os mesmos números.
 *
 * ---------------------------------------------------------------------------
 * CUSTO DO MATERIAL VENDIDO (CMV) — limitação documentada
 * ---------------------------------------------------------------------------
 * O sistema NÃO tem método de custo de estoque: não há controle de lote, nem
 * coluna de custo em estoque_movimentacoes, nem vínculo de vendas_itens com a
 * compra que abasteceu a venda. Não dá para dizer por qual preço entrou o quilo
 * que saiu.
 *
 * Por isso o CMV usa **custo médio ponderado** — método contábil reconhecido e
 * o único calculável com os dados existentes:
 *
 *     custo médio = SUM(peso_material * preco_un) / SUM(peso_material)
 *                   sobre as compras até a data final do filtro
 *     CMV         = quantidade vendida no período * custo médio
 *
 * O custo médio considera as compras ATÉ o fim do período, não só as de dentro
 * dele: material vendido em setembro pode ter sido comprado em agosto, e usar
 * só as compras do período daria custo zero para ele.
 *
 * Isso torna "custo das compras do período" (o que se gastou comprando) um
 * número diferente de "CMV" (o custo do que se vendeu). A tela mostra os dois
 * separados de propósito — confundir os dois é o erro clássico aqui.
 */

require_once __DIR__ . '/financeiro_lib.php';

/** Limite de margem a partir do qual o material é considerado saudável (§14). */
const MARGEM_ALERTA_PERCENTUAL = 15.0;

/**
 * Faixas de giro. Configurável aqui em vez de espalhado pelas telas (§15).
 * 'parado' e 'baixo' viram alerta de estoque parado.
 */
const GIRO_FAIXAS = [
    'parado' => ['ate' => 0.0,  'rotulo' => 'Parado',      'classe' => 'bg-danger-subtle text-danger'],
    'baixo'  => ['ate' => 0.5,  'rotulo' => 'Giro baixo',  'classe' => 'bg-warning-subtle text-warning-emphasis'],
    'normal' => ['ate' => 2.0,  'rotulo' => 'Giro normal', 'classe' => 'bg-secondary-subtle text-secondary'],
    'alto'   => ['ate' => null, 'rotulo' => 'Giro alto',   'classe' => 'bg-success-subtle text-success'],
];

/** Corte da curva ABC sobre a receita acumulada (§12). */
const ABC_CORTES = ['A' => 80.0, 'B' => 95.0];

/**
 * Lê e normaliza os filtros da requisição.
 *
 * O período padrão é o mês corrente. Datas inválidas caem no padrão em vez de
 * quebrar a consulta.
 */
function margemFiltros(array $req): array
{
    $atalho = $req['atalho'] ?? '';
    $hoje = new DateTimeImmutable('today');

    $periodos = [
        'hoje'          => [$hoje, $hoje],
        'mes'           => [$hoje->modify('first day of this month'), $hoje->modify('last day of this month')],
        'mes_anterior'  => [$hoje->modify('first day of last month'), $hoje->modify('last day of last month')],
        'dias30'        => [$hoje->modify('-29 days'), $hoje],
        'meses3'        => [$hoje->modify('-2 months')->modify('first day of this month'), $hoje],
        'ano'           => [$hoje->modify('first day of January this year'), $hoje],
    ];

    if (isset($periodos[$atalho])) {
        [$inicio, $fim] = $periodos[$atalho];
    } else {
        $inicio = margemData($req['inicio'] ?? '') ?? $hoje->modify('first day of this month');
        $fim    = margemData($req['fim'] ?? '') ?? $hoje->modify('last day of this month');
        $atalho = '';
    }

    // Período invertido é erro de digitação: troca em vez de devolver vazio.
    if ($inicio > $fim) {
        [$inicio, $fim] = [$fim, $inicio];
    }

    return [
        'inicio'      => $inicio->format('Y-m-d'),
        'fim'         => $fim->format('Y-m-d'),
        'id_material' => (int) ($req['id_material'] ?? 0) ?: null,
        'tipo'        => trim($req['tipo'] ?? ''),
        'atalho'      => $atalho,
    ];
}

/** Converte 'Y-m-d' ou 'd/m/Y' em DateTimeImmutable, ou null. */
function margemData(string $valor): ?DateTimeImmutable
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    foreach (['!Y-m-d', '!d/m/Y'] as $formato) {
        $data = DateTimeImmutable::createFromFormat($formato, $valor);
        if ($data !== false) {
            return $data;
        }
    }
    return null;
}

/**
 * Monta a análise de margem por material.
 *
 * As agregações de compra e de venda vêm de subconsultas SEPARADAS, cada uma já
 * agrupada por material antes do JOIN. Juntar tb_pesagem_material com
 * vendas_itens direto produziria o produto cartesiano das duas: um material com
 * 4 compras e 4 vendas viraria 16 linhas, inflando os dois totais em 4x.
 */
function margemAnalise(PDO $pdo, array $filtros): array
{
    $params = [
        ':inicio' => $filtros['inicio'] . ' 00:00:00',
        ':fim'    => $filtros['fim'] . ' 23:59:59',
    ];

    $where = ['m.status = 1'];
    if ($filtros['id_material']) {
        $where[] = 'm.id_material = :id_material';
        $params[':id_material'] = $filtros['id_material'];
    }
    if ($filtros['tipo'] !== '') {
        $where[] = 'm.tipo = :tipo';
        $params[':tipo'] = $filtros['tipo'];
    }
    $filtro_sql = implode(' AND ', $where);

    $sql = "
        SELECT m.id_material, m.nm_material, m.tipo, m.unidade_medida,
               COALESCE(m.qt_estoque, 0)   AS estoque_atual,
               m.preco_compra,

               COALESCE(c.qtd, 0)          AS qtd_comprada,
               COALESCE(c.custo, 0)        AS custo_compras,
               COALESCE(v.qtd, 0)          AS qtd_vendida,
               COALESCE(v.receita, 0)      AS receita,

               -- Custo médio ponderado de TODAS as compras até o fim do período.
               hist.custo_medio_historico
        FROM tb_material m

        LEFT JOIN (
            SELECT pm.id_material,
                   SUM(pm.peso_material)                  AS qtd,
                   SUM(pm.peso_material * pm.preco_un)    AS custo
            FROM tb_pesagem_material pm
            JOIN tb_pesagem p ON p.id_pesagem = pm.id_pesagem
            WHERE p.data_pesagem BETWEEN :inicio AND :fim
              AND p.status <> 'cancelada'
            GROUP BY pm.id_material
        ) c ON c.id_material = m.id_material

        LEFT JOIN (
            SELECT vi.id_material,
                   SUM(vi.quantidade)   AS qtd,
                   SUM(vi.valor_total)  AS receita
            FROM vendas_itens vi
            JOIN vendas v2 ON v2.id_venda = vi.id_venda
            WHERE v2.data_venda BETWEEN :inicio AND :fim
              AND v2.status <> 'cancelada'
            GROUP BY vi.id_material
        ) v ON v.id_material = m.id_material

        LEFT JOIN (
            SELECT pm.id_material,
                   CASE WHEN SUM(pm.peso_material) > 0
                        THEN SUM(pm.peso_material * pm.preco_un) / SUM(pm.peso_material)
                   END AS custo_medio_historico
            FROM tb_pesagem_material pm
            JOIN tb_pesagem p ON p.id_pesagem = pm.id_pesagem
            WHERE p.data_pesagem <= :fim
              AND p.status <> 'cancelada'
            GROUP BY pm.id_material
        ) hist ON hist.id_material = m.id_material

        WHERE $filtro_sql
        ORDER BY m.nm_material
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $saldos_medios = margemSaldoMedio($pdo, $filtros['inicio'], $filtros['fim']);

    $materiais = [];
    $receita_total = 0.0;

    foreach ($linhas as $l) {
        $id = (int) $l['id_material'];

        $qtd_comprada = (float) $l['qtd_comprada'];
        $custo_compras = (float) $l['custo_compras'];
        $qtd_vendida = (float) $l['qtd_vendida'];
        $receita = (float) $l['receita'];

        // Divisão por zero tratada em todas as médias: sem quantidade, null.
        $preco_medio_compra = $qtd_comprada > 0 ? $custo_compras / $qtd_comprada : null;
        $preco_medio_venda  = $qtd_vendida > 0 ? $receita / $qtd_vendida : null;

        $custo_medio = $l['custo_medio_historico'] !== null ? (float) $l['custo_medio_historico'] : null;

        // CMV: só existe quando houve venda E há custo médio conhecido.
        $cmv = ($qtd_vendida > 0 && $custo_medio !== null) ? $qtd_vendida * $custo_medio : null;
        $margem_total = ($cmv !== null) ? $receita - $cmv : null;

        $margem_unitaria = ($preco_medio_venda !== null && $custo_medio !== null)
            ? $preco_medio_venda - $custo_medio
            : null;

        $margem_percentual = ($preco_medio_venda !== null && $preco_medio_venda > 0 && $custo_medio !== null)
            ? (($preco_medio_venda - $custo_medio) / $preco_medio_venda) * 100
            : null;

        $saldo_medio = $saldos_medios[$id] ?? null;
        $giro = ($saldo_medio !== null && $saldo_medio > 0) ? $qtd_vendida / $saldo_medio : null;

        $materiais[$id] = [
            'id_material'        => $id,
            'nm_material'        => $l['nm_material'],
            'tipo'               => $l['tipo'],
            'unidade'            => $l['unidade_medida'],
            'estoque_atual'      => (float) $l['estoque_atual'],

            'qtd_comprada'       => $qtd_comprada,
            'custo_compras'      => $custo_compras,
            'preco_medio_compra' => $preco_medio_compra,

            'qtd_vendida'        => $qtd_vendida,
            'receita'            => $receita,
            'preco_medio_venda'  => $preco_medio_venda,

            'custo_medio'        => $custo_medio,
            'cmv'                => $cmv,
            'margem_total'       => $margem_total,
            'margem_unitaria'    => $margem_unitaria,
            'margem_percentual'  => $margem_percentual,
            'alerta_margem'      => margemClassificar($margem_percentual),

            'saldo_medio'        => $saldo_medio,
            'giro'               => $giro,
            'faixa_giro'         => margemFaixaGiro($giro),

            'abc'                => null,   // preenchido abaixo
            'receita_percentual' => 0.0,
            'receita_acumulada'  => 0.0,
        ];

        $receita_total += $receita;
    }

    margemAplicarABC($materiais, $receita_total);

    return [
        'materiais' => $materiais,
        'totais'    => margemTotais($materiais),
        'filtros'   => $filtros,
    ];
}

/**
 * Saldo médio de cada material no período, reconstruído da trilha.
 *
 * O saldo é remontado somando as movimentações em ordem CRONOLÓGICA, e não
 * lendo `saldo_apos`: essa coluna guarda o saldo no momento em que a linha foi
 * gravada, que na massa de demonstração não corresponde à ordem das datas
 * (movimentos retroativos foram inseridos depois). Reconstruir é o único jeito
 * de ter um saldo coerente com o tempo.
 *
 * A média é ponderada pelo tempo: cada saldo pesa quantos dias durou. Um saldo
 * que ficou 28 dias não pode valer o mesmo que um que durou 2.
 *
 * @return array<int,float|null> id_material => saldo médio, ou null quando a
 *                               reconstrução não é confiável (saldo negativo).
 */
function margemSaldoMedio(PDO $pdo, string $inicio, string $fim): array
{
    $stmt = $pdo->prepare("SELECT id_material, tipo, quantidade, data_movimentacao
                           FROM estoque_movimentacoes
                           WHERE data_movimentacao <= :fim
                           ORDER BY id_material, data_movimentacao, id_movimentacao");
    $stmt->execute([':fim' => $fim . ' 23:59:59']);

    $inicio_ts = strtotime($inicio . ' 00:00:00');
    $fim_ts    = strtotime($fim . ' 23:59:59');
    $dias_periodo = max(($fim_ts - $inicio_ts) / 86400, 1);

    $por_material = [];
    foreach ($stmt as $m) {
        $por_material[(int) $m['id_material']][] = $m;
    }

    $resultado = [];

    foreach ($por_material as $id => $movs) {
        $saldo = 0.0;
        $negativou = false;
        $ultimo_ts = $inicio_ts;
        $area = 0.0;          // saldo × dias
        $entrou_no_periodo = false;

        foreach ($movs as $m) {
            $ts = strtotime($m['data_movimentacao']);
            $delta = ($m['tipo'] === 'entrada' ? 1 : -1) * (float) $m['quantidade'];

            if ($ts >= $inicio_ts) {
                // Acumula o saldo que vigorou até este movimento.
                $fatia_fim = min($ts, $fim_ts);
                if ($fatia_fim > $ultimo_ts) {
                    $area += $saldo * (($fatia_fim - $ultimo_ts) / 86400);
                    $ultimo_ts = $fatia_fim;
                }
                $entrou_no_periodo = true;
            }

            $saldo += $delta;
            if ($saldo < -0.005) {
                $negativou = true;
            }
        }

        // Do último movimento até o fim do período o saldo permaneceu o mesmo.
        if ($fim_ts > $ultimo_ts) {
            $area += $saldo * (($fim_ts - $ultimo_ts) / 86400);
        }

        $media = $area / $dias_periodo;

        // Saldo negativo em algum momento significa trilha incoerente (venda
        // datada antes da compra que a abastece). Devolver média nesse caso
        // seria inventar número: melhor assumir que não dá para calcular.
        $resultado[$id] = ($negativou || $media <= 0) ? null : $media;
    }

    return $resultado;
}

/** Classifica a margem percentual conforme §14. */
function margemClassificar(?float $percentual): array
{
    if ($percentual === null) {
        return ['chave' => 'sem_dado', 'rotulo' => 'Sem venda no período',
                'classe' => 'bg-light text-muted border', 'icone' => 'bi-dash-circle'];
    }
    if ($percentual < 0) {
        return ['chave' => 'negativa', 'rotulo' => 'Margem negativa',
                'classe' => 'bg-danger-subtle text-danger', 'icone' => 'bi-x-octagon'];
    }
    if ($percentual < MARGEM_ALERTA_PERCENTUAL) {
        return ['chave' => 'comprimida', 'rotulo' => 'Abaixo de ' . (int) MARGEM_ALERTA_PERCENTUAL . '%',
                'classe' => 'bg-warning-subtle text-warning-emphasis', 'icone' => 'bi-exclamation-triangle'];
    }
    return ['chave' => 'normal', 'rotulo' => 'Margem normal',
            'classe' => 'bg-success-subtle text-success', 'icone' => 'bi-check-circle'];
}

/** Enquadra o giro numa das faixas configuradas. */
function margemFaixaGiro(?float $giro): array
{
    if ($giro === null) {
        return ['chave' => 'sem_dado', 'rotulo' => 'Sem base de cálculo',
                'classe' => 'bg-light text-muted border'];
    }
    foreach (GIRO_FAIXAS as $chave => $faixa) {
        if ($faixa['ate'] === null || $giro <= $faixa['ate']) {
            return ['chave' => $chave, 'rotulo' => $faixa['rotulo'], 'classe' => $faixa['classe']];
        }
    }
    return ['chave' => 'alto', 'rotulo' => 'Giro alto', 'classe' => 'bg-success-subtle text-success'];
}

/**
 * Classifica os materiais em A, B e C pela receita acumulada.
 *
 * Ordena por receita decrescente, acumula o percentual e corta em 80% e 95%.
 * Material sem receita fica em C: não puxa faturamento nenhum.
 */
function margemAplicarABC(array &$materiais, float $receita_total): void
{
    if ($receita_total <= 0) {
        foreach ($materiais as &$m) {
            $m['abc'] = 'C';
        }
        return;
    }

    $ordem = $materiais;
    uasort($ordem, fn($a, $b) => $b['receita'] <=> $a['receita']);

    $acumulado = 0.0;
    foreach ($ordem as $id => $m) {
        $percentual = ($m['receita'] / $receita_total) * 100;
        $acumulado += $percentual;

        $materiais[$id]['receita_percentual'] = $percentual;
        $materiais[$id]['receita_acumulada'] = min($acumulado, 100.0);

        if ($m['receita'] <= 0) {
            $materiais[$id]['abc'] = 'C';
        } elseif ($acumulado <= ABC_CORTES['A']) {
            $materiais[$id]['abc'] = 'A';
        } elseif ($acumulado <= ABC_CORTES['B']) {
            $materiais[$id]['abc'] = 'B';
        } else {
            $materiais[$id]['abc'] = 'C';
        }
    }
}

/** Consolida os indicadores do topo da tela. */
function margemTotais(array $materiais): array
{
    $t = [
        'receita' => 0.0, 'cmv' => 0.0, 'custo_compras' => 0.0,
        'qtd_comprada' => 0.0, 'qtd_vendida' => 0.0,
        'com_venda' => 0, 'margem_negativa' => 0, 'margem_comprimida' => 0,
        'estoque_parado' => 0,
    ];

    foreach ($materiais as $m) {
        $t['receita']       += $m['receita'];
        $t['cmv']           += $m['cmv'] ?? 0.0;
        $t['custo_compras'] += $m['custo_compras'];
        $t['qtd_comprada']  += $m['qtd_comprada'];
        $t['qtd_vendida']   += $m['qtd_vendida'];

        if ($m['qtd_vendida'] > 0) { $t['com_venda']++; }
        if ($m['alerta_margem']['chave'] === 'negativa')   { $t['margem_negativa']++; }
        if ($m['alerta_margem']['chave'] === 'comprimida') { $t['margem_comprimida']++; }
        if (in_array($m['faixa_giro']['chave'], ['parado', 'baixo'], true) && $m['estoque_atual'] > 0) {
            $t['estoque_parado']++;
        }
    }

    $t['margem'] = $t['receita'] - $t['cmv'];
    $t['margem_percentual'] = $t['receita'] > 0 ? ($t['margem'] / $t['receita']) * 100 : null;

    return $t;
}

/** Tipos de material disponíveis para o filtro. */
function margemTiposMaterial(PDO $pdo): array
{
    return $pdo->query("SELECT DISTINCT tipo FROM tb_material WHERE status = 1 ORDER BY tipo")
               ->fetchAll(PDO::FETCH_COLUMN);
}
