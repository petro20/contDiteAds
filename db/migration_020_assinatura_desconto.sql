-- ============================================================
-- migration_020: desconto percentual na assinatura
-- ============================================================
-- Guarda o desconto (%) aplicado no ato da assinatura. O valor_cobrado
-- final já vem calculado com o desconto; desconto_pct fica registrado
-- pra consulta ("quem tem desconto").
-- Aplicar via phpMyAdmin (banco u788472657_contditeads).

ALTER TABLE assinaturas
    ADD COLUMN IF NOT EXISTS desconto_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER valor_cobrado;
