--
-- 0001_seed_inicial.sql
--
-- Dados minimos para o sistema operar em uma instalacao nova. Aplicar DEPOIS de
-- 0000_baseline_schema.sql e das migrations datadas.
--
-- Contem apenas dados de REFERENCIA: catalogo de materiais, categorias de
-- despesa e centros de custo, mais um usuario administrador. Nada transacional:
-- compras, vendas, estoque, contas a pagar e importacoes comecam vazios.
--
-- ACESSO INICIAL
--   e-mail : admin@reciclagem.local
--   senha  : admin@123
--   O sistema exige a troca da senha no primeiro acesso
--   (usuario.precisa_alterar_senha = 1).
--
-- Reaplicar e seguro: todos os INSERT usam ON CONFLICT DO NOTHING.
--

SET search_path TO reciclagem;

-- Usuario administrador inicial
INSERT INTO usuario (nome, email, senha, usuario, foto, status, permissao, precisa_alterar_senha)
VALUES ('Administrador', 'admin@reciclagem.local', '$2y$10$4Nkcd86aeSuaCAYQK8T5K.8Sa5UwUwqY0An88R/w2yGhij8FbdX.O', 'admin', '', 1, 'Admin', 1)
ON CONFLICT DO NOTHING;

-- Catalogo de materiais (33). qt_estoque fica em zero: a
-- instalacao nova comeca sem saldo, que so entra por compra ou inventario.
INSERT INTO material (nm_material, tipo, preco_compra, preco_especial, preco_venda, unidade_medida, codigo, estoque_minimo, status) VALUES
  ('Balde Branco', 'Plastico', 0.40, 0.40, 0.43, 'kg', NULL, NULL, 1),
  ('Balde Colorido', 'Plastico', 0.20, 0.20, 0.26, 'kg', NULL, NULL, 1),
  ('Bateria', 'Outro', 3.00, 3.00, 4.01, 'kg', NULL, NULL, 1),
  ('Caixaria', 'Plastico', 3.00, 3.00, 3.97, 'kg', NULL, NULL, 1),
  ('Chaparia', 'Aluminio', 7.00, 8.50, 9.14, 'kg', NULL, NULL, 1),
  ('Chumbo', 'Outro', 3.00, 4.00, 5.32, 'kg', NULL, NULL, 1),
  ('Cobre 4a', 'Cobre', 20.00, 25.00, 26.65, 'kg', NULL, NULL, 1),
  ('Cobre Misto', 'Aluminio', 50.00, 53.00, 111.52, 'kg', NULL, NULL, 1),
  ('Cobre Vermelho', 'Aluminio', 50.00, 55.00, 69.03, 'kg', NULL, NULL, 1),
  ('Colorido Pead', 'Plastico', 1.00, 1.50, 1.11, 'kg', NULL, NULL, 1),
  ('Cristal', 'Plastico', 0.40, 0.50, 0.81, 'kg', NULL, NULL, 1),
  ('Duro', 'Aluminio', 6.00, 7.50, 13.28, 'kg', NULL, NULL, 1),
  ('Esponja', 'Aluminio', 2.00, 2.00, 2.15, 'kg', NULL, NULL, 1),
  ('Fio Capa', 'Cobre', 7.00, 7.00, 8.99, 'kg', NULL, NULL, 1),
  ('Inox Panela', 'Outro', 0.50, 0.50, 0.56, 'kg', NULL, NULL, 1),
  ('Lata', 'Aluminio', 8.00, 9.00, 3.50, 'kg', NULL, NULL, 1),
  ('Leitoso', 'Plastico', 1.00, 1.50, 1.19, 'kg', NULL, NULL, 1),
  ('Metal', 'Cobre', 20.00, 25.00, 21.86, 'kg', NULL, NULL, 1),
  ('Misto', 'Papel', 0.10, 0.10, 0.13, 'kg', NULL, NULL, 1),
  ('Motor', 'Cobre', 25.00, 30.00, 30.17, 'kg', NULL, NULL, 1),
  ('Óleo', 'Outro', 1.00, 1.00, NULL, 'kg', NULL, 0.00, 1),
  ('Panela', 'Aluminio', 8.00, 9.00, 10.28, 'kg', NULL, NULL, 1),
  ('Papel Branco', 'Papel', 0.30, 0.30, 0.56, 'kg', NULL, NULL, 1),
  ('Papelão', 'Papel', 0.40, 0.50, 0.53, 'kg', NULL, NULL, 1),
  ('Perciana', 'Aluminio', 2.00, 4.00, 2.12, 'kg', NULL, NULL, 1),
  ('Perfil', 'Aluminio', 9.00, 10.00, 10.26, 'kg', NULL, NULL, 1),
  ('Pet Branca', 'Plastico', 2.50, 3.20, 2.80, 'kg', NULL, NULL, 1),
  ('Pet Verde', 'Plastico', 2.00, 2.70, 2.68, 'kg', NULL, NULL, 1),
  ('Pvc', 'Plastico', 0.10, 0.10, NULL, 'kg', NULL, NULL, 1),
  ('Radiador Com Cobre', 'Cobre', 20.00, 20.00, 25.87, 'kg', NULL, NULL, 1),
  ('Roda', 'Aluminio', 10.00, 11.00, NULL, 'kg', NULL, NULL, 1),
  ('Spray Com Ferro', 'Aluminio', 3.00, 3.00, 5.98, 'kg', NULL, NULL, 1),
  ('Sucata', 'Outro', 0.30, 0.40, 1.90, 'kg', NULL, 140.00, 1)
ON CONFLICT (nm_material) DO NOTHING;

-- Categorias de despesa (16)
INSERT INTO despesa_categoria (nome, tipo, status) VALUES
  ('Água', 'geral', 1),
  ('Aluguel', 'geral', 1),
  ('Combustível', 'geral', 1),
  ('Contabilidade', 'geral', 1),
  ('Energia elétrica', 'geral', 1),
  ('Equipamentos', 'geral', 1),
  ('Funcionários', 'funcionario', 1),
  ('Impostos', 'geral', 1),
  ('Internet', 'geral', 1),
  ('Manutenção', 'geral', 1),
  ('Materiais', 'geral', 1),
  ('Outros', 'geral', 1),
  ('Seguros', 'geral', 1),
  ('Serviços', 'geral', 1),
  ('Telefone', 'geral', 1),
  ('Transporte', 'geral', 1)
ON CONFLICT DO NOTHING;

-- Centros de custo (7)
INSERT INTO centro_custo (nome, status) VALUES
  ('Administrativo', 1),
  ('Comercial', 1),
  ('Logística', 1),
  ('Manutenção', 1),
  ('Operacional', 1),
  ('Reciclagem', 1),
  ('Recursos Humanos', 1)
ON CONFLICT DO NOTHING;

