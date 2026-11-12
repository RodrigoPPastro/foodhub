-- ============================================================================
-- FOOD HUB — SCRIPT SQL OFICIAL (consolidado)
-- Banco: MySQL / MariaDB (XAMPP)     Charset: utf8mb4
--
-- Cobre TODO o DER do pré-projeto:
--   Autenticação ............... usuario, autenticacao_2fa, log_acesso, usuario_permissao
--   Cardápio/Estoque ........... categoria, produto, insumo, produto_insumo, movimento_estoque
--   Pedidos/Delivery ........... mesa, entregador, canal_venda, pedido, item_pedido, notificacao_cliente
--   Financeiro ................. forma_pagamento, pagamento, fechamento_caixa, conta_financeira
--
-- Como usar:   mysql -u root < database/foodhub_completo.sql
-- Re-executável (IF NOT EXISTS / INSERT IGNORE). Para recriar do zero: DROP DATABASE foodhub;
--
-- Usuários de teste:
--   master  / Master1!   (master)  2FA: "nao informado" | "01/01/1990" | "20000000"
--   client  / Cliente1   (comum)   2FA: "maria souza"   | "15/03/1995" | "22790000"
-- TROQUE a senha do master antes de qualquer uso real (php database/gerar_hash.php).
-- ============================================================================

CREATE DATABASE IF NOT EXISTS foodhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foodhub;
SET NAMES utf8mb4;

-- ── 1. AUTENTICAÇÃO ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuario (
    id_usuario        INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo     VARCHAR(80)  NOT NULL,
    data_nascimento   DATE         NOT NULL,
    sexo              VARCHAR(20)  NOT NULL,
    nome_materno      VARCHAR(80)  NOT NULL,
    cpf               CHAR(11)     NOT NULL UNIQUE,
    email             VARCHAR(120) NOT NULL UNIQUE,
    telefone_celular  VARCHAR(20)  NOT NULL,
    telefone_fixo     VARCHAR(20)  NULL,
    endereco_completo VARCHAR(200) NOT NULL,
    login             CHAR(6)      NOT NULL UNIQUE,
    senha_hash        VARCHAR(255) NOT NULL,
    perfil            ENUM('master','comum','funcionario') NOT NULL DEFAULT 'comum',
    data_cadastro     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS autenticacao_2fa (
    id_2fa        INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario    INT NOT NULL,
    pergunta      ENUM('Qual o nome da sua mãe?','Qual a data do seu nascimento?','Qual o CEP do seu endereço?') NOT NULL,
    resposta_hash VARCHAR(255) NOT NULL,
    CONSTRAINT fk_2fa_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    UNIQUE KEY uq_usuario_pergunta (id_usuario, pergunta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_acesso (
    id_log             INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario         INT NOT NULL,
    data_hora          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_2fa_utilizado VARCHAR(60) NOT NULL,
    resultado          ENUM('sucesso','falha') NOT NULL,
    CONSTRAINT fk_log_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    INDEX idx_log_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Funcionários: perfil próprio + permissões por tela (ver / incluir / alterar / excluir).
ALTER TABLE usuario MODIFY perfil ENUM('master','comum','funcionario') NOT NULL DEFAULT 'comum';
CREATE TABLE IF NOT EXISTS usuario_permissao (
    id_usuario   INT         NOT NULL,
    modulo       VARCHAR(30) NOT NULL,      -- pedidos, cozinha, bar, mesas, estoque, contas… (src/Models/Permissao.php)
    pode_ver     TINYINT(1)  NOT NULL DEFAULT 0,
    pode_incluir TINYINT(1)  NOT NULL DEFAULT 0,
    pode_alterar TINYINT(1)  NOT NULL DEFAULT 0,
    pode_excluir TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (id_usuario, modulo),
    CONSTRAINT fk_permissao_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. CARDÁPIO E ESTOQUE (RF13–RF16) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(60)  NOT NULL UNIQUE,
    descricao    VARCHAR(200) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produto (
    id_produto   INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT           NOT NULL,
    nome         VARCHAR(80)   NOT NULL,
    descricao    VARCHAR(300)  NULL,
    foto         VARCHAR(255)  NULL,            -- caminho relativo a public/ (uploads/produtos/x.jpg)
    preco_venda  DECIMAL(10,2) NOT NULL,
    ativo        TINYINT(1)    NOT NULL DEFAULT 1,
    setor_preparo ENUM('cozinha','bar','nenhum') NOT NULL DEFAULT 'cozinha',  -- onde é preparado ('nenhum' = pronta entrega)
    CONSTRAINT fk_produto_categoria FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
    CONSTRAINT ck_produto_preco CHECK (preco_venda >= 0),
    INDEX idx_produto_categoria (id_categoria),
    INDEX idx_produto_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE produto ADD COLUMN IF NOT EXISTS setor_preparo ENUM('cozinha','bar','nenhum') NOT NULL DEFAULT 'cozinha';

CREATE TABLE IF NOT EXISTS insumo (
    id_insumo      INT AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(80)   NOT NULL UNIQUE,
    unidade_medida VARCHAR(10)   NOT NULL,            -- kg, g, l, ml, un
    estoque_atual  DECIMAL(12,3) NOT NULL DEFAULT 0,
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
    custo_unitario DECIMAL(10,4) NOT NULL DEFAULT 0   -- custo por unidade_medida
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ficha técnica: quanto de cada insumo um produto consome
CREATE TABLE IF NOT EXISTS produto_insumo (
    id_produto           INT           NOT NULL,
    id_insumo            INT           NOT NULL,
    quantidade_utilizada DECIMAL(12,3) NOT NULL,
    PRIMARY KEY (id_produto, id_insumo),
    CONSTRAINT fk_pi_produto FOREIGN KEY (id_produto) REFERENCES produto(id_produto) ON DELETE CASCADE,
    CONSTRAINT fk_pi_insumo  FOREIGN KEY (id_insumo)  REFERENCES insumo(id_insumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimento_estoque (
    id_movimento INT AUTO_INCREMENT PRIMARY KEY,
    id_insumo    INT           NOT NULL,
    id_usuario   INT           NULL,             -- NULL = movimento automático
    tipo         ENUM('entrada','saida','perda') NOT NULL,
    quantidade   DECIMAL(12,3) NOT NULL,
    data_hora    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo       VARCHAR(200)  NULL,
    CONSTRAINT fk_mov_insumo  FOREIGN KEY (id_insumo)  REFERENCES insumo(id_insumo),
    CONSTRAINT fk_mov_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE SET NULL,
    INDEX idx_mov_insumo_data (id_insumo, data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. PEDIDOS, MESAS E DELIVERY (RF17–RF21) ────────────────────────────────
CREATE TABLE IF NOT EXISTS mesa (
    id_mesa    INT AUTO_INCREMENT PRIMARY KEY,
    numero     INT NOT NULL UNIQUE,
    capacidade INT NOT NULL DEFAULT 4,
    status     ENUM('livre','ocupada') NOT NULL DEFAULT 'livre'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entregador (
    id_entregador INT AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(80) NOT NULL,
    telefone      VARCHAR(20) NOT NULL,
    veiculo       VARCHAR(40) NULL,
    status        ENUM('disponivel','em_rota','inativo') NOT NULL DEFAULT 'disponivel'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RNF09: nova plataforma de delivery = novo INSERT aqui, sem redesenhar o banco.
CREATE TABLE IF NOT EXISTS canal_venda (
    codigo     VARCHAR(30) NOT NULL PRIMARY KEY,
    rotulo     VARCHAR(60) NOT NULL,
    presencial TINYINT(1)  NOT NULL DEFAULT 0,   -- 1 = balcão/mesa; 0 = delivery
    externo    TINYINT(1)  NOT NULL DEFAULT 0,   -- 1 = plataforma de terceiros
    ativo      TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contas a receber: comissão e prazo de repasse de cada plataforma (parametrizável por canal).
-- (ADD COLUMN IF NOT EXISTS: MariaDB/XAMPP. Mantém bancos criados em versões anteriores do script.)
ALTER TABLE canal_venda ADD COLUMN IF NOT EXISTS comissao_percentual DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE canal_venda ADD COLUMN IF NOT EXISTS prazo_repasse_dias  SMALLINT     NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS pedido (
    id_pedido                   INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario_cliente          INT          NULL,
    id_entregador               INT          NULL,
    id_mesa                     INT          NULL,
    data_hora                   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_hora_inicio_preparo    DATETIME     NULL,    -- base do destaque de atraso
    canal                       VARCHAR(30)  NOT NULL,
    status                      ENUM('novo','em preparo','pronto','saiu','entregue','cancelado') NOT NULL DEFAULT 'novo',
    plataforma_externa          VARCHAR(30)  NULL,
    id_pedido_externo           VARCHAR(60)  NULL,
    sincronizado_status_externo TINYINT(1)   NOT NULL DEFAULT 0,
    valor_total                 DECIMAL(10,2) NOT NULL DEFAULT 0,
    nome_cliente                VARCHAR(80)  NULL,
    telefone_cliente            VARCHAR(20)  NULL,
    endereco_entrega            VARCHAR(255) NULL,
    observacao                  VARCHAR(300) NULL,
    estoque_baixado             TINYINT(1)   NOT NULL DEFAULT 0,   -- garante baixa única (RF15)
    CONSTRAINT fk_pedido_cliente    FOREIGN KEY (id_usuario_cliente) REFERENCES usuario(id_usuario) ON DELETE SET NULL,
    CONSTRAINT fk_pedido_entregador FOREIGN KEY (id_entregador)      REFERENCES entregador(id_entregador) ON DELETE SET NULL,
    CONSTRAINT fk_pedido_mesa       FOREIGN KEY (id_mesa)            REFERENCES mesa(id_mesa) ON DELETE SET NULL,
    CONSTRAINT fk_pedido_canal      FOREIGN KEY (canal)              REFERENCES canal_venda(codigo),
    UNIQUE KEY uq_pedido_externo (plataforma_externa, id_pedido_externo),
    INDEX idx_pedido_status (status),
    INDEX idx_pedido_data_hora (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_pedido (
    id_item        INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido      INT           NOT NULL,
    id_produto     INT           NOT NULL,
    quantidade     INT           NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,           -- congelado no momento da venda
    observacao     VARCHAR(200)  NULL,
    setor_preparo  ENUM('cozinha','bar','nenhum') NOT NULL DEFAULT 'cozinha',  -- congelado na venda
    status_item    ENUM('aguardando','em preparo','pronto','entregue') NOT NULL DEFAULT 'aguardando',
    data_hora_inicio_preparo DATETIME NULL,          -- quando o setor começou este item
    CONSTRAINT fk_item_pedido  FOREIGN KEY (id_pedido)  REFERENCES pedido(id_pedido) ON DELETE CASCADE,
    CONSTRAINT fk_item_produto FOREIGN KEY (id_produto) REFERENCES produto(id_produto),
    CONSTRAINT ck_item_qtd CHECK (quantidade > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Setores de preparo (cozinha / bar / pronta entrega). Mantém bancos criados em versões anteriores.
ALTER TABLE item_pedido ADD COLUMN IF NOT EXISTS setor_preparo ENUM('cozinha','bar','nenhum') NOT NULL DEFAULT 'cozinha';
ALTER TABLE item_pedido ADD COLUMN IF NOT EXISTS status_item ENUM('aguardando','em preparo','pronto','entregue') NOT NULL DEFAULT 'aguardando';
ALTER TABLE item_pedido ADD COLUMN IF NOT EXISTS data_hora_inicio_preparo DATETIME NULL;

-- Simulação de WhatsApp (RNF11: isolada em src/Adapters)
CREATE TABLE IF NOT EXISTS notificacao_cliente (
    id_notificacao  INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido       INT          NOT NULL,
    tipo            ENUM('confirmacao','atraso','pronto') NOT NULL,
    canal           VARCHAR(20)  NOT NULL DEFAULT 'whatsapp',
    mensagem        VARCHAR(300) NULL,
    data_hora_envio DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_envio    ENUM('enviado','simulado','falha') NOT NULL DEFAULT 'simulado',
    CONSTRAINT fk_notif_pedido FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido) ON DELETE CASCADE,
    INDEX idx_notif_pedido (id_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. FINANCEIRO (RF22–RF23) ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS forma_pagamento (
    id_forma_pagamento INT AUTO_INCREMENT PRIMARY KEY,
    descricao          VARCHAR(30) NOT NULL UNIQUE      -- dinheiro, pix, credito, debito, vale
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamento (
    id_pagamento       INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido          INT           NOT NULL,
    id_forma_pagamento INT           NOT NULL,
    valor              DECIMAL(10,2) NOT NULL,
    data_hora          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pag_pedido FOREIGN KEY (id_pedido)          REFERENCES pedido(id_pedido) ON DELETE CASCADE,
    CONSTRAINT fk_pag_forma  FOREIGN KEY (id_forma_pagamento) REFERENCES forma_pagamento(id_forma_pagamento),
    INDEX idx_pagamento_data_hora (data_hora),
    INDEX idx_pagamento_pedido (id_pedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Taxa de serviço (10% do garçom) cobrada ao fechar a conta de uma mesa.
-- Não é receita da casa: fica registrada à parte para repasse ao garçom e
-- por isso NÃO entra na tabela pagamento (o fechamento de caixa soma só vendas).
CREATE TABLE IF NOT EXISTS garcom (
    id_garcom INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(80) NOT NULL UNIQUE,
    ativo     TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO garcom (nome) VALUES ('Marcos Lima'), ('Juliana Alves');

CREATE TABLE IF NOT EXISTS taxa_servico (
    id_taxa     INT AUTO_INCREMENT PRIMARY KEY,
    id_mesa     INT           NOT NULL,
    id_usuario  INT           NULL,          -- quem fechou a conta no sistema
    id_garcom   INT           NULL,          -- garçom que atendeu (quem recebe a taxa)
    status      VARCHAR(10)   NOT NULL DEFAULT 'pendente',   -- pendente | pago (repassado ao garçom)
    data_pagamento DATETIME   NULL,
    id_usuario_pagou INT      NULL,
    base        DECIMAL(10,2) NOT NULL,
    percentual  DECIMAL(5,2)  NOT NULL,
    valor       DECIMAL(10,2) NOT NULL,
    data_hora   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_taxa_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fechamento_caixa (
    id_fechamento          INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario_responsavel INT           NULL,
    data_referencia        DATE          NOT NULL,
    total_vendas           DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_dinheiro         DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_cartao           DECIMAL(10,2) NOT NULL DEFAULT 0,   -- crédito + débito
    total_pix              DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_delivery         DECIMAL(10,2) NOT NULL DEFAULT 0,   -- todos os canais não presenciais
    observacoes            VARCHAR(500)  NULL,
    detalhamento           LONGTEXT      NULL,                 -- snapshot JSON (por forma e por canal)
    CONSTRAINT fk_fech_usuario FOREIGN KEY (id_usuario_responsavel) REFERENCES usuario(id_usuario) ON DELETE SET NULL,
    UNIQUE KEY uq_fechamento_data (data_referencia)            -- 1 fechamento por dia
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. PARÂMETROS ───────────────────────────────────────────────────────────
-- Contas a pagar / a receber. "Vencida" não é gravada: é aberta com vencimento anterior a hoje.
-- id_pedido preenchido = repasse de plataforma gerado automaticamente (UNIQUE: um repasse por pedido).
CREATE TABLE IF NOT EXISTS conta_financeira (
    id_conta            INT AUTO_INCREMENT PRIMARY KEY,
    tipo                ENUM('pagar','receber') NOT NULL,
    descricao           VARCHAR(150)  NOT NULL,
    categoria           VARCHAR(30)   NOT NULL,
    pessoa              VARCHAR(100)  NULL,                -- fornecedor (pagar) ou origem (receber)
    valor               DECIMAL(10,2) NOT NULL,            -- valor líquido previsto (ou o realmente baixado)
    valor_bruto         DECIMAL(10,2) NULL,                -- repasse: total do pedido antes da comissão
    comissao_percentual DECIMAL(5,2)  NULL,
    data_emissao        DATE          NOT NULL,
    data_vencimento     DATE          NOT NULL,
    data_baixa          DATE          NULL,
    status              ENUM('aberta','baixada','cancelada') NOT NULL DEFAULT 'aberta',
    id_pedido           INT           NULL,
    id_usuario_baixa    INT           NULL,
    observacao          VARCHAR(255)  NULL,
    CONSTRAINT fk_conta_pedido  FOREIGN KEY (id_pedido)        REFERENCES pedido(id_pedido) ON DELETE SET NULL,
    CONSTRAINT fk_conta_usuario FOREIGN KEY (id_usuario_baixa) REFERENCES usuario(id_usuario) ON DELETE SET NULL,
    UNIQUE KEY uq_conta_pedido (id_pedido),
    INDEX idx_conta_tipo_status (tipo, status, data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO forma_pagamento (descricao) VALUES ('dinheiro'),('pix'),('credito'),('debito'),('vale');

INSERT IGNORE INTO canal_venda (codigo, rotulo, presencial, externo) VALUES
    ('balcao','Balcão',1,0), ('mesa','Mesa',1,0), ('delivery_proprio','Delivery próprio',0,0),
    ('ifood','iFood',0,1), ('rappi','Rappi',0,1);

UPDATE canal_venda SET comissao_percentual = 12, prazo_repasse_dias = 30 WHERE codigo = 'ifood' AND comissao_percentual = 0 AND prazo_repasse_dias = 0;
UPDATE canal_venda SET comissao_percentual = 15, prazo_repasse_dias = 15 WHERE codigo = 'rappi' AND comissao_percentual = 0 AND prazo_repasse_dias = 0;

INSERT IGNORE INTO mesa (numero, capacidade) VALUES (1,2),(2,2),(3,4),(4,4),(5,4),(6,6),(7,6),(8,8);

INSERT IGNORE INTO entregador (id_entregador, nome, telefone, veiculo) VALUES
    (1,'Carlos Henrique','(+55)21-987651234','Moto Honda CG 160'),
    (2,'Ricardo Almeida','(+55)21-986542345','Moto Yamaha Factor'),
    (3,'Juliana Ferreira','(+55)21-985433456','Bicicleta elétrica');

-- ── 6. CARDÁPIO DE EXEMPLO ──────────────────────────────────────────────────
INSERT IGNORE INTO categoria (id_categoria, nome, descricao) VALUES
    (1,'Hambúrgueres','Artesanais, com pão brioche e carne grelhada na hora'),
    (2,'Combos','Lanche + batata + bebida com desconto'),
    (3,'Porções','Para dividir (ou não)'),
    (4,'Bebidas','Refrigerantes, sucos e água'),
    (5,'Sobremesas','Para fechar com chave de ouro');

INSERT IGNORE INTO insumo (id_insumo, nome, unidade_medida, estoque_atual, estoque_minimo, custo_unitario) VALUES
    (1,'Pão brioche','un',120,30,1.4000),
    (2,'Carne bovina (blend 150 g)','kg',18,5,38.0000),
    (3,'Queijo cheddar fatiado','kg',6,2,42.0000),
    (4,'Bacon fatiado','kg',4,1.5,36.0000),
    (5,'Alface americana','kg',3,1,12.0000),
    (6,'Tomate','kg',4,1.5,8.0000),
    (7,'Batata palito congelada','kg',25,8,14.0000),
    (8,'Óleo de fritura','l',20,6,9.5000),
    (9,'Frango empanado (nuggets)','kg',5,2,28.0000),
    (10,'Molho especial da casa','l',4,1,22.0000),
    (11,'Refrigerante lata 350 ml','un',96,24,3.2000),
    (12,'Polpa de fruta (suco)','kg',8,3,16.0000),
    (13,'Sorvete de creme','l',6,2,24.0000),
    (14,'Calda de chocolate','l',2,0.5,30.0000),
    (15,'Cebola','kg',5,2,6.0000),
    (16,'Água mineral 500 ml','un',60,20,1.2000);

INSERT IGNORE INTO produto (id_produto, id_categoria, nome, descricao, preco_venda, ativo, setor_preparo) VALUES
    (1,1,'X-Burger Clássico','Pão brioche, blend 150 g, queijo cheddar, alface, tomate e molho da casa.',24.90,1,'cozinha'),
    (2,1,'X-Bacon','Blend 150 g, cheddar derretido, bacon crocante e molho especial.',28.90,1,'cozinha'),
    (3,1,'Duplo Hub','Dois blends de 150 g, dobro de cheddar, cebola e molho da casa.',34.90,1,'cozinha'),
    (4,1,'Chicken Crunch','Frango empanado crocante, alface, tomate e molho especial no brioche.',26.90,1,'cozinha'),
    (5,2,'Combo Clássico','X-Burger Clássico + batata média + refrigerante lata.',38.90,1,'cozinha'),
    (6,2,'Combo Duplo Hub','Duplo Hub + batata média + refrigerante lata.',47.90,1,'cozinha'),
    (7,3,'Batata Frita Média','Porção de batata palito crocante (300 g).',14.90,1,'cozinha'),
    (8,3,'Nuggets (10 un)','Dez nuggets de frango empanados com molho da casa.',19.90,1,'cozinha'),
    (9,4,'Refrigerante Lata','Lata 350 ml gelada.',6.50,1,'nenhum'),
    (10,4,'Suco Natural','Suco de polpa de fruta, 400 ml.',9.90,1,'bar'),
    (11,4,'Água Mineral','Garrafa 500 ml sem gás.',4.50,1,'nenhum'),
    (12,5,'Sundae de Chocolate','Sorvete de creme com calda de chocolate.',12.90,1,'bar');

INSERT IGNORE INTO produto_insumo (id_produto, id_insumo, quantidade_utilizada) VALUES
    (1,1,1),(1,2,0.150),(1,3,0.030),(1,5,0.020),(1,6,0.030),(1,10,0.020),
    (2,1,1),(2,2,0.150),(2,3,0.030),(2,4,0.040),(2,10,0.020),
    (3,1,1),(3,2,0.300),(3,3,0.060),(3,15,0.020),(3,10,0.020),
    (4,1,1),(4,9,0.150),(4,5,0.020),(4,6,0.030),(4,10,0.020),
    (5,1,1),(5,2,0.150),(5,3,0.030),(5,5,0.020),(5,6,0.030),(5,10,0.020),(5,7,0.200),(5,8,0.030),(5,11,1),
    (6,1,1),(6,2,0.300),(6,3,0.060),(6,15,0.020),(6,10,0.020),(6,7,0.200),(6,8,0.030),(6,11,1),
    (7,7,0.300),(7,8,0.040),
    (8,9,0.200),(8,8,0.030),(8,10,0.030),
    (9,11,1),(10,12,0.120),(11,16,1),
    (12,13,0.150),(12,14,0.030);

-- ── 7. USUÁRIOS DE TESTE ────────────────────────────────────────────────────
INSERT INTO usuario (nome_completo, data_nascimento, sexo, nome_materno, cpf, email, telefone_celular, endereco_completo, login, senha_hash, perfil)
VALUES ('Administrador Master','1990-01-01','Não informado','Não informado','00000000000','master@foodhub.com',
        '(+55)21-999999999','Sede FOOD HUB','master','$2y$10$BTK15LAdZq3bQSyVXupzXu7NzuG/I00vG0s/miDi9ksHGTkGxUC/O','master')
ON DUPLICATE KEY UPDATE id_usuario = id_usuario;

INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
SELECT id_usuario,'Qual o nome da sua mãe?','$2y$10$C10fn4uBJB8qBXgAV6l6B.aHJumuTtoUeNgA0XIjoUEctX/DSVs.m' FROM usuario WHERE login='master'
ON DUPLICATE KEY UPDATE resposta_hash = VALUES(resposta_hash);
INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
SELECT id_usuario,'Qual a data do seu nascimento?','$2y$10$3Ng50fGiLGPWdlJVB3IKNuEgXAYdbiRi8xuPGkgsQX57pdTPuJiMu' FROM usuario WHERE login='master'
ON DUPLICATE KEY UPDATE resposta_hash = VALUES(resposta_hash);
INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
SELECT id_usuario,'Qual o CEP do seu endereço?','$2y$10$aOh4qAidhrYVZ/IUInOXQej3VIspNVkbrTcMmG3HeeN3Gi14W3t8a' FROM usuario WHERE login='master'
ON DUPLICATE KEY UPDATE resposta_hash = VALUES(resposta_hash);

-- Cliente de teste (CPF válido de exemplo: 529.982.247-25)
INSERT INTO usuario (nome_completo, data_nascimento, sexo, nome_materno, cpf, email, telefone_celular, endereco_completo, login, senha_hash, perfil)
VALUES ('Cliente de Teste FoodHub','1995-03-15','Feminino','Maria Souza','52998224725','cliente@foodhub.com',
        '(+55)21-988887777','Rua das Flores, 100, Centro, Rio de Janeiro - RJ, CEP 22790000','client',
        '$2y$10$fmzWlbOxhDN2RJSCtiNTouibUirtYJha3/98HS6YGV9WKDY.5hlb.','comum')
ON DUPLICATE KEY UPDATE id_usuario = id_usuario;

INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
SELECT id_usuario,'Qual o nome da sua mãe?','$2y$10$.WdEY68MOz7p7TumDHSl0e6cV4d/glRDCoGPwjpe5.vl20k3jrlBy' FROM usuario WHERE login='client'
ON DUPLICATE KEY UPDATE resposta_hash = VALUES(resposta_hash);
INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
SELECT id_usuario,'Qual a data do seu nascimento?','$2y$10$HF4L00bhfoN7rIyAm1dOUuFV0mt8QZhGcoEnwFe49n5VD.4pKHV.G' FROM usuario WHERE login='client'
ON DUPLICATE KEY UPDATE resposta_hash = VALUES(resposta_hash);
INSERT INTO autenticacao_2fa (id_usuario, pergunta, resposta_hash)
SELECT id_usuario,'Qual o CEP do seu endereço?','$2y$10$GJi1qRTGS0S0NYnx50PG3uOMvWC0xQQ4fabUC5GNLdAXHA57WG7MO' FROM usuario WHERE login='client'
ON DUPLICATE KEY UPDATE resposta_hash = VALUES(resposta_hash);

-- Fim. Opcional: php database/seed_demo.php (pedidos dos últimos 7 dias para o Dashboard).
