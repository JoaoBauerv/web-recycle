--
-- 2026-11-04_registro_movimento_descricao.sql
--
-- Aumenta registro_movimento.descricao de varchar(100) para varchar(255).
--
-- O PROBLEMA
--   As descrições de auditoria do financeiro carregam texto livre: a descrição
--   da despesa (até 150 caracteres) e o motivo do cancelamento. Com a coluna em
--   100, o INSERT falhava com
--
--     ERRO:  valor e muito longo para tipo character varying(100)
--
--   e, como a auditoria roda depois do commit da operação, o usuário via erro
--   numa ação que JA tinha sido gravada: a despesa criada aparecia como "não foi
--   possível salvar" (e era lançada de novo, em duplicidade), e o pagamento de
--   parcela terminava em erro fatal com a parcela já paga.
--
-- A correção tem duas partes: esta coluna maior e o corte em 255 dentro de
-- registraMovimentacao() (functions/funcoes.php), para nenhum texto futuro
-- voltar a derrubar a operação.
--
-- Aumentar varchar não reescreve a tabela nem mexe nos dados existentes, e
-- reaplicar é inofensivo.
--

SET search_path TO reciclagem;

BEGIN;

ALTER TABLE registro_movimento ALTER COLUMN descricao TYPE varchar(255);

DO $$
BEGIN
    IF (SELECT character_maximum_length FROM information_schema.columns
         WHERE table_schema = 'reciclagem' AND table_name = 'registro_movimento'
           AND column_name = 'descricao') <> 255 THEN
        RAISE EXCEPTION 'registro_movimento.descricao nao ficou com 255 caracteres';
    END IF;
END $$;

COMMIT;
