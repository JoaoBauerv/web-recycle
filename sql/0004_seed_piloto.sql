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
--   - Precos de mercado com compra a 70-85% da venda e oscilacao semanal (toda
--     segunda, auditada como edicao do material). Metais oscilam juntos.
--   - Vendas em lote as tercas, quintas e sextas (no maximo 3 por semana) para
--     5 compradores, quando o estoque do grupo passa do lote minimo. Vende de
--     90% a 100% do que ha, nunca mais. Parte a prazo (boleto em 15 dias ou
--     transferencia em 7), registrada em vendas.observacoes.
--   - ~1,5% das compras e 1 a 2 vendas canceladas, com estorno do estoque.
--   - Financeiro do periodo em contas_pagar: aluguel, honorarios, internet,
--     salarios, energia, agua, Simples (4% das vendas), diesel, manutencao,
--     uma balanca parcelada, uma conta paga com atraso, uma vencida em aberto
--     e uma despesa cancelada. Pago = vencimento ate 07/11/2026.
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
--   nenhum saldo negativo em momento algum, e imprime um resumo.
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

-- Materiais do piloto: preco-base de compra e de venda (R$/kg), frequencia e
-- faixa de peso por item na entrega de pessoa fisica, grupo de comprador,
-- minimo para entrar numa venda e indice de preco da semana.
CREATE TEMP TABLE _mat (
    id int, nome text, compra_base numeric, venda_base numeric,
    fpf numeric, pf_min numeric, pf_max numeric, grupo text, vmin numeric, idx numeric DEFAULT 1
) ON COMMIT DROP;

INSERT INTO _mat (nome, compra_base, venda_base, fpf, pf_min, pf_max, grupo, vmin) VALUES
    ('Sucata',          0.80,  1.05, 30, 10.0, 80, 'metais',   1000),
    ('Papelão',         0.45,  0.62, 28,  8.0, 70, 'papel',     500),
    ('Lata',            6.50,  8.00, 22,  2.0, 25, 'aluminio',  100),
    ('Pet Branca',      2.20,  2.90, 10,  3.0, 30, 'plastico',  100),
    ('Papel Branco',    0.55,  0.75,  6,  5.0, 40, 'papel',     150),
    ('Panela',          7.50,  9.20,  5,  1.0, 12, 'aluminio',   30),
    ('Perfil',          8.50, 10.30,  4,  2.0, 20, 'aluminio',   50),
    ('Pet Verde',       1.80,  2.40,  4,  3.0, 25, 'plastico',   60),
    ('Fio Capa',       14.00, 17.50,  4,  1.0, 15, 'metais',     30),
    ('Cobre Misto',    36.00, 44.00,  3,  0.5,  8, 'metais',     15),
    ('Metal',          26.00, 32.00,  3,  0.5, 10, 'metais',     20),
    ('Bateria',         3.20,  4.20,  3, 12.0, 45, 'bateria',   100),
    ('Cobre Vermelho', 42.00, 50.00,  2,  0.3,  6, 'metais',     10),
    ('Duro',            6.00,  7.60,  2,  3.0, 25, 'aluminio',   50),
    ('Inox Panela',     4.50,  6.00,  2,  1.0, 15, 'metais',     40);

UPDATE _mat t SET id = m.id_material FROM material m WHERE m.nm_material = t.nome;

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
    FOR r IN SELECT t.*, m.preco_compra AS pc, m.preco_especial AS pe
               FROM _mat t JOIN material m ON m.id_material = t.id ORDER BY t.id LOOP
        novo := r.idx * (1 + CASE WHEN r.grupo IN ('metais', 'aluminio') THEN comum * 0.6 ELSE 0 END
                           + ((random() - 0.5) * 0.04)::numeric);
        novo := LEAST(1.10, GREATEST(0.90, 1 + (novo - 1) * 0.85));
        UPDATE _mat SET idx = novo WHERE id = r.id;

        nc := round(r.compra_base * novo, 2);
        ne := round(r.compra_base * 1.05 * novo, 2);
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

    RETURN pg_temp.compra(p_cli, p_t, pg_temp.quem_pesa(), itens);
END $f$;

-- Uma venda: o grupo com mais estoque em relacao ao lote, se passou do lote
-- e a semana ainda nao teve 3 vendas. Vende 90-100% do saldo de cada material.
CREATE FUNCTION pg_temp.piloto_venda(p_t timestamp) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE g record; itens jsonb; v_id int; obs text; usr int;
BEGIN
    IF pg_temp.id('vendas_semana') >= 3 THEN RETURN NULL; END IF;

    SELECT f.*, s.total INTO g
      FROM _forn f
      JOIN LATERAL (SELECT SUM(m.qt_estoque) AS total
                      FROM _mat t JOIN material m ON m.id_material = t.id
                     WHERE t.grupo = f.grupo AND COALESCE(m.qt_estoque, 0) >= t.vmin) s ON true
     WHERE s.total >= f.lote
     ORDER BY s.total / f.lote DESC, f.id
     LIMIT 1;
    IF NOT FOUND THEN RETURN NULL; END IF;

    SELECT jsonb_agg(jsonb_build_object('m', nome, 'b', qtd + tara, 't', tara, 'p', preco) ORDER BY nome)
      INTO itens
      FROM (SELECT t.nome,
                   CASE WHEN m.qt_estoque >= 1000 THEN floor(m.qt_estoque * (0.90 + 0.10 * random())::numeric)
                        ELSE floor(m.qt_estoque * (0.90 + 0.10 * random())::numeric * 10) / 10 END AS qtd,
                   CASE WHEN m.qt_estoque > 500 THEN round((8 + random() * 22)::numeric, 1)
                        ELSE round((random() * 3)::numeric, 1) END AS tara,
                   round(t.venda_base * t.idx * (0.98 + 0.04 * random())::numeric, 2) AS preco
              FROM _mat t JOIN material m ON m.id_material = t.id
             WHERE t.grupo = g.grupo AND COALESCE(m.qt_estoque, 0) >= t.vmin
             ORDER BY t.id) s;

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
    FOR r IN SELECT t.id, t.compra_base, t.venda_base, m.preco_compra AS pc, m.preco_especial AS pe
               FROM _mat t JOIN material m ON m.id_material = t.id ORDER BY t.id LOOP
        UPDATE material SET preco_compra = r.compra_base, preco_especial = round(r.compra_base * 1.05, 2),
                            preco_venda = r.venda_base
         WHERE id_material = r.id;
        IF r.pc <> r.compra_base OR r.pe IS DISTINCT FROM round(r.compra_base * 1.05, 2) THEN
            PERFORM pg_temp.auditar(pg_temp.id('dono'), pg_temp.id('dono'),
                format('Material #%s editado: %s', r.id,
                       pg_temp.mudancas(r.pc, r.compra_base, r.pe, round(r.compra_base * 1.05, 2))),
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
                INSERT INTO _ev (t, kind) VALUES (t, 'venda') RETURNING seq INTO s;
                IF random() < 0.07 THEN
                    INSERT INTO _ev (t, kind, ref) VALUES (t + make_interval(secs => 1800 + floor(random() * 3600)), 'cancela_venda', s);
                END IF;
            END LOOP;
        END IF;

        -- Grava na ordem do relogio.
        FOR ev IN SELECT * FROM _ev ORDER BY t, seq LOOP
            IF ev.kind = 'compra' THEN
                UPDATE _ev SET result = pg_temp.piloto_compra(ev.cli, ev.t) WHERE seq = ev.seq;

            ELSIF ev.kind = 'venda' THEN
                UPDATE _ev SET result = pg_temp.piloto_venda(ev.t) WHERE seq = ev.seq;

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

    -- Manutenção.
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

-- ---------------------------------------------------------------------------
-- Resumo
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    r record; fat numeric; cmv numeric; custo numeric; n_c int; n_cc int; n_v int; n_vc int; n_prazo int; v_prazo numeric;
    kg_c numeric; kg_v numeric; i int := 0;
BEGIN
    SELECT COUNT(*) FILTER (WHERE status = 'ativa'), COUNT(*) FILTER (WHERE status = 'cancelada'),
           SUM(total_valor) FILTER (WHERE status = 'ativa'), SUM(total_peso) FILTER (WHERE status = 'ativa')
      INTO n_c, n_cc, custo, kg_c FROM pesagem;
    SELECT COUNT(*) FILTER (WHERE status = 'concluida'), COUNT(*) FILTER (WHERE status = 'cancelada'),
           SUM(total_valor) FILTER (WHERE status = 'concluida'), SUM(total_peso) FILTER (WHERE status = 'concluida'),
           COUNT(*) FILTER (WHERE status = 'concluida' AND observacoes LIKE 'A prazo%'),
           SUM(total_valor) FILTER (WHERE status = 'concluida' AND observacoes LIKE 'A prazo%')
      INTO n_v, n_vc, fat, kg_v, n_prazo, v_prazo FROM vendas;

    -- CMV: kg vendido de cada material ao custo medio de compra dele no piloto.
    SELECT SUM(v.kg * c.valor / c.kg) INTO cmv
      FROM (SELECT id_material, SUM(peso_material) kg, SUM(peso_material * preco_un) valor
              FROM pesagem_material JOIN pesagem USING (id_pesagem) WHERE status = 'ativa' GROUP BY 1) c
      JOIN (SELECT id_material, SUM(quantidade) kg
              FROM vendas_itens JOIN vendas USING (id_venda) WHERE status = 'concluida' GROUP BY 1) v USING (id_material);

    RAISE NOTICE '================ PILOTO 01/08/2026 a 07/11/2026 ================';
    RAISE NOTICE 'Compras: % ativas + % canceladas | % kg | R$ %', n_c, n_cc, pg_temp.num(kg_c), pg_temp.num(custo);
    RAISE NOTICE 'Vendas:  % concluídas + % canceladas | % kg | % a prazo (R$ %)',
        n_v, n_vc, pg_temp.num(kg_v), n_prazo, pg_temp.num(COALESCE(v_prazo, 0));
    RAISE NOTICE 'Faturamento (vendas):        R$ %', pg_temp.num(fat);
    RAISE NOTICE 'Custo do material vendido:   R$ %', pg_temp.num(cmv);
    RAISE NOTICE 'Margem bruta:                R$ % (%)', pg_temp.num(fat - cmv), replace(round((fat - cmv) / fat * 100, 1)::text, '.', ',') || '%';
    RAISE NOTICE 'Compras por dia útil (seg-sex): mín % / média % / máx %; sábado: média %',
        (SELECT MIN(n) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) BETWEEN 1 AND 5),
        (SELECT round(AVG(n), 1) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) BETWEEN 1 AND 5),
        (SELECT MAX(n) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) BETWEEN 1 AND 5),
        (SELECT round(AVG(n), 1) FROM (SELECT data_pesagem::date d, COUNT(*) n FROM pesagem GROUP BY 1) x WHERE extract(dow FROM d) = 6);
    RAISE NOTICE 'Compras por mês: %',
        (SELECT string_agg(format('%s=%s', to_char(m, 'MM'), n), ' ' ORDER BY m)
           FROM (SELECT date_trunc('month', data_pesagem) m, COUNT(*) n FROM pesagem GROUP BY 1) x);
    RAISE NOTICE '';
    RAISE NOTICE '% % % % %', rpad('Material', 15), lpad('comprado kg', 12), lpad('vendido kg', 12),
        lpad('estoque kg', 11), lpad('compra R$', 12);

    FOR r IN
        SELECT m.nm_material, c.kg AS ckg, COALESCE(v.kg, 0) AS vkg, m.qt_estoque, c.valor
          FROM material m
          JOIN (SELECT id_material, SUM(peso_material) kg, SUM(peso_material * preco_un) valor
                  FROM pesagem_material JOIN pesagem USING (id_pesagem) WHERE status = 'ativa' GROUP BY 1) c USING (id_material)
          LEFT JOIN (SELECT id_material, SUM(quantidade) kg
                       FROM vendas_itens JOIN vendas USING (id_venda) WHERE status = 'concluida' GROUP BY 1) v USING (id_material)
         ORDER BY c.kg DESC
    LOOP
        RAISE NOTICE '% % % % %', rpad(r.nm_material, 15), lpad(pg_temp.num(r.ckg), 12), lpad(pg_temp.num(r.vkg), 12),
            lpad(pg_temp.num(r.qt_estoque), 11), lpad(pg_temp.num(r.valor), 12);
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
    RAISE NOTICE 'Conferido: saldo = trilha, saldo_apos cronológico, estoque nunca negativo.';
END $$;

COMMIT;
