--
-- 0002_seed_demo.sql
--
-- Massa de DEMONSTRACAO: dados ficticios em todas as tabelas, para apresentar o
-- sistema, testar telas e relatorios sem precisar lancar tudo a mao.
--
-- OPCIONAL. Uma instalacao de producao NAO roda este arquivo. Ele vem depois das
-- migrations (inclusive 2026-11-04_registro_movimento_tipo.sql) e de
-- 0001_seed_inicial.sql, e pressupoe o catalogo e o administrador que ele cria.
--
-- O que entra (contagens aproximadas):
--
--   usuario                 3   operador, gerente (Admin) e um inativo
--   endereco               13   de usuarios, clientes e fornecedores
--   clientes                8   catadores, cooperativas, sucateiros (1 inativo)
--   fornecedores            4   industrias recicladoras
--   pesagem / _material   32/62 compras dos ultimos 75 dias (1 cancelada)
--   vendas / vendas_itens 9/17 vendas manuais e importadas (1 cancelada)
--   estoque_movimentacoes  83   gerada pela propria replay, com saldo_apos real
--   importacao_modelo       1   mapeamento de planilha de um fornecedor
--   importacao_historico    3   concluida, concluida com avisos e com erro
--   material_relacao        4   nomes/codigos externos -> material do catalogo
--   contas_pagar / parcelas 9   pagas, vencidas, a vencer, parcelada, cancelada
--   evento                  5   agenda
--   registro_movimento     79   trilha de auditoria de tudo o que foi lancado
--
-- DATAS RELATIVAS
--   Tudo e datado a partir de CURRENT_DATE (ate 75 dias atras e alguns
--   vencimentos a frente). Rodar hoje ou daqui a um ano produz um painel com a
--   mesma cara: compras recentes, contas vencidas e a vencer.
--
-- CONSISTENCIA
--   Compras, vendas, cancelamentos e ajustes sao aplicados em ordem cronologica
--   pelas mesmas regras do PHP (compra/registrar.php, venda_lib.php,
--   estoque_lib.php, */cancelar.php): entrada soma, saida e perda subtraem,
--   saldo_apos vem do RETURNING do UPDATE, estorno gera o movimento contrario.
--   material.qt_estoque no fim bate com a soma da trilha, e um saldo que ficasse
--   negativo aborta o arquivo. preco_venda fica com o preco da ultima venda nao
--   cancelada, como vendaAtualizarUltimoPreco().
--
-- DADOS FICTICIOS
--   Nomes, documentos, telefones e enderecos sao inventados. CPF e CNPJ sao
--   gerados com digito verificador valido (para a edicao no formulario nao
--   recusar), a partir de bases sem relacao com pessoas reais. E-mails usam o
--   dominio reservado example.com.
--
-- ACESSO DOS USUARIOS DE DEMONSTRACAO (todos com a senha admin@123)
--   carlos.ramos@example.com     Usuario  (operador de balanca)
--   fernanda.oliveira@example.com Admin    (gerente: ve o financeiro, que e so Admin)
--   ricardo.alves@example.com    Usuario  (INATIVO, nao entra)
--   Diferente do admin, estes nao exigem troca de senha no primeiro acesso.
--
-- NAO E IDEMPOTENTE, de proposito: so roda em banco sem nenhum cliente, compra
-- ou venda. Num banco ja em uso ele aborta logo no inicio, sem gravar nada --
-- misturar massa ficticia com movimento real estragaria os relatorios.
--
-- Roda inteiro em uma transacao. As funcoes auxiliares sao criadas em pg_temp
-- e somem ao fim da sessao.
--

SET client_encoding TO 'UTF8';
SET search_path TO reciclagem;

BEGIN;

-- ---------------------------------------------------------------------------
-- Pre-condicoes
-- ---------------------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM usuario WHERE permissao = 'Admin' AND status = 1) THEN
        RAISE EXCEPTION 'nenhum administrador ativo: rode 0001_seed_inicial.sql antes';
    END IF;
    IF (SELECT COUNT(*) FROM material) < 33 THEN
        RAISE EXCEPTION 'catalogo incompleto: rode 0001_seed_inicial.sql antes';
    END IF;
    IF EXISTS (SELECT 1 FROM clientes) OR EXISTS (SELECT 1 FROM pesagem) OR EXISTS (SELECT 1 FROM vendas) THEN
        RAISE EXCEPTION 'o banco ja tem clientes, compras ou vendas; a massa de demonstracao so entra em banco recem-instalado';
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- Funcoes auxiliares (temporarias)
-- ---------------------------------------------------------------------------

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

-- Conta a pagar. Parcelas: [{"v": valor, "venc": dias a partir de hoje (negativo = passado),
--                            "pg": dia do pagamento ou null, "f": forma de pagamento}]
CREATE FUNCTION pg_temp.conta(p_admin int, p_desc text, p_categoria text, p_centro text, p_favorecido text,
                              p_func int, p_emissao int, p_parcelas jsonb,
                              p_periodo text DEFAULT NULL, p_dia int DEFAULT NULL,
                              p_obs text DEFAULT NULL, p_cancelada boolean DEFAULT false) RETURNS int
LANGUAGE plpgsql AS $f$
DECLARE v_id int; v_total numeric; it jsonb; n int := 0;
BEGIN
    SELECT SUM((e->>'v')::numeric) INTO v_total FROM jsonb_array_elements(p_parcelas) e;

    INSERT INTO contas_pagar (descricao, id_categoria, id_centro, favorecido, id_usuario_func, valor_total,
                              data_emissao, observacoes, recorrente, recorrencia_periodo, recorrencia_dia,
                              recorrencia_inicio, status, data_cadastro)
    VALUES (p_desc,
            (SELECT id_categoria FROM despesa_categoria WHERE nome = p_categoria AND status = 1),
            (SELECT id_centro FROM centro_custo WHERE nome = p_centro AND status = 1),
            p_favorecido, p_func, v_total, CURRENT_DATE + p_emissao, p_obs,
            p_periodo IS NOT NULL, p_periodo, p_dia,
            CASE WHEN p_periodo IS NOT NULL THEN CURRENT_DATE + p_emissao END,
            CASE WHEN p_cancelada THEN 'cancelada' ELSE 'ativa' END,
            pg_temp.ts(-p_emissao, '08:30'))
    RETURNING id_conta INTO v_id;

    FOR it IN SELECT * FROM jsonb_array_elements(p_parcelas) LOOP
        n := n + 1;
        INSERT INTO contas_pagar_parcelas (id_conta, numero_parcela, valor, data_vencimento, valor_pago,
                                           data_pagamento, forma_pagamento, id_usuario_pagamento, status)
        VALUES (v_id, n, (it->>'v')::numeric, CURRENT_DATE + (it->>'venc')::int,
                CASE WHEN it ? 'pg' THEN (it->>'v')::numeric END,
                CASE WHEN it ? 'pg' THEN CURRENT_DATE + (it->>'pg')::int END,
                it->>'f',
                CASE WHEN it ? 'pg' THEN p_admin END,
                CASE WHEN p_cancelada THEN 'cancelada' WHEN it ? 'pg' THEN 'paga' ELSE 'pendente' END);

        IF it ? 'pg' THEN
            PERFORM pg_temp.auditar(p_admin, p_admin,
                format('Parcela %s/%s da despesa #%s paga: R$ %s', n, jsonb_array_length(p_parcelas), v_id,
                       pg_temp.num((it->>'v')::numeric)),
                'Financeiro', pg_temp.ts(-(it->>'pg')::int, '10:00'));
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

-- ---------------------------------------------------------------------------
-- Massa
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    -- senha admin@123, o mesmo hash do administrador do seed inicial
    v_hash constant text := '$2y$10$4Nkcd86aeSuaCAYQK8T5K.8Sa5UwUwqY0An88R/w2yGhij8FbdX.O';

    u_admin int; u_op int; u_fin int; u_ina int;
    c1 int; c2 int; c3 int; c4 int; c5 int; c6 int; c7 int; c8 int;
    f1 int; f2 int; f3 int; f4 int;
    v_modelo int; i1 int; i2 int; i3 int;
    v_id int;
BEGIN
    SELECT id_usuario INTO u_admin FROM usuario WHERE permissao = 'Admin' AND status = 1 ORDER BY id_usuario LIMIT 1;

    -- ---------------------------------------------------------- usuarios
    INSERT INTO usuario (nome, email, senha, usuario, foto, status, permissao, precisa_alterar_senha,
                         data_nascimento, celular, cpf_cnpj, id_endereco)
    VALUES ('Carlos Eduardo Ramos', 'carlos.ramos@example.com', v_hash, 'carlos.ramos', '', 1, 'Usuario', 0,
            '1988-03-14', '48991230001', pg_temp.cpf('418273650'),
            pg_temp.endereco('88010100', 'Rua das Acácias', '215', 'Centro', 'Florianópolis', 'SC', 'Apto 302'))
    RETURNING id_usuario INTO u_op;

    INSERT INTO usuario (nome, email, senha, usuario, foto, status, permissao, precisa_alterar_senha,
                         data_nascimento, celular, cpf_cnpj, id_endereco)
    VALUES ('Fernanda Oliveira', 'fernanda.oliveira@example.com', v_hash, 'fernanda.oliveira', '', 1, 'Admin', 0,
            '1993-11-02', '48991230002', pg_temp.cpf('527384916'),
            pg_temp.endereco('88104200', 'Rua Hermínio Silva', '48', 'Campinas', 'São José', 'SC'))
    RETURNING id_usuario INTO u_fin;

    INSERT INTO usuario (nome, email, senha, usuario, foto, status, permissao, precisa_alterar_senha,
                         data_nascimento, celular, cpf_cnpj)
    VALUES ('Ricardo Alves', 'ricardo.alves@example.com', v_hash, 'ricardo.alves', '', 0, 'Usuario', 0,
            '1979-07-21', '48991230003', pg_temp.cpf('639182745'))
    RETURNING id_usuario INTO u_ina;

    PERFORM pg_temp.auditar(u_admin, u_op,  'Cadastro do usuário Carlos Eduardo Ramos', 'Cadastro Usuario', pg_temp.ts(80, '09:00'));
    PERFORM pg_temp.auditar(u_admin, u_fin, 'Cadastro do usuário Fernanda Oliveira',    'Cadastro Usuario', pg_temp.ts(80, '09:10'));
    PERFORM pg_temp.auditar(u_admin, u_ina, 'Cadastro do usuário Ricardo Alves',        'Cadastro Usuario', pg_temp.ts(80, '09:20'));
    PERFORM pg_temp.auditar(u_admin, u_ina, 'Usuário Ricardo Alves inativado',           'Usuario editado',  pg_temp.ts(20, '17:40'));

    -- ---------------------------------------------------------- clientes (de quem a empresa COMPRA)
    INSERT INTO clientes (nome, cpf_cnpj, telefone, celular, email, preco_especial, status, data_cadastro, id_endereco)
    VALUES ('Cooperativa Recicla Vida', pg_temp.cnpj('284719360001'), '4832410001', '48998410001',
            'contato@reciclavida.example.com', true, 1, pg_temp.ts(78, '10:00'),
            pg_temp.endereco('88090300', 'Rua Joaquim Nabuco', '1200', 'Capoeiras', 'Florianópolis', 'SC', 'Galpão 2'))
    RETURNING id_cliente INTO c1;

    INSERT INTO clientes (nome, cpf_cnpj, celular, preco_especial, status, data_cadastro, id_endereco)
    VALUES ('José Aparecido da Silva', pg_temp.cpf('713264589'), '48998410002', false, 1, pg_temp.ts(78, '10:30'),
            pg_temp.endereco('88111500', 'Servidão dos Pinheiros', '37', 'Serraria', 'São José', 'SC', NULL, 'Próximo ao campo de futebol'))
    RETURNING id_cliente INTO c2;

    INSERT INTO clientes (nome, cpf_cnpj, celular, preco_especial, status, data_cadastro, id_endereco)
    VALUES ('Maria das Graças Souza', pg_temp.cpf('825173946'), '48998410003', false, 1, pg_temp.ts(76, '14:00'),
            pg_temp.endereco('88132400', 'Rua Valdir Tomaz', '520', 'Pagani', 'Palhoça', 'SC'))
    RETURNING id_cliente INTO c3;

    INSERT INTO clientes (nome, cpf_cnpj, telefone, celular, email, preco_especial, status, data_cadastro, id_endereco)
    VALUES ('Sucataria Dois Irmãos Ltda', pg_temp.cnpj('391847260001'), '4832420004', '48998410004',
            'compras@doisirmaos.example.com', true, 1, pg_temp.ts(75, '09:00'),
            pg_temp.endereco('88160000', 'Rodovia SC-407', 'km 4', 'Areias', 'Biguaçu', 'SC'))
    RETURNING id_cliente INTO c4;

    INSERT INTO clientes (nome, cpf_cnpj, celular, email, preco_especial, status, data_cadastro, id_endereco)
    VALUES ('Antônio Pereira Lima', pg_temp.cpf('936281457'), '48998410005', 'antonio.lima@example.com', false, 1, pg_temp.ts(70, '11:00'),
            pg_temp.endereco('88063000', 'Rua Laurindo Januário da Silveira', '890', 'Lagoa da Conceição', 'Florianópolis', 'SC'))
    RETURNING id_cliente INTO c5;

    INSERT INTO clientes (nome, cpf_cnpj, telefone, email, preco_especial, status, data_cadastro, id_endereco)
    VALUES ('Associação de Catadores Nova Esperança', pg_temp.cnpj('472918350001'), '4832430006',
            'nova.esperanca@example.com', true, 1, pg_temp.ts(68, '08:40'),
            pg_temp.endereco('88117000', 'Rua São Pedro', '76', 'Forquilhinha', 'São José', 'SC'))
    RETURNING id_cliente INTO c6;

    INSERT INTO clientes (nome, cpf_cnpj, telefone, celular, preco_especial, status, data_cadastro, id_endereco)
    VALUES ('Ferro Velho do Zé', pg_temp.cnpj('518364920001'), '4832440007', '48998410007', false, 1, pg_temp.ts(67, '15:20'),
            pg_temp.endereco('88133500', 'Avenida Barão do Rio Branco', '3100', 'Ponte do Imaruim', 'Palhoça', 'SC'))
    RETURNING id_cliente INTO c7;

    INSERT INTO clientes (nome, cpf_cnpj, celular, preco_especial, status, data_cadastro)
    VALUES ('Luiz Carlos Mendes', pg_temp.cpf('147258369'), '48998410008', false, 0, pg_temp.ts(74, '16:00'))
    RETURNING id_cliente INTO c8;

    PERFORM pg_temp.auditar(u_op, u_op, 'Cliente ' || nome || ' cadastrado', 'Cadastro Cliente', data_cadastro)
       FROM clientes ORDER BY id_cliente;

    -- ---------------------------------------------------------- fornecedores (para quem a empresa VENDE)
    INSERT INTO fornecedores (nome_razao_social, cpf_cnpj, telefone, email, status, data_cadastro, observacoes, id_endereco)
    VALUES ('Reciclagem Sul Indústria de Papéis Ltda', pg_temp.cnpj('603728150001'), '4733510001',
            'compras@reciclagemsul.example.com', 1, pg_temp.ts(79, '09:00'),
            'Compra papelão e papel branco. Envia a planilha do pedido por e-mail.',
            pg_temp.endereco('89120000', 'Rua Industrial', '1500', 'Distrito Industrial', 'Timbó', 'SC'))
    RETURNING id_fornecedor INTO f1;

    INSERT INTO fornecedores (nome_razao_social, cpf_cnpj, telefone, email, status, data_cadastro, observacoes, id_endereco)
    VALUES ('PlastiNova Indústria de Plásticos S.A.', pg_temp.cnpj('718263940001'), '4733520002',
            'suprimentos@plastinova.example.com', 1, pg_temp.ts(79, '09:30'),
            'PET e plásticos rígidos. Coleta às quintas.',
            pg_temp.endereco('88301000', 'Rodovia Antônio Heil', '8000', 'Itaipava', 'Itajaí', 'SC'))
    RETURNING id_fornecedor INTO f2;

    INSERT INTO fornecedores (nome_razao_social, cpf_cnpj, telefone, email, status, data_cadastro, observacoes, id_endereco)
    VALUES ('Metalúrgica Alumifort Ltda', pg_temp.cnpj('829374610001'), '4734530003',
            'comercial@alumifort.example.com', 1, pg_temp.ts(77, '10:00'),
            'Alumínio e sucata ferrosa. Paga em 15 dias.',
            pg_temp.endereco('89219000', 'Rua Dona Francisca', '9200', 'Zona Industrial Norte', 'Joinville', 'SC'))
    RETURNING id_fornecedor INTO f3;

    INSERT INTO fornecedores (nome_razao_social, cpf_cnpj, telefone, email, status, data_cadastro, id_endereco)
    VALUES ('Cobrefio Fundição e Comércio Ltda', pg_temp.cnpj('931827460001'), '1140050004',
            'vendas@cobrefio.example.com', 1, pg_temp.ts(66, '13:00'),
            pg_temp.endereco('07220000', 'Avenida Industrial', '640', 'Cumbica', 'Guarulhos', 'SP'))
    RETURNING id_fornecedor INTO f4;

    PERFORM pg_temp.auditar(u_admin, u_admin, 'Fornecedor ' || nome_razao_social || ' cadastrado', 'Cadastro Fornecedor', data_cadastro)
       FROM fornecedores ORDER BY id_fornecedor;

    -- ---------------------------------------------------------- estoque minimo (para o alerta aparecer)
    UPDATE material SET estoque_minimo = 500 WHERE nm_material = 'Papelão';
    UPDATE material SET estoque_minimo = 100 WHERE nm_material = 'Pet Branca';
    UPDATE material SET estoque_minimo = 30  WHERE nm_material = 'Lata';
    UPDATE material SET estoque_minimo = 20  WHERE nm_material = 'Cobre Misto';

    -- ---------------------------------------------------------- importacao: modelo e relacoes
    -- mapeamento: campo -> indice da coluna (0 = A), como importacao_acoes.php grava.
    INSERT INTO importacao_modelo (nome, id_fornecedor, linha_cabecalho, mapeamento, data_cadastro)
    VALUES ('Pedido Reciclagem Sul', f1, 1,
            '{"codigo": 0, "material": 1, "peso_bruto": 2, "tara": 3, "preco_un": 4, "pedido": 5, "data": 6}',
            pg_temp.ts(32, '10:00'))
    RETURNING id_modelo INTO v_modelo;

    INSERT INTO material_relacao (id_material, nome_externo, nome_normalizado, codigo_externo, codigo_normalizado, id_modelo, origem, data_cadastro)
    VALUES (pg_temp.mat('Papelão'),      'PAPELAO ONDULADO', 'papelao ondulado', 'PA-001', 'pa001', v_modelo, 'importacao', pg_temp.ts(30, '10:20')),
           (pg_temp.mat('Papel Branco'), 'APARA BRANCA',     'apara branca',     'PA-014', 'pa014', v_modelo, 'importacao', pg_temp.ts(30, '10:21')),
           (pg_temp.mat('Pet Branca'),   'PET CRISTAL',      'pet cristal',      NULL,     NULL,    NULL,     'manual',     pg_temp.ts(45, '15:00')),
           (pg_temp.mat('Sucata'),       'Sucata Mista',     'sucata mista',     'SUC-1',  'suc1',  NULL,     'manual',     pg_temp.ts(45, '15:05'));

    -- 'Relacionamento Material' tem 23 caracteres: depende da migration
    -- 2026-11-04_registro_movimento_tipo.sql, que levou a coluna a varchar(40).
    PERFORM pg_temp.auditar(u_admin, u_admin, 'Relacionamento: ' || nome_externo || ' -> material #' || id_material,
                            'Relacionamento Material', data_cadastro)
       FROM material_relacao ORDER BY id_relacao;

    -- ---------------------------------------------------------- movimento, em ordem cronologica
    PERFORM pg_temp.compra(c1, pg_temp.ts(75, '08:20'), u_op, '[{"m":"Papelão","b":850,"t":15},{"m":"Pet Branca","b":120,"t":2},{"m":"Lata","b":45,"t":0.5}]');
    PERFORM pg_temp.compra(c2, pg_temp.ts(72, '09:10'), u_op, '[{"m":"Papelão","b":180,"t":3},{"m":"Lata","b":12}]');
    PERFORM pg_temp.compra(c4, pg_temp.ts(70, '10:45'), u_op, '[{"m":"Sucata","b":1200,"t":40},{"m":"Perfil","b":85,"t":1},{"m":"Panela","b":30,"t":0.5}]');
    PERFORM pg_temp.compra(c3, pg_temp.ts(68, '14:15'), u_op, '[{"m":"Papelão","b":95,"t":1.5},{"m":"Pet Verde","b":40,"t":1}]');
    PERFORM pg_temp.compra(c7, pg_temp.ts(65, '11:30'), u_op, '[{"m":"Cobre Misto","b":38,"t":0.8},{"m":"Fio Capa","b":60,"t":1},{"m":"Bateria","b":120,"t":2}]');
    PERFORM pg_temp.compra(c6, pg_temp.ts(63, '08:50'), u_op, '[{"m":"Papelão","b":640,"t":10},{"m":"Pet Branca","b":210,"t":3},{"m":"Cristal","b":150,"t":2}]');
    PERFORM pg_temp.compra(c5, pg_temp.ts(60, '16:05'), u_op, '[{"m":"Lata","b":22,"t":0.3},{"m":"Papel Branco","b":140,"t":2}]');
    PERFORM pg_temp.compra(c1, pg_temp.ts(58, '08:35'), u_op, '[{"m":"Papelão","b":920,"t":18},{"m":"Caixaria","b":75,"t":1}]');
    PERFORM pg_temp.compra(c2, pg_temp.ts(55, '09:40'), u_op, '[{"m":"Papelão","b":160,"t":2},{"m":"Lata","b":15,"t":0.2}]');
    PERFORM pg_temp.compra(c4, pg_temp.ts(52, '10:10'), u_op, '[{"m":"Sucata","b":980,"t":30},{"m":"Metal","b":25,"t":0.5},{"m":"Perfil","b":60,"t":1}]');

    PERFORM pg_temp.venda(f1, pg_temp.ts(50, '13:30'), u_op, '[{"m":"Papelão","b":2520,"t":20,"p":0.53}]',
                          p_obs => 'Carga completa, caminhão truck.');

    PERFORM pg_temp.compra(c3, pg_temp.ts(48, '15:00'), u_op, '[{"m":"Pet Branca","b":35,"t":0.5},{"m":"Lata","b":8}]');

    PERFORM pg_temp.venda(f2, pg_temp.ts(46, '10:00'), u_op,
        '[{"m":"Pet Branca","b":340,"t":4,"p":2.80},{"m":"Cristal","b":140,"t":2,"p":0.81},{"m":"Pet Verde","b":35,"p":2.68}]');

    PERFORM pg_temp.compra(c6, pg_temp.ts(45, '08:30'), u_op, '[{"m":"Papelão","b":700,"t":12},{"m":"Pet Verde","b":90,"t":1.5}]');

    PERFORM pg_temp.venda(f3, pg_temp.ts(42, '14:20'), u_op,
        '[{"m":"Lata","b":101,"t":1,"p":12.00},{"m":"Perfil","b":140,"t":2,"p":10.26},{"m":"Panela","b":28,"p":10.28}]');

    PERFORM pg_temp.compra(c7, pg_temp.ts(40, '11:00'), u_op, '[{"m":"Cobre Misto","b":25,"t":0.5},{"m":"Motor","b":80,"t":2}]');

    PERFORM pg_temp.venda(f4, pg_temp.ts(38, '09:30'), u_admin,
        '[{"m":"Cobre Misto","b":60,"t":0.5,"p":111.52},{"m":"Fio Capa","b":58,"p":8.99},{"m":"Motor","b":75,"p":30.17}]',
        p_obs => 'Pagamento antecipado via PIX.');

    PERFORM pg_temp.compra(c1, pg_temp.ts(36, '08:15'), u_op, '[{"m":"Papelão","b":880,"t":15},{"m":"Pet Branca","b":150,"t":2}]');
    PERFORM pg_temp.compra(c5, pg_temp.ts(35, '16:30'), u_op, '[{"m":"Lata","b":30,"t":0.5},{"m":"Papel Branco","b":90,"t":1}]');
    PERFORM pg_temp.compra(c2, pg_temp.ts(33, '09:05'), u_op, '[{"m":"Papelão","b":140,"t":2}]');
    PERFORM pg_temp.compra(c4, pg_temp.ts(31, '10:40'), u_op, '[{"m":"Sucata","b":1500,"t":50}]');

    -- Importacao 1: planilha do fornecedor, concluida sem avisos.
    INSERT INTO importacao_historico (arquivo, id_modelo, id_usuario, pedido_externo, total_registros,
                                      registros_importados, valor_total, status, data_importacao)
    VALUES ('pedido_reciclagem_sul_0412.xlsx', v_modelo, u_admin, 'PED-0412', 2, 2, 0, 'concluido', pg_temp.ts(30, '10:20'))
    RETURNING id_importacao INTO i1;
    v_id := pg_temp.venda(f1, pg_temp.ts(30, '10:20'), u_admin,
        '[{"m":"Papelão","b":1800,"t":20,"p":0.55},{"m":"Papel Branco","b":220,"t":2,"p":0.56}]',
        'importacao', 'PED-0412', i1);
    UPDATE importacao_historico SET valor_total = (SELECT total_valor FROM vendas WHERE id_venda = v_id) WHERE id_importacao = i1;

    PERFORM pg_temp.venda(f3, pg_temp.ts(28, '15:10'), u_op, '[{"m":"Sucata","b":3500,"t":60,"p":1.90}]',
                          p_obs => 'Retirada com caçamba do comprador.');

    PERFORM pg_temp.compra(c6, pg_temp.ts(25, '08:45'), u_op, '[{"m":"Papelão","b":720,"t":10},{"m":"Pet Branca","b":180,"t":2},{"m":"Cristal","b":120,"t":1}]');
    PERFORM pg_temp.compra(c1, pg_temp.ts(22, '08:25'), u_op, '[{"m":"Papelão","b":1050,"t":20},{"m":"Caixaria","b":60,"t":1}]');

    PERFORM pg_temp.mov(pg_temp.mat('Papelão'), 'perda', 35, 'ajuste_manual', NULL, u_admin,
                        'Umidade: fardos molhados pela chuva no pátio descoberto', pg_temp.ts(20, '17:00'));

    PERFORM pg_temp.compra(c7, pg_temp.ts(18, '11:15'), u_op, '[{"m":"Cobre Misto","b":18,"t":0.3},{"m":"Bateria","b":95,"t":1}]');

    PERFORM pg_temp.mov(pg_temp.mat('Pet Verde'), 'entrada', 12, 'ajuste_manual', NULL, u_admin,
                        'Sobra na contagem do inventário mensal', pg_temp.ts(16, '18:00'));

    PERFORM pg_temp.compra(c5, pg_temp.ts(15, '16:00'), u_op, '[{"m":"Lata","b":25,"t":0.4}]');

    PERFORM pg_temp.venda(f2, pg_temp.ts(14, '10:30'), u_op, '[{"m":"Pet Branca","b":330,"t":4,"p":2.85},{"m":"Pet Verde","b":100,"t":1,"p":2.70}]');

    PERFORM pg_temp.compra(c2, pg_temp.ts(12, '09:20'), u_op, '[{"m":"Papelão","b":200,"t":3}]');

    -- Venda cancelada: a carga voltou.
    v_id := pg_temp.venda(f1, pg_temp.ts(10, '14:00'), u_op, '[{"m":"Papelão","b":1500,"t":15,"p":0.54}]');
    PERFORM pg_temp.cancelar_venda(v_id, u_admin, pg_temp.ts(9, '09:15'),
                                   'Fornecedor recusou a carga: umidade acima do contratado');

    PERFORM pg_temp.compra(c4, pg_temp.ts(8, '10:30'), u_op, '[{"m":"Sucata","b":800,"t":20},{"m":"Perfil","b":70,"t":1}]');

    -- Importacao 2: concluida com avisos (uma linha nao reconhecida).
    INSERT INTO importacao_historico (arquivo, id_modelo, id_usuario, pedido_externo, total_registros,
                                      registros_importados, valor_total, status, erros, data_importacao)
    VALUES ('pedido_reciclagem_sul_0457.xlsx', v_modelo, u_admin, 'PED-0457', 2, 1, 0, 'concluido_avisos',
            '[{"linha": 4, "material": "PAPELAO MISTO", "motivo": "Material não reconhecido no catálogo"}]',
            pg_temp.ts(7, '11:00'))
    RETURNING id_importacao INTO i2;
    v_id := pg_temp.venda(f1, pg_temp.ts(7, '11:00'), u_admin, '[{"m":"Papelão","b":900,"t":10,"p":0.55}]',
                          'importacao', 'PED-0457', i2);
    UPDATE importacao_historico SET valor_total = (SELECT total_valor FROM vendas WHERE id_venda = v_id) WHERE id_importacao = i2;

    PERFORM pg_temp.compra(c6, pg_temp.ts(6, '08:40'), u_op, '[{"m":"Papelão","b":560,"t":8}]');

    -- Compra cancelada: lancada em duplicidade.
    v_id := pg_temp.compra(c2, pg_temp.ts(5, '09:00'), u_op, '[{"m":"Pet Verde","b":30,"t":0.5}]');
    PERFORM pg_temp.cancelar_compra(v_id, u_admin, pg_temp.ts(5, '09:25'), 'Peso lançado em duplicidade');
    PERFORM pg_temp.compra(c2, pg_temp.ts(5, '09:30'), u_op, '[{"m":"Pet Verde","b":30,"t":0.5}]');

    PERFORM pg_temp.compra(c3, pg_temp.ts(4, '14:50'), u_op, '[{"m":"Lata","b":9},{"m":"Cristal","b":42,"t":1}]');

    -- Importacao 3: falhou, nenhuma venda gerada.
    INSERT INTO importacao_historico (arquivo, id_modelo, id_usuario, total_registros, registros_importados,
                                      valor_total, status, erros, data_importacao)
    VALUES ('pedido_plastinova_out.xlsx', NULL, u_admin, 3, 0, 0, 'erro',
            '[{"linha": 2, "material": "PP-REC", "motivo": "Material não reconhecido no catálogo"}, {"linha": 3, "material": "PEAD SOPRO", "motivo": "Material não reconhecido no catálogo"}, {"linha": 4, "material": "", "motivo": "Quantidade em branco"}]',
            pg_temp.ts(3, '16:20'))
    RETURNING id_importacao INTO i3;

    PERFORM pg_temp.compra(c5, pg_temp.ts(2, '15:40'), u_op, '[{"m":"Papel Branco","b":60,"t":1},{"m":"Lata","b":14,"t":0.2}]');
    PERFORM pg_temp.compra(c7, pg_temp.ts(1, '11:10'), u_op, '[{"m":"Cobre Misto","b":12,"t":0.2},{"m":"Fio Capa","b":35,"t":0.5}]');
    PERFORM pg_temp.compra(c1, pg_temp.ts(1, '08:30'), u_op, '[{"m":"Papelão","b":610,"t":10},{"m":"Pet Branca","b":95,"t":1}]');
    PERFORM pg_temp.compra(c2, pg_temp.ts(0, '08:10'), u_op, '[{"m":"Papelão","b":130,"t":2}]');
    PERFORM pg_temp.compra(c3, pg_temp.ts(0, '08:40'), u_op, '[{"m":"Pet Branca","b":28,"t":0.5},{"m":"Lata","b":6}]');

    -- preco_venda = preco da ultima venda nao cancelada, como vendaAtualizarUltimoPreco()
    UPDATE material m
       SET preco_venda = u.preco_un
      FROM (SELECT DISTINCT ON (vi.id_material) vi.id_material, vi.preco_un
              FROM vendas_itens vi JOIN vendas v USING (id_venda)
             WHERE v.status <> 'cancelada'
             ORDER BY vi.id_material, v.data_venda DESC, v.id_venda DESC, vi.id_venda_item DESC) u
     WHERE m.id_material = u.id_material;

    -- ---------------------------------------------------------- contas a pagar
    PERFORM pg_temp.conta(u_admin, 'Aluguel do galpão', 'Aluguel', 'Administrativo', 'Imobiliária Horizonte', NULL, -60,
        '[{"v":3500,"venc":-50,"pg":-50,"f":"transferencia"},{"v":3500,"venc":-20,"pg":-21,"f":"transferencia"},{"v":3500,"venc":10,"f":"transferencia"}]',
        'mensal', 10, 'Contrato de 24 meses, reajuste anual pelo IGP-M.');

    PERFORM pg_temp.conta(u_admin, 'Energia elétrica', 'Energia elétrica', 'Operacional', 'Companhia de Energia', NULL, -12,
        '[{"v":1280.45,"venc":-3,"f":"boleto"}]');

    PERFORM pg_temp.conta(u_admin, 'Salário Fernanda Oliveira', 'Funcionários', 'Recursos Humanos', 'Fernanda Oliveira', u_fin, -40,
        '[{"v":2400,"venc":-30,"pg":-30,"f":"pix"},{"v":2400,"venc":2,"f":"pix"}]',
        'mensal', 5);

    PERFORM pg_temp.conta(u_admin, 'Balança rodoviária nova', 'Equipamentos', 'Operacional', 'Balanças Precisão Ltda', NULL, -45,
        '[{"v":1600,"venc":-40,"pg":-40,"f":"boleto"},{"v":1600,"venc":-10,"f":"boleto"},{"v":1600,"venc":20,"f":"boleto"}]',
        p_obs => 'Parcelado em 3x sem juros.');

    PERFORM pg_temp.conta(u_admin, 'Internet fibra', 'Internet', 'Administrativo', 'Provedor NetSul', NULL, -20,
        '[{"v":149.90,"venc":-15,"pg":-15,"f":"debito_auto"}]',
        'mensal', 15);

    PERFORM pg_temp.conta(u_admin, 'Diesel do caminhão', 'Combustível', 'Logística', 'Posto Rota 101', NULL, -6,
        '[{"v":620,"venc":-6,"pg":-6,"f":"dinheiro"}]');

    PERFORM pg_temp.conta(u_admin, 'Manutenção da prensa enfardadeira', 'Manutenção', 'Manutenção', 'HidroPress Serviços', NULL, -4,
        '[{"v":980,"venc":12,"f":"pix"}]');

    PERFORM pg_temp.conta(u_admin, 'Honorários contábeis', 'Contabilidade', 'Administrativo', 'Escritório Contábil Exato', NULL, -25,
        '[{"v":750,"venc":-2,"f":"boleto"}]');

    PERFORM pg_temp.conta(u_admin, 'Seguro do caminhão', 'Seguros', 'Logística', 'Seguradora Confiança', NULL, -35,
        '[{"v":410,"venc":-5,"f":"boleto"},{"v":410,"venc":25,"f":"boleto"}]',
        p_obs => 'Cancelado: apólice renovada por outra corretora.', p_cancelada => true);

    -- ---------------------------------------------------------- agenda
    INSERT INTO evento (title, color, start, "end") VALUES
        ('Coleta Reciclagem Sul (papelão)',      '#198754', (CURRENT_DATE + 2) + time '08:00', (CURRENT_DATE + 2) + time '11:00'),
        ('Manutenção preventiva da prensa',      '#FFD700', (CURRENT_DATE + 12) + time '13:00', (CURRENT_DATE + 12) + time '17:00'),
        ('Inventário mensal do estoque',         '#0d6efd', (CURRENT_DATE - 16) + time '14:00', (CURRENT_DATE - 16) + time '18:00'),
        ('Visita técnica PlastiNova',            '#6f42c1', (CURRENT_DATE + 6) + time '10:00', (CURRENT_DATE + 6) + time '11:30'),
        ('Aferição da balança (INMETRO)',        '#dc3545', (CURRENT_DATE + 20) + time '09:00', (CURRENT_DATE + 20) + time '12:00');
END $$;

-- ---------------------------------------------------------------------------
-- Conferencia
-- ---------------------------------------------------------------------------
-- O saldo de cada material precisa bater com a trilha. Se nao bater, a replay
-- acima tem defeito e a transacao inteira volta.
DO $$
DECLARE
    n_div int;
    r record;
BEGIN
    SELECT COUNT(*) INTO n_div
      FROM material m
      LEFT JOIN (SELECT id_material,
                        SUM(CASE WHEN tipo = 'entrada' THEN quantidade ELSE -quantidade END) AS saldo
                   FROM estoque_movimentacoes GROUP BY id_material) t USING (id_material)
     WHERE COALESCE(m.qt_estoque, 0) <> COALESCE(t.saldo, 0);

    IF n_div > 0 THEN
        RAISE EXCEPTION '% material(is) com saldo diferente da trilha de movimentacao', n_div;
    END IF;

    FOR r IN SELECT relname, n FROM (
                 SELECT 'usuario' AS relname, COUNT(*) AS n FROM usuario UNION ALL
                 SELECT 'clientes', COUNT(*) FROM clientes UNION ALL
                 SELECT 'fornecedores', COUNT(*) FROM fornecedores UNION ALL
                 SELECT 'endereco', COUNT(*) FROM endereco UNION ALL
                 SELECT 'pesagem', COUNT(*) FROM pesagem UNION ALL
                 SELECT 'pesagem_material', COUNT(*) FROM pesagem_material UNION ALL
                 SELECT 'vendas', COUNT(*) FROM vendas UNION ALL
                 SELECT 'vendas_itens', COUNT(*) FROM vendas_itens UNION ALL
                 SELECT 'estoque_movimentacoes', COUNT(*) FROM estoque_movimentacoes UNION ALL
                 SELECT 'importacao_modelo', COUNT(*) FROM importacao_modelo UNION ALL
                 SELECT 'importacao_historico', COUNT(*) FROM importacao_historico UNION ALL
                 SELECT 'material_relacao', COUNT(*) FROM material_relacao UNION ALL
                 SELECT 'contas_pagar', COUNT(*) FROM contas_pagar UNION ALL
                 SELECT 'contas_pagar_parcelas', COUNT(*) FROM contas_pagar_parcelas UNION ALL
                 SELECT 'evento', COUNT(*) FROM evento UNION ALL
                 SELECT 'registro_movimento', COUNT(*) FROM registro_movimento) x LOOP
        IF r.n = 0 THEN
            RAISE EXCEPTION 'tabela % ficou vazia', r.relname;
        END IF;
        RAISE NOTICE '%: %', rpad(r.relname, 22), r.n;
    END LOOP;
END $$;

COMMIT;
