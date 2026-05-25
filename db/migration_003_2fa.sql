-- Migration 003 — 2FA TOTP

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL AFTER senha_hash,
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret;
