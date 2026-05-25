-- Migration 001 — Reestruturação para sistema financeiro
-- Drop tarefas/apontamentos (estavam vazios), criar servicos/cobrancas/pagamentos
-- Adicionar role 'cliente', cliente_id e percentual_comissao em usuarios

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS apontamentos;
DROP TABLE IF EXISTS tarefas;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE usuarios
    MODIFY role ENUM('admin','funcionario','cliente') NOT NULL DEFAULT 'funcionario',
    ADD COLUMN cliente_id INT UNSIGNED NULL AFTER role,
    ADD COLUMN percentual_comissao DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER cliente_id,
    ADD CONSTRAINT fk_usuarios_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS servicos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(120) NOT NULL,
    descricao   VARCHAR(255) NULL,
    valor_padrao DECIMAL(10,2) NULL,
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_servicos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobrancas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id      INT UNSIGNED NOT NULL,
    servico_id      INT UNSIGNED NULL,
    funcionario_id  INT UNSIGNED NULL,
    descricao       VARCHAR(255) NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    vencimento      DATE NULL,
    status          ENUM('aberta','paga','cancelada') NOT NULL DEFAULT 'aberta',
    criado_por      INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_cobr_cliente (cliente_id),
    KEY ix_cobr_funcionario (funcionario_id),
    KEY ix_cobr_status (status),
    KEY ix_cobr_venc (vencimento),
    CONSTRAINT fk_cobr_cliente     FOREIGN KEY (cliente_id)     REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cobr_servico     FOREIGN KEY (servico_id)     REFERENCES servicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_cobr_funcionario FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_cobr_criadopor   FOREIGN KEY (criado_por)     REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cobranca_id     INT UNSIGNED NOT NULL,
    valor_pago      DECIMAL(10,2) NOT NULL,
    data_pagamento  DATE NOT NULL,
    metodo          VARCHAR(50) NULL,
    observacao      TEXT NULL,
    registrado_por  INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_pag_cobranca (cobranca_id),
    KEY ix_pag_data (data_pagamento),
    CONSTRAINT fk_pag_cobranca FOREIGN KEY (cobranca_id)    REFERENCES cobrancas(id) ON DELETE CASCADE,
    CONSTRAINT fk_pag_regpor   FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
