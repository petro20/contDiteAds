-- Migration 010 — período mínimo de contrato por item do catálogo
-- Quantos meses o cliente precisa manter a assinatura ativa antes de poder cancelar
-- sem ônus. 0 = sem mínimo (cancela quando quiser).

ALTER TABLE itens_catalogo
  ADD COLUMN periodo_minimo_meses TINYINT UNSIGNED NOT NULL DEFAULT 0
  AFTER tipo;
