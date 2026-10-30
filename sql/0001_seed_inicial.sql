--
-- 0001_seed_inicial.sql
--
-- Seed unico da instalacao. E o unico arquivo de dados que uma instalacao nova
-- precisa: aplicar DEPOIS de 0000_baseline_schema.sql e das migrations de
-- 14/10/2026, que sao o que deixa o schema no estado final.
--
-- Traz apenas dados de REFERENCIA, o minimo para o sistema abrir e operar:
--
--   usuario            1 administrador, para o primeiro acesso
--   material          33 materiais do catalogo
--   despesa_categoria 16 categorias de despesa
--   centro_custo       7 centros de custo
--
-- Nada transacional. Compra, venda, estoque, contas a pagar e importacao
-- comecam vazios: o saldo de cada material entra em NULL e so se forma por
-- compra registrada ou por inventario.
--
-- ACESSO INICIAL
--   e-mail : admin@reciclagem.local
--   senha  : admin@123   (hash bcrypt custo 10, conferido)
--   O sistema EXIGE a troca no primeiro acesso: precisa_alterar_senha = 1
--   bloqueia a navegacao ate que a senha seja trocada.
--
-- IDEMPOTENCIA
--   Rodar duas vezes nao duplica nada, e isso vale tambem sobre um banco ja
--   mexido. A versao anterior deste arquivo confiava em ON CONFLICT DO NOTHING,
--   que falhava em tres casos reais:
--
--     1. admin com e-mail ja alterado  -> o unico indice que pegaria o conflito
--        e o de LOWER(email), entao um segundo administrador era inserido.
--     2. categoria de despesa inativada -> uq_categoria_nome e um indice
--        PARCIAL (WHERE status = 1); a linha inativa nao esta nele, nao ha
--        conflito, e o nome era inserido de novo.
--     3. centro de custo inativado     -> idem, por uq_centro_nome.
--
--   Por isso cada bloco abaixo testa a propria pre-condicao com NOT EXISTS, em
--   vez de depender de um indice. Material segue por ON CONFLICT porque ali a
--   unicidade e uma constraint de verdade (unique_nm_material), valida
--   independente de status.
--
-- Roda inteiro em uma transacao: ou o seed entra completo, ou nao entra.
--
-- CODIFICACAO
--   O arquivo esta em UTF-8 e precisa declarar isso. Sem a linha abaixo, o psql
--   assume a codificacao do cliente -- no Windows em portugues, WIN1252 -- e
--   aborta na categoria 'Agua': o 'A' com acento agudo ocupa os bytes C3 81 em
--   UTF-8, e 0x81 nao existe em WIN1252. Os outros acentos do arquivo escapam
--   porque seus bytes casam com algum caractere do WIN1252, mas 'Agua' nao.
--   A baseline nao tem esse problema porque e saida de pg_dump, que ja declara.
--

SET client_encoding TO 'UTF8';
SET search_path TO reciclagem;

BEGIN;

-- ---------------------------------------------------------------------------
-- Usuario administrador
-- ---------------------------------------------------------------------------
-- A condicao e "nao existe nenhum Admin ativo", nao "nao existe este e-mail":
-- quem ja tem administrador nao precisa deste, qualquer que seja o e-mail dele.
INSERT INTO usuario (nome, email, senha, usuario, foto, status, permissao, precisa_alterar_senha)
SELECT 'Administrador', 'admin@reciclagem.local',
       '$2y$10$4Nkcd86aeSuaCAYQK8T5K.8Sa5UwUwqY0An88R/w2yGhij8FbdX.O',
       'admin', '', 1, 'Admin', 1
WHERE NOT EXISTS (
    SELECT 1 FROM usuario WHERE permissao = 'Admin' AND status = 1
);

-- ---------------------------------------------------------------------------
-- Catalogo de materiais (33)
-- ---------------------------------------------------------------------------
-- qt_estoque fica em NULL de proposito: instalacao nova nao tem saldo, e o
-- codigo trata NULL como zero (COALESCE(qt_estoque,0)). Zero gravado afirmaria
-- um inventario que nunca houve.
--
-- preco_venda e o ultimo preco praticado na venda ao fornecedor. Aqui ele entra
-- como preco de referencia do catalogo; a primeira venda de cada material o
-- sobrescreve (vendaAtualizarUltimoPreco()).
--
-- estoque_minimo em NULL significa "sem alerta configurado" - o material nao
-- entra no aviso de estoque baixo.
--
-- Duas correcoes de catalogo em 30/10/2026, sobre dados herdados da base de
-- origem:
--   Lata                          compra 8,00 -> 10,00 e venda 3,50 -> 12,00.
--                                 Vendia por menos da metade do que comprava.
--                                 O especial foi de 9,00 para 11,00: com a
--                                 compra em 10,00 ele teria ficado ABAIXO do
--                                 preco normal, e o beneficio do cliente
--                                 viraria penalidade. 11,00 e o mesmo padrao
--                                 de Roda, que tambem compra a 10,00.
--   Cobre Misto, Cobre Vermelho   tipo 'Aluminio' -> 'Cobre'.
--
-- Nenhuma das duas mexe em historico: compra e venda guardam o preco praticado
-- em pesagem_material.preco_un e vendas_itens.preco_un, linha por linha. Estas
-- colunas aqui so alimentam o formulario de um lancamento NOVO e a avaliacao do
-- estoque parado (qt_estoque * preco_compra).
INSERT INTO material (nm_material, tipo, preco_compra, preco_especial, preco_venda, unidade_medida, codigo, estoque_minimo, status) VALUES
  ('Balde Branco', 'Plastico', 0.40, 0.40, 0.43, 'kg', NULL, NULL, 1),
  ('Balde Colorido', 'Plastico', 0.20, 0.20, 0.26, 'kg', NULL, NULL, 1),
  ('Bateria', 'Outro', 3.00, 3.00, 4.01, 'kg', NULL, NULL, 1),
  ('Caixaria', 'Plastico', 3.00, 3.00, 3.97, 'kg', NULL, NULL, 1),
  ('Chaparia', 'Aluminio', 7.00, 8.50, 9.14, 'kg', NULL, NULL, 1),
  ('Chumbo', 'Outro', 3.00, 4.00, 5.32, 'kg', NULL, NULL, 1),
  ('Cobre 4a', 'Cobre', 20.00, 25.00, 26.65, 'kg', NULL, NULL, 1),
  ('Cobre Misto', 'Cobre', 50.00, 53.00, 111.52, 'kg', NULL, NULL, 1),
  ('Cobre Vermelho', 'Cobre', 50.00, 55.00, 69.03, 'kg', NULL, NULL, 1),
  ('Colorido Pead', 'Plastico', 1.00, 1.50, 1.11, 'kg', NULL, NULL, 1),
  ('Cristal', 'Plastico', 0.40, 0.50, 0.81, 'kg', NULL, NULL, 1),
  ('Duro', 'Aluminio', 6.00, 7.50, 13.28, 'kg', NULL, NULL, 1),
  ('Esponja', 'Aluminio', 2.00, 2.00, 2.15, 'kg', NULL, NULL, 1),
  ('Fio Capa', 'Cobre', 7.00, 7.00, 8.99, 'kg', NULL, NULL, 1),
  ('Inox Panela', 'Outro', 0.50, 0.50, 0.56, 'kg', NULL, NULL, 1),
  ('Lata', 'Aluminio', 10.00, 11.00, 12.00, 'kg', NULL, NULL, 1),
  ('Leitoso', 'Plastico', 1.00, 1.50, 1.19, 'kg', NULL, NULL, 1),
  ('Metal', 'Cobre', 20.00, 25.00, 21.86, 'kg', NULL, NULL, 1),
  ('Misto', 'Papel', 0.10, 0.10, 0.13, 'kg', NULL, NULL, 1),
  ('Motor', 'Cobre', 25.00, 30.00, 30.17, 'kg', NULL, NULL, 1),
  ('Óleo', 'Outro', 1.00, 1.00, NULL, 'kg', NULL, 0.00, 1),
  ('Panela', 'Aluminio', 8.00, 9.00, 10.28, 'kg', NULL, NULL, 1),
  ('Papel Branco', 'Papel', 0.30, 0.30, 0.56, 'kg', NULL, NULL, 1),
  ('Papelão', 'Papel', 0.40, 0.50, 0.53, 'kg', NULL, NULL, 1),
  ('Perciana', 'Aluminio', 2.00, 4.00, 2.12, 'kg', NULL, NULL, 1),
  ('Perfil', 'Aluminio', 9.00, 10.00, 10.26, 'kg', NULL, NULL, 1),
  ('Pet Branca', 'Plastico', 2.50, 3.20, 2.80, 'kg', NULL, NULL, 1),
  ('Pet Verde', 'Plastico', 2.00, 2.70, 2.68, 'kg', NULL, NULL, 1),
  ('Pvc', 'Plastico', 0.10, 0.10, NULL, 'kg', NULL, NULL, 1),
  ('Radiador Com Cobre', 'Cobre', 20.00, 20.00, 25.87, 'kg', NULL, NULL, 1),
  ('Roda', 'Aluminio', 10.00, 11.00, NULL, 'kg', NULL, NULL, 1),
  ('Spray Com Ferro', 'Aluminio', 3.00, 3.00, 5.98, 'kg', NULL, NULL, 1),
  ('Sucata', 'Outro', 0.30, 0.40, 1.90, 'kg', NULL, 140.00, 1)
ON CONFLICT (nm_material) DO NOTHING;

-- ---------------------------------------------------------------------------
-- Categorias de despesa (16)
-- ---------------------------------------------------------------------------
-- tipo = 'funcionario' e o que habilita, no formulario de despesa, o vinculo
-- com um usuario do sistema. As demais sao 'geral'.
-- O ORDER BY nao e enfeite: sem ele o planejador resolve o NOT EXISTS por
-- anti-join e insere as linhas fora de ordem, deixando id_categoria embaralhado
-- a cada instalacao. Com a coluna de ordem explicita, a numeracao sai sempre a
-- mesma e igual a de antes -- alfabetica.
INSERT INTO despesa_categoria (nome, tipo, status)
SELECT v.nome, v.tipo, 1
  FROM (VALUES
    ( 1, 'Água',             'geral'),
    ( 2, 'Aluguel',          'geral'),
    ( 3, 'Combustível',      'geral'),
    ( 4, 'Contabilidade',    'geral'),
    ( 5, 'Energia elétrica', 'geral'),
    ( 6, 'Equipamentos',     'geral'),
    ( 7, 'Funcionários',     'funcionario'),
    ( 8, 'Impostos',         'geral'),
    ( 9, 'Internet',         'geral'),
    (10, 'Manutenção',       'geral'),
    (11, 'Materiais',        'geral'),
    (12, 'Outros',           'geral'),
    (13, 'Seguros',          'geral'),
    (14, 'Serviços',         'geral'),
    (15, 'Telefone',         'geral'),
    (16, 'Transporte',       'geral')
  ) AS v(ordem, nome, tipo)
 WHERE NOT EXISTS (
    SELECT 1 FROM despesa_categoria d WHERE LOWER(d.nome) = LOWER(v.nome)
 )
 ORDER BY v.ordem;

-- ---------------------------------------------------------------------------
-- Centros de custo (7)
-- ---------------------------------------------------------------------------
INSERT INTO centro_custo (nome, status)
SELECT v.nome, 1
  FROM (VALUES
    (1, 'Administrativo'),
    (2, 'Comercial'),
    (3, 'Logística'),
    (4, 'Manutenção'),
    (5, 'Operacional'),
    (6, 'Reciclagem'),
    (7, 'Recursos Humanos')
  ) AS v(ordem, nome)
 WHERE NOT EXISTS (
    SELECT 1 FROM centro_custo c WHERE LOWER(c.nome) = LOWER(v.nome)
 )
 ORDER BY v.ordem;

-- ---------------------------------------------------------------------------
-- Conferencia
-- ---------------------------------------------------------------------------
-- Seed que entra pela metade e volta 0 na saida nao serve: o sistema abriria
-- sem catalogo ou sem acesso. Aqui a transacao aborta em vez de deixar passar.
DO $$
DECLARE
    n_admin integer;
    n_mat   integer;
    n_cat   integer;
    n_cc    integer;
BEGIN
    SELECT COUNT(*) INTO n_admin FROM usuario WHERE permissao = 'Admin' AND status = 1;
    SELECT COUNT(*) INTO n_mat   FROM material;
    SELECT COUNT(*) INTO n_cat   FROM despesa_categoria;
    SELECT COUNT(*) INTO n_cc    FROM centro_custo;

    IF n_admin < 1 THEN
        RAISE EXCEPTION 'nenhum administrador ativo ao final do seed';
    END IF;
    IF n_mat < 33 THEN
        RAISE EXCEPTION 'catalogo incompleto: % materiais, esperado ao menos 33', n_mat;
    END IF;
    IF n_cat < 16 THEN
        RAISE EXCEPTION 'categorias de despesa incompletas: %, esperado ao menos 16', n_cat;
    END IF;
    IF n_cc < 7 THEN
        RAISE EXCEPTION 'centros de custo incompletos: %, esperado ao menos 7', n_cc;
    END IF;

    RAISE NOTICE 'seed ok: % admin, % materiais, % categorias, % centros de custo',
        n_admin, n_mat, n_cat, n_cc;
END $$;

COMMIT;
