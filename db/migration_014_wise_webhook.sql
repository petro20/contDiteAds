-- Migration 014 — Wise webhook events log

CREATE TABLE IF NOT EXISTS wise_eventos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recebido_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_type      VARCHAR(80) NOT NULL,
    delivery_id     VARCHAR(120) NULL,
    payload_json    LONGTEXT NOT NULL,
    valor           DECIMAL(10,2) NULL,
    moeda           CHAR(3) NULL,
    payer_nome      VARCHAR(200) NULL,
    cobranca_id     INT UNSIGNED NULL,
    pagamento_id    INT UNSIGNED NULL,
    status          ENUM('recebido','casado','sem_cobranca','assinatura_invalida','erro') NOT NULL DEFAULT 'recebido',
    erro            TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_delivery (delivery_id),
    KEY ix_recebido (recebido_em),
    CONSTRAINT fk_wise_cob FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id) ON DELETE SET NULL,
    CONSTRAINT fk_wise_pag FOREIGN KEY (pagamento_id) REFERENCES pagamentos_cliente(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
