-- ============================================================
-- migration_021: duração do desconto (em meses) na assinatura
-- ============================================================
-- Complementa a migration_020 (desconto_pct). Agora o desconto pode
-- durar N meses:
--   desconto_pct = 0            -> sem desconto
--   desconto_pct > 0, meses = 0 -> desconto permanente
--   desconto_pct > 0, meses = N -> desconto só nas primeiras N cobranças
-- O valor_cobrado passa a ser o PREÇO CHEIO; o desconto é aplicado na
-- geração da cobrança dentro da janela.
-- Aplicar via phpMyAdmin (banco u788472657_contditeads).

ALTER TABLE assinaturas
    ADD COLUMN IF NOT EXISTS desconto_meses INT NOT NULL DEFAULT 0 AFTER desconto_pct;
