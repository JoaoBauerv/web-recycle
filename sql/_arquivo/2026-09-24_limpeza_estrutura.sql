-- ============================================================================
-- Limpeza e correção da estrutura do banco
-- Backup dos dados removidos: sql/backup/colunas_removidas_2026-09-24.json
-- ============================================================================

SET search_path TO reciclagem;

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. Remove coluna sem uso: resquício do modelo antigo, quando a pesagem era
--    vinculada a um usuário em vez de um cliente. Nenhuma linha do sistema lê.
-- ----------------------------------------------------------------------------
ALTER TABLE tb_pesagem DROP COLUMN IF EXISTS id_usuario_cliente_legado;

-- ----------------------------------------------------------------------------
-- 2. Remove coluna sem uso: marcava usuários do tipo cliente, mas nenhuma tela
--    consultava o campo — um usuário marcado via o mesmo sistema do operador.
-- ----------------------------------------------------------------------------
ALTER TABLE tb_usuario DROP COLUMN IF EXISTS cliente;

-- ----------------------------------------------------------------------------
-- 3. Padroniza a precisão das colunas de tara. Foram criadas como `numeric`
--    sem escala, aceitando qualquer número de casas decimais, enquanto o resto
--    do banco usa numeric(10,2) / (12,2).
-- ----------------------------------------------------------------------------
ALTER TABLE tb_pesagem_material
    ALTER COLUMN peso_bruto TYPE numeric(10,2),
    ALTER COLUMN tara       TYPE numeric(10,2);

ALTER TABLE vendas_itens
    ALTER COLUMN peso_bruto TYPE numeric(12,2),
    ALTER COLUMN tara       TYPE numeric(12,2);

-- ----------------------------------------------------------------------------
-- 4. Garante unicidade do e-mail, que passou a ser a credencial de login.
--    LOWER() porque o login compara sem diferenciar maiúsculas: sem isso seria
--    possível cadastrar "Joao@x.com" com "joao@x.com" já existente.
-- ----------------------------------------------------------------------------
CREATE UNIQUE INDEX IF NOT EXISTS idx_tb_usuario_email_unico
    ON tb_usuario (LOWER(email));

-- ----------------------------------------------------------------------------
-- 5. Integridade dos itens de compra.
--
--    Os materiais 1 e 3 foram apagados fisicamente antes do soft delete existir,
--    deixando 6 itens de compra órfãos. Em vez de apagar esses itens (o que
--    reescreveria compras já realizadas), os materiais são recriados como
--    INATIVOS — preservando o histórico, que é a razão de o sistema inativar
--    em vez de excluir.
--
--    O preço histórico de cada item continua em tb_pesagem_material.preco_un,
--    então preco_compra aqui é apenas um marcador.
-- ----------------------------------------------------------------------------
INSERT INTO tb_material (id_material, nm_material, tipo, preco_compra, qt_estoque, status, unidade_medida)
SELECT DISTINCT pm.id_material,
       'Material removido #' || pm.id_material,
       'Outro',
       0,
       0,
       0,                      -- inativo: não aparece em seleções
       'kg'
FROM tb_pesagem_material pm
LEFT JOIN tb_material m ON m.id_material = pm.id_material
WHERE m.id_material IS NULL;

-- Evita que a sequência gere um id já ocupado pelos registros recriados.
SELECT setval(
    pg_get_serial_sequence('tb_material', 'id_material'),
    (SELECT MAX(id_material) FROM tb_material)
);

-- Agora a FK pode ser criada: a partir daqui o banco impede que um material
-- referenciado por uma compra seja apagado fisicamente.
ALTER TABLE tb_pesagem_material
    ADD CONSTRAINT fk_pesagem_material_material
    FOREIGN KEY (id_material) REFERENCES tb_material (id_material);

COMMIT;
