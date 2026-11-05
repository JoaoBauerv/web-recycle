--
-- 2026-10-14_endereco_unico_e_documento.sql
--
-- Fase 2 da padronização do schema.
--
-- 1. 'endereco' passa a ser a única tabela de endereços do sistema. Antes ela
--    era uma tabela-satélite exclusiva de usuário (tinha endereco.id_usuario),
--    enquanto cliente e fornecedor carregavam o endereço embutido em colunas
--    próprias — três representações para o mesmo conceito.
--
--    A direção da FK se inverte: quem passa a apontar é a entidade
--    (usuario.id_endereco, clientes.id_endereco, fornecedores.id_endereco).
--    É FK normal, não chave polimórfica: o banco continua validando a
--    integridade referencial, o que uma dupla (tipo_entidade, id_entidade)
--    não permitiria.
--
-- 2. 'documento' é removida. Tinha cpf, rg e cnh, e nunca foi preenchida. O
--    único dado que o sistema realmente usa é o CPF/CNPJ, que passa a ser uma
--    coluna direta em cada entidade — como já era em clientes.cpf_cnpj.
--
-- 3. 'public.produtos' é removida: tabela de outro projeto, fora do schema
--    reciclagem e sem nenhuma referência no código.
--
-- SEGURANÇA: a migration é defensiva. Antes de derrubar qualquer coluna ela
-- confere, linha por linha, que o dado chegou ao destino, e aborta a transação
-- inteira se encontrar divergência. Também se recusa a rodar se documento.rg ou
-- documento.cnh tiverem conteúdo, já que esses dois campos não têm destino.
--

-- CODIFICAÇÃO: o arquivo está em UTF-8 e declara isso abaixo. Sem a declaração,
-- o psql assume a codificação do cliente — no Windows em português, WIN1252 — e
-- o COMMENT ON do passo 1 entra no banco como "EndereÃ§o"; as mensagens de
-- RAISE EXCEPTION dos passos 5 e 6 saem igualmente tortas.

SET client_encoding TO 'UTF8';
SET search_path TO reciclagem;

BEGIN;

-- ---------------------------------------------------------------------------
-- 1. endereco ganha o que faltava para servir às três entidades
-- ---------------------------------------------------------------------------

-- cliente e fornecedor tinham 'estado'; a tabela de endereço não.
ALTER TABLE endereco ADD COLUMN estado varchar(2);

-- numero era varchar(5) aqui e varchar(10) em cliente/fornecedor: alarga para
-- o maior dos dois, senão o dado migrado não caberia.
ALTER TABLE endereco ALTER COLUMN numero TYPE varchar(10);

COMMENT ON TABLE endereco IS
    'Endereço de qualquer entidade do sistema. Referenciada por usuario, clientes e fornecedores.';

-- ---------------------------------------------------------------------------
-- 2. as três entidades passam a apontar para endereco
-- ---------------------------------------------------------------------------

ALTER TABLE usuario      ADD COLUMN id_endereco integer;
ALTER TABLE clientes     ADD COLUMN id_endereco integer;
ALTER TABLE fornecedores ADD COLUMN id_endereco integer;

-- ON DELETE SET NULL: apagar um endereço não pode apagar o cliente.
ALTER TABLE usuario
    ADD CONSTRAINT fk_usuario_endereco FOREIGN KEY (id_endereco)
    REFERENCES endereco (id_endereco) ON DELETE SET NULL;
ALTER TABLE clientes
    ADD CONSTRAINT fk_clientes_endereco FOREIGN KEY (id_endereco)
    REFERENCES endereco (id_endereco) ON DELETE SET NULL;
ALTER TABLE fornecedores
    ADD CONSTRAINT fk_fornecedores_endereco FOREIGN KEY (id_endereco)
    REFERENCES endereco (id_endereco) ON DELETE SET NULL;

-- O PostgreSQL não indexa o lado referenciante de uma FK automaticamente, e é
-- esse lado que o ON DELETE SET NULL percorre.
CREATE INDEX idx_usuario_endereco      ON usuario      (id_endereco);
CREATE INDEX idx_clientes_endereco     ON clientes     (id_endereco);
CREATE INDEX idx_fornecedores_endereco ON fornecedores (id_endereco);

-- ---------------------------------------------------------------------------
-- 3. usuário: a ligação só troca de lado, o endereço já estava na tabela certa
-- ---------------------------------------------------------------------------

UPDATE usuario u
   SET id_endereco = e.id_endereco
  FROM endereco e
 WHERE e.id_usuario = u.id_usuario;

-- Com a ligação já do outro lado, id_usuario deixa de ser obrigatória: os
-- endereços de cliente e fornecedor inseridos no passo seguinte não têm
-- usuário nenhum. A coluna cai no passo 7, depois da conferência.
ALTER TABLE endereco ALTER COLUMN id_usuario DROP NOT NULL;

-- ---------------------------------------------------------------------------
-- 4. cliente e fornecedor: o endereço embutido vira linha em endereco
-- ---------------------------------------------------------------------------

-- Laço explícito em vez de INSERT ... SELECT porque é preciso guardar, para
-- cada linha inserida, de qual cliente ela veio — o RETURNING de um INSERT em
-- massa não devolve essa correspondência.
DO $$
DECLARE
    r    RECORD;
    novo integer;
BEGIN
    FOR r IN
        SELECT id_cliente, cep, logradouro, numero, complemento, bairro, cidade, estado
          FROM clientes
         WHERE COALESCE(TRIM(cep), '')         <> ''
            OR COALESCE(TRIM(logradouro), '')  <> ''
            OR COALESCE(TRIM(numero), '')      <> ''
            OR COALESCE(TRIM(complemento), '') <> ''
            OR COALESCE(TRIM(bairro), '')      <> ''
            OR COALESCE(TRIM(cidade), '')      <> ''
            OR COALESCE(TRIM(estado), '')      <> ''
         ORDER BY id_cliente
    LOOP
        INSERT INTO endereco (cep, logradouro, numero, complemento, bairro, cidade, estado)
        VALUES (r.cep, r.logradouro, r.numero, r.complemento, r.bairro, r.cidade, r.estado)
        RETURNING id_endereco INTO novo;

        UPDATE clientes SET id_endereco = novo WHERE id_cliente = r.id_cliente;
    END LOOP;
END $$;

DO $$
DECLARE
    r    RECORD;
    novo integer;
BEGIN
    FOR r IN
        SELECT id_fornecedor, cep, logradouro, numero, complemento, bairro, cidade, estado
          FROM fornecedores
         WHERE COALESCE(TRIM(cep), '')         <> ''
            OR COALESCE(TRIM(logradouro), '')  <> ''
            OR COALESCE(TRIM(numero), '')      <> ''
            OR COALESCE(TRIM(complemento), '') <> ''
            OR COALESCE(TRIM(bairro), '')      <> ''
            OR COALESCE(TRIM(cidade), '')      <> ''
            OR COALESCE(TRIM(estado), '')      <> ''
         ORDER BY id_fornecedor
    LOOP
        INSERT INTO endereco (cep, logradouro, numero, complemento, bairro, cidade, estado)
        VALUES (r.cep, r.logradouro, r.numero, r.complemento, r.bairro, r.cidade, r.estado)
        RETURNING id_endereco INTO novo;

        UPDATE fornecedores SET id_endereco = novo WHERE id_fornecedor = r.id_fornecedor;
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- 5. conferência: nenhum dado pode ter se perdido no caminho
-- ---------------------------------------------------------------------------

-- Compara cada campo da origem com o que chegou em endereco. Linha sem
-- endereço nenhum também passa: ali os dois lados são vazios.
DO $$
DECLARE div integer;
BEGIN
    SELECT COUNT(*) INTO div
      FROM clientes c
      LEFT JOIN endereco e ON e.id_endereco = c.id_endereco
     WHERE COALESCE(TRIM(c.cep), '')         <> COALESCE(TRIM(e.cep), '')
        OR COALESCE(TRIM(c.logradouro), '')  <> COALESCE(TRIM(e.logradouro), '')
        OR COALESCE(TRIM(c.numero), '')      <> COALESCE(TRIM(e.numero), '')
        OR COALESCE(TRIM(c.complemento), '') <> COALESCE(TRIM(e.complemento), '')
        OR COALESCE(TRIM(c.bairro), '')      <> COALESCE(TRIM(e.bairro), '')
        OR COALESCE(TRIM(c.cidade), '')      <> COALESCE(TRIM(e.cidade), '')
        OR COALESCE(TRIM(c.estado), '')      <> COALESCE(TRIM(e.estado), '');

    IF div > 0 THEN
        RAISE EXCEPTION 'endereco de clientes divergiu em % linha(s): nada foi removido', div;
    END IF;

    SELECT COUNT(*) INTO div
      FROM fornecedores f
      LEFT JOIN endereco e ON e.id_endereco = f.id_endereco
     WHERE COALESCE(TRIM(f.cep), '')         <> COALESCE(TRIM(e.cep), '')
        OR COALESCE(TRIM(f.logradouro), '')  <> COALESCE(TRIM(e.logradouro), '')
        OR COALESCE(TRIM(f.numero), '')      <> COALESCE(TRIM(e.numero), '')
        OR COALESCE(TRIM(f.complemento), '') <> COALESCE(TRIM(e.complemento), '')
        OR COALESCE(TRIM(f.bairro), '')      <> COALESCE(TRIM(e.bairro), '')
        OR COALESCE(TRIM(f.cidade), '')      <> COALESCE(TRIM(e.cidade), '')
        OR COALESCE(TRIM(f.estado), '')      <> COALESCE(TRIM(e.estado), '');

    IF div > 0 THEN
        RAISE EXCEPTION 'endereco de fornecedores divergiu em % linha(s): nada foi removido', div;
    END IF;

    -- O usuário não teve dado copiado, só a FK invertida: toda linha que existia
    -- em endereco precisa ter encontrado seu usuário.
    SELECT COUNT(*) INTO div
      FROM endereco e
      LEFT JOIN usuario u ON u.id_endereco = e.id_endereco
     WHERE e.id_usuario IS NOT NULL AND u.id_usuario IS NULL;

    IF div > 0 THEN
        RAISE EXCEPTION '% endereco(s) de usuario ficaram sem ligação: nada foi removido', div;
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 6. documento: o CPF/CNPJ vira coluna da própria entidade
-- ---------------------------------------------------------------------------

-- clientes.cpf_cnpj já existe e é reaproveitada.
-- fornecedores tinha o nome invertido.
ALTER TABLE fornecedores RENAME COLUMN cnpj_cpf TO cpf_cnpj;

ALTER TABLE usuario ADD COLUMN cpf_cnpj varchar(18);

UPDATE usuario u
   SET cpf_cnpj = NULLIF(TRIM(d.cpf), '')
  FROM documento d
 WHERE d.id_usuario = u.id_usuario
   AND COALESCE(TRIM(d.cpf), '') <> '';

DO $$
DECLARE pend integer;
BEGIN
    -- Nenhum CPF preenchido pode ter ficado para trás.
    SELECT COUNT(*) INTO pend
      FROM documento d
      JOIN usuario u ON u.id_usuario = d.id_usuario
     WHERE COALESCE(TRIM(d.cpf), '') <> ''
       AND COALESCE(TRIM(u.cpf_cnpj), '') <> COALESCE(TRIM(d.cpf), '');

    IF pend > 0 THEN
        RAISE EXCEPTION '% cpf(s) não chegaram em usuario.cpf_cnpj: nada foi removido', pend;
    END IF;

    -- rg e cnh não têm destino nesta estrutura. Estavam inteiramente vazios quando a
    -- migration foi escrita; se houver conteúdo, para tudo em vez de descartar.
    SELECT COUNT(*) INTO pend
      FROM documento
     WHERE COALESCE(TRIM(rg), '') <> ''
        OR COALESCE(TRIM(cnh), '') <> '';

    IF pend > 0 THEN
        RAISE EXCEPTION
            '% linha(s) de documento têm rg ou cnh preenchidos; esses campos não têm destino na nova estrutura. Resolva antes de aplicar esta migration.',
            pend;
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- 7. só agora o que ficou redundante é removido
-- ---------------------------------------------------------------------------

ALTER TABLE endereco DROP CONSTRAINT fk_endereco_usuario;
ALTER TABLE endereco DROP COLUMN id_usuario;

ALTER TABLE clientes
    DROP COLUMN cep,
    DROP COLUMN logradouro,
    DROP COLUMN numero,
    DROP COLUMN complemento,
    DROP COLUMN bairro,
    DROP COLUMN cidade,
    DROP COLUMN estado;

ALTER TABLE fornecedores
    DROP COLUMN cep,
    DROP COLUMN logradouro,
    DROP COLUMN numero,
    DROP COLUMN complemento,
    DROP COLUMN bairro,
    DROP COLUMN cidade,
    DROP COLUMN estado;

DROP TABLE documento;

-- ---------------------------------------------------------------------------
-- 8. endereços sem nenhum conteúdo não precisam existir
-- ---------------------------------------------------------------------------

-- O cadastro de usuário criava uma linha vazia em endereco para todo usuário
-- novo (e outra em documento). O WHERE abaixo é a própria prova de que nada se
-- perde: só casa linha em que todos os campos estão vazios. O ON DELETE SET
-- NULL da FK cuida de zerar usuario.id_endereco.
DELETE FROM endereco
 WHERE COALESCE(TRIM(cep), '')         = ''
   AND COALESCE(TRIM(logradouro), '')  = ''
   AND COALESCE(TRIM(numero), '')      = ''
   AND COALESCE(TRIM(complemento), '') = ''
   AND COALESCE(TRIM(bairro), '')      = ''
   AND COALESCE(TRIM(cidade), '')      = ''
   AND COALESCE(TRIM(estado), '')      = ''
   AND COALESCE(TRIM(referencia), '')  = '';

-- ---------------------------------------------------------------------------
-- 9. tabela de outro projeto, fora do schema
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS public.produtos;

COMMIT;
