-- Migration 005 — despesas da empresa (gastos com ferramentas, software, etc.)

CREATE TABLE IF NOT EXISTS despesas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome            VARCHAR(180) NOT NULL,
    descricao       TEXT NULL,
    categoria       VARCHAR(60) NULL,
    valor           DECIMAL(10,2) NOT NULL,
    moeda           ENUM('USD','BRL','EUR') NOT NULL DEFAULT 'BRL',
    recorrencia     ENUM('unica','mensal','anual') NOT NULL DEFAULT 'mensal',
    data_inicio     DATE NOT NULL,
    data_fim        DATE NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    criado_por      INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_desp_ativo (ativo),
    KEY ix_desp_moeda (moeda),
    KEY ix_desp_data (data_inicio),
    CONSTRAINT fk_desp_criadopor FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
