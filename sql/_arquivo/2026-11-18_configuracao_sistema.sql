--
-- 2026-11-18_configuracao_sistema.sql
--
-- Cria a tabela configuracao: parametros do sistema que o administrador ajusta
-- pela tela Configuracoes do sistema (rota /configuracoes), sem editar o .env.
--
-- O primeiro uso e a integracao da balanca. Antes, o endereco do agente vinha
-- so de BALANCA_API_URL no .env e as rotas (/peso, /captura) e os campos da
-- resposta estavam fixos no JavaScript -- quem usasse outro software de
-- integracao de balanca nao tinha como apontar o sistema para ele.
--
-- Chave/valor de proposito: cada configuracao nova e uma linha, nao uma coluna,
-- entao a proxima nao exige migration.
--
-- JA INCORPORADA na 0000_baseline_schema.sql. Serve so para atualizar um banco
-- instalado antes de 18/11/2026. Pode ser reaplicada (IF NOT EXISTS).
--
-- Nao grava nenhuma linha: sem configuracao salva, o sistema continua lendo
-- BALANCA_API_URL do .env, exatamente como antes.
--

SET client_encoding = 'UTF8';

BEGIN;

CREATE TABLE IF NOT EXISTS reciclagem.configuracao (
    chave character varying(60) NOT NULL,
    valor text,
    data_atualizacao timestamp without time zone DEFAULT now() NOT NULL,
    id_usuario integer,
    CONSTRAINT configuracao_pkey PRIMARY KEY (chave),
    CONSTRAINT configuracao_id_usuario_fkey FOREIGN KEY (id_usuario)
        REFERENCES reciclagem.usuario(id_usuario) ON DELETE SET NULL
);

COMMENT ON TABLE reciclagem.configuracao IS 'Parâmetros do sistema ajustados pelo Admin na tela Configurações do sistema. Uma linha por chave (ex.: balanca.url).';
COMMENT ON COLUMN reciclagem.configuracao.valor IS 'Sempre texto; quem lê converte. Vazio desliga o item quando ele é opcional (ex.: balanca.rota_captura).';
COMMENT ON COLUMN reciclagem.configuracao.id_usuario IS 'Último Admin que alterou a chave.';

COMMIT;
