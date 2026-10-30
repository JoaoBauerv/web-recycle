-- =====================================================================
-- Módulo de Estoque — alerta de estoque mínimo (roadmap 1.4)
-- =====================================================================
-- Única alteração de banco do módulo: os itens 1.1 a 1.3 usam apenas
-- estruturas que já existem (tb_material.qt_estoque e estoque_movimentacoes).
--
-- NULL significa "sem mínimo definido": o material simplesmente não entra no
-- alerta. Por isso a coluna é nullable e sem default — zero seria um mínimo
-- legítimo ("me avise quando zerar") e não pode se confundir com "não configurado".
-- =====================================================================

BEGIN;

ALTER TABLE tb_material
    ADD COLUMN IF NOT EXISTS estoque_minimo numeric(10,2);

COMMENT ON COLUMN tb_material.estoque_minimo IS
    'Saldo a partir do qual o material é sinalizado como baixo. NULL = sem alerta configurado.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_material_estoque_minimo') THEN
        ALTER TABLE tb_material
            ADD CONSTRAINT ck_material_estoque_minimo CHECK (estoque_minimo IS NULL OR estoque_minimo >= 0);
    END IF;
END $$;

-- Índice parcial: a tela de estoque e o painel inicial só varrem quem tem mínimo.
CREATE INDEX IF NOT EXISTS ix_material_estoque_minimo
    ON tb_material (id_material)
    WHERE estoque_minimo IS NOT NULL;

COMMIT;
