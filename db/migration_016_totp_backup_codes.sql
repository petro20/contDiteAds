-- Migration 016 — Backup codes pra 2FA TOTP
--
-- Resolve o cenário "admin perdeu o celular e fica trancado pra sempre".
-- Cada usuário com 2FA ativo recebe 8 códigos one-time-use gerados na ativação.
-- O usuário deve guardar em local seguro (impressão, gerenciador de senhas).
--
-- Estrutura:
--   - codigo_hash: bcrypt do código (não armazenamos plaintext)
--   - usado_em: NULL se ainda válido; timestamp quando foi consumido
--   - Após login com backup code, o registro é marcado como usado (não pode reusar)

CREATE TABLE IF NOT EXISTS totp_backup_codes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED NOT NULL,
    codigo_hash   VARCHAR(255) NOT NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usado_em      DATETIME NULL,
    PRIMARY KEY (id),
    KEY ix_bcodes_user (usuario_id, usado_em),
    CONSTRAINT fk_bcodes_user FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
