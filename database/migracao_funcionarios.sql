-- ============================================================================
-- FOOD HUB — MIGRAÇÃO: funcionários com permissões por tela
--
-- Para quem JÁ TEM o banco "foodhub". Rode UMA vez (pode repetir sem erro):
--   phpMyAdmin → clique no banco "foodhub" (à esquerda) → aba Importar → este arquivo.
--
-- O que muda:
--   usuario.perfil ............ ganha o valor 'funcionario' (além de master e comum)
--   usuario_permissao ......... o que cada funcionário pode ver / incluir / alterar / excluir em cada tela
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE usuario MODIFY perfil ENUM('master','comum','funcionario') NOT NULL DEFAULT 'comum';

CREATE TABLE IF NOT EXISTS usuario_permissao (
    id_usuario   INT         NOT NULL,
    modulo       VARCHAR(30) NOT NULL,
    pode_ver     TINYINT(1)  NOT NULL DEFAULT 0,
    pode_incluir TINYINT(1)  NOT NULL DEFAULT 0,
    pode_alterar TINYINT(1)  NOT NULL DEFAULT 0,
    pode_excluir TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (id_usuario, modulo),
    CONSTRAINT fk_permissao_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fim.
