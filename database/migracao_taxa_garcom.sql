-- ============================================================================
-- FOOD HUB — MIGRAÇÃO: taxa de serviço (10%) dos garçons
--
-- Para quem JÁ TEM o banco "foodhub". Rode UMA vez (pode repetir sem erro):
--   phpMyAdmin → clique no banco "foodhub" (à esquerda) → aba Importar → este arquivo.
--
-- O que muda:
--   garcom ........ cadastro dos garçons (quem recebe a taxa)
--   taxa_servico .. taxa de 10% cobrada ao fechar a conta da mesa, com status pendente/pago
-- ============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS garcom (
    id_garcom INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(80) NOT NULL UNIQUE,
    ativo     TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO garcom (nome) VALUES ('Marcos Lima'), ('Juliana Alves');

CREATE TABLE IF NOT EXISTS taxa_servico (
    id_taxa     INT AUTO_INCREMENT PRIMARY KEY,
    id_mesa     INT           NOT NULL,
    id_usuario  INT           NULL,
    id_garcom   INT           NULL,
    status      VARCHAR(10)   NOT NULL DEFAULT 'pendente',
    data_pagamento DATETIME   NULL,
    id_usuario_pagou INT      NULL,
    base        DECIMAL(10,2) NOT NULL,
    percentual  DECIMAL(5,2)  NOT NULL,
    valor       DECIMAL(10,2) NOT NULL,
    data_hora   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_taxa_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garçom que atende a mesa, escolhido ao lançar o pedido (compatível com MySQL e MariaDB)
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedido' AND COLUMN_NAME = 'id_garcom');
SET @comando := IF(@existe = 0, 'ALTER TABLE pedido ADD COLUMN id_garcom INT NULL AFTER id_mesa', 'SELECT 1');
PREPARE passo FROM @comando;
EXECUTE passo;
DEALLOCATE PREPARE passo;
