--
-- 2026-11-05_remove_recorrencia_despesa.sql
--
-- Remove a recorrencia de contas_pagar: as colunas recorrente,
-- recorrencia_periodo, recorrencia_dia, recorrencia_inicio e recorrencia_fim, e
-- a constraint ck_conta_recorrencia que as amarrava.
--
-- POR QUE
--   A tela permitia marcar a despesa como recorrente, mas nada gerava as
--   proximas contas: o dado era gravado, mostrado como um icone e nunca usado.
--   A decisao foi nao automatizar. Despesa que se repete e lancada com o numero
--   de parcelas que cobre o periodo (12x mensal para um ano de aluguel, por
--   exemplo), pelo parcelamento que ja existe e ja gera, vence e baixa cada
--   parcela.
--
-- O QUE SE PERDE
--   So a marcacao. Nenhuma parcela e tocada. Quantas contas estavam marcadas
--   como recorrentes sai num NOTICE, para constar no log da aplicacao.
--
-- Nao e idempotente: uma segunda passada falha porque as colunas ja sairam.
--

SET search_path TO reciclagem;

BEGIN;

DO $$
DECLARE
    n_rec int;
BEGIN
    SELECT COUNT(*) INTO n_rec FROM contas_pagar WHERE recorrente;
    RAISE NOTICE 'contas marcadas como recorrentes (marcacao descartada): %', n_rec;
END $$;

ALTER TABLE contas_pagar DROP CONSTRAINT ck_conta_recorrencia;

ALTER TABLE contas_pagar
    DROP COLUMN recorrente,
    DROP COLUMN recorrencia_periodo,
    DROP COLUMN recorrencia_dia,
    DROP COLUMN recorrencia_inicio,
    DROP COLUMN recorrencia_fim;

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'reciclagem' AND table_name = 'contas_pagar'
                  AND column_name LIKE 'recorr%') THEN
        RAISE EXCEPTION 'ainda ha colunas de recorrencia em contas_pagar';
    END IF;
END $$;

COMMIT;
