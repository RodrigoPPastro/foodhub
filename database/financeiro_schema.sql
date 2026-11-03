-- ============================================================
-- FOOD HUB — Ajustes do módulo Financeiro/Relatórios (Lucas)
-- RF22–RF26. PROPOSTA a combinar com a Fernanda (dona do SQL oficial):
-- aplicar DEPOIS que ela consolidar as tabelas do DER.
-- ============================================================

-- 1) Formas de pagamento (idempotente: só insere o que não existe)
INSERT INTO forma_pagamento (descricao)
SELECT v.d FROM (
    SELECT 'dinheiro' AS d UNION ALL SELECT 'pix' UNION ALL
    SELECT 'credito'  UNION ALL SELECT 'debito'   UNION ALL SELECT 'vale'
) v
WHERE NOT EXISTS (SELECT 1 FROM forma_pagamento f WHERE f.descricao = v.d);

-- 2) FECHAMENTO_CAIXA: o DER tem só total_dinheiro/cartao/pix/delivery.
--    O RF23 exige crédito x débito, vale e TODOS os canais (RNF09: canais
--    parametrizáveis). Solução: um snapshot JSON do fechamento, mantendo
--    as colunas do DER preenchidas (total_cartao = crédito + débito;
--    total_delivery = todos os canais que não são balcão/mesa).
ALTER TABLE fechamento_caixa
    ADD COLUMN detalhamento JSON NULL,
    ADD UNIQUE KEY uq_fechamento_data (data_referencia);   -- 1 fechamento por dia

-- 3) Índices para as queries de agregação por período
CREATE INDEX idx_pedido_data_hora    ON pedido (data_hora);
CREATE INDEX idx_pagamento_data_hora ON pagamento (data_hora);
