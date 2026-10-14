--
-- 0000_baseline_schema.sql
--
-- Ponto zero do schema. Ate 14/10/2026 as tabelas centrais do sistema
-- (tb_usuario, tb_material, tb_pesagem, clientes, fornecedores, vendas,
-- vendas_itens) nunca tiveram DDL versionado: foram criadas a mao no inicio do
-- projeto, e as 8 migrations existentes apenas as alteravam. Sem uma baseline
-- nao havia como recriar o banco do zero nem validar uma refatoracao.
--
-- Este arquivo foi gerado com pg_dump --schema-only sobre o banco em producao,
-- ANTES da refatoracao de nomenclatura. A ordem de aplicacao passa a ser:
--
--   1. 0000_baseline_schema.sql        (este arquivo)
--   2. as migrations datadas, em ordem cronologica
--
-- As migrations anteriores a esta data ja estao refletidas aqui; sao mantidas
-- no repositorio como historico e sao idempotentes (IF EXISTS / IF NOT EXISTS),
-- de modo que reaplicar nao quebra.
--

--
-- PostgreSQL database dump
--

\restrict Dqaubo09jjquAia3igP25nJViuEShMSoztEpXNMTMTGSdZfRLtopZcsoqSHcYzU

-- Dumped from database version 18.4
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: reciclagem; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA reciclagem;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: centro_custo; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.centro_custo (
    id_centro integer NOT NULL,
    nome character varying(60) NOT NULL,
    status smallint DEFAULT 1 NOT NULL,
    data_cadastro timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: centro_custo_id_centro_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.centro_custo_id_centro_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: centro_custo_id_centro_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.centro_custo_id_centro_seq OWNED BY reciclagem.centro_custo.id_centro;


--
-- Name: clientes; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.clientes (
    id_cliente integer NOT NULL,
    id_usuario integer,
    nome character varying(100) NOT NULL,
    cpf_cnpj character varying(18),
    telefone character varying(20),
    celular character varying(20),
    email character varying(100),
    cep character varying(9),
    logradouro character varying(100),
    numero character varying(10),
    complemento character varying(50),
    bairro character varying(50),
    cidade character varying(50),
    estado character varying(2),
    preco_especial boolean DEFAULT false NOT NULL,
    status smallint DEFAULT 1 NOT NULL,
    data_cadastro timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: TABLE clientes; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON TABLE reciclagem.clientes IS 'Pessoas/empresas que vendem material reciclável para a empresa (fluxo de compra/pesagem)';


--
-- Name: COLUMN clientes.id_usuario; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.clientes.id_usuario IS 'Vínculo opcional com tb_usuario — preenchido quando o cliente tem acesso de login';


--
-- Name: clientes_id_cliente_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.clientes_id_cliente_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_cliente_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.clientes_id_cliente_seq OWNED BY reciclagem.clientes.id_cliente;


--
-- Name: contas_pagar; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.contas_pagar (
    id_conta integer NOT NULL,
    descricao character varying(150) NOT NULL,
    id_categoria integer NOT NULL,
    id_centro integer,
    favorecido character varying(150),
    id_usuario_func integer,
    valor_total numeric(12,2) NOT NULL,
    data_emissao date DEFAULT CURRENT_DATE NOT NULL,
    observacoes character varying(255),
    recorrente boolean DEFAULT false NOT NULL,
    recorrencia_periodo character varying(15),
    recorrencia_dia smallint,
    recorrencia_inicio date,
    recorrencia_fim date,
    status character varying(15) DEFAULT 'ativa'::character varying NOT NULL,
    data_cadastro timestamp without time zone DEFAULT now() NOT NULL,
    id_usuario_cadastro integer NOT NULL,
    data_alteracao timestamp without time zone,
    id_usuario_alteracao integer,
    CONSTRAINT ck_conta_recorrencia CHECK (((NOT recorrente) OR (((recorrencia_periodo)::text = ANY ((ARRAY['mensal'::character varying, 'quinzenal'::character varying, 'semanal'::character varying, 'anual'::character varying])::text[])) AND (recorrencia_inicio IS NOT NULL)))),
    CONSTRAINT ck_conta_status CHECK (((status)::text = ANY ((ARRAY['ativa'::character varying, 'cancelada'::character varying])::text[]))),
    CONSTRAINT ck_conta_valor CHECK ((valor_total > (0)::numeric))
);


--
-- Name: TABLE contas_pagar; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON TABLE reciclagem.contas_pagar IS 'Cabeçalho da despesa. O valor a pagar de fato vive nas parcelas; uma despesa à vista tem 1 parcela.';


--
-- Name: COLUMN contas_pagar.favorecido; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.contas_pagar.favorecido IS 'Quem recebe. Texto livre de propósito: a tabela fornecedores é quem compra da empresa, papel diferente.';


--
-- Name: contas_pagar_id_conta_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.contas_pagar_id_conta_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contas_pagar_id_conta_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.contas_pagar_id_conta_seq OWNED BY reciclagem.contas_pagar.id_conta;


--
-- Name: contas_pagar_parcelas; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.contas_pagar_parcelas (
    id_parcela integer NOT NULL,
    id_conta integer NOT NULL,
    numero_parcela smallint NOT NULL,
    total_parcelas smallint NOT NULL,
    valor numeric(12,2) NOT NULL,
    data_vencimento date NOT NULL,
    valor_pago numeric(12,2),
    data_pagamento date,
    forma_pagamento character varying(20),
    id_usuario_pagamento integer,
    data_registro_pagamento timestamp without time zone,
    status character varying(15) DEFAULT 'pendente'::character varying NOT NULL,
    observacao character varying(255),
    CONSTRAINT ck_parcela_numero CHECK (((numero_parcela >= 1) AND (numero_parcela <= total_parcelas))),
    CONSTRAINT ck_parcela_paga CHECK ((((status)::text <> 'paga'::text) OR ((data_pagamento IS NOT NULL) AND (valor_pago IS NOT NULL) AND (valor_pago > (0)::numeric) AND (id_usuario_pagamento IS NOT NULL)))),
    CONSTRAINT ck_parcela_status CHECK (((status)::text = ANY ((ARRAY['pendente'::character varying, 'paga'::character varying, 'cancelada'::character varying])::text[]))),
    CONSTRAINT ck_parcela_valor CHECK ((valor > (0)::numeric))
);


--
-- Name: COLUMN contas_pagar_parcelas.status; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.contas_pagar_parcelas.status IS 'pendente, paga ou cancelada. "vencida" não é gravada: é derivada de data_vencimento < hoje com status pendente.';


--
-- Name: contas_pagar_parcelas_id_parcela_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.contas_pagar_parcelas_id_parcela_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contas_pagar_parcelas_id_parcela_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.contas_pagar_parcelas_id_parcela_seq OWNED BY reciclagem.contas_pagar_parcelas.id_parcela;


--
-- Name: despesa_categoria; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.despesa_categoria (
    id_categoria integer NOT NULL,
    nome character varying(60) NOT NULL,
    tipo character varying(20) DEFAULT 'geral'::character varying NOT NULL,
    status smallint DEFAULT 1 NOT NULL,
    data_cadastro timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_categoria_tipo CHECK (((tipo)::text = ANY ((ARRAY['geral'::character varying, 'funcionario'::character varying])::text[])))
);


--
-- Name: despesa_categoria_id_categoria_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.despesa_categoria_id_categoria_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: despesa_categoria_id_categoria_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.despesa_categoria_id_categoria_seq OWNED BY reciclagem.despesa_categoria.id_categoria;


--
-- Name: estoque_movimentacoes; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.estoque_movimentacoes (
    id_movimentacao integer NOT NULL,
    id_material integer NOT NULL,
    tipo character varying(20) NOT NULL,
    quantidade numeric(12,2) NOT NULL,
    origem_tipo character varying(20) NOT NULL,
    origem_id integer,
    saldo_apos numeric(12,2) NOT NULL,
    id_usuario integer NOT NULL,
    data_movimentacao timestamp without time zone DEFAULT now() NOT NULL,
    observacoes character varying(255),
    CONSTRAINT ck_mov_origem CHECK (((origem_tipo)::text = ANY ((ARRAY['pesagem'::character varying, 'venda'::character varying, 'ajuste_manual'::character varying, 'processamento'::character varying, 'estorno_compra'::character varying, 'estorno_venda'::character varying])::text[]))),
    CONSTRAINT ck_mov_tipo CHECK (((tipo)::text = ANY ((ARRAY['entrada'::character varying, 'saida'::character varying, 'ajuste'::character varying, 'perda'::character varying])::text[])))
);


--
-- Name: TABLE estoque_movimentacoes; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON TABLE reciclagem.estoque_movimentacoes IS 'Histórico de toda entrada/saída/ajuste de estoque, para rastreabilidade';


--
-- Name: COLUMN estoque_movimentacoes.origem_tipo; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.estoque_movimentacoes.origem_tipo IS 'De onde veio o lançamento. estorno_compra e estorno_venda são gerados pelo cancelamento do documento, com origem_id apontando para ele. Os sinais ficam em estoqueSinal() (functions/estoque/estoque_lib.php).';


--
-- Name: estoque_movimentacoes_id_movimentacao_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.estoque_movimentacoes_id_movimentacao_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: estoque_movimentacoes_id_movimentacao_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.estoque_movimentacoes_id_movimentacao_seq OWNED BY reciclagem.estoque_movimentacoes.id_movimentacao;


--
-- Name: fornecedores; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.fornecedores (
    id_fornecedor integer NOT NULL,
    nome_razao_social character varying(150) NOT NULL,
    cnpj_cpf character varying(18),
    telefone character varying(20),
    email character varying(100),
    cep character varying(9),
    logradouro character varying(100),
    numero character varying(10),
    complemento character varying(50),
    bairro character varying(50),
    cidade character varying(50),
    estado character varying(2),
    status smallint DEFAULT 1 NOT NULL,
    data_cadastro timestamp without time zone DEFAULT now() NOT NULL,
    observacoes character varying(255)
);


--
-- Name: TABLE fornecedores; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON TABLE reciclagem.fornecedores IS 'Empresas/indústrias que compram material já processado da empresa (destino das vendas)';


--
-- Name: fornecedores_id_fornecedor_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.fornecedores_id_fornecedor_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fornecedores_id_fornecedor_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.fornecedores_id_fornecedor_seq OWNED BY reciclagem.fornecedores.id_fornecedor;


--
-- Name: importacao_historico; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.importacao_historico (
    id_importacao integer NOT NULL,
    arquivo character varying(255) NOT NULL,
    id_modelo integer,
    id_usuario integer NOT NULL,
    pedido_externo character varying(60),
    total_registros integer DEFAULT 0 NOT NULL,
    registros_importados integer DEFAULT 0 NOT NULL,
    registros_com_erro integer DEFAULT 0 NOT NULL,
    valor_total numeric(12,2) DEFAULT 0 NOT NULL,
    status character varying(25) DEFAULT 'processando'::character varying NOT NULL,
    erros jsonb,
    data_importacao timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT ck_importacao_status CHECK (((status)::text = ANY ((ARRAY['processando'::character varying, 'concluido'::character varying, 'concluido_avisos'::character varying, 'erro'::character varying, 'cancelado'::character varying])::text[])))
);


--
-- Name: TABLE importacao_historico; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON TABLE reciclagem.importacao_historico IS 'Uma linha por tentativa de importação, inclusive as canceladas.';


--
-- Name: importacao_historico_id_importacao_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.importacao_historico_id_importacao_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: importacao_historico_id_importacao_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.importacao_historico_id_importacao_seq OWNED BY reciclagem.importacao_historico.id_importacao;


--
-- Name: importacao_modelo; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.importacao_modelo (
    id_modelo integer NOT NULL,
    nome character varying(100) NOT NULL,
    id_fornecedor integer,
    linha_cabecalho smallint DEFAULT 1 NOT NULL,
    mapeamento jsonb NOT NULL,
    status smallint DEFAULT 1 NOT NULL,
    data_cadastro timestamp without time zone DEFAULT now() NOT NULL,
    data_alteracao timestamp without time zone
);


--
-- Name: TABLE importacao_modelo; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON TABLE reciclagem.importacao_modelo IS 'Como ler a planilha de um parceiro: qual coluna é material, quantidade, etc.';


--
-- Name: COLUMN importacao_modelo.id_fornecedor; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.importacao_modelo.id_fornecedor IS 'Amarra o modelo a um parceiro; também delimita o de/para daquela origem.';


--
-- Name: COLUMN importacao_modelo.mapeamento; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.importacao_modelo.mapeamento IS 'JSON campo_do_sistema -> índice da coluna. Ex: {"material":1,"quantidade":2}';


--
-- Name: importacao_modelo_id_modelo_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.importacao_modelo_id_modelo_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: importacao_modelo_id_modelo_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.importacao_modelo_id_modelo_seq OWNED BY reciclagem.importacao_modelo.id_modelo;


--
-- Name: material_relacao; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.material_relacao (
    id_relacao integer NOT NULL,
    id_material integer NOT NULL,
    nome_externo character varying(150),
    nome_normalizado character varying(150),
    codigo_externo character varying(60),
    codigo_normalizado character varying(60),
    id_modelo integer,
    origem character varying(100),
    status smallint DEFAULT 1 NOT NULL,
    data_cadastro timestamp without time zone DEFAULT now() NOT NULL,
    data_alteracao timestamp without time zone,
    CONSTRAINT ck_relacao_tem_chave CHECK (((nome_normalizado IS NOT NULL) OR (codigo_normalizado IS NOT NULL)))
);


--
-- Name: TABLE material_relacao; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON TABLE reciclagem.material_relacao IS 'De/para: nomes e códigos externos que representam um material oficial.';


--
-- Name: COLUMN material_relacao.nome_normalizado; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.material_relacao.nome_normalizado IS 'Nome externo sem acento/maiúscula/pontuação, gravado pelo PHP na inserção.';


--
-- Name: COLUMN material_relacao.id_modelo; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.material_relacao.id_modelo IS 'Quando preenchido, a relação vale só para aquela origem: o mesmo "PET B" pode ser materiais diferentes em parceiros diferentes.';


--
-- Name: material_relacao_id_relacao_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.material_relacao_id_relacao_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: material_relacao_id_relacao_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.material_relacao_id_relacao_seq OWNED BY reciclagem.material_relacao.id_relacao;


--
-- Name: tb_documento; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_documento (
    id integer NOT NULL,
    id_usuario integer NOT NULL,
    cpf character varying(14) DEFAULT NULL::character varying,
    rg character varying(9) DEFAULT NULL::character varying,
    cnh character varying(11) DEFAULT NULL::character varying
);


--
-- Name: tb_documento_id_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_documento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_documento_id_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.tb_documento_id_seq OWNED BY reciclagem.tb_documento.id;


--
-- Name: tb_endereco; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_endereco (
    id integer NOT NULL,
    id_usuario integer NOT NULL,
    cep character varying(9) DEFAULT NULL::character varying,
    logradouro character varying(100) DEFAULT NULL::character varying,
    complemento character varying(50) DEFAULT NULL::character varying,
    cidade character varying(50) DEFAULT NULL::character varying,
    bairro character varying(50) DEFAULT NULL::character varying,
    referencia character varying(100) DEFAULT NULL::character varying,
    numero character varying(5)
);


--
-- Name: tb_endereco_id_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_endereco_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_endereco_id_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.tb_endereco_id_seq OWNED BY reciclagem.tb_endereco.id;


--
-- Name: tb_evento_id_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_evento_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_evento; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_evento (
    id integer DEFAULT nextval('reciclagem.tb_evento_id_seq'::regclass) NOT NULL,
    title character varying(150) NOT NULL,
    color character varying(50) DEFAULT '#FFD700'::character varying NOT NULL,
    start timestamp without time zone NOT NULL,
    "end" timestamp without time zone NOT NULL
);


--
-- Name: tb_material; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_material (
    id_material integer NOT NULL,
    nm_material character varying(40) NOT NULL,
    tipo character varying(20) NOT NULL,
    preco_compra numeric(10,2) NOT NULL,
    qt_estoque numeric(10,2),
    status smallint DEFAULT 1,
    preco_especial numeric(10,2),
    preco_venda numeric(10,2),
    unidade_medida character varying(10) DEFAULT 'kg'::character varying NOT NULL,
    codigo character varying(30),
    estoque_minimo numeric(10,2),
    CONSTRAINT ck_material_estoque_minimo CHECK (((estoque_minimo IS NULL) OR (estoque_minimo >= (0)::numeric)))
);


--
-- Name: COLUMN tb_material.preco_venda; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.tb_material.preco_venda IS 'Preço médio de venda ao fornecedor (material já processado)';


--
-- Name: COLUMN tb_material.codigo; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.tb_material.codigo IS 'Código oficial do material, usado como chave no de/para das importações.';


--
-- Name: COLUMN tb_material.estoque_minimo; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.tb_material.estoque_minimo IS 'Saldo a partir do qual o material é sinalizado como baixo. NULL = sem alerta configurado.';


--
-- Name: tb_material_id_material_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_material_id_material_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_material_id_material_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.tb_material_id_material_seq OWNED BY reciclagem.tb_material.id_material;


--
-- Name: tb_pesagem; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_pesagem (
    id_pesagem integer NOT NULL,
    total_valor numeric(10,2) NOT NULL,
    total_peso numeric(10,2) NOT NULL,
    data_pesagem timestamp without time zone NOT NULL,
    id_cliente integer NOT NULL,
    status character varying(20) DEFAULT 'ativa'::character varying NOT NULL,
    cancelada_em timestamp without time zone,
    id_usuario_cancelou integer,
    motivo_cancelamento character varying(500),
    CONSTRAINT ck_pesagem_cancelamento CHECK ((((status)::text <> 'cancelada'::text) OR ((cancelada_em IS NOT NULL) AND (id_usuario_cancelou IS NOT NULL) AND (motivo_cancelamento IS NOT NULL) AND (btrim((motivo_cancelamento)::text) <> ''::text)))),
    CONSTRAINT ck_pesagem_status CHECK (((status)::text = ANY ((ARRAY['ativa'::character varying, 'cancelada'::character varying])::text[])))
);


--
-- Name: COLUMN tb_pesagem.status; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.tb_pesagem.status IS 'Situação do documento: ativa ou cancelada. Compra cancelada fica fora das somas, mas continua visível.';


--
-- Name: COLUMN tb_pesagem.motivo_cancelamento; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.tb_pesagem.motivo_cancelamento IS 'Obrigatório quando status = cancelada (ck_pesagem_cancelamento).';


--
-- Name: tb_pesagem_id_pesagem_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_pesagem_id_pesagem_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_pesagem_id_pesagem_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.tb_pesagem_id_pesagem_seq OWNED BY reciclagem.tb_pesagem.id_pesagem;


--
-- Name: tb_pesagem_material; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_pesagem_material (
    id_pesagem integer NOT NULL,
    id_material integer NOT NULL,
    preco_un numeric(10,2) NOT NULL,
    peso_material numeric(10,2) NOT NULL,
    obs character varying(100),
    id integer NOT NULL,
    peso_bruto numeric(10,2) DEFAULT 0 NOT NULL,
    tara numeric(10,2) DEFAULT 0 NOT NULL
);


--
-- Name: tb_pesagem_material_id_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_pesagem_material_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_pesagem_material_id_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.tb_pesagem_material_id_seq OWNED BY reciclagem.tb_pesagem_material.id;


--
-- Name: tb_registro_movimento; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_registro_movimento (
    id integer NOT NULL,
    id_usuario_admin integer NOT NULL,
    id_usuario_modificado integer NOT NULL,
    descricao character varying(100) NOT NULL,
    tipo character varying(20) NOT NULL,
    data timestamp without time zone NOT NULL
);


--
-- Name: tb_registro_movimento_id_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_registro_movimento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_registro_movimento_id_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.tb_registro_movimento_id_seq OWNED BY reciclagem.tb_registro_movimento.id;


--
-- Name: tb_usuario; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.tb_usuario (
    id_usuario integer NOT NULL,
    nome character varying(40) NOT NULL,
    email character varying(50) NOT NULL,
    senha character varying(255) NOT NULL,
    foto character varying(255) NOT NULL,
    status smallint DEFAULT 1 NOT NULL,
    usuario character varying(40) NOT NULL,
    data_nascimento date,
    permissao character varying(30) NOT NULL,
    telefone character varying(20) DEFAULT NULL::character varying,
    celular character varying(20) DEFAULT NULL::character varying,
    precisa_alterar_senha smallint DEFAULT 0,
    CONSTRAINT ck_usuario_permissao CHECK (((permissao)::text = ANY ((ARRAY['Admin'::character varying, 'Usuario'::character varying])::text[])))
);


--
-- Name: COLUMN tb_usuario.permissao; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.tb_usuario.permissao IS 'Perfil de acesso: Admin ou Usuario. As permissões por ação ficam em components/permissoes.php.';


--
-- Name: tb_usuario_id_usuario_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.tb_usuario_id_usuario_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tb_usuario_id_usuario_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.tb_usuario_id_usuario_seq OWNED BY reciclagem.tb_usuario.id_usuario;


--
-- Name: vendas; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.vendas (
    id_venda integer NOT NULL,
    id_fornecedor integer NOT NULL,
    id_usuario integer NOT NULL,
    total_peso numeric(12,2) DEFAULT 0 NOT NULL,
    total_valor numeric(12,2) DEFAULT 0 NOT NULL,
    status character varying(20) DEFAULT 'concluida'::character varying NOT NULL,
    data_venda timestamp without time zone DEFAULT now() NOT NULL,
    observacoes character varying(255),
    comprovante_arquivo character varying(255),
    comprovante_importado_em timestamp without time zone,
    origem character varying(15) DEFAULT 'manual'::character varying NOT NULL,
    pedido_externo character varying(60),
    id_importacao integer,
    cancelada_em timestamp without time zone,
    id_usuario_cancelou integer,
    motivo_cancelamento character varying(500),
    CONSTRAINT ck_venda_cancelamento CHECK ((((status)::text <> 'cancelada'::text) OR ((cancelada_em IS NOT NULL) AND (id_usuario_cancelou IS NOT NULL) AND (motivo_cancelamento IS NOT NULL) AND (btrim((motivo_cancelamento)::text) <> ''::text)))),
    CONSTRAINT ck_venda_origem CHECK (((origem)::text = ANY ((ARRAY['manual'::character varying, 'importacao'::character varying])::text[]))),
    CONSTRAINT ck_venda_status CHECK (((status)::text = ANY ((ARRAY['concluida'::character varying, 'cancelada'::character varying])::text[])))
);


--
-- Name: COLUMN vendas.origem; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.vendas.origem IS 'manual = digitada na tela; importacao = veio de planilha.';


--
-- Name: COLUMN vendas.motivo_cancelamento; Type: COMMENT; Schema: reciclagem; Owner: -
--

COMMENT ON COLUMN reciclagem.vendas.motivo_cancelamento IS 'Obrigatório quando status = cancelada (ck_venda_cancelamento).';


--
-- Name: vendas_id_venda_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.vendas_id_venda_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendas_id_venda_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.vendas_id_venda_seq OWNED BY reciclagem.vendas.id_venda;


--
-- Name: vendas_itens; Type: TABLE; Schema: reciclagem; Owner: -
--

CREATE TABLE reciclagem.vendas_itens (
    id_venda_item integer NOT NULL,
    id_venda integer NOT NULL,
    id_material integer NOT NULL,
    quantidade numeric(12,2) NOT NULL,
    preco_un numeric(10,2) NOT NULL,
    valor_total numeric(12,2) NOT NULL,
    peso_bruto numeric(12,2) DEFAULT 0 NOT NULL,
    tara numeric(12,2) DEFAULT 0 NOT NULL
);


--
-- Name: vendas_itens_id_venda_item_seq; Type: SEQUENCE; Schema: reciclagem; Owner: -
--

CREATE SEQUENCE reciclagem.vendas_itens_id_venda_item_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vendas_itens_id_venda_item_seq; Type: SEQUENCE OWNED BY; Schema: reciclagem; Owner: -
--

ALTER SEQUENCE reciclagem.vendas_itens_id_venda_item_seq OWNED BY reciclagem.vendas_itens.id_venda_item;


--
-- Name: centro_custo id_centro; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.centro_custo ALTER COLUMN id_centro SET DEFAULT nextval('reciclagem.centro_custo_id_centro_seq'::regclass);


--
-- Name: clientes id_cliente; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.clientes ALTER COLUMN id_cliente SET DEFAULT nextval('reciclagem.clientes_id_cliente_seq'::regclass);


--
-- Name: contas_pagar id_conta; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar ALTER COLUMN id_conta SET DEFAULT nextval('reciclagem.contas_pagar_id_conta_seq'::regclass);


--
-- Name: contas_pagar_parcelas id_parcela; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar_parcelas ALTER COLUMN id_parcela SET DEFAULT nextval('reciclagem.contas_pagar_parcelas_id_parcela_seq'::regclass);


--
-- Name: despesa_categoria id_categoria; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.despesa_categoria ALTER COLUMN id_categoria SET DEFAULT nextval('reciclagem.despesa_categoria_id_categoria_seq'::regclass);


--
-- Name: estoque_movimentacoes id_movimentacao; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.estoque_movimentacoes ALTER COLUMN id_movimentacao SET DEFAULT nextval('reciclagem.estoque_movimentacoes_id_movimentacao_seq'::regclass);


--
-- Name: fornecedores id_fornecedor; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.fornecedores ALTER COLUMN id_fornecedor SET DEFAULT nextval('reciclagem.fornecedores_id_fornecedor_seq'::regclass);


--
-- Name: importacao_historico id_importacao; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.importacao_historico ALTER COLUMN id_importacao SET DEFAULT nextval('reciclagem.importacao_historico_id_importacao_seq'::regclass);


--
-- Name: importacao_modelo id_modelo; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.importacao_modelo ALTER COLUMN id_modelo SET DEFAULT nextval('reciclagem.importacao_modelo_id_modelo_seq'::regclass);


--
-- Name: material_relacao id_relacao; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.material_relacao ALTER COLUMN id_relacao SET DEFAULT nextval('reciclagem.material_relacao_id_relacao_seq'::regclass);


--
-- Name: tb_documento id; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_documento ALTER COLUMN id SET DEFAULT nextval('reciclagem.tb_documento_id_seq'::regclass);


--
-- Name: tb_endereco id; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_endereco ALTER COLUMN id SET DEFAULT nextval('reciclagem.tb_endereco_id_seq'::regclass);


--
-- Name: tb_material id_material; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_material ALTER COLUMN id_material SET DEFAULT nextval('reciclagem.tb_material_id_material_seq'::regclass);


--
-- Name: tb_pesagem id_pesagem; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem ALTER COLUMN id_pesagem SET DEFAULT nextval('reciclagem.tb_pesagem_id_pesagem_seq'::regclass);


--
-- Name: tb_pesagem_material id; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem_material ALTER COLUMN id SET DEFAULT nextval('reciclagem.tb_pesagem_material_id_seq'::regclass);


--
-- Name: tb_registro_movimento id; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_registro_movimento ALTER COLUMN id SET DEFAULT nextval('reciclagem.tb_registro_movimento_id_seq'::regclass);


--
-- Name: tb_usuario id_usuario; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_usuario ALTER COLUMN id_usuario SET DEFAULT nextval('reciclagem.tb_usuario_id_usuario_seq'::regclass);


--
-- Name: vendas id_venda; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas ALTER COLUMN id_venda SET DEFAULT nextval('reciclagem.vendas_id_venda_seq'::regclass);


--
-- Name: vendas_itens id_venda_item; Type: DEFAULT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas_itens ALTER COLUMN id_venda_item SET DEFAULT nextval('reciclagem.vendas_itens_id_venda_item_seq'::regclass);


--
-- Name: centro_custo centro_custo_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.centro_custo
    ADD CONSTRAINT centro_custo_pkey PRIMARY KEY (id_centro);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id_cliente);


--
-- Name: contas_pagar_parcelas contas_pagar_parcelas_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar_parcelas
    ADD CONSTRAINT contas_pagar_parcelas_pkey PRIMARY KEY (id_parcela);


--
-- Name: contas_pagar contas_pagar_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar
    ADD CONSTRAINT contas_pagar_pkey PRIMARY KEY (id_conta);


--
-- Name: despesa_categoria despesa_categoria_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.despesa_categoria
    ADD CONSTRAINT despesa_categoria_pkey PRIMARY KEY (id_categoria);


--
-- Name: estoque_movimentacoes estoque_movimentacoes_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.estoque_movimentacoes
    ADD CONSTRAINT estoque_movimentacoes_pkey PRIMARY KEY (id_movimentacao);


--
-- Name: fornecedores fornecedores_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.fornecedores
    ADD CONSTRAINT fornecedores_pkey PRIMARY KEY (id_fornecedor);


--
-- Name: importacao_historico importacao_historico_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.importacao_historico
    ADD CONSTRAINT importacao_historico_pkey PRIMARY KEY (id_importacao);


--
-- Name: importacao_modelo importacao_modelo_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.importacao_modelo
    ADD CONSTRAINT importacao_modelo_pkey PRIMARY KEY (id_modelo);


--
-- Name: material_relacao material_relacao_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.material_relacao
    ADD CONSTRAINT material_relacao_pkey PRIMARY KEY (id_relacao);


--
-- Name: tb_pesagem pk_pesagem; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem
    ADD CONSTRAINT pk_pesagem PRIMARY KEY (id_pesagem);


--
-- Name: tb_pesagem_material pk_pesagem_material; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem_material
    ADD CONSTRAINT pk_pesagem_material PRIMARY KEY (id);


--
-- Name: tb_documento tb_documento_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_documento
    ADD CONSTRAINT tb_documento_pkey PRIMARY KEY (id);


--
-- Name: tb_endereco tb_endereco_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_endereco
    ADD CONSTRAINT tb_endereco_pkey PRIMARY KEY (id);


--
-- Name: tb_evento tb_evento_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_evento
    ADD CONSTRAINT tb_evento_pkey PRIMARY KEY (id);


--
-- Name: tb_material tb_material_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_material
    ADD CONSTRAINT tb_material_pkey PRIMARY KEY (id_material);


--
-- Name: tb_registro_movimento tb_registro_movimento_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_registro_movimento
    ADD CONSTRAINT tb_registro_movimento_pkey PRIMARY KEY (id);


--
-- Name: tb_usuario tb_usuario_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_usuario
    ADD CONSTRAINT tb_usuario_pkey PRIMARY KEY (id_usuario);


--
-- Name: tb_material unique_nm_material; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_material
    ADD CONSTRAINT unique_nm_material UNIQUE (nm_material);


--
-- Name: clientes uq_cliente_usuario; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.clientes
    ADD CONSTRAINT uq_cliente_usuario UNIQUE (id_usuario);


--
-- Name: contas_pagar_parcelas uq_parcela_numero; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar_parcelas
    ADD CONSTRAINT uq_parcela_numero UNIQUE (id_conta, numero_parcela);


--
-- Name: vendas_itens vendas_itens_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas_itens
    ADD CONSTRAINT vendas_itens_pkey PRIMARY KEY (id_venda_item);


--
-- Name: vendas vendas_pkey; Type: CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas
    ADD CONSTRAINT vendas_pkey PRIMARY KEY (id_venda);


--
-- Name: idx_mov_material; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX idx_mov_material ON reciclagem.estoque_movimentacoes USING btree (id_material);


--
-- Name: idx_mov_origem; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX idx_mov_origem ON reciclagem.estoque_movimentacoes USING btree (origem_tipo, origem_id);


--
-- Name: idx_pesagem_cliente; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX idx_pesagem_cliente ON reciclagem.tb_pesagem USING btree (id_cliente);


--
-- Name: idx_pesagem_status; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX idx_pesagem_status ON reciclagem.tb_pesagem USING btree (status);


--
-- Name: idx_tb_usuario_email_unico; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX idx_tb_usuario_email_unico ON reciclagem.tb_usuario USING btree (lower((email)::text));


--
-- Name: idx_vendas_fornecedor; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX idx_vendas_fornecedor ON reciclagem.vendas USING btree (id_fornecedor);


--
-- Name: idx_vendas_itens_venda; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX idx_vendas_itens_venda ON reciclagem.vendas_itens USING btree (id_venda);


--
-- Name: idx_vendas_status; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX idx_vendas_status ON reciclagem.vendas USING btree (status);


--
-- Name: ix_conta_categoria; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_conta_categoria ON reciclagem.contas_pagar USING btree (id_categoria);


--
-- Name: ix_conta_emissao; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_conta_emissao ON reciclagem.contas_pagar USING btree (data_emissao DESC);


--
-- Name: ix_importacao_data; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_importacao_data ON reciclagem.importacao_historico USING btree (data_importacao DESC);


--
-- Name: ix_material_estoque_minimo; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_material_estoque_minimo ON reciclagem.tb_material USING btree (id_material) WHERE (estoque_minimo IS NOT NULL);


--
-- Name: ix_parcela_conta; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_parcela_conta ON reciclagem.contas_pagar_parcelas USING btree (id_conta);


--
-- Name: ix_parcela_status; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_parcela_status ON reciclagem.contas_pagar_parcelas USING btree (status, data_vencimento);


--
-- Name: ix_parcela_vencimento; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_parcela_vencimento ON reciclagem.contas_pagar_parcelas USING btree (data_vencimento);


--
-- Name: ix_relacao_material; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE INDEX ix_relacao_material ON reciclagem.material_relacao USING btree (id_material);


--
-- Name: uq_categoria_nome; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX uq_categoria_nome ON reciclagem.despesa_categoria USING btree (lower((nome)::text)) WHERE (status = 1);


--
-- Name: uq_centro_nome; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX uq_centro_nome ON reciclagem.centro_custo USING btree (lower((nome)::text)) WHERE (status = 1);


--
-- Name: uq_material_codigo; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX uq_material_codigo ON reciclagem.tb_material USING btree (lower((codigo)::text)) WHERE ((codigo IS NOT NULL) AND (status = 1));


--
-- Name: uq_modelo_nome; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX uq_modelo_nome ON reciclagem.importacao_modelo USING btree (lower((nome)::text)) WHERE (status = 1);


--
-- Name: uq_relacao_codigo; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX uq_relacao_codigo ON reciclagem.material_relacao USING btree (codigo_normalizado, COALESCE(id_modelo, 0)) WHERE ((codigo_normalizado IS NOT NULL) AND (status = 1));


--
-- Name: uq_relacao_nome; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX uq_relacao_nome ON reciclagem.material_relacao USING btree (nome_normalizado, COALESCE(id_modelo, 0)) WHERE ((nome_normalizado IS NOT NULL) AND (status = 1));


--
-- Name: uq_venda_pedido_externo; Type: INDEX; Schema: reciclagem; Owner: -
--

CREATE UNIQUE INDEX uq_venda_pedido_externo ON reciclagem.vendas USING btree (lower((pedido_externo)::text)) WHERE (pedido_externo IS NOT NULL);


--
-- Name: contas_pagar contas_pagar_id_categoria_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar
    ADD CONSTRAINT contas_pagar_id_categoria_fkey FOREIGN KEY (id_categoria) REFERENCES reciclagem.despesa_categoria(id_categoria);


--
-- Name: contas_pagar contas_pagar_id_centro_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar
    ADD CONSTRAINT contas_pagar_id_centro_fkey FOREIGN KEY (id_centro) REFERENCES reciclagem.centro_custo(id_centro);


--
-- Name: contas_pagar contas_pagar_id_usuario_alteracao_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar
    ADD CONSTRAINT contas_pagar_id_usuario_alteracao_fkey FOREIGN KEY (id_usuario_alteracao) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: contas_pagar contas_pagar_id_usuario_cadastro_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar
    ADD CONSTRAINT contas_pagar_id_usuario_cadastro_fkey FOREIGN KEY (id_usuario_cadastro) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: contas_pagar contas_pagar_id_usuario_func_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar
    ADD CONSTRAINT contas_pagar_id_usuario_func_fkey FOREIGN KEY (id_usuario_func) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: contas_pagar_parcelas contas_pagar_parcelas_id_conta_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar_parcelas
    ADD CONSTRAINT contas_pagar_parcelas_id_conta_fkey FOREIGN KEY (id_conta) REFERENCES reciclagem.contas_pagar(id_conta);


--
-- Name: contas_pagar_parcelas contas_pagar_parcelas_id_usuario_pagamento_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.contas_pagar_parcelas
    ADD CONSTRAINT contas_pagar_parcelas_id_usuario_pagamento_fkey FOREIGN KEY (id_usuario_pagamento) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: clientes fk_cliente_usuario; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.clientes
    ADD CONSTRAINT fk_cliente_usuario FOREIGN KEY (id_usuario) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: tb_documento fk_documento_usuario; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_documento
    ADD CONSTRAINT fk_documento_usuario FOREIGN KEY (id_usuario) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: tb_endereco fk_endereco_usuario; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_endereco
    ADD CONSTRAINT fk_endereco_usuario FOREIGN KEY (id_usuario) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: vendas_itens fk_item_material; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas_itens
    ADD CONSTRAINT fk_item_material FOREIGN KEY (id_material) REFERENCES reciclagem.tb_material(id_material);


--
-- Name: vendas_itens fk_item_venda; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas_itens
    ADD CONSTRAINT fk_item_venda FOREIGN KEY (id_venda) REFERENCES reciclagem.vendas(id_venda);


--
-- Name: estoque_movimentacoes fk_mov_material; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.estoque_movimentacoes
    ADD CONSTRAINT fk_mov_material FOREIGN KEY (id_material) REFERENCES reciclagem.tb_material(id_material);


--
-- Name: estoque_movimentacoes fk_mov_usuario; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.estoque_movimentacoes
    ADD CONSTRAINT fk_mov_usuario FOREIGN KEY (id_usuario) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: tb_pesagem fk_pesagem_cliente; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem
    ADD CONSTRAINT fk_pesagem_cliente FOREIGN KEY (id_cliente) REFERENCES reciclagem.clientes(id_cliente);


--
-- Name: tb_pesagem_material fk_pesagem_material_material; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem_material
    ADD CONSTRAINT fk_pesagem_material_material FOREIGN KEY (id_material) REFERENCES reciclagem.tb_material(id_material);


--
-- Name: tb_pesagem fk_pesagem_usuario_cancelou; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem
    ADD CONSTRAINT fk_pesagem_usuario_cancelou FOREIGN KEY (id_usuario_cancelou) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: tb_registro_movimento fk_registro_admin; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_registro_movimento
    ADD CONSTRAINT fk_registro_admin FOREIGN KEY (id_usuario_admin) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: tb_registro_movimento fk_registro_modificado; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_registro_movimento
    ADD CONSTRAINT fk_registro_modificado FOREIGN KEY (id_usuario_modificado) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: vendas fk_venda_fornecedor; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas
    ADD CONSTRAINT fk_venda_fornecedor FOREIGN KEY (id_fornecedor) REFERENCES reciclagem.fornecedores(id_fornecedor);


--
-- Name: vendas fk_venda_importacao; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas
    ADD CONSTRAINT fk_venda_importacao FOREIGN KEY (id_importacao) REFERENCES reciclagem.importacao_historico(id_importacao);


--
-- Name: vendas fk_venda_usuario; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas
    ADD CONSTRAINT fk_venda_usuario FOREIGN KEY (id_usuario) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: vendas fk_venda_usuario_cancelou; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.vendas
    ADD CONSTRAINT fk_venda_usuario_cancelou FOREIGN KEY (id_usuario_cancelou) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: importacao_historico importacao_historico_id_modelo_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.importacao_historico
    ADD CONSTRAINT importacao_historico_id_modelo_fkey FOREIGN KEY (id_modelo) REFERENCES reciclagem.importacao_modelo(id_modelo);


--
-- Name: importacao_historico importacao_historico_id_usuario_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.importacao_historico
    ADD CONSTRAINT importacao_historico_id_usuario_fkey FOREIGN KEY (id_usuario) REFERENCES reciclagem.tb_usuario(id_usuario);


--
-- Name: importacao_modelo importacao_modelo_id_fornecedor_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.importacao_modelo
    ADD CONSTRAINT importacao_modelo_id_fornecedor_fkey FOREIGN KEY (id_fornecedor) REFERENCES reciclagem.fornecedores(id_fornecedor);


--
-- Name: material_relacao material_relacao_id_material_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.material_relacao
    ADD CONSTRAINT material_relacao_id_material_fkey FOREIGN KEY (id_material) REFERENCES reciclagem.tb_material(id_material);


--
-- Name: material_relacao material_relacao_id_modelo_fkey; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.material_relacao
    ADD CONSTRAINT material_relacao_id_modelo_fkey FOREIGN KEY (id_modelo) REFERENCES reciclagem.importacao_modelo(id_modelo);


--
-- Name: tb_pesagem_material tb_pesagem_tb_pesagem_material_fk; Type: FK CONSTRAINT; Schema: reciclagem; Owner: -
--

ALTER TABLE ONLY reciclagem.tb_pesagem_material
    ADD CONSTRAINT tb_pesagem_tb_pesagem_material_fk FOREIGN KEY (id_pesagem) REFERENCES reciclagem.tb_pesagem(id_pesagem);


--
-- PostgreSQL database dump complete
--

\unrestrict Dqaubo09jjquAia3igP25nJViuEShMSoztEpXNMTMTGSdZfRLtopZcsoqSHcYzU

