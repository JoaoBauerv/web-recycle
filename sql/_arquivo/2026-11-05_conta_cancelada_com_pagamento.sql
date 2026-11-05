--
-- 2026-11-05_conta_cancelada_com_pagamento.sql
--
-- Devolve a 'ativa' as despesas canceladas que têm parcela paga.
--
-- O PROBLEMA
--   Cancelar uma despesa marcava contas_pagar.status = 'cancelada' mesmo quando
--   parte dela já tinha sido paga. As parcelas pagas continuavam 'paga' — o
--   código dizia, com razão, que o dinheiro saiu de verdade — mas o painel, a
--   tela de contas e o relatório financeiro filtram c.status = 'ativa', então
--   esses pagamentos sumiam de todas as somas.
--
--   A partir de 05/11/2026 o cancelamento é por parcela, e a despesa só passa a
--   'cancelada' quando não sobra nenhuma parcela fora de 'cancelada'. Esta
--   migration aplica a mesma regra ao que já estava gravado.
--
-- O QUE MUDA
--   Só contas_pagar.status das despesas nessa situação. As parcelas pendentes
--   delas já estão 'cancelada' e continuam assim; as pagas voltam às somas.
--   A anotação [CANCELADA: motivo] em observacoes é mantida como histórico.
--
-- Idempotente: numa segunda passada não há mais linha que case.
--

SET search_path TO reciclagem;

BEGIN;

DO $$
DECLARE
    n int;
BEGIN
    UPDATE contas_pagar c
       SET status = 'ativa'
     WHERE c.status = 'cancelada'
       AND EXISTS (SELECT 1 FROM contas_pagar_parcelas pa
                    WHERE pa.id_conta = c.id_conta AND pa.status = 'paga');
    GET DIAGNOSTICS n = ROW_COUNT;
    RAISE NOTICE 'despesas canceladas com parcela paga devolvidas a ativa: %', n;

    IF EXISTS (SELECT 1 FROM contas_pagar c
                WHERE c.status = 'cancelada'
                  AND EXISTS (SELECT 1 FROM contas_pagar_parcelas pa
                               WHERE pa.id_conta = c.id_conta AND pa.status = 'paga')) THEN
        RAISE EXCEPTION 'ainda ha despesa cancelada com parcela paga';
    END IF;
END $$;

COMMIT;
