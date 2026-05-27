-- Migration 011 — simulações de preço salvas (rascunhos do simulador)

CREATE TABLE IF NOT EXISTS simulacoes_preco (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome                 VARCHAR(150) NOT NULL,
    descricao            TEXT NULL,
    tipo                 ENUM('unico','mensal','por_unidade') NOT NULL DEFAULT 'mensal',
    periodo_minimo_meses TINYINT UNSIGNED NOT NULL DEFAULT 0,
    margem_pct           DECIMAL(5,2) NOT NULL DEFAULT 50,
    tem_variante_ia      TINYINT(1) NOT NULL DEFAULT 0,
    custos_json          TEXT NOT NULL,
    criado_por           INT UNSIGNED NOT NULL,
    criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_sim_criadopor (criado_por),
    CONSTRAINT fk_sim_criadopor FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
