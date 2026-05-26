-- Migration 007 — comprovante em análise
-- cobranca status ganha 'em_analise'
-- pagamentos_cliente ganha flag pendente (1 = ainda não confirmado pelo admin)

ALTER TABLE cobrancas
    MODIFY status ENUM('aberta','em_analise','paga','cancelada') NOT NULL DEFAULT 'aberta';

ALTER TABLE pagamentos_cliente
    ADD COLUMN IF NOT EXISTS pendente TINYINT(1) NOT NULL DEFAULT 0 AFTER comprovante_path;
