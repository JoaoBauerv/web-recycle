-- =====================================================================
-- Cancelamento de compra e de venda, com estorno de estoque
-- =====================================================================
-- Documento cancelado não é documento apagado: ele continua visível na
-- listagem e no detalhe, com selo "Cancelada", porque a compra existiu, o
-- dinheiro circulou e a trilha precisa explicar o que aconteceu. O que o
-- cancelamento faz é tirar o documento das somas (painel, relatórios, margem,
-- último preço) e devolver o estoque ao estado anterior.
--
-- Por que status textual e não o status = 0/1 de tb_material e clientes:
-- naquelas tabelas o campo é exclusão lógica de cadastro. Aqui é situação de
-- documento, o mesmo papel de vendas.status ('concluida') e de
-- contas_pagar.status ('ativa'/'cancelada') — e é esse padrão que se segue.
--
-- O cancelamento é por documento inteiro, nunca por item: cancelar meia compra
-- seria uma edição de compra, que é outra operação e não existe no sistema.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. tb_pesagem (compras) — a tabela não tinha coluna de situação nenhuma
-- ---------------------------------------------------------------------
ALTER TABLE tb_pesagem
    ADD COLUMN IF NOT EXISTS status              VARCHAR(20) NOT NULL DEFAULT 'ativa',
    ADD COLUMN IF NOT EXISTS cancelada_em        TIMESTAMP   NULL,
    ADD COLUMN IF NOT EXISTS id_usuario_cancelou INTEGER     NULL,
    ADD COLUMN IF NOT EXISTS motivo_cancelamento VARCHAR(500) NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_pesagem_status') THEN
        ALTER TABLE tb_pesagem
            ADD CONSTRAINT ck_pesagem_status CHECK (status IN ('ativa', 'cancelada'));
    END IF;

    -- Compra cancelada sem data, autor ou motivo não é auditável: ou os três
    -- estão preenchidos, ou a compra não está cancelada.
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_pesagem_cancelamento') THEN
        ALTER TABLE tb_pesagem
            ADD CONSTRAINT ck_pesagem_cancelamento CHECK (
                status <> 'cancelada'
                OR (cancelada_em IS NOT NULL
                    AND id_usuario_cancelou IS NOT NULL
                    AND motivo_cancelamento IS NOT NULL
                    AND btrim(motivo_cancelamento) <> '')
            );
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_pesagem_usuario_cancelou') THEN
        ALTER TABLE tb_pesagem
            ADD CONSTRAINT fk_pesagem_usuario_cancelou
                FOREIGN KEY (id_usuario_cancelou) REFERENCES tb_usuario (id_usuario);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_pesagem_status ON tb_pesagem (status);

COMMENT ON COLUMN tb_pesagem.status IS
    'Situação do documento: ativa ou cancelada. Compra cancelada fica fora das somas, mas continua visível.';
COMMENT ON COLUMN tb_pesagem.motivo_cancelamento IS
    'Obrigatório quando status = cancelada (ck_pesagem_cancelamento).';

-- ---------------------------------------------------------------------
-- 2. vendas — status já existia, mas sem CHECK: qualquer texto entrava
-- ---------------------------------------------------------------------
ALTER TABLE vendas
    ADD COLUMN IF NOT EXISTS cancelada_em        TIMESTAMP   NULL,
    ADD COLUMN IF NOT EXISTS id_usuario_cancelou INTEGER     NULL,
    ADD COLUMN IF NOT EXISTS motivo_cancelamento VARCHAR(500) NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_venda_status') THEN
        ALTER TABLE vendas
            ADD CONSTRAINT ck_venda_status CHECK (status IN ('concluida', 'cancelada'));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_venda_cancelamento') THEN
        ALTER TABLE vendas
            ADD CONSTRAINT ck_venda_cancelamento CHECK (
                status <> 'cancelada'
                OR (cancelada_em IS NOT NULL
                    AND id_usuario_cancelou IS NOT NULL
                    AND motivo_cancelamento IS NOT NULL
                    AND btrim(motivo_cancelamento) <> '')
            );
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_venda_usuario_cancelou') THEN
        ALTER TABLE vendas
            ADD CONSTRAINT fk_venda_usuario_cancelou
                FOREIGN KEY (id_usuario_cancelou) REFERENCES tb_usuario (id_usuario);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_vendas_status ON vendas (status);

COMMENT ON COLUMN vendas.motivo_cancelamento IS
    'Obrigatório quando status = cancelada (ck_venda_cancelamento).';

-- Decisão de 01/10/2026: venda cancelada NÃO libera o pedido do cliente para
-- reimportação. O índice uq_venda_pedido_externo continua sem filtro de status,
-- de propósito: cada número de pedido aparece uma única vez no sistema, e
-- tentar importá-lo de novo devolve "Pedido X já foi importado na venda #N".
-- Para mudar isso bastaria recriar o índice com AND status <> 'cancelada' e
-- acrescentar o mesmo filtro em functions/venda/importacao_acoes.php.

-- ---------------------------------------------------------------------
-- 3. estoque_movimentacoes — origem própria para o estorno
-- ---------------------------------------------------------------------
-- O estorno recebe origem_tipo próprio em vez de reusar 'pesagem'/'venda':
-- assim o extrato do material distingue o lançamento original do seu estorno
-- sem depender de ler a coluna observacoes, e a trilha fica filtrável.
-- origem_id continua apontando para o documento estornado.
ALTER TABLE estoque_movimentacoes DROP CONSTRAINT IF EXISTS ck_mov_origem;

ALTER TABLE estoque_movimentacoes
    ADD CONSTRAINT ck_mov_origem CHECK (origem_tipo IN (
        'pesagem',
        'venda',
        'ajuste_manual',
        'processamento',
        'estorno_compra',
        'estorno_venda'
    ));

COMMENT ON COLUMN estoque_movimentacoes.origem_tipo IS
    'De onde veio o lançamento. estorno_compra e estorno_venda são gerados pelo cancelamento do documento, com origem_id apontando para ele. Os sinais ficam em estoqueSinal() (functions/estoque/estoque_lib.php).';

COMMIT;
