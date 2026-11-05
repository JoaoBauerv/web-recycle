--
-- seed_funcoes.sql
--
-- Funcoes auxiliares das seeds de dados ficticios (0002_seed_demo.sql e
-- 0003_seed_massa.sql). NAO se roda sozinho: cada seed o inclui com
--   \ir seed_funcoes.sql
-- dentro da propria transacao. As funcoes sao criadas em pg_temp e somem ao
-- fim da sessao.
--
-- Elas aplicam as mesmas regras do PHP (compra/registrar.php, venda_lib.php,
-- estoque_lib.php, */cancelar.php): entrada soma, saida e perda subtraem,
-- saldo_apos vem do RETURNING do UPDATE, estorno gera o movimento contrario e
-- saldo negativo aborta. Quem chama precisa aplicar o movimento em ordem
-- cronologica, senao saldo_apos deixa de contar a historia certa.
--

-- Momento N dias atras, na hora indicada. Nunca no futuro.
CREATE FUNCTION pg_temp.ts(p_dias int, p_hora text) RETURNS timestamp
LANGUAGE sql AS $f$
    SELECT LEAST((CURRENT_DATE - p_dias) + p_hora::time, LOCALTIMESTAMP - interval '1 minute');
$f$;

-- 1234.5 -> '1.234,50', como number_format($v, 2, ',', '.')
CREATE FUNCTION pg_temp.num(p numeric) RETURNS text
LANGUAGE sql AS $f$
    SELECT translate(to_char(p, 'FM999,999,990.00'), ',.', '.,');
$f$;

CREATE FUNCTION pg_temp.mat(p_nome text) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE v int;
BEGIN
    SELECT id_material INTO v FROM material WHERE nm_material = p_nome;
    IF v IS NULL THEN RAISE EXCEPTION 'material nao encontrado: %', p_nome; END IF;
    RETURN v;
END $f$;

-- CPF a partir de 9 digitos-base, com os dois verificadores.
CREATE FUNCTION pg_temp.cpf(b text) RETURNS text
LANGUAGE plpgsql AS $f$
DECLARE s int; d1 int; d2 int; i int;
BEGIN
    s := 0;
    FOR i IN 1..9 LOOP s := s + substr(b, i, 1)::int * (11 - i); END LOOP;
    d1 := (s * 10) % 11; IF d1 = 10 THEN d1 := 0; END IF;
    s := 0;
    FOR i IN 1..9 LOOP s := s + substr(b, i, 1)::int * (12 - i); END LOOP;
    s := s + d1 * 2;
    d2 := (s * 10) % 11; IF d2 = 10 THEN d2 := 0; END IF;
    RETURN b || d1 || d2;
END $f$;

-- CNPJ a partir de 12 digitos-base, com os dois verificadores.
CREATE FUNCTION pg_temp.cnpj(b text) RETURNS text
LANGUAGE plpgsql AS $f$
DECLARE
    p1 int[] := ARRAY[5,4,3,2,9,8,7,6,5,4,3,2];
    p2 int[] := ARRAY[6,5,4,3,2,9,8,7,6,5,4,3,2];
    s int; r int; d1 int; d2 int; i int;
BEGIN
    s := 0;
    FOR i IN 1..12 LOOP s := s + substr(b, i, 1)::int * p1[i]; END LOOP;
    r := s % 11; d1 := CASE WHEN r < 2 THEN 0 ELSE 11 - r END;
    s := 0;
    FOR i IN 1..12 LOOP s := s + substr(b, i, 1)::int * p2[i]; END LOOP;
    s := s + d1 * p2[13];
    r := s % 11; d2 := CASE WHEN r < 2 THEN 0 ELSE 11 - r END;
    RETURN b || d1 || d2;
END $f$;

CREATE FUNCTION pg_temp.endereco(p_cep text, p_log text, p_num text, p_bairro text, p_cidade text, p_uf text, p_compl text DEFAULT NULL, p_ref text DEFAULT NULL)
RETURNS int LANGUAGE sql AS $f$
    INSERT INTO endereco (cep, logradouro, numero, complemento, bairro, cidade, estado, referencia)
    VALUES (p_cep, p_log, p_num, p_compl, p_bairro, p_cidade, p_uf, p_ref)
    RETURNING id_endereco;
$f$;

CREATE FUNCTION pg_temp.auditar(p_admin int, p_alvo int, p_desc text, p_tipo text, p_data timestamp) RETURNS void
LANGUAGE sql AS $f$
    INSERT INTO registro_movimento (id_usuario_admin, id_usuario_modificado, descricao, tipo, data)
    VALUES (p_admin, p_alvo, left(p_desc, 255), p_tipo, p_data);
$f$;

-- Mesma conta de estoqueMovimentar(): entrada soma, o resto subtrai.
CREATE FUNCTION pg_temp.mov(p_mat int, p_tipo text, p_qtd numeric, p_origem text, p_origem_id int, p_user int, p_obs text, p_data timestamp)
RETURNS numeric LANGUAGE plpgsql AS $f$
DECLARE v_saldo numeric; v_nome text;
BEGIN
    UPDATE material
       SET qt_estoque = COALESCE(qt_estoque, 0) + CASE WHEN p_tipo = 'entrada' THEN p_qtd ELSE -p_qtd END
     WHERE id_material = p_mat
    RETURNING qt_estoque, nm_material INTO v_saldo, v_nome;

    IF v_saldo < 0 THEN
        RAISE EXCEPTION 'massa de demonstracao deixaria % com saldo negativo (%)', v_nome, v_saldo;
    END IF;

    INSERT INTO estoque_movimentacoes
           (id_material, tipo, quantidade, origem_tipo, origem_id, saldo_apos, id_usuario, data_movimentacao, observacoes)
    VALUES (p_mat, p_tipo, p_qtd, p_origem, p_origem_id, v_saldo, p_user, p_data, left(p_obs, 255));
    RETURN v_saldo;
END $f$;

-- Compra (pesagem). Itens: [{"m": material, "b": peso bruto, "t": tara, "obs": opcional}]
-- Preco: o especial quando o cliente tem tabela especial, senao o normal.
CREATE FUNCTION pg_temp.compra(p_cliente int, p_data timestamp, p_user int, p_itens jsonb) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE v_id int; v_esp boolean; it jsonb; v_mat int; v_preco numeric; v_tara numeric; r record;
BEGIN
    SELECT preco_especial INTO v_esp FROM clientes WHERE id_cliente = p_cliente;

    INSERT INTO pesagem (total_valor, total_peso, data_pesagem, id_cliente)
    VALUES (0, 0, p_data, p_cliente) RETURNING id_pesagem INTO v_id;

    FOR it IN SELECT * FROM jsonb_array_elements(p_itens) LOOP
        v_mat  := pg_temp.mat(it->>'m');
        v_tara := COALESCE((it->>'t')::numeric, 0);
        SELECT CASE WHEN v_esp THEN COALESCE(preco_especial, preco_compra) ELSE preco_compra END
          INTO v_preco FROM material WHERE id_material = v_mat;

        INSERT INTO pesagem_material (id_pesagem, id_material, preco_un, peso_material, peso_bruto, tara, obs)
        VALUES (v_id, v_mat, v_preco, (it->>'b')::numeric - v_tara, (it->>'b')::numeric, v_tara, it->>'obs');
    END LOOP;

    UPDATE pesagem p
       SET total_peso = s.peso, total_valor = s.valor
      FROM (SELECT SUM(peso_material) AS peso, ROUND(SUM(peso_material * preco_un), 2) AS valor
              FROM pesagem_material WHERE id_pesagem = v_id) s
     WHERE p.id_pesagem = v_id;

    FOR r IN SELECT pm.id_material, m.nm_material, SUM(pm.peso_material) AS q
               FROM pesagem_material pm JOIN material m USING (id_material)
              WHERE pm.id_pesagem = v_id GROUP BY 1, 2 ORDER BY 1 LOOP
        PERFORM pg_temp.mov(r.id_material, 'entrada', r.q, 'pesagem', v_id, p_user,
                            format('Compra #%s - %s', v_id, r.nm_material), p_data);
    END LOOP;

    PERFORM pg_temp.auditar(p_user, p_user,
        format('Compra #%s registrada: R$ %s (%s kg)', v_id, pg_temp.num(total_valor), pg_temp.num(total_peso)),
        'Registro Compra', p_data)
      FROM pesagem WHERE id_pesagem = v_id;

    RETURN v_id;
END $f$;

-- Venda. Itens: [{"m": material, "b": peso bruto, "t": tara, "p": preco unitario}]
CREATE FUNCTION pg_temp.venda(p_forn int, p_data timestamp, p_user int, p_itens jsonb,
                              p_origem text DEFAULT 'manual', p_pedido text DEFAULT NULL,
                              p_importacao int DEFAULT NULL, p_obs text DEFAULT NULL) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE v_id int; it jsonb; v_tara numeric; v_qtd numeric; r record;
BEGIN
    INSERT INTO vendas (id_fornecedor, id_usuario, data_venda, observacoes, origem, pedido_externo, id_importacao)
    VALUES (p_forn, p_user, p_data, p_obs, p_origem, p_pedido, p_importacao) RETURNING id_venda INTO v_id;

    FOR it IN SELECT * FROM jsonb_array_elements(p_itens) LOOP
        v_tara := COALESCE((it->>'t')::numeric, 0);
        v_qtd  := (it->>'b')::numeric - v_tara;
        INSERT INTO vendas_itens (id_venda, id_material, quantidade, preco_un, valor_total, peso_bruto, tara)
        VALUES (v_id, pg_temp.mat(it->>'m'), v_qtd, (it->>'p')::numeric,
                ROUND(v_qtd * (it->>'p')::numeric, 2), (it->>'b')::numeric, v_tara);
    END LOOP;

    UPDATE vendas v
       SET total_peso = s.peso, total_valor = s.valor
      FROM (SELECT SUM(quantidade) AS peso, SUM(valor_total) AS valor FROM vendas_itens WHERE id_venda = v_id) s
     WHERE v.id_venda = v_id;

    FOR r IN SELECT vi.id_material, m.nm_material, SUM(vi.quantidade) AS q
               FROM vendas_itens vi JOIN material m USING (id_material)
              WHERE vi.id_venda = v_id GROUP BY 1, 2 ORDER BY 1 LOOP
        PERFORM pg_temp.mov(r.id_material, 'saida', r.q, 'venda', v_id, p_user,
                            format('Venda #%s - %s', v_id, r.nm_material), p_data);
    END LOOP;

    PERFORM pg_temp.auditar(p_user, p_user,
        format('Venda #%s registrada: R$ %s (%s kg)', v_id, pg_temp.num(total_valor), pg_temp.num(total_peso)),
        'Registro Venda', p_data)
      FROM vendas WHERE id_venda = v_id;

    RETURN v_id;
END $f$;

-- Cancelamento de compra: estorna a entrada com uma saida do mesmo peso.
CREATE FUNCTION pg_temp.cancelar_compra(p_id int, p_user int, p_data timestamp, p_motivo text) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE r record;
BEGIN
    FOR r IN SELECT id_material, SUM(peso_material) AS q FROM pesagem_material
              WHERE id_pesagem = p_id GROUP BY 1 ORDER BY 1 LOOP
        PERFORM pg_temp.mov(r.id_material, 'saida', r.q, 'estorno_compra', p_id, p_user,
                            format('Estorno do cancelamento da compra #%s: %s', p_id, p_motivo), p_data);
    END LOOP;
    UPDATE pesagem SET status = 'cancelada', cancelada_em = p_data, id_usuario_cancelou = p_user,
                       motivo_cancelamento = p_motivo
     WHERE id_pesagem = p_id;
    PERFORM pg_temp.auditar(p_user, p_user, format('Compra #%s cancelada: %s', p_id, p_motivo), 'Cancelamento Compra', p_data);
END $f$;

-- Cancelamento de venda: estorna a saida com uma entrada.
CREATE FUNCTION pg_temp.cancelar_venda(p_id int, p_user int, p_data timestamp, p_motivo text) RETURNS void
LANGUAGE plpgsql AS $f$
DECLARE r record;
BEGIN
    FOR r IN SELECT id_material, SUM(quantidade) AS q FROM vendas_itens
              WHERE id_venda = p_id GROUP BY 1 ORDER BY 1 LOOP
        PERFORM pg_temp.mov(r.id_material, 'entrada', r.q, 'estorno_venda', p_id, p_user,
                            format('Estorno do cancelamento da venda #%s: %s', p_id, p_motivo), p_data);
    END LOOP;
    UPDATE vendas SET status = 'cancelada', cancelada_em = p_data, id_usuario_cancelou = p_user,
                      motivo_cancelamento = p_motivo
     WHERE id_venda = p_id;
    PERFORM pg_temp.auditar(p_user, p_user, format('Venda #%s cancelada: %s', p_id, p_motivo), 'Cancelamento Venda', p_data);
END $f$;

-- N parcelas mensais iguais, no formato que pg_temp.conta() recebe. As que
-- vencem antes de hoje entram como pagas no próprio vencimento; as demais,
-- pendentes. É assim que o sistema lança despesa que se repete (aluguel,
-- salário): um parcelamento que cobre o período.
CREATE FUNCTION pg_temp.mensais(p_qtd int, p_valor numeric, p_primeiro_venc int, p_forma text) RETURNS jsonb
LANGUAGE sql AS $f$
    SELECT jsonb_agg(
               CASE WHEN venc < 0
                    THEN jsonb_build_object('v', p_valor, 'venc', venc, 'pg', venc, 'f', p_forma)
                    ELSE jsonb_build_object('v', p_valor, 'venc', venc, 'f', p_forma) END
               ORDER BY i)
      FROM (SELECT i, (CURRENT_DATE + p_primeiro_venc + (i * interval '1 month'))::date - CURRENT_DATE AS venc
              FROM generate_series(0, p_qtd - 1) i) s;
$f$;

-- Conta a pagar. Parcelas: [{"v": valor, "venc": dias a partir de hoje (negativo = passado),
--                            "pg": dia do pagamento ou null, "f": forma de pagamento,
--                            "vp": valor pago, quando difere do previsto (juros, desconto),
--                            "c": true para parcela cancelada avulsa}]
-- A conta fica 'cancelada' só com p_cancelada, e aí todas as parcelas também
-- (mesma regra de contas.php: despesa cancelada não tem parcela viva).
CREATE FUNCTION pg_temp.conta(p_admin int, p_desc text, p_categoria text, p_centro text, p_favorecido text,
                              p_func int, p_emissao int, p_parcelas jsonb,
                              p_obs text DEFAULT NULL, p_cancelada boolean DEFAULT false) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE v_id int; v_total numeric; it jsonb; n int := 0;
BEGIN
    SELECT SUM((e->>'v')::numeric) INTO v_total FROM jsonb_array_elements(p_parcelas) e;

    INSERT INTO contas_pagar (descricao, id_categoria, id_centro, favorecido, id_usuario_func, valor_total,
                              data_emissao, observacoes, status, data_cadastro)
    VALUES (p_desc,
            (SELECT id_categoria FROM despesa_categoria WHERE nome = p_categoria AND status = 1),
            (SELECT id_centro FROM centro_custo WHERE nome = p_centro AND status = 1),
            p_favorecido, p_func, v_total, CURRENT_DATE + p_emissao, p_obs,
            CASE WHEN p_cancelada THEN 'cancelada' ELSE 'ativa' END,
            pg_temp.ts(-p_emissao, '08:30'))
    RETURNING id_conta INTO v_id;

    FOR it IN SELECT * FROM jsonb_array_elements(p_parcelas) LOOP
        n := n + 1;
        INSERT INTO contas_pagar_parcelas (id_conta, numero_parcela, valor, data_vencimento, valor_pago,
                                           data_pagamento, forma_pagamento, id_usuario_pagamento, status)
        VALUES (v_id, n, (it->>'v')::numeric, CURRENT_DATE + (it->>'venc')::int,
                CASE WHEN it ? 'pg' THEN COALESCE((it->>'vp')::numeric, (it->>'v')::numeric) END,
                CASE WHEN it ? 'pg' THEN CURRENT_DATE + (it->>'pg')::int END,
                it->>'f',
                CASE WHEN it ? 'pg' THEN p_admin END,
                CASE WHEN p_cancelada OR it ? 'c' THEN 'cancelada' WHEN it ? 'pg' THEN 'paga' ELSE 'pendente' END);

        IF it ? 'pg' THEN
            PERFORM pg_temp.auditar(p_admin, p_admin,
                format('Parcela %s/%s da despesa #%s paga: R$ %s', n, jsonb_array_length(p_parcelas), v_id,
                       pg_temp.num(COALESCE((it->>'vp')::numeric, (it->>'v')::numeric))),
                'Financeiro', pg_temp.ts(-(it->>'pg')::int, '10:00'));
        END IF;
        IF it ? 'c' THEN
            PERFORM pg_temp.auditar(p_admin, p_admin,
                format('Despesa #%s: parcela %s/%s cancelada', v_id, n, jsonb_array_length(p_parcelas)),
                'Financeiro', pg_temp.ts(-(it->>'venc')::int + 5, '09:00'));   -- 5 dias antes do vencimento
        END IF;
    END LOOP;

    PERFORM pg_temp.auditar(p_admin, p_admin,
        format('Despesa #%s criada: %s, R$ %s em %sx', v_id, p_desc, pg_temp.num(v_total), n),
        'Financeiro', pg_temp.ts(-p_emissao, '08:30'));
    IF p_cancelada THEN
        PERFORM pg_temp.auditar(p_admin, p_admin, format('Despesa #%s cancelada', v_id),
                                'Financeiro', pg_temp.ts(-p_emissao, '09:00'));
    END IF;

    RETURN v_id;
END $f$;
