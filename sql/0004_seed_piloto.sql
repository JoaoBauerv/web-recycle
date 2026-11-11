--
-- 0004_seed_piloto.sql
--
-- Massa de dados ficticios que simula o sistema em uso num ferro-velho de
-- pequeno/medio porte durante um piloto: de 01/08/2026 a 07/11/2026, dia a dia.
--
-- OPCIONAL, nunca em producao, e ALTERNATIVA a 0002 e a 0003: rode uma so.
-- Vem depois de 0000_baseline_schema.sql e 0001_seed_inicial.sql:
--
--   psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0004_seed_piloto.sql
--
-- Para apagar e recriar o banco inteiro com esta massa (reset):
--
--   powershell -File sql/recriar_banco.ps1 -Banco reciclagem -Confirmar
--
-- NOMES DO SISTEMA
--   Quem VENDE material para o ferro-velho e "cliente" (tabela clientes; a
--   compra e uma pesagem). Quem COMPRA do ferro-velho e "fornecedor" (tabela
--   fornecedores; a saida e uma venda). Os nomes seguem a otica da empresa.
--
-- O QUE A SIMULACAO FAZ
--   - 31/07: implantacao. Usuarios, cadastros importados do caderno e precos
--     de mercado lancados no catalogo (com auditoria).
--   - Compras de segunda a sabado, 15 a 40 por dia util e menos no sabado
--     (so de manha), nenhuma no domingo nem nos feriados de 07/09, 12/10 e
--     02/11. Movimento cresce de agosto para outubro (~17 -> ~30 por dia).
--     Horario 7h-17h30 com pico pela manha (8h-10h).
--   - 40 catadores/carroceiros/moradores (PF, 5 a 80 kg por entrega; uns 8 vem
--     quase todo dia) e 8 empresas (PJ, 200 kg a 2 t; oficina, serralheria,
--     obra, eletricista), algumas chegando ao longo do piloto.
--   - Ferro, papelao e latinha dominam; cobre e bronze menos frequentes e caros.
--   - Precos de mercado por classe (markup = (venda - compra) / compra):
--       A alto valor, baixo giro (cobres, fio, metal, inox, aluminios, bateria): 30-45%
--       B ferro, volume com impureza: 20-30%
--       C alto giro, baixo valor (lata, PET, papelao, papel): 8-15%
--     Oscilacao semanal (toda segunda, auditada como edicao do material);
--     metais oscilam juntos. Preco especial sem tirar o markup da faixa.
--   - Precos em degraus, como no balcao: A R$ 1,00 (>= R$ 10/kg) ou R$ 0,50;
--     B R$ 0,10; C R$ 0,50 (>= R$ 5/kg) ou R$ 0,10; abaixo de R$ 1,00/kg,
--     R$ 0,05. Vale para compra, venda, especial e oscilacao.
--   - Valor pago na compra arredondado no balcao (ate R$ 100, para R$ 0,50;
--     acima, para o real): so nesta massa. Os itens ficam peso x preco, o
--     total do documento e o valor pago, e a diferenca vai para a auditoria.
--   - Triagem antes de cada lote: o que entrou desde a triagem anterior perde a
--     quebra do material (A 3-10%, B 3-8%, C 1-3%), lancada como 'perda' por
--     ajuste manual, como na tela de estoque. Vendido < comprado.
--   - Vendas em lote as tercas, quintas e sextas (no maximo 3 por semana) para
--     5 compradores, quando o estoque do grupo passa do lote minimo. Vende de
--     90% a 100% do que ha, nunca mais. Parte a prazo (boleto em 15 dias ou
--     transferencia em 7), registrada em vendas.observacoes.
--   - ~1,5% das compras e 1 a 2 vendas canceladas, com estorno do estoque.
--   - Financeiro do periodo em contas_pagar: aluguel, honorarios, internet,
--     telefone, 3 salarios e FGTS, energia, agua, Simples (4% das vendas),
--     diesel e manutencao do caminhao, aferição da balanca, manutencao da
--     prensa, uma balanca parcelada, uma conta paga com atraso, a de agua de
--     setembro vencida em aberto e uma despesa cancelada. Pago = vencimento
--     ate 07/11/2026.
--
-- O QUE O SCHEMA NAO TEM (e a massa nao inventa)
--   Caixa (abertura/fechamento), forma de pagamento da compra e contas a
--   receber. O prazo das vendas fica no texto de observacoes.
--
-- MAPA DOS MATERIAIS
--   ferro/sucata mista = Sucata      cobre mel = Cobre Vermelho
--   bronze/latao       = Metal       aluminio bloco = Duro
--   latinha            = Lata        perfil = Perfil, panela = Panela
--   inox               = Inox Panela fio de cobre encapado = Fio Capa
--   PET                = Pet Branca / Pet Verde
--
-- CONSISTENCIA
--   Usa as funcoes de seed_funcoes.sql, que aplicam as regras do PHP; saldo
--   negativo aborta. Os eventos de cada dia sao gravados em ordem de horario.
--   No fim confere: saldo = trilha, saldo_apos = soma acumulada cronologica,
--   nenhum saldo negativo em momento algum, markup de cada classe e quebra de
--   cada material dentro da faixa, vendido < comprado, precos no degrau,
--   total de compra pela regra do balcao, resultado entre 4% e 8% do
--   faturamento; e imprime um resumo.
--
-- REPRODUTIVEL
--   setseed() fixa o sorteio e as datas sao absolutas: rodar de novo gera
--   exatamente a mesma massa, em qualquer dia.
--
-- ACESSO (senha admin@123, sem troca obrigatoria)
--   rogerio.antunes@example.com   Admin   (dono)
--   diego.lemos@example.com       Usuario (operador de balanca/patio)
--   patricia.fagundes@example.com Usuario (atendente de caixa)
--
-- Dados ficticios: nomes inventados, CPF/CNPJ gerados com digito verificador
-- valido a partir de bases arbitrarias, e-mails em example.com. So roda em
-- banco sem cliente, compra ou venda.
--

SET client_encoding TO 'UTF8';
SET search_path TO reciclagem;

BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM usuario WHERE permissao = 'Admin' AND status = 1) THEN
        RAISE EXCEPTION 'nenhum administrador ativo: rode 0001_seed_inicial.sql antes';
    END IF;
    IF (SELECT COUNT(*) FROM material) < 33 THEN
        RAISE EXCEPTION 'catalogo incompleto: rode 0001_seed_inicial.sql antes';
    END IF;
    IF EXISTS (SELECT 1 FROM clientes) OR EXISTS (SELECT 1 FROM pesagem) OR EXISTS (SELECT 1 FROM vendas) THEN
        RAISE EXCEPTION 'o banco ja tem clientes, compras ou vendas; a massa so entra em banco recem-instalado (use sql/recriar_banco.ps1)';
    END IF;
END $$;

\ir seed_funcoes.sql

SELECT setseed(0.2026);

-- ---------------------------------------------------------------------------
-- Tabelas de trabalho (somem no COMMIT)
-- ---------------------------------------------------------------------------

-- Classes de preco. Definicoes usadas em todo o arquivo:
--   markup             = (venda - compra) / compra
--   margem sobre venda = (venda - compra) / venda
-- A: alto valor, baixo giro: a diferenca paga despesa e a quebra da triagem.
-- B: ferro, volume com impureza (terra, pecas misturadas).
-- C: alto giro, baixo valor: o lucro vem do volume.
-- esp = fator do preco especial (empresas e catadores antigos) sobre a compra:
-- melhor para o cliente, mas sem tirar o markup da faixa da classe.
CREATE TEMP TABLE _classe (classe text, markup_min numeric, markup_max numeric,
                           quebra_min numeric, quebra_max numeric, esp numeric, descricao text) ON COMMIT DROP;
INSERT INTO _classe VALUES
    ('A', 0.30, 0.45, 0.03, 0.10, 1.04, 'alto valor, baixo giro'),
    ('B', 0.20, 0.30, 0.03, 0.08, 1.03, 'volume com impureza'),
    ('C', 0.08, 0.15, 0.01, 0.03, 1.02, 'alto giro, baixo valor');

-- Materiais do piloto: classe, preco-base de compra e de venda (R$/kg, mercado
-- de 2026), quebra na triagem, frequencia e faixa de peso por item na entrega
-- de pessoa fisica, grupo de comprador, minimo para entrar numa venda, indice
-- de preco da semana e o momento da ultima triagem.
CREATE TEMP TABLE _mat (
    id int, nome text, classe text, compra_base numeric, venda_base numeric, quebra numeric,
    fpf numeric, pf_min numeric, pf_max numeric, grupo text, vmin numeric, idx numeric DEFAULT 1,
    ult_triagem timestamp DEFAULT TIMESTAMP '2026-07-31 23:59'
) ON COMMIT DROP;

-- Precos em degraus, como no balcao (ver pg_temp.degrau):
--   A: R$ 1,00 a partir de R$ 10/kg, R$ 0,50 abaixo   B: R$ 0,10
--   C: R$ 0,50 a partir de R$ 5/kg,  R$ 0,10 abaixo
--   qualquer classe abaixo de R$ 1,00/kg: R$ 0,05
--                     classe  compra   venda  quebra  (markup)
INSERT INTO _mat (nome, classe, compra_base, venda_base, quebra, fpf, pf_min, pf_max, grupo, vmin) VALUES
    ('Cobre Vermelho', 'A', 50.00, 70.00, 0.035,  2,  0.3,  6, 'metais',     10),   -- 40,0%; cobre mel, limpo
    ('Cobre Misto',    'A', 42.00, 59.00, 0.060,  3,  0.5,  8, 'metais',     15),   -- 40,5%; solda, verniz
    ('Fio Capa',       'A', 18.00, 26.00, 0.085,  4,  1.0, 15, 'metais',     30),   -- 44,4%; capa e terminais
    ('Metal',          'A', 30.00, 42.00, 0.050,  3,  0.5, 10, 'metais',     20),   -- 40,0%; bronze/latao com ferro
    ('Inox Panela',    'A',  5.00,  7.00, 0.040,  2,  1.0, 15, 'metais',     40),   -- 40,0%; cabos e alcas
    ('Duro',           'A',  6.50,  9.00, 0.070,  2,  3.0, 25, 'aluminio',   50),   -- 38,5%; oleo e terra do bloco
    ('Perfil',         'A',  9.00, 13.00, 0.040,  4,  2.0, 20, 'aluminio',   50),   -- 44,4%; borracha, parafuso
    ('Panela',         'A',  8.00, 11.00, 0.050,  5,  1.0, 12, 'aluminio',   30),   -- 37,5%; cabos de baquelite
    ('Bateria',        'A',  3.00,  4.00, 0.045,  3, 12.0, 45, 'bateria',   100),   -- 33,3%; acido escorrido
    ('Sucata',         'B',  0.80,  1.00, 0.060, 30, 10.0, 80, 'metais',   1000),   -- 25,0%; terra, pecas mistas
    ('Lata',           'C',  8.00,  9.00, 0.020, 22,  2.0, 25, 'aluminio',  100),   -- 12,5%; areia, liquido
    ('Pet Branca',     'C',  2.40,  2.70, 0.025, 10,  3.0, 30, 'plastico',  100),   -- 12,5%; tampa, rotulo
    ('Pet Verde',      'C',  1.90,  2.10, 0.025,  4,  3.0, 25, 'plastico',   60),   -- 10,5%
    ('Papelão',        'C',  0.50,  0.55, 0.020, 28,  8.0, 70, 'papel',     500),   -- 10,0%; umidade, fita
    ('Papel Branco',   'C',  0.80,  0.90, 0.015,  6,  5.0, 40, 'papel',     150);   -- 12,5%

UPDATE _mat t SET id = m.id_material FROM material m WHERE m.nm_material = t.nome;

-- Degrau de preco por kg da classe, pela faixa do proprio preco. Abaixo de
-- R$ 1,00/kg, qualquer classe anda de R$ 0,05.
CREATE FUNCTION pg_temp.degrau(p_classe text, p_preco numeric) RETURNS numeric
LANGUAGE sql IMMUTABLE AS $f$
    SELECT CASE WHEN p_preco < 1 THEN 0.05 ELSE
           CASE p_classe
               WHEN 'A' THEN CASE WHEN p_preco >= 10 THEN 1.00 ELSE 0.50 END
               WHEN 'B' THEN 0.10
               ELSE          CASE WHEN p_preco >= 5 THEN 0.50 ELSE 0.10 END END END
$f$;

-- Preco levado ao degrau mais proximo.
CREATE FUNCTION pg_temp.arred(p_classe text, p_preco numeric) RETURNS numeric
LANGUAGE sql IMMUTABLE AS $f$
    SELECT round(round(p_preco / pg_temp.degrau(p_classe, p_preco)) * pg_temp.degrau(p_classe, p_preco), 2)
$f$;

-- Preco no degrau? (conferencia)
CREATE FUNCTION pg_temp.no_degrau(p_classe text, p_preco numeric) RETURNS boolean
LANGUAGE sql IMMUTABLE AS $f$
    SELECT p_preco > 0 AND mod(p_preco, pg_temp.degrau(p_classe, p_preco)) = 0
$f$;

-- Preco especial: o fator da classe levado ao degrau, recuando degrau a degrau
-- enquanto tirar o markup da faixa; no limite, igual ao preco normal.
CREATE FUNCTION pg_temp.especial(p_classe text, p_compra numeric, p_venda numeric) RETURNS numeric
LANGUAGE plpgsql AS $f$
DECLARE c record; e numeric;
BEGIN
    SELECT * INTO c FROM _classe WHERE classe = p_classe;
    e := pg_temp.arred(p_classe, p_compra * c.esp);
    WHILE e > p_compra AND (p_venda - e) / e < c.markup_min LOOP
        e := e - pg_temp.degrau(p_classe, e - 0.01);
    END LOOP;
    RETURN GREATEST(e, p_compra);
END $f$;

-- Valor pago no balcao: ate R$ 100, o R$ 0,50 mais proximo; acima, o real inteiro.
CREATE FUNCTION pg_temp.balcao(p_valor numeric) RETURNS numeric
LANGUAGE sql IMMUTABLE AS $f$
    SELECT CASE WHEN p_valor <= 100 THEN round(p_valor * 2) / 2 ELSE round(p_valor) END::numeric(10,2)
$f$;

DO $$
BEGIN
    -- Precos-base no degrau da classe.
    IF EXISTS (SELECT 1 FROM _mat WHERE NOT pg_temp.no_degrau(classe, compra_base) OR NOT pg_temp.no_degrau(classe, venda_base)) THEN
        RAISE EXCEPTION 'preco-base fora do degrau: %',
            (SELECT string_agg(nome, ', ') FROM _mat WHERE NOT pg_temp.no_degrau(classe, compra_base) OR NOT pg_temp.no_degrau(classe, venda_base));
    END IF;
END $$;

DO $$
BEGIN
    -- O preco-base, inclusive o especial, precisa nascer dentro da faixa da classe.
    IF EXISTS (SELECT 1 FROM _mat t JOIN _classe c USING (classe)
                WHERE (t.venda_base - pg_temp.especial(t.classe, t.compra_base, t.venda_base))
                        / pg_temp.especial(t.classe, t.compra_base, t.venda_base) < c.markup_min
                   OR (t.venda_base - t.compra_base) / t.compra_base > c.markup_max
                   OR t.quebra NOT BETWEEN c.quebra_min AND c.quebra_max) THEN
        RAISE EXCEPTION 'preco-base ou quebra fora da faixa da classe: %',
            (SELECT string_agg(t.nome, ', ') FROM _mat t JOIN _classe c USING (classe)
              WHERE (t.venda_base - pg_temp.especial(t.classe, t.compra_base, t.venda_base))
                      / pg_temp.especial(t.classe, t.compra_base, t.venda_base) < c.markup_min
                 OR (t.venda_base - t.compra_base) / t.compra_base > c.markup_max
                 OR t.quebra NOT BETWEEN c.quebra_min AND c.quebra_max);
    END IF;
END $$;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM _mat WHERE id IS NULL) THEN
        RAISE EXCEPTION 'material do piloto ausente no catalogo: %', (SELECT string_agg(nome, ', ') FROM _mat WHERE id IS NULL);
    END IF;
END $$;

-- O que cada perfil de empresa traz: frequencia relativa e faixa de peso.
CREATE TEMP TABLE _pjmix (perfil text, nome text, freq numeric, kmin numeric, kmax numeric) ON COMMIT DROP;
INSERT INTO _pjmix VALUES
    ('oficina',     'Sucata',         10, 150,  900),
    ('oficina',     'Duro',            5,  30,  200),
    ('oficina',     'Bateria',         5,  45,  300),
    ('oficina',     'Fio Capa',        2,   5,   40),
    ('oficina',     'Cobre Misto',     2,   3,   25),
    ('oficina',     'Metal',           2,   5,   40),
    ('serralheria', 'Sucata',         10, 200, 1500),
    ('serralheria', 'Perfil',          6,  20,  150),
    ('serralheria', 'Inox Panela',     3,  10,   80),
    ('obra',        'Sucata',         10, 300, 1800),
    ('obra',        'Fio Capa',        5,  10,   80),
    ('obra',        'Cobre Misto',     3,   5,   40),
    ('obra',        'Papelão',         4,  30,  200),
    ('eletricista', 'Fio Capa',       10,  15,  120),
    ('eletricista', 'Cobre Vermelho',  6,   5,   40),
    ('eletricista', 'Cobre Misto',     5,   5,   50),
    ('eletricista', 'Sucata',          3,  50,  300);

-- Clientes (quem vende ao ferro-velho): tipo, peso no sorteio (PF), perfil e
-- chance de vir num dia util (PJ), e o primeiro dia em que aparece.
CREATE TEMP TABLE _cli (id int, tipo text, peso numeric, perfil text, freq_dia numeric, ini date) ON COMMIT DROP;

-- Compradores (fornecedores, na convencao do sistema), um por grupo.
CREATE TEMP TABLE _forn (id int, grupo text, lote numeric, prazo_pct numeric, prazo_dias int,
                         forma_prazo text, forma_vista text) ON COMMIT DROP;

CREATE TEMP TABLE _ids (chave text PRIMARY KEY, valor int) ON COMMIT DROP;

-- Eventos de um dia, gravados depois em ordem de horario.
CREATE TEMP TABLE _ev (seq serial, t timestamp, kind text, cli int, ref int, result int) ON COMMIT DROP;

-- ---------------------------------------------------------------------------
-- Funcoes da simulacao
-- ---------------------------------------------------------------------------
CREATE FUNCTION pg_temp.id(p_chave text) RETURNS int
LANGUAGE sql AS $f$ SELECT valor FROM _ids WHERE chave = p_chave $f$;

CREATE FUNCTION pg_temp.momento(p_dia date, p_seg double precision) RETURNS timestamp
LANGUAGE sql AS $f$ SELECT p_dia + make_interval(secs => floor(p_seg)) $f$;

-- Segundo do dia de uma compra de pessoa fisica: 2/3 de manha com pico entre
-- 8h e 10h (soma de dois sorteios), um pouco no almoco, o resto a tarde.
CREATE FUNCTION pg_temp.segundo_compra(p_sabado boolean) RETURNS double precision
LANGUAGE plpgsql AS $f$
DECLARE r double precision := random();
BEGIN
    IF p_sabado THEN
        RETURN 25200 + 18000 * power(random(), 1.3);                  -- 07h00-12h00
    ELSIF r < 0.66 THEN
        RETURN 25200 + 18000 * (random() + random()) / 2;             -- 07h00-12h00
    ELSIF r < 0.72 THEN
        RETURN 43200 + 3600 * random();                               -- 12h00-13h00
    END IF;
    RETURN 46800 + 16200 * random();                                  -- 13h00-17h30
END $f$;

CREATE FUNCTION pg_temp.quem_pesa() RETURNS int
LANGUAGE sql AS $f$
    SELECT CASE WHEN random() < 0.8 THEN pg_temp.id('operador') ELSE pg_temp.id('atendente') END
$f$;

-- "compra R$ 0,80 -> 0,82; especial R$ 0,84 -> 0,86", como functions/material/registrar.php
CREATE FUNCTION pg_temp.mudancas(p_c0 numeric, p_c1 numeric, p_e0 numeric, p_e1 numeric) RETURNS text
LANGUAGE sql AS $f$
    SELECT concat_ws('; ',
        CASE WHEN p_c0 IS DISTINCT FROM p_c1 THEN format('compra R$ %s -> %s', pg_temp.num(p_c0), pg_temp.num(p_c1)) END,
        CASE WHEN p_e0 IS DISTINCT FROM p_e1 THEN format('especial R$ %s -> %s', pg_temp.num(COALESCE(p_e0, 0)), pg_temp.num(p_e1)) END)
$f$;

-- Oscilacao semanal: choque comum aos metais (cotacao) + choque proprio, com
-- retorno a media e limite de +-10% sobre o preco-base.
CREATE FUNCTION pg_temp.piloto_reajuste(p_t timestamp) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE
    r record; comum numeric := ((random() - 0.5) * 0.05)::numeric; novo numeric; nc numeric; ne numeric;
BEGIN
    FOR r IN SELECT t.*, c.esp, m.preco_compra AS pc, m.preco_especial AS pe
               FROM _mat t JOIN _classe c USING (classe) JOIN material m ON m.id_material = t.id ORDER BY t.id LOOP
        novo := r.idx * (1 + CASE WHEN r.grupo IN ('metais', 'aluminio') THEN comum * 0.6 ELSE 0 END
                           + ((random() - 0.5) * 0.04)::numeric);
        novo := LEAST(1.10, GREATEST(0.90, 1 + (novo - 1) * 0.85));
        UPDATE _mat SET idx = novo WHERE id = r.id;

        nc := pg_temp.arred(r.classe, r.compra_base * novo);
        ne := pg_temp.especial(r.classe, nc, pg_temp.arred(r.classe, r.venda_base * novo));
        IF nc <> r.pc OR ne IS DISTINCT FROM r.pe THEN
            UPDATE material SET preco_compra = nc, preco_especial = ne WHERE id_material = r.id;
            PERFORM pg_temp.auditar(pg_temp.id('dono'), pg_temp.id('dono'),
                format('Material #%s editado: %s', r.id, pg_temp.mudancas(r.pc, nc, r.pe, ne)),
                'Material editado', p_t);
        END IF;
    END LOOP;
END $f$;

-- Uma compra: itens sorteados pelo perfil do cliente, total levado para a
-- faixa (PF 5-80 kg, PJ 200-2000 kg), tara de sacaria/palete.
CREATE FUNCTION pg_temp.piloto_compra(p_cli int, p_t timestamp) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE
    c record; r record; n int; i int; b numeric; t numeric; soma numeric := 0; fator numeric := 1;
    lim_min numeric; lim_max numeric; nomes text[] := '{}'; brutos numeric[] := '{}'; itens jsonb := '[]';
    v_id int; v_exato numeric; v_pago numeric; v_peso numeric;
BEGIN
    SELECT * INTO c FROM _cli WHERE id = p_cli;

    IF c.tipo = 'pf' THEN
        n := CASE WHEN random() < 0.55 THEN 1 WHEN random() < 0.71 THEN 2 ELSE 3 END;
        FOR r IN SELECT nome, pf_min AS kmin, pf_max AS kmax FROM _mat
                  ORDER BY power(random(), 1.0 / fpf) DESC LIMIT n LOOP
            b := r.kmin + (r.kmax - r.kmin) * power(random(), 1.7)::numeric;
            nomes := nomes || r.nome; brutos := brutos || b; soma := soma + b;
        END LOOP;
        lim_min := 5; lim_max := 80;
    ELSE
        n := 2 + (random() < 0.4)::int;
        FOR r IN SELECT nome, kmin, kmax FROM _pjmix WHERE perfil = c.perfil
                  ORDER BY power(random(), 1.0 / freq) DESC LIMIT n LOOP
            b := r.kmin + (r.kmax - r.kmin) * power(random(), 1.4)::numeric;
            nomes := nomes || r.nome; brutos := brutos || b; soma := soma + b;
        END LOOP;
        lim_min := 200; lim_max := 2000;
    END IF;

    IF soma > lim_max THEN
        fator := lim_max * (0.75 + 0.25 * random())::numeric / soma;
    ELSIF soma < lim_min THEN
        fator := lim_min * (1 + 0.5 * random())::numeric / soma;
    END IF;

    FOR i IN 1..array_length(nomes, 1) LOOP
        b := GREATEST(round(brutos[i] * fator, 1), 0.3);
        t := CASE WHEN b > 100 THEN round((b * (0.004 + 0.008 * random()))::numeric, 1)
                  ELSE round((random() * LEAST(1.2, b / 6))::numeric, 1) END;
        itens := itens || jsonb_build_object('m', nomes[i], 'b', b, 't', t);
    END LOOP;

    v_id := pg_temp.compra(p_cli, p_t, pg_temp.quem_pesa(), itens);

    -- Pagamento no balcao arredondado (so na massa de demonstracao: o sistema
    -- grava a soma exata dos itens). Os itens continuam peso x preco; o total
    -- do documento e o valor pago, e a diferenca fica no texto da auditoria.
    SELECT total_valor, total_peso INTO v_exato, v_peso FROM pesagem WHERE id_pesagem = v_id;
    v_pago := pg_temp.balcao(v_exato);
    IF v_pago <> v_exato THEN
        UPDATE pesagem SET total_valor = v_pago WHERE id_pesagem = v_id;
        UPDATE registro_movimento
           SET descricao = format('Compra #%s registrada: R$ %s (%s kg); itens somam R$ %s, arredondado no balcão (%s%s)',
                                  v_id, pg_temp.num(v_pago), pg_temp.num(v_peso), pg_temp.num(v_exato),
                                  CASE WHEN v_pago > v_exato THEN '+' ELSE '−' END, pg_temp.num(abs(v_pago - v_exato)))
         WHERE tipo = 'Registro Compra' AND descricao LIKE format('Compra #%s registrada:%%', v_id);
    END IF;
    RETURN v_id;
END $f$;

-- Comprador da proxima venda: o grupo com mais estoque em relacao ao lote, se
-- passou do lote e a semana ainda nao teve 3 vendas.
CREATE FUNCTION pg_temp.piloto_escolher_comprador() RETURNS int
LANGUAGE sql AS $f$
    SELECT f.id
      FROM _forn f
      JOIN LATERAL (SELECT SUM(m.qt_estoque) AS total
                      FROM _mat t JOIN material m ON m.id_material = t.id
                     WHERE t.grupo = f.grupo AND COALESCE(m.qt_estoque, 0) >= t.vmin) s ON true
     WHERE s.total >= f.lote AND pg_temp.id('vendas_semana') < 3
     ORDER BY s.total / f.lote DESC, f.id
     LIMIT 1
$f$;

-- Triagem antes do lote (40 a 70 min antes do carregamento): o que entrou de
-- cada material do grupo desde a ultima triagem perde a quebra do material
-- (umidade, impureza, capa, embalagem), lancada como 'perda' por ajuste manual,
-- com o mesmo texto de functions/estoque/ajustar.php. Nunca mais que o saldo.
CREATE FUNCTION pg_temp.piloto_triagem(p_forn int, p_t timestamp) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE r record; v_entrou numeric; v_perda numeric; v_saldo numeric; v_motivo text; adm int := pg_temp.id('dono');
BEGIN
    FOR r IN SELECT t.*, m.qt_estoque, m.nm_material, m.unidade_medida
               FROM _mat t JOIN material m ON m.id_material = t.id
              WHERE t.grupo = (SELECT grupo FROM _forn WHERE id = p_forn) AND COALESCE(m.qt_estoque, 0) > 0
              ORDER BY t.id LOOP
        SELECT COALESCE(SUM(pm.peso_material), 0) INTO v_entrou
          FROM pesagem_material pm JOIN pesagem p USING (id_pesagem)
         WHERE pm.id_material = r.id AND p.status = 'ativa'
           AND p.data_pesagem > r.ult_triagem AND p.data_pesagem <= p_t;
        UPDATE _mat SET ult_triagem = p_t WHERE id = r.id;

        v_perda := LEAST(round(v_entrou * r.quebra * (0.85 + 0.30 * random())::numeric, 1), r.qt_estoque);
        CONTINUE WHEN v_perda < 0.1;

        v_motivo := CASE
            WHEN r.nome = 'Sucata'     THEN 'triagem do lote: terra, peças misturadas e não metálicos'
            WHEN r.nome = 'Fio Capa'   THEN 'triagem do lote: capa, terminais e emendas'
            WHEN r.grupo = 'metais'    THEN 'triagem do lote: impurezas, solda e ferro agregado'
            WHEN r.nome = 'Lata'       THEN 'triagem do lote: areia e líquido nas latas'
            WHEN r.grupo = 'aluminio'  THEN 'triagem do lote: ferro agregado, borracha e sujeira'
            WHEN r.grupo = 'papel'     THEN 'triagem do lote: umidade, fita e contaminação'
            WHEN r.grupo = 'plastico'  THEN 'triagem do lote: tampas, rótulos e resíduo'
            ELSE                            'triagem do lote: ácido escorrido e caixas quebradas' END;

        v_saldo := pg_temp.mov(r.id, 'perda', v_perda, 'ajuste_manual', NULL, adm, 'Perda: ' || v_motivo, p_t);
        PERFORM pg_temp.auditar(adm, adm,
            format('Perda de %s %s em %s (saldo %s -> %s). Motivo: %s', pg_temp.num(v_perda), r.unidade_medida,
                   r.nm_material, pg_temp.num(r.qt_estoque), pg_temp.num(v_saldo), v_motivo),
            'Ajuste Estoque', p_t);
    END LOOP;
END $f$;

-- Uma venda do grupo do comprador escolhido na triagem: 90-100% do saldo de
-- cada material, nunca mais do que ha.
CREATE FUNCTION pg_temp.piloto_venda(p_forn int, p_t timestamp) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE g record; itens jsonb; v_id int; obs text; usr int;
BEGIN
    SELECT * INTO g FROM _forn WHERE id = p_forn;

    SELECT jsonb_agg(jsonb_build_object('m', nome, 'b', qtd + tara, 't', tara, 'p', preco) ORDER BY nome)
      INTO itens
      FROM (SELECT t.nome,
                   CASE WHEN m.qt_estoque >= 1000 THEN floor(m.qt_estoque * (0.90 + 0.10 * random())::numeric)
                        ELSE floor(m.qt_estoque * (0.90 + 0.10 * random())::numeric * 10) / 10 END AS qtd,
                   CASE WHEN m.qt_estoque > 500 THEN round((8 + random() * 22)::numeric, 1)
                        ELSE round((random() * 3)::numeric, 1) END AS tara,
                   pg_temp.arred(t.classe, t.venda_base * t.idx) AS preco
              FROM _mat t JOIN material m ON m.id_material = t.id
             WHERE t.grupo = g.grupo AND COALESCE(m.qt_estoque, 0) >= t.vmin
             ORDER BY t.id) s;
    IF itens IS NULL THEN RETURN NULL; END IF;

    IF random() < g.prazo_pct THEN
        obs := format('A prazo: %s em %s dias, vencimento %s.', g.forma_prazo, g.prazo_dias,
                      to_char(p_t::date + g.prazo_dias, 'DD/MM/YYYY'));
    ELSE
        obs := format('À vista: %s na saída da carga.', g.forma_vista);
    END IF;

    usr := CASE WHEN random() < 0.5 THEN pg_temp.id('dono') ELSE pg_temp.id('atendente') END;
    v_id := pg_temp.venda(g.id, p_t, usr, itens, 'manual', NULL, NULL, obs);
    UPDATE _ids SET valor = valor + 1 WHERE chave = 'vendas_semana';
    RETURN v_id;
END $f$;

-- Parcela no formato de pg_temp.conta(), com datas absolutas. Paga quando
-- informada a data de pagamento.
CREATE FUNCTION pg_temp.parc(p_valor numeric, p_venc date, p_forma text, p_pago date DEFAULT NULL, p_vp numeric DEFAULT NULL)
RETURNS jsonb LANGUAGE sql AS $f$
    SELECT jsonb_build_object('v', p_valor, 'venc', p_venc - CURRENT_DATE, 'f', p_forma)
        || CASE WHEN p_pago IS NULL THEN '{}'::jsonb ELSE jsonb_build_object('pg', p_pago - CURRENT_DATE) END
        || CASE WHEN p_vp IS NULL THEN '{}'::jsonb ELSE jsonb_build_object('vp', p_vp) END
$f$;

-- N parcelas mensais a partir de p_primeiro; paga no vencimento se vence ate 07/11/2026.
CREATE FUNCTION pg_temp.parc_mensais(p_qtd int, p_valor numeric, p_primeiro date, p_forma text) RETURNS jsonb
LANGUAGE sql AS $f$
    SELECT jsonb_agg(pg_temp.parc(p_valor, venc, p_forma, CASE WHEN venc <= DATE '2026-11-07' THEN venc END) ORDER BY i)
      FROM (SELECT i, (p_primeiro + i * interval '1 month')::date AS venc FROM generate_series(0, p_qtd - 1) i) s
$f$;

-- ---------------------------------------------------------------------------
-- Implantacao (31/07/2026): usuarios, cadastros e precos de mercado
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    v_hash constant text := '$2y$10$4Nkcd86aeSuaCAYQK8T5K.8Sa5UwUwqY0An88R/w2yGhij8FbdX.O';  -- admin@123
    implantacao constant timestamp := TIMESTAMP '2026-07-31 08:00';
    u_inicial int; u int; i int; v_id int; nome text; doc text; papel text; perfil text; ende int;
    peso numeric; ini date; especial boolean; r record;

    primeiros text[] := ARRAY['José','Maria','Antônio','Francisco','Ana','Paulo','Luiz','Marcos','Rita','Sebastião',
                              'Raimunda','João','Pedro','Luciana','Jorge','Sandra','Edson','Cláudia','Valdir','Rosângela',
                              'Adilson','Neusa','Gilberto','Terezinha','Vanderlei','Marlene','Osmar','Vera','Nilton','Dirce',
                              'Jair','Lurdes','Ivo','Elaine','Darci','Cleusa','Alceu','Ivone','Ademir','Solange'];
    sobrenomes text[] := ARRAY['da Silva','dos Santos','Pereira','Oliveira','Souza','Rodrigues','Ferreira','Alves',
                               'Lima','Gomes','Ribeiro','Martins','Carvalho','da Rosa','Barbosa','Moraes','Teixeira',
                               'Cardoso','Nunes','Machado','Fagundes','Vargas','Schmidt','Becker','Ávila'];
    bairros text[] := ARRAY['Centro','Morada do Vale','Parque dos Anjos','São Geraldo','Barnabé','Itacolomi',
                            'Bom Princípio','Passo das Pedras','Moradas do Sobrado','Neópolis','Santa Cruz','Rincão da Madalena'];
    ruas text[] := ARRAY['Rua Anápio Gomes','Rua Dr. Luiz Bastos do Prado','Avenida Dorival Cândido Luz de Oliveira',
                         'Rua Adolfo Inácio de Barcelos','Rua Ely Corrêa','Rua Coronel Sarmento','Rua Bento Gonçalves',
                         'Rua Pinheiro Machado','Rua Santa Catarina','Rua Itororó','Rua das Hortênsias','Estrada do Barro Vermelho'];
    cidades text[] := ARRAY['Gravataí','Gravataí','Gravataí','Cachoeirinha','Alvorada','Glorinha'];
BEGIN
    SELECT id_usuario INTO u_inicial FROM usuario WHERE permissao = 'Admin' AND status = 1 ORDER BY id_usuario LIMIT 1;

    -- Usuarios: dono, operador de balanca/patio, atendente de caixa.
    FOR nome, doc, papel, i IN
        SELECT * FROM (VALUES
            ('Rogério Antunes',     'rogerio.antunes@example.com',   'dono',      1),
            ('Diego Ramos Lemos',   'diego.lemos@example.com',       'operador',  2),
            ('Patrícia Fagundes',   'patricia.fagundes@example.com', 'atendente', 3)) v
    LOOP
        INSERT INTO usuario (nome, email, senha, usuario, foto, status, permissao, precisa_alterar_senha,
                             data_nascimento, celular, cpf_cnpj)
        VALUES (nome, doc, v_hash, split_part(doc, '@', 1), '', 1,
                CASE WHEN papel = 'dono' THEN 'Admin' ELSE 'Usuario' END, 0,
                DATE '1972-03-14' + i * 2311, '5199' || lpad((i * 6151 % 10000000)::text, 7, '0'),
                pg_temp.cpf(lpad((i * 271828183 % 900000000 + 100000000)::text, 9, '0')))
        RETURNING id_usuario INTO u;
        INSERT INTO _ids VALUES (papel, u);
        PERFORM pg_temp.auditar(u_inicial, u, 'Cadastro do usuário ' || nome, 'Cadastro Usuario', implantacao + i * interval '2 minutes');
    END LOOP;
    INSERT INTO _ids VALUES ('vendas_semana', 0);

    -- 40 pessoas fisicas: 8 quase diarias, 12 frequentes, 20 eventuais;
    -- 28 ja vinham antes do sistema, 12 aparecem ao longo do piloto.
    FOR i IN 1..40 LOOP
        nome := primeiros[i] || ' ' || sobrenomes[1 + (i * 7) % 25];
        doc  := pg_temp.cpf(lpad((i * 314159 % 900000000 + 100000000)::text, 9, '0'));
        peso := CASE WHEN i <= 8 THEN 4 + random() * 2 WHEN i <= 20 THEN 2 + random() ELSE 0.5 + random() * 0.5 END;
        ini  := CASE WHEN i <= 28 THEN DATE '2026-08-01' ELSE DATE '2026-08-08' + floor(random() * 68)::int END;
        especial := i IN (2, 5);   -- dois catadores antigos com tabela especial

        ende := NULL;
        IF i <= 20 OR random() < 0.5 THEN
            ende := pg_temp.endereco('940' || lpad((i * 4127 % 100000)::text, 5, '0'),
                                     ruas[1 + (i * 5) % 12], (10 + i * 53 % 2400)::text,
                                     bairros[1 + (i * 3) % 12], cidades[1 + i % 6], 'RS');
        END IF;

        INSERT INTO clientes (nome, cpf_cnpj, celular, preco_especial, status, data_cadastro, id_endereco)
        VALUES (nome, doc, '5198' || lpad((i * 7907 % 10000000)::text, 7, '0'), especial, 1,
                CASE WHEN ini = DATE '2026-08-01' THEN implantacao + interval '30 minutes' + i * interval '1 minute'
                     ELSE ini + time '07:00' END, ende)
        RETURNING id_cliente INTO v_id;

        INSERT INTO _cli VALUES (v_id, 'pf', peso, NULL, NULL, ini);
        PERFORM pg_temp.auditar(pg_temp.id('atendente'), pg_temp.id('atendente'),
            format('Cliente cadastrado: %s (#%s)', nome, v_id), 'Cadastro Cliente',
            (SELECT data_cadastro FROM clientes WHERE id_cliente = v_id));
    END LOOP;

    -- 8 empresas: (nome, perfil, chance por dia util, primeiro dia)
    FOR nome, perfil, peso, ini, i IN
        SELECT * FROM (VALUES
            ('Auto Mecânica Irmãos Becker Ltda',              'oficina',     0.30, DATE '2026-08-01', 1),
            ('Retífica de Motores Morada do Vale Ltda',       'oficina',     0.22, DATE '2026-08-01', 2),
            ('Serralheria Aço Forte Gravataí Ltda',           'serralheria', 0.25, DATE '2026-08-01', 3),
            ('Esquadrias Parque dos Anjos ME',                'serralheria', 0.18, DATE '2026-09-01', 4),
            ('Construtora Vale do Gravataí Ltda',             'obra',        0.14, DATE '2026-08-01', 5),
            ('Demolidora Cachoeirinha Ltda',                  'obra',        0.12, DATE '2026-10-13', 6),
            ('Eletro Instalações Silveira ME',                'eletricista', 0.25, DATE '2026-08-01', 7),
            ('Instaladora Elétrica Rio Branco Ltda',          'eletricista', 0.20, DATE '2026-09-21', 8)) v
    LOOP
        INSERT INTO clientes (nome, cpf_cnpj, telefone, celular, email, preco_especial, status, data_cadastro, id_endereco)
        VALUES (nome, pg_temp.cnpj(lpad((i * 48611 % 90000000 + 10000000)::text, 8, '0') || '0001'),
                '5134' || lpad((i * 4421 % 1000000)::text, 6, '0'), '5199' || lpad((i * 8191 % 10000000)::text, 7, '0'),
                'contato.pj' || i || '@example.com', true, 1,
                CASE WHEN ini = DATE '2026-08-01' THEN implantacao + interval '1 hour' + i * interval '2 minutes'
                     ELSE ini + time '07:00' END,
                pg_temp.endereco('941' || lpad((i * 3571 % 100000)::text, 5, '0'), ruas[1 + (i * 7) % 12],
                                 (100 + i * 211)::text, bairros[1 + (i * 5) % 12], cidades[1 + i % 6], 'RS'))
        RETURNING id_cliente INTO v_id;

        INSERT INTO _cli VALUES (v_id, 'pj', NULL, perfil, peso, ini);
        PERFORM pg_temp.auditar(pg_temp.id('atendente'), pg_temp.id('atendente'),
            format('Cliente cadastrado: %s (#%s)', nome, v_id), 'Cadastro Cliente',
            (SELECT data_cadastro FROM clientes WHERE id_cliente = v_id));
    END LOOP;

    -- 5 compradores: (nome, grupo, lote minimo em kg, % a prazo, dias, forma a prazo, forma a vista, cidade)
    FOR r IN
        SELECT * FROM (VALUES
            (1, 'Fundição e Siderúrgica Guaíba Metais Ltda', 'metais',   8000, 0.50, 15, 'boleto',        'transferência', 'Guaíba',
                'Compra sucata ferrosa, inox, bronze e cobre.'),
            (2, 'Recicladora de Alumínio Sul Latas Ltda',    'aluminio', 1000, 0.30,  7, 'transferência', 'PIX',           'Canoas',
                'Compra latinha, perfil, panela e bloco de alumínio.'),
            (3, 'Aparas Papel Gaúcho Comércio Ltda',         'papel',    3500, 0.60, 15, 'boleto',        'PIX',           'Porto Alegre',
                'Aparista: papelão e papel branco, coleta com caminhão próprio.'),
            (4, 'PoliReciclagem Plásticos do Sul Ltda',      'plastico',  700, 0.25,  7, 'transferência', 'PIX',           'Esteio',
                'Compra PET branco e verde enfardado.'),
            (5, 'Acumuladores Recupera Energia Ltda',        'bateria',   700, 0.00,  0, NULL,            'PIX',           'Sapucaia do Sul',
                'Compra bateria automotiva inteira.')) v(i, nome, grupo, lote, pct, dias, fprazo, fvista, cidade, obs)
    LOOP
        INSERT INTO fornecedores (nome_razao_social, cpf_cnpj, telefone, email, status, data_cadastro, observacoes, id_endereco)
        VALUES (r.nome, pg_temp.cnpj(lpad((r.i * 15485863 % 90000000 + 10000000)::text, 8, '0') || '0001'),
                '5130' || lpad((r.i * 9973 % 1000000)::text, 6, '0'), 'compras.' || r.grupo || '@example.com', 1,
                implantacao + interval '2 hours' + r.i * interval '3 minutes', r.obs,
                pg_temp.endereco('92' || lpad((r.i * 7717 % 1000000)::text, 6, '0'), 'Rua Industrial', (200 * r.i)::text,
                                 'Distrito Industrial', r.cidade, 'RS'))
        RETURNING id_fornecedor INTO v_id;

        INSERT INTO _forn VALUES (v_id, r.grupo, r.lote, r.pct, r.dias, r.fprazo, r.fvista);
        PERFORM pg_temp.auditar(pg_temp.id('dono'), pg_temp.id('dono'), 'Fornecedor ' || r.nome || ' cadastrado',
                                'Cadastro Fornecedor', implantacao + interval '2 hours' + r.i * interval '3 minutes');
    END LOOP;

    -- Precos de mercado no catalogo (o dono revisou a tabela antiga antes de comecar).
    FOR r IN SELECT t.id, t.compra_base, t.venda_base, pg_temp.especial(t.classe, t.compra_base, t.venda_base) AS esp,
                    m.preco_compra AS pc, m.preco_especial AS pe
               FROM _mat t JOIN _classe c USING (classe) JOIN material m ON m.id_material = t.id ORDER BY t.id LOOP
        UPDATE material SET preco_compra = r.compra_base, preco_especial = r.esp, preco_venda = r.venda_base
         WHERE id_material = r.id;
        IF r.pc <> r.compra_base OR r.pe IS DISTINCT FROM r.esp THEN
            PERFORM pg_temp.auditar(pg_temp.id('dono'), pg_temp.id('dono'),
                format('Material #%s editado: %s', r.id, pg_temp.mudancas(r.pc, r.compra_base, r.pe, r.esp)),
                'Material editado', implantacao + interval '9 hours');
        END IF;
    END LOOP;

    UPDATE material SET estoque_minimo = v.minimo
      FROM (VALUES ('Sucata', 2500), ('Papelão', 800), ('Lata', 120), ('Pet Branca', 100), ('Cobre Misto', 10)) v(nome, minimo)
     WHERE nm_material = v.nome;
END $$;

-- ---------------------------------------------------------------------------
-- O piloto, dia a dia
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    dia date; dow int; semana int := extract(week FROM DATE '2026-08-01')::int; g double precision;
    n_pf int; n_pj int; k int; s int; t timestamp; v_ref int; ev record; c record; slot time;
    feriados date[] := ARRAY[DATE '2026-09-07', DATE '2026-10-12', DATE '2026-11-02'];
    motivos_compra text[] := ARRAY['Peso lançado em duplicidade', 'Material lançado no item errado',
                                   'Cliente desistiu do preço e levou o material', 'Lançado no cliente errado'];
    motivos_venda text[] := ARRAY['Comprador recusou a carga na balança dele', 'Venda lançada em duplicidade',
                                  'Caminhão do comprador não carregou'];
BEGIN
    FOR dia IN SELECT generate_series(DATE '2026-08-01', DATE '2026-11-07', interval '1 day')::date LOOP
        dow := extract(dow FROM dia)::int;
        CONTINUE WHEN dow = 0 OR dia = ANY (feriados);

        -- Primeiro dia util da semana: reajuste de precos e contador de vendas zerado.
        IF extract(week FROM dia)::int <> semana THEN
            semana := extract(week FROM dia)::int;
            UPDATE _ids SET valor = 0 WHERE chave = 'vendas_semana';
            PERFORM pg_temp.piloto_reajuste(dia + time '06:50');
        END IF;

        TRUNCATE _ev;

        -- Empresas: cada uma com sua chance de vir no dia.
        n_pj := 0;
        FOR c IN SELECT * FROM _cli WHERE tipo = 'pj' AND ini <= dia ORDER BY id LOOP
            IF random() < c.freq_dia * (CASE WHEN dow = 6 THEN 0.4 ELSE 1 END) THEN
                INSERT INTO _ev (t, kind, cli)
                VALUES (pg_temp.momento(dia, CASE WHEN dow = 6 THEN 28800 + 12600 * random() ELSE 28800 + 30600 * random() END),
                        'compra', c.id);
                n_pj := n_pj + 1;
            END IF;
        END LOOP;

        -- Pessoas fisicas: ~17/dia em agosto, ~30/dia no fim do piloto.
        g := 0.72 + 0.56 * (dia - DATE '2026-08-01') / 98.0;
        n_pf := round(24 * g * (0.8 + 0.4 * random()))::int;
        IF dow = 6 THEN
            n_pf := GREATEST(5, round(n_pf * 0.4)::int);
        ELSE
            n_pf := LEAST(40 - n_pj, GREATEST(15 - n_pj, n_pf));
        END IF;

        FOR k IN 1..n_pf LOOP
            INSERT INTO _ev (t, kind, cli)
            SELECT pg_temp.momento(dia, pg_temp.segundo_compra(dow = 6)), 'compra', id
              FROM _cli WHERE tipo = 'pf' AND ini <= dia
             ORDER BY power(random(), 1.0 / peso) DESC LIMIT 1;
        END LOOP;

        -- Cancelamentos de compra, 3 a 12 minutos depois.
        FOR ev IN SELECT * FROM _ev WHERE kind = 'compra' ORDER BY seq LOOP
            IF random() < 0.015 THEN
                INSERT INTO _ev (t, kind, ref) VALUES (ev.t + make_interval(secs => 180 + floor(random() * 540)), 'cancela_compra', ev.seq);
            END IF;
        END LOOP;

        -- Janelas de venda: tercas, quintas e sextas, de manha e a tarde.
        IF dow IN (2, 4, 5) THEN
            FOREACH slot IN ARRAY ARRAY[time '10:00', time '14:00'] LOOP
                t := pg_temp.momento(dia, extract(epoch FROM slot) + 5400 * random());
                INSERT INTO _ev (t, kind) VALUES (t - make_interval(secs => 2400 + floor(random() * 1800)), 'triagem')
                RETURNING seq INTO s;
                INSERT INTO _ev (t, kind, ref) VALUES (t, 'venda', s) RETURNING seq INTO s;
                IF random() < 0.07 THEN
                    INSERT INTO _ev (t, kind, ref) VALUES (t + make_interval(secs => 1800 + floor(random() * 3600)), 'cancela_venda', s);
                END IF;
            END LOOP;
        END IF;

        -- Grava na ordem do relogio.
        FOR ev IN SELECT * FROM _ev ORDER BY t, seq LOOP
            IF ev.kind = 'compra' THEN
                UPDATE _ev SET result = pg_temp.piloto_compra(ev.cli, ev.t) WHERE seq = ev.seq;

            ELSIF ev.kind = 'triagem' THEN
                -- Decide o comprador do lote e tira a quebra antes do carregamento.
                v_ref := pg_temp.piloto_escolher_comprador();
                IF v_ref IS NOT NULL THEN
                    PERFORM pg_temp.piloto_triagem(v_ref, ev.t);
                    UPDATE _ev SET result = v_ref WHERE seq = ev.seq;
                END IF;

            ELSIF ev.kind = 'venda' THEN
                SELECT result INTO v_ref FROM _ev WHERE seq = ev.ref;
                IF v_ref IS NOT NULL THEN
                    UPDATE _ev SET result = pg_temp.piloto_venda(v_ref, ev.t) WHERE seq = ev.seq;
                END IF;

            ELSIF ev.kind = 'cancela_compra' THEN
                SELECT result INTO v_ref FROM _ev WHERE seq = ev.ref;
                -- Se uma venda ja levou o material, o estorno deixaria saldo negativo: nao cancela.
                IF v_ref IS NOT NULL AND NOT EXISTS (
                       SELECT 1 FROM pesagem_material pm JOIN material m USING (id_material)
                        WHERE pm.id_pesagem = v_ref
                        GROUP BY pm.id_material, m.qt_estoque
                       HAVING SUM(pm.peso_material) > COALESCE(m.qt_estoque, 0)) THEN
                    PERFORM pg_temp.cancelar_compra(v_ref, pg_temp.id('dono'), ev.t,
                                                    motivos_compra[1 + floor(random() * 4)::int]);
                END IF;

            ELSIF ev.kind = 'cancela_venda' THEN
                SELECT result INTO v_ref FROM _ev WHERE seq = ev.ref;
                IF v_ref IS NOT NULL THEN
                    PERFORM pg_temp.cancelar_venda(v_ref, pg_temp.id('dono'), ev.t,
                                                   motivos_venda[1 + floor(random() * 3)::int]);
                    UPDATE _ids SET valor = valor - 1 WHERE chave = 'vendas_semana';
                END IF;
            END IF;
        END LOOP;
    END LOOP;

    -- preco_venda = preco da ultima venda nao cancelada, como vendaAtualizarUltimoPreco()
    UPDATE material m
       SET preco_venda = u.preco_un
      FROM (SELECT DISTINCT ON (vi.id_material) vi.id_material, vi.preco_un
              FROM vendas_itens vi JOIN vendas v USING (id_venda)
             WHERE v.status <> 'cancelada'
             ORDER BY vi.id_material, v.data_venda DESC, v.id_venda DESC, vi.id_venda_item DESC) u
     WHERE m.id_material = u.id_material;
END $$;

-- ---------------------------------------------------------------------------
-- Financeiro do periodo (contas a pagar)
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    adm int := pg_temp.id('dono'); op int := pg_temp.id('operador'); atd int := pg_temp.id('atendente');
    mes date; prox date; v numeric; k int; d date;
BEGIN
    -- Contratos de 12 meses, lancados na implantacao.
    PERFORM pg_temp.conta(adm, 'Aluguel do galpão — 08/2026 a 07/2027', 'Aluguel', 'Administrativo',
        'Imobiliária Gravataí Imóveis', NULL, DATE '2026-08-01' - CURRENT_DATE,
        pg_temp.parc_mensais(12, 3800.00, DATE '2026-08-10', 'transferencia'), 'Contrato anual: 12x de R$ 3.800,00.');
    PERFORM pg_temp.conta(adm, 'Honorários contábeis — 08/2026 a 07/2027', 'Contabilidade', 'Administrativo',
        'Escritório Contábil Exatus', NULL, DATE '2026-08-01' - CURRENT_DATE,
        pg_temp.parc_mensais(12, 650.00, DATE '2026-08-10', 'boleto'));
    PERFORM pg_temp.conta(adm, 'Internet fibra — 08/2026 a 07/2027', 'Internet', 'Administrativo',
        'Provedor NetVale', NULL, DATE '2026-08-01' - CURRENT_DATE,
        pg_temp.parc_mensais(12, 129.90, DATE '2026-08-15', 'debito_auto'));

    -- Balança de balcão comprada na implantação, 6x no cartão.
    PERFORM pg_temp.conta(adm, 'Balança de plataforma 300 kg (balcão)', 'Equipamentos', 'Operacional',
        'Balanças Precisão Sul Ltda', NULL, DATE '2026-08-03' - CURRENT_DATE,
        pg_temp.parc_mensais(6, 698.33, DATE '2026-09-03', 'cartao'), 'Parcelado em 6x.');

    FOR mes IN SELECT generate_series(DATE '2026-08-01', DATE '2026-10-01', interval '1 month')::date LOOP
        prox := (mes + interval '1 month')::date;

        -- Salários do mês, pagos no 5º dia do mês seguinte.
        PERFORM pg_temp.conta(adm, 'Salário Diego Ramos Lemos — ' || to_char(mes, 'MM/YYYY'), 'Funcionários', 'Recursos Humanos',
            'Diego Ramos Lemos', op, prox - CURRENT_DATE,
            jsonb_build_array(pg_temp.parc(2450.00, prox + 4, 'pix', prox + 4)));
        PERFORM pg_temp.conta(adm, 'Salário Patrícia Fagundes — ' || to_char(mes, 'MM/YYYY'), 'Funcionários', 'Recursos Humanos',
            'Patrícia Fagundes', atd, prox - CURRENT_DATE,
            jsonb_build_array(pg_temp.parc(2150.00, prox + 4, 'pix', prox + 4)));
        -- O terceiro funcionário não usa o sistema: sem vínculo com usuário.
        PERFORM pg_temp.conta(adm, 'Salário Jonas Pereira (ajudante de pátio e motorista) — ' || to_char(mes, 'MM/YYYY'),
            'Funcionários', 'Recursos Humanos', 'Jonas Pereira', NULL, prox - CURRENT_DATE,
            jsonb_build_array(pg_temp.parc(2250.00, prox + 4, 'pix', prox + 4)));

        -- FGTS: 8% da folha, vence dia 20 do mês seguinte (a contribuição
        -- previdenciária patronal já está dentro do Simples, no comércio).
        PERFORM pg_temp.conta(adm, 'FGTS — competência ' || to_char(mes, 'MM/YYYY'), 'Impostos', 'Recursos Humanos',
            'Caixa Econômica Federal', NULL, prox + 4 - CURRENT_DATE,
            jsonb_build_array(pg_temp.parc(round((2450 + 2150 + 2250) * 0.08, 2), prox + 19, 'boleto',
                                           CASE WHEN prox + 19 <= DATE '2026-11-07' THEN prox + 18 END)));

        -- Celular da empresa (atendimento e WhatsApp com os compradores).
        PERFORM pg_temp.conta(adm, 'Telefone celular — ' || to_char(mes, 'MM/YYYY'), 'Telefone', 'Comercial',
            'Operadora Fone Sul', NULL, prox - CURRENT_DATE,
            jsonb_build_array(pg_temp.parc(79.90, prox + 9, 'debito_auto',
                                           CASE WHEN prox + 9 <= DATE '2026-11-07' THEN prox + 9 END)));

        -- Energia (prensa e iluminação do pátio): conta de setembro paga com 6 dias de atraso e juros.
        v := round((1150 + random() * 300)::numeric, 2);
        PERFORM pg_temp.conta(adm, 'Energia elétrica — ' || to_char(mes, 'MM/YYYY'), 'Energia elétrica', 'Operacional',
            'RGE Distribuidora', NULL, prox + 4 - CURRENT_DATE,
            jsonb_build_array(CASE
                WHEN prox + 14 > DATE '2026-11-07' THEN pg_temp.parc(v, prox + 14, 'boleto')
                WHEN mes = DATE '2026-09-01'       THEN pg_temp.parc(v, prox + 14, 'boleto', prox + 20, round(v * 1.022, 2))
                ELSE                                    pg_temp.parc(v, prox + 14, 'boleto', prox + 14) END));

        -- Água: a de setembro (vence 27/10) ficou em aberto.
        v := round((140 + random() * 90)::numeric, 2);
        PERFORM pg_temp.conta(adm, 'Água — ' || to_char(mes, 'MM/YYYY'), 'Água', 'Operacional',
            'Corsan', NULL, prox + 5 - CURRENT_DATE,
            jsonb_build_array(CASE
                WHEN prox + 26 > DATE '2026-11-07' OR mes = DATE '2026-09-01' THEN pg_temp.parc(v, prox + 26, 'boleto')
                ELSE pg_temp.parc(v, prox + 26, 'boleto', prox + 25) END));

        -- Simples Nacional: 4% das vendas do mês, vence dia 20 do mês seguinte.
        SELECT round(COALESCE(SUM(total_valor), 0) * 0.04, 2) INTO v FROM vendas
         WHERE status <> 'cancelada' AND data_venda >= mes AND data_venda < prox;
        IF v > 0 THEN
            PERFORM pg_temp.conta(adm, 'Simples Nacional — competência ' || to_char(mes, 'MM/YYYY'), 'Impostos', 'Administrativo',
                'Receita Federal', NULL, prox + 2 - CURRENT_DATE,
                jsonb_build_array(pg_temp.parc(v, prox + 19, 'boleto',
                                               CASE WHEN prox + 19 <= DATE '2026-11-07' THEN prox + 18 END)));
        END IF;

        -- Diesel do caminhão de coleta: 3 abastecimentos, pagos na hora.
        FOR k IN 1..3 LOOP
            d := mes + 2 + (k - 1) * 9 + floor(random() * 6)::int;
            PERFORM pg_temp.conta(adm, 'Diesel do caminhão de coleta', 'Combustível', 'Logística', 'Posto Free Way', NULL,
                d - CURRENT_DATE,
                jsonb_build_array(pg_temp.parc(round((320 + random() * 380)::numeric, 2), d,
                                               (ARRAY['dinheiro', 'pix', 'cartao'])[1 + floor(random() * 3)::int], d)));
        END LOOP;
    END LOOP;

    -- Manutenção do caminhão de coleta.
    PERFORM pg_temp.conta(adm, 'Revisão do caminhão de coleta (óleo, filtros e freios)', 'Manutenção', 'Logística',
        'Mecânica Diesel Gravataí', NULL, DATE '2026-08-24' - CURRENT_DATE,
        jsonb_build_array(pg_temp.parc(1480.00, DATE '2026-08-31', 'boleto', DATE '2026-08-31')));
    PERFORM pg_temp.conta(adm, 'Troca de dois pneus traseiros do caminhão', 'Manutenção', 'Logística',
        'Pneus Rota 290', NULL, DATE '2026-10-06' - CURRENT_DATE,
        jsonb_build_array(pg_temp.parc(1290.00, DATE '2026-10-06', 'cartao', DATE '2026-10-06'),
                          pg_temp.parc(1290.00, DATE '2026-11-06', 'cartao', DATE '2026-11-06')),
        'Parcelado em 2x.');

    -- Balança e prensa.
    PERFORM pg_temp.conta(adm, 'Aferição da balança rodoviária', 'Manutenção', 'Manutenção', 'Balanças Precisão Sul Ltda', NULL,
        DATE '2026-09-16' - CURRENT_DATE,
        jsonb_build_array(pg_temp.parc(980.00, DATE '2026-09-30', 'boleto', DATE '2026-09-29')));
    PERFORM pg_temp.conta(adm, 'Troca do óleo hidráulico e vedações da prensa', 'Manutenção', 'Manutenção', 'HidroPress Serviços', NULL,
        DATE '2026-10-20' - CURRENT_DATE,
        jsonb_build_array(pg_temp.parc(810.00, DATE '2026-11-04', 'boleto', DATE '2026-11-04'),
                          pg_temp.parc(810.00, DATE '2026-12-04', 'boleto')));

    -- Despesa lançada e cancelada por inteiro antes de vencer.
    PERFORM pg_temp.conta(adm, 'Curso de operador de empilhadeira (cancelado)', 'Serviços', 'Recursos Humanos',
        'Centro de Treinamento Seguro', NULL, DATE '2026-09-10' - CURRENT_DATE,
        jsonb_build_array(pg_temp.parc(1200.00, DATE '2026-10-10', 'boleto')),
        'Turma cancelada pela escola.', true);
END $$;

-- ---------------------------------------------------------------------------
-- Conferencia
-- ---------------------------------------------------------------------------
DO $$
DECLARE n int;
BEGIN
    -- 1. saldo de cada material = soma da trilha
    SELECT COUNT(*) INTO n
      FROM material m
      LEFT JOIN (SELECT id_material, SUM(CASE WHEN tipo = 'entrada' THEN quantidade ELSE -quantidade END) AS saldo
                   FROM estoque_movimentacoes GROUP BY id_material) t USING (id_material)
     WHERE COALESCE(m.qt_estoque, 0) <> COALESCE(t.saldo, 0);
    IF n > 0 THEN RAISE EXCEPTION '% material(is) com saldo diferente da trilha', n; END IF;

    -- 2. saldo_apos = soma acumulada em ordem cronologica
    SELECT COUNT(*) INTO n FROM (
        SELECT saldo_apos,
               SUM(CASE WHEN tipo = 'entrada' THEN quantidade ELSE -quantidade END)
                   OVER (PARTITION BY id_material ORDER BY data_movimentacao, id_movimentacao) AS acumulado
          FROM estoque_movimentacoes) x
     WHERE acumulado <> saldo_apos;
    IF n > 0 THEN RAISE EXCEPTION '% movimentacao(oes) com saldo_apos fora da ordem cronologica', n; END IF;

    -- 3. estoque nunca negativo, em nenhum momento
    IF EXISTS (SELECT 1 FROM estoque_movimentacoes WHERE saldo_apos < 0)
       OR EXISTS (SELECT 1 FROM material WHERE qt_estoque < 0) THEN
        RAISE EXCEPTION 'estoque negativo em algum momento do piloto';
    END IF;

    -- 4. tudo dentro do periodo do piloto
    IF EXISTS (SELECT 1 FROM pesagem WHERE data_pesagem < '2026-08-01' OR data_pesagem >= '2026-11-08')
       OR EXISTS (SELECT 1 FROM vendas WHERE data_venda < '2026-08-01' OR data_venda >= '2026-11-08') THEN
        RAISE EXCEPTION 'compra ou venda fora de 01/08/2026 a 07/11/2026';
    END IF;

    -- 5. nenhuma despesa cancelada com parcela paga (regra de contas.php)
    IF EXISTS (SELECT 1 FROM contas_pagar c JOIN contas_pagar_parcelas pa USING (id_conta)
                WHERE c.status = 'cancelada' AND pa.status = 'paga') THEN
        RAISE EXCEPTION 'despesa cancelada com parcela paga';
    END IF;
END $$;

-- Resultado por material, base da conferencia de margem e quebra e do resumo.
--   custo medio  = valor das compras ativas / kg comprado (como a tela de Margem)
--   CMV          = kg vendido x custo medio
--   quebra %     = kg perdido na triagem / kg comprado ate a ultima triagem
CREATE TEMP TABLE _res ON COMMIT DROP AS
SELECT t.id, t.nome, t.classe,
       c.kg AS comprado, c.valor AS valor_compra, c.valor / c.kg AS custo_medio,
       COALESCE((SELECT SUM(pm.peso_material) FROM pesagem_material pm JOIN pesagem p USING (id_pesagem)
                  WHERE pm.id_material = t.id AND p.status = 'ativa' AND p.data_pesagem <= t.ult_triagem), 0) AS triado,
       COALESCE((SELECT SUM(quantidade) FROM estoque_movimentacoes
                  WHERE id_material = t.id AND tipo = 'perda'), 0) AS perda,
       COALESCE(v.kg, 0) AS vendido, COALESCE(v.valor, 0) AS receita,
       m.qt_estoque AS estoque
  FROM _mat t
  JOIN material m ON m.id_material = t.id
  JOIN (SELECT id_material, SUM(peso_material) kg, SUM(peso_material * preco_un) valor
          FROM pesagem_material JOIN pesagem USING (id_pesagem) WHERE status = 'ativa' GROUP BY 1) c ON c.id_material = t.id
  LEFT JOIN (SELECT id_material, SUM(quantidade) kg, SUM(valor_total) valor
               FROM vendas_itens JOIN vendas USING (id_venda) WHERE status = 'concluida' GROUP BY 1) v ON v.id_material = t.id;

DO $$
DECLARE r record; n int;
BEGIN
    -- 6a. todo preco praticado e de catalogo no degrau da classe
    SELECT COUNT(*) INTO n FROM (
        SELECT t.classe, pm.preco_un AS p FROM pesagem_material pm JOIN _mat t ON t.id = pm.id_material
        UNION ALL
        SELECT t.classe, vi.preco_un FROM vendas_itens vi JOIN _mat t ON t.id = vi.id_material
        UNION ALL
        SELECT t.classe, x.p FROM _mat t JOIN material m ON m.id_material = t.id,
               LATERAL (VALUES (m.preco_compra), (m.preco_especial), (m.preco_venda)) x(p)) z
     WHERE NOT pg_temp.no_degrau(classe, p);
    IF n > 0 THEN RAISE EXCEPTION '% preço(s) fora do degrau da classe', n; END IF;

    -- 6b. total de cada compra = soma dos itens arredondada no balcao
    SELECT COUNT(*) INTO n
      FROM pesagem p
      JOIN (SELECT id_pesagem, round(SUM(peso_material * preco_un), 2) AS itens FROM pesagem_material GROUP BY 1) i USING (id_pesagem)
     WHERE p.total_valor <> pg_temp.balcao(i.itens);
    IF n > 0 THEN RAISE EXCEPTION '% compra(s) com total fora da regra do balcão', n; END IF;

    -- 6c. resultado operacional positivo e modesto: 4% a 8% do faturamento
    -- (conferido no resumo, que tem o calculo completo)

    -- 6. markup realizado de cada classe dentro da faixa
    FOR r IN SELECT c.classe, c.markup_min, c.markup_max,
                    (SUM(x.receita) - SUM(x.vendido * x.custo_medio)) / SUM(x.vendido * x.custo_medio) AS markup
               FROM _res x JOIN _classe c USING (classe) GROUP BY 1, 2, 3 LOOP
        IF r.markup NOT BETWEEN r.markup_min AND r.markup_max THEN
            RAISE EXCEPTION 'classe %: markup realizado de % fora da faixa', r.classe, round(r.markup * 100, 1) || '%';
        END IF;
    END LOOP;

    -- 7. quebra de cada material dentro da faixa da classe, e vendido menor que o comprado
    FOR r IN SELECT x.*, c.quebra_min, c.quebra_max FROM _res x JOIN _classe c USING (classe) LOOP
        IF r.triado = 0 OR r.perda / r.triado NOT BETWEEN r.quebra_min AND r.quebra_max THEN
            RAISE EXCEPTION '%: quebra de % fora da faixa da classe %', r.nome,
                round(r.perda / NULLIF(r.triado, 0) * 100, 2) || '%', r.classe;
        END IF;
        IF r.vendido >= r.comprado THEN
            RAISE EXCEPTION '%: vendido (%) não é menor que o comprado (%)', r.nome, r.vendido, r.comprado;
        END IF;
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- Resumo
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    r record; fat numeric; cmv numeric; custo numeric; quebra_rs numeric; desp numeric; bruta numeric;
    n_c int; n_cc int; n_v int; n_vc int; n_prazo int; v_prazo numeric; kg_c numeric; kg_v numeric; kg_p numeric;
    i int := 0;
BEGIN
    SELECT COUNT(*) FILTER (WHERE status = 'ativa'), COUNT(*) FILTER (WHERE status = 'cancelada'),
           SUM(total_valor) FILTER (WHERE status = 'ativa'), SUM(total_peso) FILTER (WHERE status = 'ativa')
      INTO n_c, n_cc, custo, kg_c FROM pesagem;
    SELECT COUNT(*) FILTER (WHERE status = 'concluida'), COUNT(*) FILTER (WHERE status = 'cancelada'),
           SUM(total_valor) FILTER (WHERE status = 'concluida'), SUM(total_peso) FILTER (WHERE status = 'concluida'),
           COUNT(*) FILTER (WHERE status = 'concluida' AND observacoes LIKE 'A prazo%'),
           SUM(total_valor) FILTER (WHERE status = 'concluida' AND observacoes LIKE 'A prazo%')
      INTO n_v, n_vc, fat, kg_v, n_prazo, v_prazo FROM vendas;
    SELECT SUM(vendido * custo_medio), SUM(perda * custo_medio), SUM(perda) INTO cmv, quebra_rs, kg_p FROM _res;
    bruta := fat - cmv;

    -- Despesas pelo vencimento no periodo, sem as canceladas: o mesmo criterio do Relatorio Financeiro.
    SELECT COALESCE(SUM(pa.valor), 0) INTO desp
      FROM contas_pagar_parcelas pa JOIN contas_pagar c USING (id_conta)
     WHERE c.status <> 'cancelada' AND pa.status <> 'cancelada'
       AND pa.data_vencimento BETWEEN DATE '2026-08-01' AND DATE '2026-11-07';

    RAISE NOTICE '================ PILOTO 01/08/2026 a 07/11/2026 ================';
    RAISE NOTICE 'markup = (venda - compra) / compra   |   margem sobre a venda = (venda - compra) / venda';
    RAISE NOTICE 'Compras: % ativas + % canceladas | % kg | R$ %', n_c, n_cc, pg_temp.num(kg_c), pg_temp.num(custo);
    RAISE NOTICE 'Vendas:  % concluídas + % canceladas | % kg | % a prazo (R$ %)',
        n_v, n_vc, pg_temp.num(kg_v), n_prazo, pg_temp.num(COALESCE(v_prazo, 0));
    RAISE NOTICE 'Pago no balcão: R$ %  | itens (peso x preço): R$ %  | arredondamento: R$ %',
        (SELECT pg_temp.num(SUM(total_valor)) FROM pesagem WHERE status = 'ativa'),
        (SELECT pg_temp.num(SUM(pm.peso_material * pm.preco_un)) FROM pesagem_material pm JOIN pesagem p USING (id_pesagem) WHERE p.status = 'ativa'),
        (SELECT pg_temp.num(SUM(p.total_valor) - SUM(i.itens))
           FROM pesagem p JOIN (SELECT id_pesagem, SUM(peso_material * preco_un) itens FROM pesagem_material GROUP BY 1) i USING (id_pesagem)
          WHERE p.status = 'ativa');
    RAISE NOTICE 'Quebra na triagem: % kg', pg_temp.num(kg_p);
    RAISE NOTICE 'Compras por dia útil (seg-sex): mín % / média % / máx %; sábado: média %',
        (SELECT MIN(n) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) BETWEEN 1 AND 5),
        (SELECT round(AVG(n), 1) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) BETWEEN 1 AND 5),
        (SELECT MAX(n) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) BETWEEN 1 AND 5),
        (SELECT round(AVG(n), 1) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) = 6);
    RAISE NOTICE 'Compras por mês: %',
        (SELECT string_agg(format('%s=%s', to_char(m, 'MM'), n), ' ' ORDER BY m)
           FROM (SELECT date_trunc('month', data_pesagem) m, COUNT(*) n FROM pesagem GROUP BY 1) x);

    RAISE NOTICE '';
    RAISE NOTICE '% % % % % % % % % %', rpad('Material', 15), 'C', lpad('comprado kg', 11), lpad('quebra kg', 9),
        lpad('quebra', 6), lpad('vendido kg', 11), lpad('custo/kg', 8), lpad('venda/kg', 8), lpad('markup', 7), lpad('margem', 7);
    FOR r IN SELECT * FROM _res ORDER BY classe, receita DESC LOOP
        RAISE NOTICE '% % % % % % % % % %', rpad(r.nome, 15), r.classe, lpad(pg_temp.num(r.comprado), 11),
            lpad(pg_temp.num(r.perda), 9), lpad(pg_temp.num(r.perda / r.triado * 100) || '%', 6),
            lpad(pg_temp.num(r.vendido), 11), lpad(pg_temp.num(r.custo_medio), 8),
            lpad(pg_temp.num(r.receita / NULLIF(r.vendido, 0)), 8),
            lpad(pg_temp.num((r.receita / r.vendido - r.custo_medio) / r.custo_medio * 100) || '%', 7),
            lpad(pg_temp.num((r.receita / r.vendido - r.custo_medio) / (r.receita / r.vendido) * 100) || '%', 7);
    END LOOP;

    RAISE NOTICE '';
    FOR r IN SELECT x.classe, c.descricao, c.markup_min, c.markup_max, c.quebra_min, c.quebra_max,
                    SUM(x.receita) AS receita, SUM(x.vendido * x.custo_medio) AS cmv,
                    SUM(x.perda) / SUM(x.triado) AS quebra
               FROM _res x JOIN _classe c USING (classe) GROUP BY 1, 2, 3, 4, 5, 6 ORDER BY 1 LOOP
        RAISE NOTICE 'Classe % (%): markup % (faixa %-%), margem s/ venda %, quebra % (faixa %-%)',
            r.classe, r.descricao,
            pg_temp.num((r.receita - r.cmv) / r.cmv * 100) || '%', round(r.markup_min * 100), round(r.markup_max * 100) || '%',
            pg_temp.num((r.receita - r.cmv) / r.receita * 100) || '%',
            pg_temp.num(r.quebra * 100) || '%', round(r.quebra_min * 100), round(r.quebra_max * 100) || '%';
    END LOOP;

    RAISE NOTICE '';
    RAISE NOTICE 'Preços por kg (base em 31/07; praticados = menor e maior do piloto)';
    RAISE NOTICE '% % % % % % % %', rpad('Material', 15), 'C', lpad('compra', 7), lpad('venda', 7), lpad('markup', 7),
        lpad('especial', 8), lpad('compra praticada', 17), lpad('venda praticada', 16);
    FOR r IN SELECT t.nome, t.classe, t.compra_base, t.venda_base,
                    pg_temp.especial(t.classe, t.compra_base, t.venda_base) AS esp,
                    (SELECT MIN(preco_un) FROM pesagem_material WHERE id_material = t.id) AS cmin,
                    (SELECT MAX(preco_un) FROM pesagem_material WHERE id_material = t.id) AS cmax,
                    (SELECT MIN(preco_un) FROM vendas_itens WHERE id_material = t.id) AS vmin,
                    (SELECT MAX(preco_un) FROM vendas_itens WHERE id_material = t.id) AS vmax
               FROM _mat t ORDER BY t.classe, t.compra_base DESC LOOP
        RAISE NOTICE '% % % % % % % %', rpad(r.nome, 15), r.classe, lpad(pg_temp.num(r.compra_base), 7),
            lpad(pg_temp.num(r.venda_base), 7),
            lpad(pg_temp.num((r.venda_base - r.compra_base) / r.compra_base * 100) || '%', 7),
            lpad(pg_temp.num(r.esp), 8),
            lpad(pg_temp.num(r.cmin) || ' a ' || pg_temp.num(r.cmax), 17),
            lpad(COALESCE(pg_temp.num(r.vmin) || ' a ' || pg_temp.num(r.vmax), '-'), 16);
    END LOOP;

    RAISE NOTICE '';
    RAISE NOTICE 'Faturamento (vendas):                    R$ %', lpad(pg_temp.num(fat), 12);
    RAISE NOTICE '- Custo do material vendido (CMV):       R$ %', lpad(pg_temp.num(cmv), 12);
    RAISE NOTICE '= Margem bruta:                          R$ %  (markup %, margem s/ venda %)', lpad(pg_temp.num(bruta), 12),
        pg_temp.num(bruta / cmv * 100) || '%', pg_temp.num(bruta / fat * 100) || '%';
    RAISE NOTICE '- Quebra na triagem (kg x custo médio):  R$ %', lpad(pg_temp.num(quebra_rs), 12);
    RAISE NOTICE '= Margem após a quebra:                  R$ %  (% do faturamento)', lpad(pg_temp.num(bruta - quebra_rs), 12),
        pg_temp.num((bruta - quebra_rs) / fat * 100) || '%';
    RAISE NOTICE '- Despesas com vencimento no período:    R$ %', lpad(pg_temp.num(desp), 12);
    RAISE NOTICE '= Resultado operacional:                 R$ %  (% do faturamento)', lpad(pg_temp.num(bruta - quebra_rs - desp), 12),
        pg_temp.num((bruta - quebra_rs - desp) / fat * 100) || '%';

    -- Resultado positivo e modesto: entre 4% e 8% do faturamento.
    IF (bruta - quebra_rs - desp) / fat NOT BETWEEN 0.04 AND 0.08 THEN
        RAISE EXCEPTION 'resultado operacional de % do faturamento, fora de 4%% a 8%%',
            pg_temp.num((bruta - quebra_rs - desp) / fat * 100) || '%';
    END IF;

    RAISE NOTICE '';
    RAISE NOTICE 'Despesas por categoria (vencimento no período):';
    FOR r IN SELECT dc.nome, SUM(pa.valor) AS total
               FROM contas_pagar_parcelas pa JOIN contas_pagar c USING (id_conta)
               JOIN despesa_categoria dc USING (id_categoria)
              WHERE c.status <> 'cancelada' AND pa.status <> 'cancelada'
                AND pa.data_vencimento BETWEEN DATE '2026-08-01' AND DATE '2026-11-07'
              GROUP BY 1 ORDER BY 2 DESC LOOP
        RAISE NOTICE '  % R$ %', rpad(r.nome, 17), lpad(pg_temp.num(r.total), 10);
    END LOOP;

    RAISE NOTICE '';
    RAISE NOTICE 'Top 5 materiais mais movimentados (kg comprado + vendido):';
    FOR r IN
        SELECT m.nm_material, SUM(x.kg) kg, SUM(x.valor) valor
          FROM (SELECT id_material, peso_material kg, peso_material * preco_un valor
                  FROM pesagem_material JOIN pesagem USING (id_pesagem) WHERE status = 'ativa'
                UNION ALL
                SELECT id_material, quantidade, valor_total
                  FROM vendas_itens JOIN vendas USING (id_venda) WHERE status = 'concluida') x
          JOIN material m USING (id_material)
         GROUP BY 1 ORDER BY 2 DESC LIMIT 5
    LOOP
        i := i + 1;
        RAISE NOTICE '  %. % % kg (R$ % movimentados)', i, rpad(r.nm_material, 15), lpad(pg_temp.num(r.kg), 11), pg_temp.num(r.valor);
    END LOOP;

    RAISE NOTICE '';
    RAISE NOTICE 'Conferido: saldo = trilha, saldo_apos cronológico, estoque nunca negativo,';
    RAISE NOTICE 'markup de cada classe e quebra de cada material dentro da faixa, vendido < comprado,';
    RAISE NOTICE 'preços no degrau da classe, total de compra arredondado no balcão, resultado entre 4%% e 8%%.';
END $$;

COMMIT;
