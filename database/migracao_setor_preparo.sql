-- ============================================================================
-- FOOD HUB — MIGRAÇÃO: setores de preparo (Cozinha / Bar / Pronta entrega)
--
-- Para quem JÁ TEM o banco "foodhub" criado. Rode UMA vez:
--   phpMyAdmin → clique no banco "foodhub" (à esquerda) → aba Importar → este arquivo.
--   (ou: mysql -u root foodhub < database/migracao_setor_preparo.sql)
--
-- O que muda:
--   produto.setor_preparo ........ onde o produto é preparado: cozinha, bar ou nenhum
--   item_pedido.setor_preparo .... setor congelado no momento da venda
--   item_pedido.status_item ...... andamento de cada item (aguardando, em preparo, pronto, entregue)
-- ============================================================================

SET NAMES utf8mb4;

-- 1. Novas colunas (IF NOT EXISTS: pode rodar de novo sem erro)
ALTER TABLE produto     ADD COLUMN IF NOT EXISTS setor_preparo ENUM('cozinha','bar','nenhum') NOT NULL DEFAULT 'cozinha';
ALTER TABLE item_pedido ADD COLUMN IF NOT EXISTS setor_preparo ENUM('cozinha','bar','nenhum') NOT NULL DEFAULT 'cozinha';
ALTER TABLE item_pedido ADD COLUMN IF NOT EXISTS status_item ENUM('aguardando','em preparo','pronto','entregue') NOT NULL DEFAULT 'aguardando';
ALTER TABLE item_pedido ADD COLUMN IF NOT EXISTS data_hora_inicio_preparo DATETIME NULL;

-- 2. Cardápio de exemplo: bebidas prontas não passam por preparo; suco e sobremesa vão para o bar.
--    (Depois ajuste pela tela Produtos → Editar → "Setor de preparo".)
UPDATE produto SET setor_preparo = 'nenhum' WHERE nome IN ('Refrigerante Lata', 'Água Mineral');
UPDATE produto SET setor_preparo = 'bar'    WHERE nome IN ('Suco Natural', 'Sundae de Chocolate');

-- 3. Itens de pedidos já existentes: copia o setor do produto e acerta o andamento conforme o pedido.
UPDATE item_pedido ip
  JOIN produto pr ON pr.id_produto = ip.id_produto
   SET ip.setor_preparo = pr.setor_preparo
 WHERE ip.status_item = 'aguardando';

UPDATE item_pedido ip
  JOIN pedido p ON p.id_pedido = ip.id_pedido
  JOIN canal_venda cv ON cv.codigo = p.canal
   SET ip.status_item = CASE
         WHEN p.status IN ('entregue', 'cancelado')                 THEN 'entregue'
         WHEN p.status IN ('pronto', 'saiu')                         THEN 'pronto'
         WHEN ip.setor_preparo = 'nenhum' AND cv.presencial = 1      THEN 'entregue'
         WHEN ip.setor_preparo = 'nenhum'                            THEN 'pronto'
         WHEN p.status = 'em preparo'                                THEN 'em preparo'
         ELSE 'aguardando'
       END,
       ip.data_hora_inicio_preparo = CASE WHEN p.status = 'em preparo' THEN p.data_hora_inicio_preparo ELSE NULL END
 WHERE ip.status_item = 'aguardando';

-- Fim.
