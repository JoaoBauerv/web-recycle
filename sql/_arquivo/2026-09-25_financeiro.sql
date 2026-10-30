-- =====================================================================
-- Módulo Financeiro — Contas a Pagar
-- =====================================================================
-- A análise de margem NÃO precisa de tabela nova: ela é calculada sobre
-- tb_pesagem_material, vendas_itens, tb_material e estoque_movimentacoes.
-- Tudo aqui é da parte de despesas.
--
-- Sobre o "fornecedor" da despesa: a tabela `fornecedores` deste sistema é
-- quem COMPRA material da empresa, não quem vende serviço para ela. Usar
-- id_fornecedor numa despesa misturaria os dois papéis. Por isso o favorecido
-- é texto livre, com vínculo opcional a tb_usuario quando a despesa é de
-- funcionário.
-- =====================================================================

BEGIN;

-- ------------------------------------------------- categorias de despesa
CREATE TABLE IF NOT EXISTS despesa_categoria (
    id_categoria  serial PRIMARY KEY,
    nome          varchar(60) NOT NULL,
    -- 'funcionario' habilita o vínculo com tb_usuario no formulário.
    tipo          varchar(20) NOT NULL DEFAULT 'geral',
    status        smallint NOT NULL DEFAULT 1,
    data_cadastro timestamp NOT NULL DEFAULT now(),
    CONSTRAINT ck_categoria_tipo CHECK (tipo IN ('geral', 'funcionario'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_categoria_nome
    ON despesa_categoria (LOWER(nome)) WHERE status = 1;

-- ------------------------------------------------------ centro de custo
CREATE TABLE IF NOT EXISTS centro_custo (
    id_centro     serial PRIMARY KEY,
    nome          varchar(60) NOT NULL,
    status        smallint NOT NULL DEFAULT 1,
    data_cadastro timestamp NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_centro_nome
    ON centro_custo (LOWER(nome)) WHERE status = 1;

-- ------------------------------------------------------- conta a pagar
CREATE TABLE IF NOT EXISTS contas_pagar (
    id_conta            serial PRIMARY KEY,
    descricao           varchar(150) NOT NULL,
    id_categoria        integer NOT NULL REFERENCES despesa_categoria(id_categoria),
    id_centro           integer REFERENCES centro_custo(id_centro),
    favorecido          varchar(150),
    id_usuario_func     integer REFERENCES tb_usuario(id_usuario),
    valor_total         numeric(12,2) NOT NULL,
    data_emissao        date NOT NULL DEFAULT CURRENT_DATE,
    observacoes         varchar(255),

    -- Recorrência: a arquitetura fica pronta, a geração automática é etapa 2.
    recorrente          boolean NOT NULL DEFAULT false,
    recorrencia_periodo varchar(15),
    recorrencia_dia     smallint,
    recorrencia_inicio  date,
    recorrencia_fim     date,

    status              varchar(15) NOT NULL DEFAULT 'ativa',
    data_cadastro       timestamp NOT NULL DEFAULT now(),
    id_usuario_cadastro integer NOT NULL REFERENCES tb_usuario(id_usuario),
    data_alteracao      timestamp,
    id_usuario_alteracao integer REFERENCES tb_usuario(id_usuario),

    CONSTRAINT ck_conta_status     CHECK (status IN ('ativa', 'cancelada')),
    CONSTRAINT ck_conta_valor      CHECK (valor_total > 0),
    CONSTRAINT ck_conta_recorrencia CHECK (
        NOT recorrente OR (recorrencia_periodo IN ('mensal','quinzenal','semanal','anual')
                           AND recorrencia_inicio IS NOT NULL)
    )
);

COMMENT ON TABLE contas_pagar IS
    'Cabeçalho da despesa. O valor a pagar de fato vive nas parcelas; uma despesa à vista tem 1 parcela.';
COMMENT ON COLUMN contas_pagar.favorecido IS
    'Quem recebe. Texto livre de propósito: a tabela fornecedores é quem compra da empresa, papel diferente.';

CREATE INDEX IF NOT EXISTS ix_conta_categoria ON contas_pagar (id_categoria);
CREATE INDEX IF NOT EXISTS ix_conta_emissao   ON contas_pagar (data_emissao DESC);

-- ------------------------------------------------------------ parcelas
CREATE TABLE IF NOT EXISTS contas_pagar_parcelas (
    id_parcela          serial PRIMARY KEY,
    id_conta            integer NOT NULL REFERENCES contas_pagar(id_conta),
    numero_parcela      smallint NOT NULL,
    total_parcelas      smallint NOT NULL,
    valor               numeric(12,2) NOT NULL,
    data_vencimento     date NOT NULL,

    valor_pago          numeric(12,2),
    data_pagamento      date,
    forma_pagamento     varchar(20),
    id_usuario_pagamento integer REFERENCES tb_usuario(id_usuario),
    data_registro_pagamento timestamp,

    -- 'vencida' é derivada da data e NÃO é gravada: ver financeiroSituacaoParcela().
    status              varchar(15) NOT NULL DEFAULT 'pendente',
    observacao          varchar(255),

    CONSTRAINT ck_parcela_status CHECK (status IN ('pendente', 'paga', 'cancelada')),
    CONSTRAINT ck_parcela_valor  CHECK (valor > 0),
    CONSTRAINT ck_parcela_numero CHECK (numero_parcela >= 1 AND numero_parcela <= total_parcelas),
    -- Integridade financeira: paga exige data, valor e usuário (§31).
    CONSTRAINT ck_parcela_paga CHECK (
        status <> 'paga'
        OR (data_pagamento IS NOT NULL AND valor_pago IS NOT NULL
            AND valor_pago > 0 AND id_usuario_pagamento IS NOT NULL)
    ),
    CONSTRAINT uq_parcela_numero UNIQUE (id_conta, numero_parcela)
);

COMMENT ON COLUMN contas_pagar_parcelas.status IS
    'pendente, paga ou cancelada. "vencida" não é gravada: é derivada de data_vencimento < hoje com status pendente.';

CREATE INDEX IF NOT EXISTS ix_parcela_vencimento ON contas_pagar_parcelas (data_vencimento);
CREATE INDEX IF NOT EXISTS ix_parcela_conta      ON contas_pagar_parcelas (id_conta);
CREATE INDEX IF NOT EXISTS ix_parcela_status     ON contas_pagar_parcelas (status, data_vencimento);

-- ------------------------------------------------ categorias iniciais
INSERT INTO despesa_categoria (nome, tipo)
SELECT v.nome, v.tipo
FROM (VALUES
    ('Funcionários', 'funcionario'), ('Aluguel', 'geral'), ('Energia elétrica', 'geral'),
    ('Água', 'geral'), ('Internet', 'geral'), ('Telefone', 'geral'),
    ('Combustível', 'geral'), ('Manutenção', 'geral'), ('Equipamentos', 'geral'),
    ('Impostos', 'geral'), ('Serviços', 'geral'), ('Materiais', 'geral'),
    ('Transporte', 'geral'), ('Contabilidade', 'geral'), ('Seguros', 'geral'),
    ('Outros', 'geral')
) AS v(nome, tipo)
WHERE NOT EXISTS (SELECT 1 FROM despesa_categoria d WHERE LOWER(d.nome) = LOWER(v.nome));

INSERT INTO centro_custo (nome)
SELECT v.nome
FROM (VALUES ('Operacional'), ('Administrativo'), ('Logística'),
             ('Manutenção'), ('Reciclagem'), ('Comercial'), ('Recursos Humanos')
) AS v(nome)
WHERE NOT EXISTS (SELECT 1 FROM centro_custo c WHERE LOWER(c.nome) = LOWER(v.nome));

COMMIT;
