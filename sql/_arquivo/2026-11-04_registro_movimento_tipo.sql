--
-- 2026-11-04_registro_movimento_tipo.sql
--
-- Aumenta registro_movimento.tipo de varchar(20) para varchar(40).
--
-- O PROBLEMA
--   functions/material/relacao.php grava o tipo 'Relacionamento Material', que
--   tem 23 caracteres. Com a coluna em varchar(20) o INSERT de auditoria falha:
--
--     ERRO:  valor e muito longo para tipo character varying(20)
--
--   e quem cria ou inativa um relacionamento de material pela tela recebe erro.
--   O tipo e um rotulo livre (nao ha CHECK nem tabela de dominio), entao a
--   correcao e no tamanho da coluna, nao no texto: assim os registros de
--   auditoria continuam com o mesmo rotulo das demais telas.
--
-- 40 da folga para rotulos futuros sem virar texto livre.
--
-- Aumentar varchar nao reescreve a tabela nem mexe nos dados existentes.
-- Nao e idempotente no sentido estrito, mas reaplicar e inofensivo: alterar
-- para o mesmo tamanho nao faz nada.
--

SET search_path TO reciclagem;

BEGIN;

ALTER TABLE registro_movimento ALTER COLUMN tipo TYPE varchar(40);

DO $$
BEGIN
    IF (SELECT character_maximum_length FROM information_schema.columns
         WHERE table_schema = 'reciclagem' AND table_name = 'registro_movimento'
           AND column_name = 'tipo') <> 40 THEN
        RAISE EXCEPTION 'registro_movimento.tipo nao ficou com 40 caracteres';
    END IF;
END $$;

COMMIT;
