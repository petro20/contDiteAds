-- Migration 008 — pagamento de lucros aos sócios

CREATE TABLE IF NOT EXISTS pagamentos_socio (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    socio_id        INT UNSIGNED NULL,
    competencia_mes CHAR(7) NOT NULL,
    moeda           ENUM('USD','BRL','EUR') NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    data_pagamento  DATE NOT NULL,
    observacao      TEXT NULL,
    criado_por      INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_pagsocio_socio (socio_id),
    KEY ix_pagsocio_comp (competencia_mes, moeda),
    CONSTRAINT fk_pagsocio_socio FOREIGN KEY (socio_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagsocio_user  FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
