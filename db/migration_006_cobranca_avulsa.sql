-- Migration 006 — permite múltiplas cobranças por (cliente, mês)
-- Remove a constraint UNIQUE mas mantém um índice na mesma combinação
-- (precisa do índice porque a FK fk_cob_cliente o utiliza).

ALTER TABLE cobrancas
    ADD INDEX ix_cob_cli_mes (cliente_id, competencia_mes),
    DROP INDEX uk_cob_cli_mes;
