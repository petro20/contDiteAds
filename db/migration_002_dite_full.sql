-- Migration 002 — Schema completo Dite Ads (Sprint 0 / BUILD_PLAN seção 2)
-- DESTRUTIVA: dropa todas as tabelas exceto `clientes` e `usuarios`
-- (e mesmo essas serão alteradas).
--
-- ATENÇÃO: rode SOMENTE depois que todos os arquivos PHP do Sprint 1
-- estiverem em produção, senão o site quebra entre a migration e o
-- deploy.

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1) Limpeza do schema MVP anterior
-- ============================================================
DROP TABLE IF EXISTS pagamentos;
DROP TABLE IF EXISTS cobrancas;
DROP TABLE IF EXISTS servicos;
DROP TABLE IF EXISTS apontamentos;
DROP TABLE IF EXISTS tarefas;

-- Ajustes em clientes (acrescenta moeda, link grupo, dia cobrança, contato)
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS nome_empresa VARCHAR(180) NULL AFTER nome,
    ADD COLUMN IF NOT EXISTS nome_contato VARCHAR(180) NULL AFTER nome_empresa,
    ADD COLUMN IF NOT EXISTS moeda ENUM('USD','BRL','EUR') NOT NULL DEFAULT 'BRL' AFTER email,
    ADD COLUMN IF NOT EXISTS link_grupo VARCHAR(500) NULL AFTER endereco,
    ADD COLUMN IF NOT EXISTS dia_cobranca TINYINT UNSIGNED NULL AFTER link_grupo;

-- Ajustes em usuarios (cpf, wisetag, país, toggle aceitando)
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS cpf VARCHAR(20) NULL AFTER cliente_id,
    ADD COLUMN IF NOT EXISTS wisetag VARCHAR(80) NULL AFTER cpf,
    ADD COLUMN IF NOT EXISTS pais VARCHAR(60) NULL AFTER wisetag,
    ADD COLUMN IF NOT EXISTS aceitando_clientes TINYINT(1) NOT NULL DEFAULT 1 AFTER pais;

-- Drop comissão antiga (vira percentual_comissao zerado, vamos usar func_servico_pagamento)
-- Mantém a coluna por compat se admin já preencheu — fica como referência.

-- ============================================================
-- 2) Convites
-- ============================================================
CREATE TABLE IF NOT EXISTS convites (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token           VARCHAR(64) NOT NULL,
    tipo            ENUM('cliente','funcionario') NOT NULL,
    pre_preenchidos JSON NULL,
    criado_por      INT UNSIGNED NOT NULL,
    expira_em       DATETIME NOT NULL,
    usado_em        DATETIME NULL,
    usado_por       INT UNSIGNED NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_convites_token (token),
    KEY ix_convites_status (usado_em),
    CONSTRAINT fk_conv_criadopor FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_conv_usadopor  FOREIGN KEY (usado_por)  REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3) Reset de senha
-- ============================================================
CREATE TABLE IF NOT EXISTS senha_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED NOT NULL,
    token       VARCHAR(64) NOT NULL,
    expira_em   DATETIME NOT NULL,
    usado_em    DATETIME NULL,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_reset_token (token),
    KEY ix_reset_usuario (usuario_id),
    CONSTRAINT fk_reset_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4) Catálogo
-- ============================================================
CREATE TABLE IF NOT EXISTS itens_catalogo (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome                VARCHAR(120) NOT NULL,
    descricao           TEXT NULL,
    tipo                ENUM('unico','mensal','por_unidade') NOT NULL,
    preco_usd           DECIMAL(10,2) NULL,
    preco_brl           DECIMAL(10,2) NULL,
    preco_eur           DECIMAL(10,2) NULL,
    a_negociar          TINYINT(1) NOT NULL DEFAULT 0,
    e_pacote            TINYINT(1) NOT NULL DEFAULT 0,
    tem_variante_ia     TINYINT(1) NOT NULL DEFAULT 0,
    preco_ia_usd        DECIMAL(10,2) NULL,
    preco_ia_brl        DECIMAL(10,2) NULL,
    preco_ia_eur        DECIMAL(10,2) NULL,
    resp_agencia        TEXT NULL,
    resp_funcionario    TEXT NULL,
    resp_cliente        TEXT NULL,
    ativo               TINYINT(1) NOT NULL DEFAULT 1,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_catalogo_nome (nome),
    KEY ix_catalogo_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS itens_pacote_composicao (
    pacote_id       INT UNSIGNED NOT NULL,
    componente_id   INT UNSIGNED NOT NULL,
    quantidade      INT NOT NULL DEFAULT 1,
    variante        ENUM('normal','ia') NOT NULL DEFAULT 'normal',
    PRIMARY KEY (pacote_id, componente_id, variante),
    CONSTRAINT fk_comp_pacote     FOREIGN KEY (pacote_id)     REFERENCES itens_catalogo(id) ON DELETE CASCADE,
    CONSTRAINT fk_comp_componente FOREIGN KEY (componente_id) REFERENCES itens_catalogo(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5) Folha de pagamento (func × item)
-- ============================================================
CREATE TABLE IF NOT EXISTS func_servico_pagamento (
    funcionario_id  INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    valor_usd       DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (funcionario_id, item_id),
    CONSTRAINT fk_fsp_func FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_fsp_item FOREIGN KEY (item_id) REFERENCES itens_catalogo(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6) Capacidade do funcionário (N13)
-- ============================================================
CREATE TABLE IF NOT EXISTS capacidade_funcionario (
    funcionario_id      INT UNSIGNED NOT NULL,
    categoria           VARCHAR(50) NOT NULL,
    capacidade_mensal   INT NOT NULL,
    PRIMARY KEY (funcionario_id, categoria),
    CONSTRAINT fk_cap_func FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7) Assinaturas (cliente × item)
-- ============================================================
CREATE TABLE IF NOT EXISTS assinaturas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id      INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    funcionario_id  INT UNSIGNED NULL,
    variante        ENUM('normal','ia') NOT NULL DEFAULT 'normal',
    valor_cobrado   DECIMAL(10,2) NOT NULL,
    status          ENUM('ativa','pausada','cancelada') NOT NULL DEFAULT 'ativa',
    iniciada_em     DATE NOT NULL,
    encerrada_em    DATE NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_assin_cliente (cliente_id),
    KEY ix_assin_func (funcionario_id),
    KEY ix_assin_status (status),
    CONSTRAINT fk_assin_cliente FOREIGN KEY (cliente_id)     REFERENCES clientes(id)       ON DELETE RESTRICT,
    CONSTRAINT fk_assin_item    FOREIGN KEY (item_id)        REFERENCES itens_catalogo(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assin_func    FOREIGN KEY (funcionario_id) REFERENCES usuarios(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8) Cobranças (consolidadas por mês) + itens
-- ============================================================
CREATE TABLE IF NOT EXISTS cobrancas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id      INT UNSIGNED NOT NULL,
    competencia_mes CHAR(7) NOT NULL,
    valor_total     DECIMAL(10,2) NOT NULL,
    moeda           ENUM('USD','BRL','EUR') NOT NULL,
    vencimento      DATE NOT NULL,
    status          ENUM('aberta','paga','cancelada') NOT NULL DEFAULT 'aberta',
    silenciada      TINYINT(1) NOT NULL DEFAULT 0,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cob_cli_mes (cliente_id, competencia_mes),
    KEY ix_cob_status (status),
    KEY ix_cob_venc (vencimento),
    CONSTRAINT fk_cob_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobranca_itens (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cobranca_id     INT UNSIGNED NOT NULL,
    assinatura_id   INT UNSIGNED NULL,
    descricao       VARCHAR(255) NOT NULL,
    quantidade      INT NOT NULL DEFAULT 1,
    valor_unitario  DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_cobit_cobranca (cobranca_id),
    CONSTRAINT fk_cobit_cobranca FOREIGN KEY (cobranca_id)   REFERENCES cobrancas(id)   ON DELETE CASCADE,
    CONSTRAINT fk_cobit_assin    FOREIGN KEY (assinatura_id) REFERENCES assinaturas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9) Pagamentos do cliente (com upload de comprovante)
-- ============================================================
CREATE TABLE IF NOT EXISTS pagamentos_cliente (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cobranca_id         INT UNSIGNED NOT NULL,
    valor_pago          DECIMAL(10,2) NOT NULL,
    data_pagamento      DATE NOT NULL,
    metodo              VARCHAR(50) NULL,
    observacao          TEXT NULL,
    comprovante_path    VARCHAR(255) NULL,
    registrado_por      INT UNSIGNED NOT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_pag_cli_cobranca (cobranca_id),
    KEY ix_pag_cli_data (data_pagamento),
    CONSTRAINT fk_pagcli_cob   FOREIGN KEY (cobranca_id)    REFERENCES cobrancas(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagcli_user  FOREIGN KEY (registrado_por) REFERENCES usuarios(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10) Entregas (checkbox do funcionário)
-- ============================================================
CREATE TABLE IF NOT EXISTS entregas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assinatura_id   INT UNSIGNED NOT NULL,
    competencia_mes CHAR(7) NOT NULL,
    data_marcada    DATE NULL,
    indice          INT NULL,
    funcionario_id  INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_entr_assin (assinatura_id, competencia_mes),
    KEY ix_entr_func (funcionario_id),
    CONSTRAINT fk_entr_assin FOREIGN KEY (assinatura_id)  REFERENCES assinaturas(id) ON DELETE CASCADE,
    CONSTRAINT fk_entr_func  FOREIGN KEY (funcionario_id) REFERENCES usuarios(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11) Pagamentos AO funcionário (Wise) + detalhamento
-- ============================================================
CREATE TABLE IF NOT EXISTS pagamentos_funcionario (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    funcionario_id       INT UNSIGNED NOT NULL,
    valor_usd            DECIMAL(10,2) NOT NULL,
    data_pagamento       DATE NOT NULL,
    comprovante_pdf_path VARCHAR(255) NULL,
    criado_por           INT UNSIGNED NOT NULL,
    criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_pagfunc_func (funcionario_id),
    KEY ix_pagfunc_data (data_pagamento),
    CONSTRAINT fk_pagfunc_func FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pagfunc_user FOREIGN KEY (criado_por)     REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamento_funcionario_itens (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pagamento_id            INT UNSIGNED NOT NULL,
    cobranca_item_id        INT UNSIGNED NULL,
    descricao               VARCHAR(255) NOT NULL,
    quantidade              INT NOT NULL DEFAULT 1,
    valor_unitario_usd      DECIMAL(10,2) NOT NULL,
    subtotal_usd            DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_pagfit_pag (pagamento_id),
    CONSTRAINT fk_pagfit_pag FOREIGN KEY (pagamento_id)     REFERENCES pagamentos_funcionario(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagfit_cob FOREIGN KEY (cobranca_item_id) REFERENCES cobranca_itens(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12) Templates de mensagem + Régua
-- ============================================================
CREATE TABLE IF NOT EXISTS templates_mensagem (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo      VARCHAR(50) NOT NULL,
    canal       ENUM('email','whatsapp') NOT NULL,
    assunto     VARCHAR(255) NULL,
    corpo       TEXT NOT NULL,
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tpl_codigo (codigo, canal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS regua_etapas (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem                    INT NOT NULL,
    dias_apos_vencimento     INT NOT NULL,
    template_email_id        INT UNSIGNED NULL,
    template_whatsapp_id     INT UNSIGNED NULL,
    ativa                    TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_regua_ordem (ordem),
    CONSTRAINT fk_regua_email FOREIGN KEY (template_email_id)    REFERENCES templates_mensagem(id) ON DELETE SET NULL,
    CONSTRAINT fk_regua_zap   FOREIGN KEY (template_whatsapp_id) REFERENCES templates_mensagem(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS regua_eventos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cobranca_id     INT UNSIGNED NOT NULL,
    etapa_id        INT UNSIGNED NOT NULL,
    canal           ENUM('email','whatsapp') NOT NULL,
    enviado_em      DATETIME NULL,
    marcado_por     INT UNSIGNED NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_regev_cob (cobranca_id),
    KEY ix_regev_pendente (enviado_em),
    CONSTRAINT fk_regev_cob   FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id)    ON DELETE CASCADE,
    CONSTRAINT fk_regev_etapa FOREIGN KEY (etapa_id)    REFERENCES regua_etapas(id) ON DELETE CASCADE,
    CONSTRAINT fk_regev_user  FOREIGN KEY (marcado_por) REFERENCES usuarios(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13) Audit log
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id      INT UNSIGNED NULL,
    acao            VARCHAR(80) NOT NULL,
    entidade        VARCHAR(50) NULL,
    entidade_id     INT UNSIGNED NULL,
    payload_antes   JSON NULL,
    payload_depois  JSON NULL,
    ip              VARCHAR(45) NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_audit_user (usuario_id),
    KEY ix_audit_ent (entidade, entidade_id),
    KEY ix_audit_data (criado_em),
    CONSTRAINT fk_audit_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
