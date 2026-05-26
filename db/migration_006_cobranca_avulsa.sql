-- Migration 006 — permite múltiplas cobranças por (cliente, mês)
-- Remove a constraint UNIQUE que limitava a 1 cobrança por mês.

ALTER TABLE cobrancas DROP INDEX uk_cob_cli_mes;
ALTER TABLE cobrancas ADD INDEX ix_cob_cli_mes (cliente_id, competencia_mes);
