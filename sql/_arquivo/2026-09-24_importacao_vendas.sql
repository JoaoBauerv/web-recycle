-- =====================================================================
-- Importação de vendas por planilha
-- =====================================================================
-- Acrescenta:
--   1. código oficial do material (não existia)
--   2. modelos de importação (mapeamento de colunas)
--   3. de/para de materiais (nome/código externo -> material oficial)
--   4. histórico de importações
--   5. origem e pedido externo na venda
--
-- Tudo é aditivo: nenhuma coluna existente muda de tipo ou some.
-- =====================================================================

BEGIN;

-- ------------------------------------------------------------ 1. código
ALTER TABLE tb_material
    ADD COLUMN IF NOT EXISTS codigo varchar(30);

COMMENT ON COLUMN tb_material.codigo IS
    'Código oficial do material, usado como chave no de/para das importações.';

-- Parcial: só materiais ativos com código preenchido disputam a unicidade,
-- então material inativado não impede reaproveitar o código depois.
CREATE UNIQUE INDEX IF NOT EXISTS uq_material_codigo
    ON tb_material (LOWER(codigo))
    WHERE codigo IS NOT NULL AND status = 1;

-- --------------------------------------------- 2. modelos de importação
CREATE TABLE IF NOT EXISTS importacao_modelo (
    id_modelo       serial PRIMARY KEY,
    nome            varchar(100) NOT NULL,
    id_fornecedor   integer REFERENCES fornecedores(id_fornecedor),
    linha_cabecalho smallint NOT NULL DEFAULT 1,
    mapeamento      jsonb NOT NULL,
    status          smallint NOT NULL DEFAULT 1,
    data_cadastro   timestamp NOT NULL DEFAULT now(),
    data_alteracao  timestamp
);

COMMENT ON TABLE importacao_modelo IS
    'Como ler a planilha de um parceiro: qual coluna é material, quantidade, etc.';
COMMENT ON COLUMN importacao_modelo.mapeamento IS
    'JSON campo_do_sistema -> índice da coluna. Ex: {"material":1,"quantidade":2}';
COMMENT ON COLUMN importacao_modelo.id_fornecedor IS
    'Amarra o modelo a um parceiro; também delimita o de/para daquela origem.';

CREATE UNIQUE INDEX IF NOT EXISTS uq_modelo_nome
    ON importacao_modelo (LOWER(nome))
    WHERE status = 1;

-- ------------------------------------------------ 3. de/para de material
CREATE TABLE IF NOT EXISTS material_relacao (
    id_relacao         serial PRIMARY KEY,
    id_material        integer NOT NULL REFERENCES tb_material(id_material),
    nome_externo       varchar(150),
    nome_normalizado   varchar(150),
    codigo_externo     varchar(60),
    codigo_normalizado varchar(60),
    id_modelo          integer REFERENCES importacao_modelo(id_modelo),
    origem             varchar(100),
    status             smallint NOT NULL DEFAULT 1,
    data_cadastro      timestamp NOT NULL DEFAULT now(),
    data_alteracao     timestamp,
    CONSTRAINT ck_relacao_tem_chave
        CHECK (nome_normalizado IS NOT NULL OR codigo_normalizado IS NOT NULL)
);

COMMENT ON TABLE material_relacao IS
    'De/para: nomes e códigos externos que representam um material oficial.';
COMMENT ON COLUMN material_relacao.nome_normalizado IS
    'Nome externo sem acento/maiúscula/pontuação, gravado pelo PHP na inserção.';
COMMENT ON COLUMN material_relacao.id_modelo IS
    'Quando preenchido, a relação vale só para aquela origem: o mesmo "PET B" pode ser materiais diferentes em parceiros diferentes.';

-- COALESCE(id_modelo, 0): em índice único o NULL não colide com NULL, então
-- sem isso daria para cadastrar a mesma relação global várias vezes.
CREATE UNIQUE INDEX IF NOT EXISTS uq_relacao_nome
    ON material_relacao (nome_normalizado, COALESCE(id_modelo, 0))
    WHERE nome_normalizado IS NOT NULL AND status = 1;

CREATE UNIQUE INDEX IF NOT EXISTS uq_relacao_codigo
    ON material_relacao (codigo_normalizado, COALESCE(id_modelo, 0))
    WHERE codigo_normalizado IS NOT NULL AND status = 1;

CREATE INDEX IF NOT EXISTS ix_relacao_material
    ON material_relacao (id_material);

-- ----------------------------------------------------- 4. histórico
CREATE TABLE IF NOT EXISTS importacao_historico (
    id_importacao        serial PRIMARY KEY,
    arquivo              varchar(255) NOT NULL,
    id_modelo            integer REFERENCES importacao_modelo(id_modelo),
    id_usuario           integer NOT NULL REFERENCES tb_usuario(id_usuario),
    pedido_externo       varchar(60),
    total_registros      integer NOT NULL DEFAULT 0,
    registros_importados integer NOT NULL DEFAULT 0,
    registros_com_erro   integer NOT NULL DEFAULT 0,
    valor_total          numeric(12,2) NOT NULL DEFAULT 0,
    status               varchar(25) NOT NULL DEFAULT 'processando',
    erros                jsonb,
    data_importacao      timestamp NOT NULL DEFAULT now(),
    CONSTRAINT ck_importacao_status
        CHECK (status IN ('processando','concluido','concluido_avisos','erro','cancelado'))
);

COMMENT ON TABLE importacao_historico IS
    'Uma linha por tentativa de importação, inclusive as canceladas.';

CREATE INDEX IF NOT EXISTS ix_importacao_data
    ON importacao_historico (data_importacao DESC);

-- ------------------------------------------- 5. origem/pedido na venda
ALTER TABLE vendas
    ADD COLUMN IF NOT EXISTS origem         varchar(15) NOT NULL DEFAULT 'manual',
    ADD COLUMN IF NOT EXISTS pedido_externo varchar(60),
    ADD COLUMN IF NOT EXISTS id_importacao  integer;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_venda_importacao') THEN
        ALTER TABLE vendas
            ADD CONSTRAINT fk_venda_importacao
            FOREIGN KEY (id_importacao) REFERENCES importacao_historico(id_importacao);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_venda_origem') THEN
        ALTER TABLE vendas
            ADD CONSTRAINT ck_venda_origem CHECK (origem IN ('manual','importacao'));
    END IF;
END $$;

COMMENT ON COLUMN vendas.origem IS 'manual = digitada na tela; importacao = veio de planilha.';

-- A trava de verdade contra importar o mesmo pedido duas vezes: mesmo com duas
-- abas abertas ao mesmo tempo, o banco recusa a segunda.
CREATE UNIQUE INDEX IF NOT EXISTS uq_venda_pedido_externo
    ON vendas (LOWER(pedido_externo))
    WHERE pedido_externo IS NOT NULL;

COMMIT;
