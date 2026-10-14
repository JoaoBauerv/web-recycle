-- Adiciona tara/desconto de peso na pesagem de compras e vendas.
-- peso_bruto = peso lido na balança; tara = valor descontado (embalagem, veículo, etc.);
-- peso_material / quantidade continuam sendo o peso líquido (peso_bruto - tara),
-- usado no cálculo do valor e na movimentação de estoque.

SET search_path TO reciclagem;

ALTER TABLE tb_pesagem_material
    ADD COLUMN IF NOT EXISTS peso_bruto NUMERIC NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tara NUMERIC NOT NULL DEFAULT 0;

UPDATE tb_pesagem_material
   SET peso_bruto = peso_material
 WHERE peso_bruto = 0;

ALTER TABLE vendas_itens
    ADD COLUMN IF NOT EXISTS peso_bruto NUMERIC NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tara NUMERIC NOT NULL DEFAULT 0;

UPDATE vendas_itens
   SET peso_bruto = quantidade
 WHERE peso_bruto = 0;
