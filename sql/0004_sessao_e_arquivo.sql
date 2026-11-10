--
-- 0004_sessao_e_arquivo.sql
--
-- Duas tabelas que a versao do sistema hospedada na Vercel precisa, e que
-- valem para qualquer instalacao da branch `neon`:
--
--   sessao   as sessoes do PHP. Na Vercel cada requisicao pode cair numa
--            instancia diferente, e o arquivo de sessao gravado por uma nao
--            existe na outra -- o usuario perderia o login a cada clique.
--
--   arquivo  o conteudo dos arquivos enviados (fotos de perfil, comprovantes
--            de venda e a planilha de uma importacao em andamento). O disco da
--            Vercel e somente leitura, e o /tmp e apagado com a instancia.
--
-- Roda depois de 0000_baseline_schema.sql e 0001_seed_inicial.sql:
--
--   psql -v ON_ERROR_STOP=1 -f sql/0004_sessao_e_arquivo.sql "$DATABASE_URL_UNPOOLED"
--
-- Pode rodar de novo sem efeito: as tabelas so sao criadas se faltarem.
--

SET client_encoding = 'UTF8';

BEGIN;

CREATE TABLE IF NOT EXISTS reciclagem.sessao (
    id            varchar(128) PRIMARY KEY,
    dados         text         NOT NULL,
    atualizado_em timestamptz  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS sessao_atualizado_em_idx ON reciclagem.sessao (atualizado_em);

COMMENT ON TABLE reciclagem.sessao IS 'Sessoes do PHP (session_set_save_handler em banco.php), para o login sobreviver entre instancias da Vercel.';

CREATE TABLE IF NOT EXISTS reciclagem.arquivo (
    nome      varchar(255) PRIMARY KEY,
    mime      varchar(100) NOT NULL,
    conteudo  bytea        NOT NULL,
    criado_em timestamptz  NOT NULL DEFAULT now()
);

COMMENT ON TABLE  reciclagem.arquivo      IS 'Conteudo dos arquivos enviados pelos usuarios (components/arquivo.php).';
COMMENT ON COLUMN reciclagem.arquivo.nome IS 'Chave com prefixo da origem: user/<foto>, comprovante_venda/<arquivo> ou tmp/<planilha>.';

COMMIT;
