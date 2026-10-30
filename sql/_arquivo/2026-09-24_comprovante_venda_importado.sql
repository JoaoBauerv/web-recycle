-- ============================================================================
-- Importação do comprovante da venda
--
-- Na venda quem emite o documento é o comprador (o fornecedor que recebe o
-- material), não este sistema. A coluna guarda o nome do arquivo enviado pelo
-- comprador, arquivado junto da venda para conferência posterior.
--
-- O arquivo em si fica em uploads/comprovantes_venda/ e é servido por
-- functions/venda/comprovante_arquivo.php, que exige sessão autenticada.
-- ============================================================================

SET search_path TO reciclagem;

ALTER TABLE vendas
    ADD COLUMN IF NOT EXISTS comprovante_arquivo varchar(255),
    ADD COLUMN IF NOT EXISTS comprovante_importado_em timestamp without time zone;
