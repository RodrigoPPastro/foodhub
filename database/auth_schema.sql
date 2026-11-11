-- ============================================================
-- FOOD HUB — Schema das tabelas de Autenticação (módulo Rodrigo)
-- Tabelas: usuario, autenticacao_2fa, log_acesso
--
-- IMPORTANTE: este arquivo cobre só as tabelas do módulo de
-- autenticação. A Fernanda vai consolidar isso no script SQL
-- oficial do projeto junto com categoria/produto/insumo/pedido/etc,
-- conforme o DER completo do pré-projeto. Não rodar os dois scripts
-- de forma duplicada — combinar com ela antes.
-- ============================================================

CREATE TABLE IF NOT EXISTS usuario (
    id_usuario        INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo      VARCHAR(80)  NOT NULL,
    data_nascimento    DATE         NOT NULL,
    sexo               VARCHAR(20)  NOT NULL,
    nome_materno       VARCHAR(80)  NOT NULL,
    cpf                CHAR(11)     NOT NULL UNIQUE,
    email              VARCHAR(120) NOT NULL UNIQUE,
    telefone_celular   VARCHAR(20)  NOT NULL,
    telefone_fixo      VARCHAR(20)  NULL,
    endereco_completo  VARCHAR(200) NOT NULL,
    login              CHAR(6)      NOT NULL UNIQUE,
    senha_hash         VARCHAR(255) NOT NULL,
    perfil             ENUM('master','comum','funcionario') NOT NULL DEFAULT 'comum',
    data_cadastro      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS autenticacao_2fa (
    id_2fa          INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT NOT NULL,
    pergunta        ENUM(
        'Qual o nome da sua mãe?',
        'Qual a data do seu nascimento?',
        'Qual o CEP do seu endereço?'
    ) NOT NULL,
    resposta_hash   VARCHAR(255) NOT NULL,
    CONSTRAINT fk_2fa_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY uq_usuario_pergunta (id_usuario, pergunta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS log_acesso (
    id_log            INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario        INT NOT NULL,
    data_hora         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_2fa_utilizado VARCHAR(60) NOT NULL,
    resultado         ENUM('sucesso','falha') NOT NULL,
    CONSTRAINT fk_log_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário master de exemplo (conforme enunciado: master é criado via script SQL).
-- Senha de exemplo: "Master1!" -> troque o hash abaixo pelo gerado com password_hash() real.
-- Rode scripts/gerar_hash.php (incluso) para gerar o hash correto antes de inserir.
-- INSERT INTO usuario (nome_completo, data_nascimento, sexo, nome_materno, cpf, email,
--     telefone_celular, endereco_completo, login, senha_hash, perfil)
-- VALUES ('Administrador Master', '1990-01-01', 'Não informado', 'Não informado',
--     '00000000000', 'master@foodhub.com', '(+55)21-999999999', 'Sede FOOD HUB',
--     'master', '<COLE_O_HASH_AQUI>', 'master');
