-- ============================================================
-- migration_019: integração Dite Gateway (pay.diteads.com)
-- ============================================================
-- Tabela de eventos recebidos do gateway via webhook.
-- Usada para idempotência (event_id UNIQUE) e auditoria.
-- Aplicar via phpMyAdmin (banco u788472657_contditeads).

CREATE TABLE IF NOT EXISTS dite_eventos (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id      VARCHAR(255) NOT NULL,
    event_type    VARCHAR(100) NOT NULL,
    payload_json  MEDIUMTEXT NULL,
    status        VARCHAR(40) NOT NULL DEFAULT 'recebido',
    cobranca_id   INT UNSIGNED NULL,
    pagamento_id  INT UNSIGNED NULL,
    valor         DECIMAL(10,2) NULL,
    moeda         VARCHAR(3) NULL,
    erro          TEXT NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dite_event (event_id),
    KEY ix_dite_cobranca (cobranca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
