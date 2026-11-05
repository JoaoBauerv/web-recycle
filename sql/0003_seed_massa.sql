--
-- 0003_seed_massa.sql
--
-- Massa GRANDE de dados ficticios: dois anos de operacao simulada, dia a dia,
-- para testar relatorios, filtros, paginacao, graficos e desempenho com volume.
--
-- OPCIONAL e ALTERNATIVA a 0002_seed_demo.sql: rode uma OU a outra, nunca as
-- duas. A 0002 e pequena e serve para apresentar o sistema; esta e para
-- carregar o banco. Vem depois das migrations e de 0001_seed_inicial.sql:
--
--   psql -d reciclagem -v ON_ERROR_STOP=1 -f sql/0003_seed_massa.sql
--
-- Leva cerca de um a dois minutos.
--
-- O QUE A SIMULACAO FAZ
--   Percorre os ultimos 730 dias, um a um, em ordem cronologica:
--     - compras de segunda a sabado (sabado so de manha), com sazonalidade
--       mensal (dezembro forte, fevereiro fraco) e crescimento de ~50% ao longo
--       dos dois anos; cada cliente tem peso proprio (cooperativas e
--       sucatarias trazem mais, e mais vezes) e data de entrada e saida;
--     - vendas as tercas e sextas, ao meio-dia: cada grupo de material
--       (papel, plastico, metal, cobre, sucata) e vendido quando o estoque
--       passa do lote minimo, para um comprador daquele grupo; parte das
--       vendas de papel chega por importacao de planilha;
--     - reajuste de precos a cada 91 dias (-3% a +5%), com auditoria;
--     - inventario no ultimo dia de cada mes: perda em dois materiais e, a
--       cada trimestre, uma sobra de contagem;
--     - cancelamentos: ~1,5% das compras e ~2,5% das vendas.
--   Depois, o financeiro dos 24 meses: salarios, energia, agua, telefone,
--   combustivel, impostos (3% das vendas do mes anterior), manutencao,
--   contratos anuais lancados em 12x (aluguel, internet, contabilidade),
--   equipamentos parcelados, seguro, parcelas pagas no prazo ou com atraso e
--   juros, algumas vencidas em aberto, cancelamentos de parcela e de despesa.
--
-- CONSISTENCIA
--   Usa as mesmas funcoes da 0002 (seed_funcoes.sql), que aplicam as regras
--   do PHP. Os eventos de cada dia sao gravados na ordem do relogio: compras da
--   manha (07h30-11h50), vendas (12h), compras da tarde (13h-17h), inventario
--   (17h40). No fim, o arquivo confere que o saldo de cada material bate com a
--   trilha E que saldo_apos de cada movimentacao e a soma acumulada em ordem
--   cronologica -- o extrato do estoque conta a historia certa.
--
-- REPRODUTIVEL
--   setseed() fixa o sorteio: rodar de novo gera a mesma massa (as datas sao
--   relativas a CURRENT_DATE, entao "a mesma massa" deslocada para hoje).
--
-- PRECOS
--   O catalogo da 0001 representa os precos de hoje. A simulacao comeca com
--   eles 14% mais baixos e os reajustes trimestrais os trazem de volta para
--   perto do catalogo, de modo que a margem varia ao longo do tempo.
--
-- ACESSO (todos com a senha admin@123, sem troca obrigatoria)
--   fernanda.oliveira@example.com  Admin   (gerente)
--   carlos.ramos@example.com       Usuario (operador desde o inicio)
--   juliana.prado@example.com      Usuario (operadora ha ~13 meses)
--   marcos.vieira@example.com      Usuario (operador ha ~7 meses)
--   ricardo.alves@example.com      Usuario (INATIVO; saiu ha ~14 meses)
--
-- Dados ficticios: nomes inventados, CPF/CNPJ gerados com digito verificador
-- valido a partir de bases sem relacao com pessoas reais, e-mails em
-- example.com. So roda em banco sem cliente, compra ou venda.
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
        RAISE EXCEPTION 'o banco ja tem clientes, compras ou vendas; a massa so entra em banco recem-instalado (e nao junto da 0002)';
    END IF;
END $$;

\ir seed_funcoes.sql

SELECT setseed(0.2026);

-- ---------------------------------------------------------------------------
-- Tabelas de trabalho (somem no COMMIT)
-- ---------------------------------------------------------------------------

-- Perfil de cada material na simulacao: frequencia relativa nas compras, faixa
-- de peso por entrega, grupo de comprador, lote minimo para vender e markup
-- sobre o preco de compra (20% a 60%, tirado do catalogo).
CREATE TEMP TABLE _mat (
    id int, nome text, freq numeric, kmin numeric, kmax numeric,
    grupo text, lote numeric, markup numeric
) ON COMMIT DROP;

INSERT INTO _mat (nome, freq, kmin, kmax, grupo, lote)
VALUES ('Papelão',            30,  40,  900, 'papel',    2000),
       ('Papel Branco',        8,  15,  250, 'papel',     600),
       ('Misto',               6,  20,  350, 'papel',    1000),
       ('Pet Branca',         12,   8,  220, 'plastico',  400),
       ('Pet Verde',           6,   5,  120, 'plastico',  250),
       ('Cristal',             5,   5,  150, 'plastico',  300),
       ('Leitoso',             4,   5,  120, 'plastico',  250),
       ('Colorido Pead',       4,   5,  120, 'plastico',  250),
       ('Balde Branco',        2,   5,   60, 'plastico',  150),
       ('Balde Colorido',      2,   5,   60, 'plastico',  150),
       ('Caixaria',            3,  10,  140, 'plastico',  250),
       ('Pvc',                 1,   5,   80, 'plastico',  200),
       ('Lata',               12,   2,   45, 'metal',     120),
       ('Perfil',              4,   3,   90, 'metal',     120),
       ('Panela',              4,   2,   40, 'metal',      80),
       ('Chaparia',            3,   3,   70, 'metal',     100),
       ('Duro',                2,   3,   60, 'metal',      80),
       ('Perciana',            1,   2,   40, 'metal',      60),
       ('Esponja',             1,   2,   25, 'metal',      50),
       ('Spray Com Ferro',     1,   3,   40, 'metal',      60),
       ('Roda',                1,   8,   60, 'metal',      60),
       ('Cobre Misto',         3, 0.5,   25, 'cobre',      30),
       ('Cobre Vermelho',      2, 0.5,   18, 'cobre',      20),
       ('Cobre 4a',            1, 0.5,   15, 'cobre',      15),
       ('Fio Capa',            3,   2,   50, 'cobre',      60),
       ('Metal',               2,   1,   30, 'cobre',      25),
       ('Motor',               2,  10,  120, 'cobre',     150),
       ('Radiador Com Cobre',  1,   5,   45, 'cobre',      40),
       ('Sucata',             14,  80, 2200, 'sucata',   4000),
       ('Bateria',             3,  10,  180, 'sucata',    300),
       ('Chumbo',              1,   3,   60, 'sucata',     60),
       ('Inox Panela',         1,   3,   50, 'sucata',     80);

-- Markup tirado do proprio catalogo (preco de venda / preco de compra),
-- limitado a uma faixa plausivel; sem preco de venda, 30%.
UPDATE _mat t
   SET id = m.id_material,
       markup = LEAST(GREATEST(COALESCE(m.preco_venda / NULLIF(m.preco_compra, 0), 1.30), 1.20), 1.60)
  FROM material m
 WHERE m.nm_material = t.nome;

-- Clientes na simulacao: peso (frequencia), dias atras em que entrou e saiu
-- (0 = continua ativo) e multiplicador de volume por entrega.
CREATE TEMP TABLE _cli (id int, peso numeric, ini int, fim int, mult numeric) ON COMMIT DROP;

-- Operadores de balanca e o periodo em que trabalharam.
CREATE TEMP TABLE _ops (id int, ini int, fim int) ON COMMIT DROP;

-- Compradores por grupo de material.
CREATE TEMP TABLE _forn (id int, grupo text, importa boolean) ON COMMIT DROP;

-- Identificadores e contadores usados pelas funcoes abaixo.
CREATE TEMP TABLE _ids (chave text PRIMARY KEY, valor int) ON COMMIT DROP;

-- ---------------------------------------------------------------------------
-- Funcoes da simulacao
-- ---------------------------------------------------------------------------
CREATE FUNCTION pg_temp.id(p_chave text) RETURNS int
LANGUAGE sql AS $f$ SELECT valor FROM _ids WHERE chave = p_chave $f$;

CREATE FUNCTION pg_temp.proximo(p_chave text) RETURNS int
LANGUAGE sql AS $f$ UPDATE _ids SET valor = valor + 1 WHERE chave = p_chave RETURNING valor $f$;

-- Momento d dias atras, m minutos depois da meia-noite.
CREATE FUNCTION pg_temp.quando(p_dias int, p_min int) RETURNS timestamp
LANGUAGE sql AS $f$ SELECT (CURRENT_DATE - p_dias) + make_interval(mins => p_min) $f$;

-- Sorteio ponderado (Efraimidis-Spirakis): ordenar por random()::numeric^(1/peso)
-- e pegar o primeiro escolhe cada linha com probabilidade proporcional ao peso.
CREATE FUNCTION pg_temp.sorteia_cliente(p_dias int) RETURNS int
LANGUAGE sql AS $f$
    SELECT id FROM _cli WHERE p_dias <= ini AND p_dias >= fim
     ORDER BY power(random()::numeric, 1.0 / peso) DESC LIMIT 1
$f$;

CREATE FUNCTION pg_temp.sorteia_operador(p_dias int) RETURNS int
LANGUAGE sql AS $f$
    SELECT id FROM _ops WHERE p_dias <= ini AND p_dias >= fim ORDER BY random()::numeric LIMIT 1
$f$;

-- Mesma conta de financeiroDividirParcelas(): a sobra de centavos vai para a 1a.
CREATE FUNCTION pg_temp.dividir(p_total numeric, p_n int) RETURNS numeric[]
LANGUAGE plpgsql AS $f$
DECLARE c bigint := round(p_total * 100); base bigint := c / p_n; r numeric[];
BEGIN
    r := array_fill((base / 100.0)::numeric, ARRAY[p_n]);
    r[1] := ((base + (c - base * p_n)) / 100.0)::numeric;
    RETURN r;
END $f$;

-- Parcelas no formato de pg_temp.conta(), com o pagamento decidido pela data:
-- vencida ha mais de 25 dias -> paga (8% com atraso de 3 a 15 dias e juros);
-- vencida nos ultimos 25 dias -> 15% ficam em aberto; futura -> pendente.
-- 1% das antigas tambem fica em aberto (inadimplencia esquecida).
CREATE FUNCTION pg_temp.parcelas(p_valores numeric[], p_primeiro date, p_forma text) RETURNS jsonb
LANGUAGE plpgsql AS $f$
DECLARE
    r jsonb := '[]'; i int; venc date; dv int; obj jsonb; atraso int;
BEGIN
    FOR i IN 1..array_length(p_valores, 1) LOOP
        venc := (p_primeiro + ((i - 1) * interval '1 month'))::date;
        dv   := venc - CURRENT_DATE;
        obj  := jsonb_build_object('v', p_valores[i], 'venc', dv, 'f', p_forma);

        IF venc < CURRENT_DATE
           AND NOT (venc >= CURRENT_DATE - 25 AND random()::numeric < 0.15)
           AND random()::numeric >= 0.01 THEN
            IF random()::numeric < 0.08 THEN
                atraso := 3 + floor(random()::numeric * 13)::int;
                obj := obj || jsonb_build_object('pg', LEAST(dv + atraso, 0),
                                                 'vp', round(p_valores[i] * (1.02 + 0.00033 * atraso), 2));
            ELSE
                obj := obj || jsonb_build_object('pg', LEAST(dv - floor(random()::numeric * 4)::int, 0));
            END IF;
        END IF;

        r := r || obj;
    END LOOP;
    RETURN r;
END $f$;

-- Compras de uma janela do dia, espalhadas em ordem crescente de horario.
CREATE FUNCTION pg_temp.massa_compras(p_dias int, p_n int, p_ini int, p_fim int) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE
    k int; slot numeric; t timestamp; cli int; op int; nitens int; mult numeric; itens jsonb; v_id int;
    motivos text[] := ARRAY['Peso lançado em duplicidade', 'Cliente desistiu da venda',
                            'Material contaminado, devolvido ao cliente', 'Lançado no cliente errado'];
BEGIN
    IF p_n <= 0 THEN RETURN; END IF;
    slot := (p_fim - p_ini)::numeric / p_n;

    FOR k IN 0..p_n - 1 LOOP
        t      := pg_temp.quando(p_dias, (p_ini + k * slot + floor(random()::numeric * slot * 0.5))::int);
        cli    := pg_temp.sorteia_cliente(p_dias);
        op     := pg_temp.sorteia_operador(p_dias);
        SELECT c.mult INTO mult FROM _cli c WHERE c.id = cli;
        nitens := 1 + floor(power(random()::numeric, 1.6) * 4)::int;

        SELECT jsonb_agg(jsonb_build_object('m', nome, 'b', b,
                   't', CASE WHEN b > 50 THEN round(b * (0.005 + 0.015 * random()::numeric), 1)
                             ELSE round(random()::numeric * LEAST(0.4, b / 4), 1) END))
          INTO itens
          FROM (SELECT nome, round((kmin + (kmax - kmin) * power(random()::numeric, 2.2)) * mult, 1) AS b
                  FROM _mat ORDER BY power(random()::numeric, 1.0 / freq) DESC LIMIT nitens) s;

        v_id := pg_temp.compra(cli, t, op, itens);

        IF random()::numeric < 0.015 THEN
            PERFORM pg_temp.cancelar_compra(v_id, pg_temp.id('admin'), t + interval '2 minutes',
                                            motivos[1 + floor(random()::numeric * 4)::int]);
        END IF;
    END LOOP;
END $f$;

-- Vendas do dia: cada grupo com material acima do lote vai para um comprador.
CREATE FUNCTION pg_temp.massa_vendas(p_dias int) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE
    g text; k int := 0; itens jsonb; forn record; t timestamp; v_id int; imp int; n int; pedido text;
    motivos text[] := ARRAY['Comprador recusou a carga na balança dele', 'Umidade acima do contratado',
                            'Venda lançada em duplicidade', 'Carga não saiu: caminhão quebrado'];
BEGIN
    FOR g IN SELECT DISTINCT grupo FROM _mat ORDER BY grupo LOOP
        SELECT jsonb_agg(jsonb_build_object('m', nome, 'b', qtd + tara, 't', tara, 'p', preco)), count(*)
          INTO itens, n
          FROM (SELECT t.nome,
                       floor(COALESCE(m.qt_estoque, 0) * (0.85 + 0.15 * random()::numeric) * 10) / 10 AS qtd,
                       CASE WHEN m.qt_estoque > 500 THEN round(5 + random()::numeric * 20, 1) ELSE round(random()::numeric * 3, 1) END AS tara,
                       round(m.preco_compra * t.markup * (0.95 + 0.10 * random()::numeric), 2) AS preco
                  FROM _mat t JOIN material m ON m.id_material = t.id
                 WHERE t.grupo = g AND COALESCE(m.qt_estoque, 0) >= t.lote) s;

        CONTINUE WHEN itens IS NULL;

        SELECT * INTO forn FROM _forn WHERE grupo = g ORDER BY random()::numeric LIMIT 1;
        t := pg_temp.quando(p_dias, 720 + k * 3);
        k := k + 1;

        IF forn.importa AND random()::numeric < 0.4 THEN
            -- Pedido que chegou por planilha: histórico da importação + venda.
            pedido := 'RS-' || lpad(pg_temp.proximo('pedido')::text, 5, '0');
            INSERT INTO importacao_historico (arquivo, id_modelo, id_usuario, pedido_externo, total_registros,
                                              registros_importados, status, erros, data_importacao)
            VALUES (lower(pedido) || '.xlsx', pg_temp.id('modelo'), pg_temp.id('admin'), pedido, n, n,
                    'concluido', NULL, t)
            RETURNING id_importacao INTO imp;
            v_id := pg_temp.venda(forn.id, t, pg_temp.id('admin'), itens, 'importacao', pedido, imp);
            UPDATE importacao_historico SET valor_total = (SELECT total_valor FROM vendas WHERE id_venda = v_id)
             WHERE id_importacao = imp;
        ELSE
            v_id := pg_temp.venda(forn.id, t, pg_temp.sorteia_operador(p_dias), itens);
        END IF;

        IF random()::numeric < 0.025 THEN
            PERFORM pg_temp.cancelar_venda(v_id, pg_temp.id('admin'), t + interval '1 minute',
                                           motivos[1 + floor(random()::numeric * 4)::int]);
        END IF;
    END LOOP;
END $f$;

-- Reajuste trimestral de precos, com a mesma auditoria da tela de materiais.
CREATE FUNCTION pg_temp.massa_reajuste(p_dias int) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE r record; fat numeric; novo numeric;
BEGIN
    FOR r IN SELECT m.id_material, m.preco_compra, m.preco_especial
               FROM material m JOIN _mat t ON t.id = m.id_material ORDER BY m.id_material LOOP
        fat  := 0.97 + 0.08 * random()::numeric;
        novo := round(r.preco_compra * fat, 2);
        UPDATE material SET preco_compra = novo, preco_especial = round(preco_especial * fat, 2)
         WHERE id_material = r.id_material;
        PERFORM pg_temp.auditar(pg_temp.id('admin'), pg_temp.id('admin'),
            format('Material #%s editado: compra R$ %s -> %s', r.id_material,
                   pg_temp.num(r.preco_compra), pg_temp.num(novo)),
            'Material editado', pg_temp.quando(p_dias, 420));
    END LOOP;
END $f$;

-- Inventario de fim de mes: perda em dois materiais e, a cada trimestre, sobra.
CREATE FUNCTION pg_temp.massa_inventario(p_dias int) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE r record; motivos text[] := ARRAY['Umidade: fardos molhados no pátio', 'Quebra e contaminação na triagem',
                                           'Diferença na contagem do inventário', 'Furto identificado no fechamento'];
BEGIN
    FOR r IN SELECT m.id_material, m.qt_estoque FROM material m JOIN _mat t ON t.id = m.id_material
              WHERE COALESCE(m.qt_estoque, 0) > 200 ORDER BY random()::numeric LIMIT 2 LOOP
        PERFORM pg_temp.mov(r.id_material, 'perda', round(r.qt_estoque * (0.003 + 0.012 * random()::numeric), 1),
                            'ajuste_manual', NULL, pg_temp.id('admin'),
                            motivos[1 + floor(random()::numeric * 4)::int], pg_temp.quando(p_dias, 1060));
    END LOOP;

    IF extract(month FROM CURRENT_DATE - p_dias)::int % 3 = 0 THEN
        PERFORM pg_temp.mov(t.id, 'entrada', round(2 + random()::numeric * 18, 1), 'ajuste_manual', NULL,
                            pg_temp.id('admin'), 'Sobra na contagem do inventário trimestral',
                            pg_temp.quando(p_dias, 1065))
           FROM (SELECT id FROM _mat ORDER BY random()::numeric LIMIT 1) t;
    END IF;
END $f$;

-- ---------------------------------------------------------------------------
-- Cadastros
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    v_hash constant text := '$2y$10$4Nkcd86aeSuaCAYQK8T5K.8Sa5UwUwqY0An88R/w2yGhij8FbdX.O';  -- admin@123
    u_admin int; u int; i int; v_id int; ini int; fim int; tipo text; nome text; doc text; especial boolean;
    peso numeric; mult numeric; ende int;

    primeiros text[] := ARRAY['José','Maria','Antônio','Francisco','Ana','Paulo','Luiz','Marcos','Rita','Sebastião',
                              'Raimunda','João','Pedro','Luciana','Jorge','Sandra','Edson','Cláudia','Valdir','Rosângela',
                              'Adilson','Neusa','Gilberto','Terezinha','Roberto','Marlene','Osmar','Vera','Nilton','Dirce'];
    sobrenomes text[] := ARRAY['da Silva','dos Santos','Pereira','Oliveira','Souza','Rodrigues','Ferreira','Alves',
                               'Lima','Gomes','Ribeiro','Martins','Carvalho','Rocha','Barbosa','Moraes','Teixeira',
                               'Cardoso','Nunes','Machado'];
    cooperativas text[] := ARRAY['Cooperativa Recicla Vida','Associação de Catadores Nova Esperança',
                                 'Cooperativa Mãos que Reciclam','Cooperativa Verde Vale',
                                 'Associação Recicladores do Norte da Ilha','Cooperativa Coopercicla'];
    sucatarias text[] := ARRAY['Sucataria Dois Irmãos Ltda','Ferro Velho do Zé','Sucatas Biguaçu Ltda',
                               'Comércio de Metais Palhoça Ltda','Reciclados Kobrasol ME','Sucataria Ponte do Imaruim',
                               'Ferro e Metais São José Ltda','Desmanche Rota 101 Ltda'];
    cidades text[] := ARRAY['Florianópolis','São José','Palhoça','Biguaçu','Santo Amaro da Imperatriz'];
    bairros text[] := ARRAY['Centro','Capoeiras','Kobrasol','Barreiros','Ponte do Imaruim','Pagani','Serraria',
                            'Forquilhinha','Areias','Ingleses','Campeche','Estreito','Bela Vista','Fazenda Santo Antônio'];
    ruas text[] := ARRAY['Rua das Acácias','Rua João Pessoa','Servidão dos Pinheiros','Rua Valdir Tomaz','Rua São Pedro',
                         'Avenida Barão do Rio Branco','Rua Joaquim Nabuco','Rua Hermínio Silva','Rua Getúlio Vargas',
                         'Rua Koesa','Rua Antônio Jovita Duarte','Rodovia SC-407'];
BEGIN
    SELECT id_usuario INTO u_admin FROM usuario WHERE permissao = 'Admin' AND status = 1 ORDER BY id_usuario LIMIT 1;
    INSERT INTO _ids VALUES ('admin_inicial', u_admin), ('pedido', 0);

    -- Usuários: (nome, e-mail, login, perfil, entrou há N dias, saiu há N dias)
    FOR nome, doc, tipo, especial, ini, fim IN
        SELECT * FROM (VALUES
            ('Fernanda Oliveira', 'fernanda.oliveira@example.com', 'fernanda.oliveira', true,  760,   0),
            ('Carlos Eduardo Ramos', 'carlos.ramos@example.com',   'carlos.ramos',      false, 745,   0),
            ('Ricardo Alves', 'ricardo.alves@example.com',         'ricardo.alves',     false, 745, 420),
            ('Juliana Prado', 'juliana.prado@example.com',         'juliana.prado',     false, 400,   0),
            ('Marcos Vieira', 'marcos.vieira@example.com',         'marcos.vieira',     false, 210,   0)) v
    LOOP
        INSERT INTO usuario (nome, email, senha, usuario, foto, status, permissao, precisa_alterar_senha,
                             data_nascimento, celular, cpf_cnpj)
        VALUES (nome, doc, v_hash, tipo, '', CASE WHEN fim > 0 THEN 0 ELSE 1 END,
                CASE WHEN especial THEN 'Admin' ELSE 'Usuario' END, 0,
                DATE '1980-01-01' + (abs(hashtext(doc)) % 6000), '4899' || lpad((abs(hashtext(doc)) % 10000000)::text, 7, '0'),
                pg_temp.cpf((abs(hashtext(doc)) % 900000000 + 100000000)::text))
        RETURNING id_usuario INTO u;

        PERFORM pg_temp.auditar(u_admin, u, 'Cadastro do usuário ' || nome, 'Cadastro Usuario', pg_temp.quando(ini, 480));
        IF fim > 0 THEN
            PERFORM pg_temp.auditar(u_admin, u, 'Usuário ' || nome || ' inativado', 'Usuario editado', pg_temp.quando(fim, 1080));
        END IF;

        IF especial THEN
            INSERT INTO _ids VALUES ('admin', u);
        ELSE
            INSERT INTO _ops VALUES (u, ini - 15, fim);
            INSERT INTO _ids VALUES ('func_' || tipo, u);
        END IF;
    END LOOP;

    -- Clientes: 6 cooperativas, 8 sucatarias, 46 catadores.
    FOR i IN 1..60 LOOP
        IF i <= 6 THEN
            tipo := 'cooperativa'; nome := cooperativas[i]; especial := true;
            doc := pg_temp.cnpj(lpad((i * 7919 % 90000000 + 10000000)::text, 8, '0') || '0001');
            peso := 5 + random()::numeric * 3; mult := 2.5;
        ELSIF i <= 14 THEN
            tipo := 'sucataria'; nome := sucatarias[i - 6]; especial := (i % 2 = 0);
            doc := pg_temp.cnpj(lpad((i * 7919 % 90000000 + 10000000)::text, 8, '0') || '0001');
            peso := 3 + random()::numeric * 2; mult := 1.8;
        ELSE
            tipo := 'catador';
            nome := primeiros[1 + (i * 7) % 30] || ' ' || sobrenomes[1 + (i * 11) % 20];
            especial := (i % 15 = 0);
            doc := pg_temp.cpf(lpad((i * 104729 % 900000000 + 100000000)::text, 9, '0'));
            peso := 0.4 + random()::numeric * 2.2; mult := 1.0;
        END IF;

        -- Os 25 primeiros estão desde o começo; os outros entram ao longo dos dois anos.
        ini := CASE WHEN i <= 25 THEN 735 ELSE 60 + floor(random()::numeric * 640)::int END;
        -- Cinco catadores param de vir e são inativados.
        fim := CASE WHEN i IN (17, 23, 31, 44, 52) THEN GREATEST(30, ini - 120 - floor(random()::numeric * 300)::int) ELSE 0 END;

        ende := NULL;
        IF tipo <> 'catador' OR random()::numeric < 0.6 THEN
            ende := pg_temp.endereco('88' || lpad((i * 3571 % 200000)::text, 6, '0'),
                                     ruas[1 + (i * 5) % 12], (10 + i * 37 % 1900)::text,
                                     bairros[1 + (i * 3) % 14], cidades[1 + i % 5], 'SC');
        END IF;

        INSERT INTO clientes (nome, cpf_cnpj, telefone, celular, email, preco_especial, status, data_cadastro, id_endereco)
        VALUES (nome, doc,
                CASE WHEN tipo <> 'catador' THEN '483' || lpad((i * 4421 % 10000000)::text, 7, '0') END,
                '4899' || lpad((i * 7907 % 10000000)::text, 7, '0'),
                CASE WHEN tipo <> 'catador' THEN 'contato' || i || '@example.com' END,
                especial, CASE WHEN fim > 0 THEN 0 ELSE 1 END, pg_temp.quando(ini, 500 + i), ende)
        RETURNING id_cliente INTO v_id;

        INSERT INTO _cli VALUES (v_id, peso, ini, fim, mult);
        PERFORM pg_temp.auditar(u_admin, u_admin, format('Cliente cadastrado: %s (#%s)', nome, v_id),
                                'Cadastro Cliente', pg_temp.quando(ini, 500 + i));
        IF fim > 0 THEN
            PERFORM pg_temp.auditar(pg_temp.id('admin'), pg_temp.id('admin'), format('Cliente excluído (#%s)', v_id),
                                    'Exclusão Cliente', pg_temp.quando(fim, 1090));
        END IF;
    END LOOP;

    -- Compradores (fornecedores, na convenção do sistema), por grupo.
    FOR nome, tipo, especial, i IN
        SELECT * FROM (VALUES
            ('Reciclagem Sul Indústria de Papéis Ltda', 'papel',    true,  1),
            ('Papelera Vale do Itajaí S.A.',            'papel',    false, 2),
            ('PlastiNova Indústria de Plásticos S.A.',  'plastico', false, 3),
            ('Polimix Reciclagem de Polímeros Ltda',    'plastico', false, 4),
            ('Metalúrgica Alumifort Ltda',              'metal',    false, 5),
            ('Fundição Metalnorte Ltda',                'metal',    false, 6),
            ('Cobrefio Fundição e Comércio Ltda',       'cobre',    false, 7),
            ('Siderúrgica Planalto S.A.',               'sucata',   false, 8),
            ('Acumuladores Energia Limpa Ltda',         'sucata',   false, 9)) v
    LOOP
        INSERT INTO fornecedores (nome_razao_social, cpf_cnpj, telefone, email, status, data_cadastro, observacoes, id_endereco)
        VALUES (nome, pg_temp.cnpj(lpad((i * 15485863 % 90000000 + 10000000)::text, 8, '0') || '0001'),
                '473' || lpad((i * 9973 % 10000000)::text, 7, '0'), 'compras' || i || '@example.com', 1,
                pg_temp.quando(740, 540 + i), 'Compra ' || tipo || '.',
                pg_temp.endereco('89' || lpad((i * 7717 % 200000)::text, 6, '0'), 'Rua Industrial', (100 * i)::text,
                                 'Distrito Industrial', (ARRAY['Timbó','Itajaí','Joinville','Blumenau','Jaraguá do Sul'])[1 + i % 5], 'SC'))
        RETURNING id_fornecedor INTO v_id;

        INSERT INTO _forn VALUES (v_id, tipo, especial);
        PERFORM pg_temp.auditar(u_admin, u_admin, 'Fornecedor ' || nome || ' cadastrado', 'Cadastro Fornecedor',
                                pg_temp.quando(740, 540 + i));
        IF especial THEN
            INSERT INTO importacao_modelo (nome, id_fornecedor, linha_cabecalho, mapeamento, data_cadastro)
            VALUES ('Pedido Reciclagem Sul', v_id, 1,
                    '{"codigo": 0, "material": 1, "peso_bruto": 2, "tara": 3, "preco_un": 4, "pedido": 5, "data": 6}',
                    pg_temp.quando(700, 600))
            RETURNING id_modelo INTO u;
            INSERT INTO _ids VALUES ('modelo', u);

            INSERT INTO material_relacao (id_material, nome_externo, nome_normalizado, codigo_externo, codigo_normalizado,
                                          id_modelo, origem, data_cadastro)
            VALUES (pg_temp.mat('Papelão'),      'PAPELAO ONDULADO', 'papelao ondulado', 'PA-001', 'pa001', u, 'importacao', pg_temp.quando(700, 610)),
                   (pg_temp.mat('Papel Branco'), 'APARA BRANCA',     'apara branca',     'PA-014', 'pa014', u, 'importacao', pg_temp.quando(700, 611)),
                   (pg_temp.mat('Misto'),        'APARA MISTA',      'apara mista',      'PA-020', 'pa020', u, 'importacao', pg_temp.quando(700, 612));
        END IF;
    END LOOP;

    -- Preços de dois anos atrás: 14% abaixo do catálogo de hoje.
    UPDATE material SET preco_compra = round(preco_compra * 0.86, 2), preco_especial = round(preco_especial * 0.86, 2)
     WHERE id_material IN (SELECT id FROM _mat);

    UPDATE material SET estoque_minimo = v.minimo
      FROM (VALUES ('Papelão', 1500), ('Pet Branca', 200), ('Lata', 60), ('Cobre Misto', 20), ('Sucata', 1500)) v(nome, minimo)
     WHERE nm_material = v.nome;
END $$;

-- ---------------------------------------------------------------------------
-- Dois anos de operação, dia a dia
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    d int; dia date; dow int; base numeric; n int;
    -- sazonalidade de jan a dez
    sazonal numeric[] := ARRAY[0.85, 0.80, 0.95, 1.00, 1.00, 0.95, 1.00, 1.05, 1.05, 1.10, 1.10, 1.20];
BEGIN
    FOR d IN REVERSE 730..1 LOOP
        dia := CURRENT_DATE - d;
        dow := extract(dow FROM dia)::int;

        IF d % 91 = 0 THEN
            PERFORM pg_temp.massa_reajuste(d);
        END IF;

        IF dow <> 0 THEN
            -- 11 compras/dia na média, crescendo de 0,8x a 1,2x ao longo do período
            base := 11 * sazonal[extract(month FROM dia)::int] * (0.8 + 0.4 * (730 - d) / 730.0);

            n := LEAST(20, round(base * 0.55 * (0.7 + 0.6 * random()::numeric) * CASE WHEN dow = 6 THEN 0.9 ELSE 1 END)::int);
            PERFORM pg_temp.massa_compras(d, n, 450, 710);          -- 07h30-11h50

            IF dow IN (2, 5) THEN
                PERFORM pg_temp.massa_vendas(d);                    -- 12h
            END IF;

            IF dow <> 6 THEN
                n := LEAST(20, round(base * 0.45 * (0.7 + 0.6 * random()::numeric))::int);
                PERFORM pg_temp.massa_compras(d, n, 780, 1020);     -- 13h-17h
            END IF;
        END IF;

        IF extract(day FROM dia + 1) = 1 THEN
            PERFORM pg_temp.massa_inventario(d);                    -- 17h40
        END IF;
    END LOOP;

    -- Duas importações que não viraram venda: uma com erro de leitura e uma
    -- planilha só com materiais desconhecidos.
    INSERT INTO importacao_historico (arquivo, id_modelo, id_usuario, total_registros, registros_importados,
                                      valor_total, status, erros, data_importacao)
    VALUES ('pedido_plastinova_marco.xlsx', NULL, pg_temp.id('admin'), 3, 0, 0, 'erro',
            '[{"linha": 2, "material": "PP-REC", "motivo": "Material não reconhecido no catálogo"}, {"linha": 3, "material": "PEAD SOPRO", "motivo": "Material não reconhecido no catálogo"}, {"linha": 4, "material": "", "motivo": "Quantidade em branco"}]',
            pg_temp.quando(240, 600)),
           ('pedido_reciclagem_sul_ajuste.xlsx', pg_temp.id('modelo'), pg_temp.id('admin'), 2, 0, 0, 'erro',
            '[{"linha": 2, "material": "PAPELAO MISTO", "motivo": "Material não reconhecido no catálogo"}, {"linha": 3, "material": "APARA KRAFT", "motivo": "Material não reconhecido no catálogo"}]',
            pg_temp.quando(45, 610));

    -- preco_venda = preço da última venda não cancelada, como vendaAtualizarUltimoPreco()
    UPDATE material m
       SET preco_venda = u.preco_un
      FROM (SELECT DISTINCT ON (vi.id_material) vi.id_material, vi.preco_un
              FROM vendas_itens vi JOIN vendas v USING (id_venda)
             WHERE v.status <> 'cancelada'
             ORDER BY vi.id_material, v.data_venda DESC, v.id_venda DESC, vi.id_venda_item DESC) u
     WHERE m.id_material = u.id_material;
END $$;

-- ---------------------------------------------------------------------------
-- Financeiro: 24 meses de despesas
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    adm int := pg_temp.id('admin');
    mm int; mes date; ano int; emis int; k int; n int; v numeric; venc date;
    func record; vendas_mes numeric;
    -- energia sobe no verão (dez-mar) por causa da refrigeração do escritório
    energia numeric[] := ARRAY[1.25, 1.25, 1.15, 1.0, 0.9, 0.85, 0.85, 0.85, 0.9, 1.0, 1.1, 1.2];
    oficinas text[] := ARRAY['HidroPress Serviços', 'Balanças Precisão Ltda', 'Elétrica Ilha Forte', 'Mecânica Rota 101'];
    servicos text[] := ARRAY['Troca de vedações da prensa enfardadeira', 'Aferição e conserto da balança rodoviária',
                             'Revisão do quadro elétrico do galpão', 'Revisão do caminhão'];
BEGIN
    FOR mm IN 0..24 LOOP
        mes  := (date_trunc('month', CURRENT_DATE) - interval '24 months' + mm * interval '1 month')::date;
        ano  := mm / 12;
        emis := mes - CURRENT_DATE;

        -- Contratos anuais, lançados no começo de cada ano como 12 parcelas iguais.
        IF mm % 12 = 0 THEN
            v := round(3200 * power(1.06, ano), 2);
            PERFORM pg_temp.conta(adm, format('Aluguel do galpão — %s/%s', extract(year FROM mes), extract(year FROM mes) + 1),
                'Aluguel', 'Administrativo', 'Imobiliária Horizonte', NULL, emis,
                pg_temp.parcelas(array_fill(v, ARRAY[12]), mes + 9, 'transferencia'),
                format('Contrato anual: 12x de R$ %s.', pg_temp.num(v)));
            PERFORM pg_temp.conta(adm, format('Internet fibra — %s/%s', extract(year FROM mes), extract(year FROM mes) + 1),
                'Internet', 'Administrativo', 'Provedor NetSul', NULL, emis,
                pg_temp.parcelas(array_fill(149.90 + 10 * ano, ARRAY[12]), mes + 14, 'debito_auto'));
            PERFORM pg_temp.conta(adm, format('Honorários contábeis — %s/%s', extract(year FROM mes), extract(year FROM mes) + 1),
                'Contabilidade', 'Administrativo', 'Escritório Contábil Exato', NULL, emis,
                pg_temp.parcelas(array_fill(round(750 * power(1.05, ano), 2), ARRAY[12]), mes + 9, 'boleto'));
            PERFORM pg_temp.conta(adm, 'Seguro do caminhão (anual)', 'Seguros', 'Logística', 'Seguradora Confiança', NULL, emis,
                pg_temp.parcelas(pg_temp.dividir(2380 + 150 * ano, 4), mes + 19, 'boleto'));
        END IF;

        -- Mensais (até o mês atual).
        CONTINUE WHEN mes > CURRENT_DATE;

        FOR func IN SELECT o.id, u.nome, o.ini, o.fim FROM _ops o JOIN usuario u ON u.id_usuario = o.id LOOP
            CONTINUE WHEN (mes - CURRENT_DATE) < -func.ini OR (func.fim > 0 AND (mes - CURRENT_DATE) > -func.fim);
            PERFORM pg_temp.conta(adm, 'Salário ' || func.nome || ' — ' || to_char(mes, 'MM/YYYY'),
                'Funcionários', 'Recursos Humanos', func.nome, func.id, emis,
                pg_temp.parcelas(ARRAY[round((2150 + (func.id % 4) * 120) * power(1.05, ano), 2)], mes + 4, 'pix'));
        END LOOP;

        PERFORM pg_temp.conta(adm, 'Energia elétrica — ' || to_char(mes, 'MM/YYYY'), 'Energia elétrica', 'Operacional',
            'Companhia de Energia', NULL, emis,
            pg_temp.parcelas(ARRAY[round((950 + random()::numeric * 250) * energia[extract(month FROM mes)::int] * (1 + 0.04 * ano), 2)],
                             mes + 14, 'boleto'));
        PERFORM pg_temp.conta(adm, 'Água — ' || to_char(mes, 'MM/YYYY'), 'Água', 'Operacional', 'Companhia de Saneamento',
            NULL, emis, pg_temp.parcelas(ARRAY[round(120 + random()::numeric * 140, 2)], mes + 19, 'boleto'));
        PERFORM pg_temp.conta(adm, 'Telefone — ' || to_char(mes, 'MM/YYYY'), 'Telefone', 'Comercial', 'Operadora Fone Sul',
            NULL, emis, pg_temp.parcelas(ARRAY[89.90], mes + 9, 'debito_auto'));

        -- Impostos: 3% do que foi vendido no mês anterior.
        SELECT COALESCE(SUM(total_valor), 0) INTO vendas_mes FROM vendas
         WHERE status <> 'cancelada' AND data_venda >= mes - interval '1 month' AND data_venda < mes;
        IF vendas_mes > 0 THEN
            PERFORM pg_temp.conta(adm, 'Simples Nacional — competência ' || to_char(mes - interval '1 month', 'MM/YYYY'),
                'Impostos', 'Administrativo', 'Receita Federal', NULL, emis,
                pg_temp.parcelas(ARRAY[round(vendas_mes * 0.03, 2)], mes + 19, 'boleto'));
        END IF;

        -- Combustível: 3 a 5 abastecimentos, pagos na hora.
        n := 3 + floor(random()::numeric * 3)::int;
        FOR k IN 1..n LOOP
            venc := mes + floor(random()::numeric * 27)::int;
            CONTINUE WHEN venc >= CURRENT_DATE;
            PERFORM pg_temp.conta(adm, 'Diesel do caminhão', 'Combustível', 'Logística', 'Posto Rota 101', NULL,
                venc - CURRENT_DATE,
                jsonb_build_array(jsonb_build_object('v', round(280 + random()::numeric * 570, 2), 'venc', venc - CURRENT_DATE,
                                                     'pg', venc - CURRENT_DATE,
                                                     'f', (ARRAY['dinheiro','cartao','pix'])[1 + floor(random()::numeric * 3)::int])));
        END LOOP;

        -- Manutenção em 35% dos meses, parcelada em 1 a 3x (valor total dividido).
        IF random()::numeric < 0.35 THEN
            k := 1 + floor(random()::numeric * 4)::int;
            n := 1 + floor(random()::numeric * 3)::int;
            PERFORM pg_temp.conta(adm, servicos[k], 'Manutenção', 'Manutenção', oficinas[k], NULL, emis + 3,
                pg_temp.parcelas(pg_temp.dividir(round(350 + random()::numeric * 3450, 2), n), mes + 10, 'boleto'));
        END IF;

        -- Equipamentos parcelados em 6 a 10x.
        IF mm IN (4, 10, 17, 22) THEN
            n := 6 + floor(random()::numeric * 5)::int;
            PERFORM pg_temp.conta(adm,
                (ARRAY['Prensa enfardadeira vertical', 'Empilhadeira usada', 'Balança de plataforma 3 t', 'Big bags e paletes'])[1 + (mm % 4)],
                'Equipamentos', 'Operacional', 'Máquinas Recicla Tec Ltda', NULL, emis + 5,
                pg_temp.parcelas(pg_temp.dividir(round(4000 + random()::numeric * 14000, 2), n), mes + 15, 'boleto'),
                format('Parcelado em %sx.', n));
        END IF;

        -- Contrato encerrado no meio: 6x com as duas últimas canceladas.
        IF mm IN (6, 15) THEN
            PERFORM pg_temp.conta(adm, 'Consultoria ambiental (licença de operação)', 'Serviços', 'Administrativo',
                'Eco Consultoria Ambiental', NULL, emis,
                (SELECT jsonb_agg(CASE WHEN ord > 4 THEN e - 'pg' - 'vp' || '{"c": true}' ELSE e END ORDER BY ord)
                   FROM jsonb_array_elements(pg_temp.parcelas(array_fill(1250.00, ARRAY[6]), mes + 14, 'pix'))
                        WITH ORDINALITY x(e, ord)),
                'Contrato encerrado após a 4ª parcela.');
        END IF;

        -- Despesa lançada e cancelada por inteiro antes de vencer.
        IF mm IN (9, 20) THEN
            PERFORM pg_temp.conta(adm, 'Treinamento NR-11 (cancelado)', 'Serviços', 'Recursos Humanos',
                'Centro de Treinamento Seguro', NULL, emis,
                jsonb_build_array(jsonb_build_object('v', 1800, 'venc', emis + 30, 'f', 'boleto')),
                'Turma cancelada pelo fornecedor.', true);
        END IF;
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- Conferência
-- ---------------------------------------------------------------------------
DO $$
DECLARE n int; r record;
BEGIN
    -- 1. saldo de cada material = soma da trilha
    SELECT COUNT(*) INTO n
      FROM material m
      LEFT JOIN (SELECT id_material, SUM(CASE WHEN tipo = 'entrada' THEN quantidade ELSE -quantidade END) AS saldo
                   FROM estoque_movimentacoes GROUP BY id_material) t USING (id_material)
     WHERE COALESCE(m.qt_estoque, 0) <> COALESCE(t.saldo, 0);
    IF n > 0 THEN RAISE EXCEPTION '% material(is) com saldo diferente da trilha', n; END IF;

    -- 2. saldo_apos = soma acumulada em ordem cronológica (o extrato conta a história certa)
    SELECT COUNT(*) INTO n FROM (
        SELECT saldo_apos,
               SUM(CASE WHEN tipo = 'entrada' THEN quantidade ELSE -quantidade END)
                   OVER (PARTITION BY id_material ORDER BY data_movimentacao, id_movimentacao) AS acumulado
          FROM estoque_movimentacoes) x
     WHERE acumulado <> saldo_apos;
    IF n > 0 THEN RAISE EXCEPTION '% movimentação(ões) com saldo_apos fora da ordem cronológica', n; END IF;

    -- 3. nenhuma despesa cancelada com parcela paga (regra de contas.php)
    IF EXISTS (SELECT 1 FROM contas_pagar c JOIN contas_pagar_parcelas pa USING (id_conta)
                WHERE c.status = 'cancelada' AND pa.status = 'paga') THEN
        RAISE EXCEPTION 'despesa cancelada com parcela paga';
    END IF;

    FOR r IN SELECT * FROM (VALUES
                 ('clientes',              (SELECT COUNT(*) FROM clientes)),
                 ('fornecedores',          (SELECT COUNT(*) FROM fornecedores)),
                 ('usuario',               (SELECT COUNT(*) FROM usuario)),
                 ('pesagem (compras)',     (SELECT COUNT(*) FROM pesagem)),
                 ('pesagem_material',      (SELECT COUNT(*) FROM pesagem_material)),
                 ('vendas',                (SELECT COUNT(*) FROM vendas)),
                 ('vendas_itens',          (SELECT COUNT(*) FROM vendas_itens)),
                 ('estoque_movimentacoes', (SELECT COUNT(*) FROM estoque_movimentacoes)),
                 ('importacao_historico',  (SELECT COUNT(*) FROM importacao_historico)),
                 ('contas_pagar',          (SELECT COUNT(*) FROM contas_pagar)),
                 ('contas_pagar_parcelas', (SELECT COUNT(*) FROM contas_pagar_parcelas)),
                 ('registro_movimento',    (SELECT COUNT(*) FROM registro_movimento))) v(tabela, total) LOOP
        RAISE NOTICE '%: %', rpad(r.tabela, 22), r.total;
    END LOOP;

    RAISE NOTICE 'compras: R$ % | vendas: R$ % | despesas pagas: R$ %',
        (SELECT pg_temp.num(SUM(total_valor)) FROM pesagem WHERE status <> 'cancelada'),
        (SELECT pg_temp.num(SUM(total_valor)) FROM vendas WHERE status <> 'cancelada'),
        (SELECT pg_temp.num(SUM(valor_pago)) FROM contas_pagar_parcelas WHERE status = 'paga');
END $$;

COMMIT;
