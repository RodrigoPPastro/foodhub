-- FOOD HUB - schema consolidado inicial (autenticacao + cardapio + estoque)
-- Executar uma vez no MySQL Workbench, com o schema foodhub selecionado.
CREATE DATABASE IF NOT EXISTS foodhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foodhub;

CREATE TABLE IF NOT EXISTS usuario (
 id_usuario INT AUTO_INCREMENT PRIMARY KEY,
 nome_completo VARCHAR(80) NOT NULL, data_nascimento DATE NOT NULL, sexo VARCHAR(20) NOT NULL,
 nome_materno VARCHAR(80) NOT NULL, cpf CHAR(11) NOT NULL UNIQUE, email VARCHAR(120) NOT NULL UNIQUE,
 telefone_celular VARCHAR(20) NOT NULL, telefone_fixo VARCHAR(20) NULL, endereco_completo VARCHAR(200) NOT NULL,
 login CHAR(6) NOT NULL UNIQUE, senha_hash VARCHAR(255) NOT NULL,
 perfil ENUM('master','comum') NOT NULL DEFAULT 'comum', data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS autenticacao_2fa (
 id_2fa INT AUTO_INCREMENT PRIMARY KEY, id_usuario INT NOT NULL,
 pergunta ENUM('Qual o nome da sua mãe?','Qual a data do seu nascimento?','Qual o CEP do seu endereço?') NOT NULL,
 resposta_hash VARCHAR(255) NOT NULL,
 CONSTRAINT fk_2fa_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
 UNIQUE KEY uq_usuario_pergunta (id_usuario, pergunta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS log_acesso (
 id_log INT AUTO_INCREMENT PRIMARY KEY, id_usuario INT NOT NULL, data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 tipo_2fa_utilizado VARCHAR(60) NOT NULL, resultado ENUM('sucesso','falha') NOT NULL,
 CONSTRAINT fk_log_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categoria (
 id_categoria INT AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(80) NOT NULL UNIQUE,
 descricao VARCHAR(255) NULL,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS produto (
 id_produto INT AUTO_INCREMENT PRIMARY KEY,
 id_categoria INT NOT NULL,
 nome VARCHAR(120) NOT NULL,
 descricao TEXT NULL,
 foto VARCHAR(255) NULL,
 preco_venda DECIMAL(10,2) NOT NULL,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_produto_categoria FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
 INDEX idx_produto_nome (nome), INDEX idx_produto_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS insumo (
 id_insumo INT AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(120) NOT NULL UNIQUE,
 unidade_medida VARCHAR(20) NOT NULL,
 estoque_atual DECIMAL(12,3) NOT NULL DEFAULT 0,
 estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
 custo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK (estoque_atual >= 0), CHECK (estoque_minimo >= 0), CHECK (custo_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS produto_insumo (
 id_produto INT NOT NULL,
 id_insumo INT NOT NULL,
 quantidade_utilizada DECIMAL(12,3) NOT NULL,
 PRIMARY KEY (id_produto,id_insumo),
 CONSTRAINT fk_pi_produto FOREIGN KEY (id_produto) REFERENCES produto(id_produto) ON DELETE CASCADE,
 CONSTRAINT fk_pi_insumo FOREIGN KEY (id_insumo) REFERENCES insumo(id_insumo),
 CHECK (quantidade_utilizada > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS movimento_estoque (
 id_movimento INT AUTO_INCREMENT PRIMARY KEY,
 id_insumo INT NOT NULL,
 id_usuario INT NOT NULL,
 tipo ENUM('entrada','saida','perda') NOT NULL,
 quantidade DECIMAL(12,3) NOT NULL,
 data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 motivo VARCHAR(255) NULL,
 CONSTRAINT fk_mov_insumo FOREIGN KEY (id_insumo) REFERENCES insumo(id_insumo),
 CONSTRAINT fk_mov_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
 CHECK (quantidade > 0), INDEX idx_mov_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
