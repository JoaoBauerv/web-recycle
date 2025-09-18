composer update

composer require vlucas/phpdotenv 
composer require phpmailer/phpmailer
composer require dompdf/dompdf

-- Tabela de usuários
CREATE TABLE tb_usuario (
    id_usuario SERIAL PRIMARY KEY,
    nome VARCHAR(40) NOT NULL,
    email VARCHAR(50) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    foto VARCHAR(255) NOT NULL,
    status SMALLINT NOT NULL DEFAULT 1,
    usuario VARCHAR(40) NOT NULL,
    data_nascimento DATE DEFAULT NULL,
    permissao VARCHAR(30) NOT NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    celular VARCHAR(20) DEFAULT NULL,
    precisa_alterar_senha SMALLINT DEFAULT 0
);

-- Tabela de documentos
CREATE TABLE tb_documento (
    id SERIAL PRIMARY KEY,
    id_usuario INT NOT NULL,
    cpf VARCHAR(14) DEFAULT NULL,
    rg VARCHAR(9) DEFAULT NULL,
    cnh VARCHAR(11) DEFAULT NULL,
    CONSTRAINT fk_documento_usuario FOREIGN KEY (id_usuario) REFERENCES tb_usuario(id_usuario)
);

-- Tabela de endereços
CREATE TABLE tb_endereco (
    id SERIAL PRIMARY KEY,
    id_usuario INT NOT NULL,
    cep VARCHAR(9) DEFAULT NULL,
    logradouro VARCHAR(100) DEFAULT NULL,
    numero INT DEFAULT NULL,
    complemento VARCHAR(50) DEFAULT NULL,
    cidade VARCHAR(50) DEFAULT NULL,
    bairro VARCHAR(50) DEFAULT NULL,
    referencia VARCHAR(100) DEFAULT NULL,
    CONSTRAINT fk_endereco_usuario FOREIGN KEY (id_usuario) REFERENCES tb_usuario(id_usuario)
);

-- Tabela de registros de movimento
CREATE TABLE tb_registro_movimento (
    id SERIAL PRIMARY KEY,
    id_usuario_admin INT NOT NULL,
    id_usuario_modificado INT NOT NULL,
    descricao VARCHAR(40) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    data TIMESTAMP NOT NULL,
    CONSTRAINT fk_registro_admin FOREIGN KEY (id_usuario_admin) REFERENCES tb_usuario(id_usuario),
    CONSTRAINT fk_registro_modificado FOREIGN KEY (id_usuario_modificado) REFERENCES tb_usuario(id_usuario)
);

CREATE TABLE tb_material (
    id_material SERIAL PRIMARY KEY,
    nm_material VARCHAR(40) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    medida VARCHAR(20) NOT NULL,
	preco_compra numeric(10,2) NOT NULL,
	qt_estoque numeric(10,2) NULL 
);
