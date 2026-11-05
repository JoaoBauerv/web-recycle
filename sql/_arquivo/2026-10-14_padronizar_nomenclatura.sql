--
-- 2026-10-14_padronizar_nomenclatura.sql
--
-- Fase 1 da padronizacao do schema.
--
-- 1. Remove o prefixo tb_ das 8 tabelas que ainda o carregavam. A divisao nao
--    era aleatoria: as com prefixo eram as mais antigas, as sem prefixo vieram
--    dos modulos novos. Era a historia do projeto aparecendo no schema.
--
-- 2. Padroniza as chaves primarias genericas. Cinco tabelas chamavam a PK
--    apenas de 'id'; passam a id_<entidade>, como as outras quinze ja faziam.
--
-- 3. Renomeia as sequences, que o PostgreSQL NAO renomeia junto com a tabela.
--
-- O que NAO muda:
--   - FKs com sufixo de papel (id_usuario_cancelou, id_usuario_cadastro,
--     id_usuario_func...) sao preservadas. Tres tabelas referenciam usuario
--     mais de uma vez, e unificar os nomes causaria colisao. O sufixo nao e
--     inconsistencia: e o que distingue quem cadastrou de quem cancelou.
--   - Plural e singular continuam convivendo (usuario x clientes x vendas).
--     Singularizar nao estava no escopo e dobraria a superficie de mudanca.
--
-- O PostgreSQL propaga o rename para FKs, indices e constraints automaticamente.
--

SET search_path TO reciclagem;

BEGIN;

-- ------------------------------------------------------------------ tabelas
ALTER TABLE tb_usuario            RENAME TO usuario;
ALTER TABLE tb_material           RENAME TO material;
ALTER TABLE tb_pesagem            RENAME TO pesagem;
ALTER TABLE tb_pesagem_material   RENAME TO pesagem_material;
ALTER TABLE tb_registro_movimento RENAME TO registro_movimento;
ALTER TABLE tb_evento             RENAME TO evento;
ALTER TABLE tb_endereco           RENAME TO endereco;
ALTER TABLE tb_documento          RENAME TO documento;

-- ------------------------------------------------- chaves primarias genericas
ALTER TABLE pesagem_material   RENAME COLUMN id TO id_pesagem_material;
ALTER TABLE registro_movimento RENAME COLUMN id TO id_registro_movimento;
ALTER TABLE evento             RENAME COLUMN id TO id_evento;
ALTER TABLE endereco           RENAME COLUMN id TO id_endereco;
ALTER TABLE documento          RENAME COLUMN id TO id_documento;

-- ---------------------------------------------------------------- sequences
ALTER SEQUENCE tb_usuario_id_usuario_seq      RENAME TO usuario_id_usuario_seq;
ALTER SEQUENCE tb_material_id_material_seq    RENAME TO material_id_material_seq;
ALTER SEQUENCE tb_pesagem_id_pesagem_seq      RENAME TO pesagem_id_pesagem_seq;
ALTER SEQUENCE tb_pesagem_material_id_seq     RENAME TO pesagem_material_id_pesagem_material_seq;
ALTER SEQUENCE tb_registro_movimento_id_seq   RENAME TO registro_movimento_id_registro_movimento_seq;
ALTER SEQUENCE tb_evento_id_seq               RENAME TO evento_id_evento_seq;
ALTER SEQUENCE tb_endereco_id_seq             RENAME TO endereco_id_endereco_seq;
ALTER SEQUENCE tb_documento_id_seq            RENAME TO documento_id_documento_seq;

COMMIT;
