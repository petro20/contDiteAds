-- contDiteAds — schema inicial
-- Executar uma vez no banco MySQL do Hostinger

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome            VARCHAR(120) NOT NULL,
    email           VARCHAR(180) NOT NULL,
    senha_hash      VARCHAR(255) NOT NULL,
    role            ENUM('admin','funcionario') NOT NULL DEFAULT 'funcionario',
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome            VARCHAR(180) NOT NULL,
    documento       VARCHAR(30) NULL,
    email           VARCHAR(180) NULL,
    telefone        VARCHAR(40) NULL,
    endereco        VARCHAR(255) NULL,
    observacoes     TEXT NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_clientes_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tarefas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id      INT UNSIGNED NOT NULL,
    funcionario_id  INT UNSIGNED NOT NULL,
    titulo          VARCHAR(180) NOT NULL,
    descricao       TEXT NULL,
    status          ENUM('pendente','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'pendente',
    prazo           DATE NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_tarefas_cliente (cliente_id),
    KEY ix_tarefas_funcionario (funcionario_id),
    KEY ix_tarefas_status (status),
    CONSTRAINT fk_tarefas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tarefas_funcionario FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apontamentos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tarefa_id       INT UNSIGNED NOT NULL,
    funcionario_id  INT UNSIGNED NOT NULL,
    data            DATE NOT NULL,
    horas           DECIMAL(5,2) NOT NULL,
    observacao      TEXT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_apont_tarefa (tarefa_id),
    KEY ix_apont_funcionario (funcionario_id),
    KEY ix_apont_data (data),
    CONSTRAINT fk_apont_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
    CONSTRAINT fk_apont_funcionario FOREIGN KEY (funcionario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
