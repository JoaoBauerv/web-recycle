--
-- 2026-10-14_remove_colunas_sem_uso.sql
--
-- Fase 3 da padronização do schema: remove 8 colunas.
--
-- O critério foi estreito de propósito. Cada coluna aqui se encaixa em um dos
-- três casos abaixo, e em nenhum deles existe informação que deixe de existir:
--
--   A) DERIVÁVEL  o valor é reconstruível exatamente a partir de outras linhas
--                 da própria base, por COUNT ou pelo tamanho de um log
--   B) DUPLICADA  o mesmo fato já é registrado em registro_movimento, pela
--                 trilha de auditoria, e este lado não tem nenhum leitor
--   C) MORTA      nenhuma linha de código escreve nem lê a coluna
--
-- Coluna que apenas está vazia no banco de desenvolvimento NÃO entrou: numa
-- base de 7 usuários isso não distingue coluna morta de campo ainda não usado.
-- Também ficaram de fora, por decisão do projeto, qt_estoque, saldo_apos,
-- valor_total, peso_material, os totais de documento e as colunas de
-- cancelamento — são redundâncias deliberadas, e os CHECK que as acompanham
-- seguem intactos.
--
-- SEGURANÇA: cada coluna do caso (A) tem uma conferência que recalcula o valor
-- a partir da origem e aborta a transação inteira se encontrar divergência.
--

SET search_path TO reciclagem;

BEGIN;

-- ---------------------------------------------------------------------------
-- (A) DERIVÁVEL — contadores que são um COUNT
-- ---------------------------------------------------------------------------

-- contas_pagar_parcelas.total_parcelas repete, em todas as parcelas da conta,
-- quantas parcelas ela tem — ou seja, COUNT(*) por id_conta.
--
-- A igualdade não é acidente: a edição de uma despesa só regera o parcelamento
-- quando NENHUMA parcela foi paga (functions/financeiro/contas.php), e nesse
-- caso apaga todas e recria. Não existe caminho que deixe a conta com um número
-- de parcelas diferente do que a coluna afirma.
DO $$
DECLARE div integer;
BEGIN
    SELECT COUNT(*) INTO div FROM (
        SELECT id_conta
          FROM contas_pagar_parcelas
         GROUP BY id_conta
        HAVING MAX(total_parcelas) <> COUNT(*)
            OR COUNT(DISTINCT total_parcelas) > 1
    ) x;

    IF div > 0 THEN
        RAISE EXCEPTION
            'total_parcelas nao equivale a COUNT(*) em % conta(s); a coluna nao e derivavel nesta base e nada foi removido',
            div;
    END IF;
END $$;

-- ATENÇÃO: o DROP abaixo derruba junto, em silêncio, o CHECK que dependia da
-- coluna — o PostgreSQL remove toda constraint que envolva a coluna removida.
-- Era:
--     ck_parcela_numero  CHECK (numero_parcela >= 1 AND numero_parcela <= total_parcelas)
--
-- A metade de baixo é reposta logo depois. A de cima não tem como voltar: um
-- CHECK só vê a própria linha, e o total passou a ser um COUNT sobre as outras.
-- O que se perde é a recusa de uma linha como "parcela 5/3". Em troca, a
-- UNIQUE (id_conta, numero_parcela) e a numeração sequencial de
-- contasGerarParcelas() continuam de pé, e o total exibido passa a ser medido
-- em vez de declarado — não há mais como a coluna discordar da realidade.
ALTER TABLE contas_pagar_parcelas DROP COLUMN total_parcelas;

ALTER TABLE contas_pagar_parcelas
    ADD CONSTRAINT ck_parcela_numero CHECK (numero_parcela >= 1);

-- importacao_historico.registros_com_erro é o tamanho da lista guardada em
-- 'erros' (o mesmo count($problemas) alimenta os dois) e também a diferença
-- entre total_registros e registros_importados. Redundante duas vezes.
DO $$
DECLARE div integer;
BEGIN
    SELECT COUNT(*) INTO div
      FROM importacao_historico
     WHERE COALESCE(registros_com_erro, 0)
           <> jsonb_array_length(COALESCE(erros, '[]'::jsonb));

    IF div > 0 THEN
        RAISE EXCEPTION
            'registros_com_erro divergiu do tamanho de erros em % importacao(oes); nada foi removido',
            div;
    END IF;
END $$;

ALTER TABLE importacao_historico DROP COLUMN registros_com_erro;

-- ---------------------------------------------------------------------------
-- (B) DUPLICADA — auditoria que registro_movimento já guarda
-- ---------------------------------------------------------------------------

-- Toda criação, edição, cancelamento, pagamento e estorno no financeiro chama
-- contasAuditar(), que grava em registro_movimento o usuário, a data e a
-- descrição do que houve. Estas colunas repetem o "quem" e o "quando" desse
-- mesmo evento, e nenhuma tela, relatório ou regra as consulta — são escritas e
-- nunca lidas.
ALTER TABLE contas_pagar
    DROP COLUMN id_usuario_cadastro,
    DROP COLUMN data_alteracao,
    DROP COLUMN id_usuario_alteracao;

-- Com isso contas_pagar deixa de ter três FKs para usuario e passa a ter uma:
-- id_usuario_func, o favorecido interno, que é a única de fato consultada.

-- data_registro_pagamento é o instante em que o pagamento foi digitado. A data
-- que o sistema usa e exibe é data_pagamento, a do pagamento em si. O instante
-- da digitação já está em registro_movimento, e esta coluna nunca foi lida.
ALTER TABLE contas_pagar_parcelas DROP COLUMN data_registro_pagamento;

-- material_relacao: a edição e a exclusão lógica de um de/para chamam
-- registraMovimentacao(). data_alteracao repetia o quando, sem leitor.
ALTER TABLE material_relacao DROP COLUMN data_alteracao;

-- ---------------------------------------------------------------------------
-- (C) MORTA — nunca escrita, nunca lida
-- ---------------------------------------------------------------------------

-- Criada por simetria com data_cadastro e nunca ligada a nada: não existe
-- UPDATE de importacao_modelo que a preencha, nem SELECT que a leia.
ALTER TABLE importacao_modelo DROP COLUMN data_alteracao;

COMMIT;
