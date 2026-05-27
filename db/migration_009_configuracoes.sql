-- Migration 009 — tabela de configurações chave/valor (formas de pagamento, etc.)

CREATE TABLE IF NOT EXISTS configuracoes (
    chave       VARCHAR(80)  NOT NULL,
    valor       TEXT NULL,
    atualizado  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds iniciais
INSERT IGNORE INTO configuracoes (chave, valor) VALUES
  ('pagamento_zelle_email',   ''),
  ('pagamento_zelle_qr',      ''),
  ('pagamento_wise_link',     ''),
  ('pagamento_instrucoes',    '');
