-- ============================================================
-- migration_023: link de pagamento do Dite Gateway por cobrança
-- ------------------------------------------------------------
-- Antes, o link do gateway era criado na hora e o cliente era
-- redirecionado — o link nunca ficava guardado nem visível.
-- Agora o admin pode GERAR o link, ele fica salvo na cobrança e
-- pode ser copiado/enviado ao cliente (WhatsApp, email...).
--   dite_pay_url    = URL de checkout gerada pelo gateway
--   dite_payment_id = id do pagamento no gateway (rastreio)
--   dite_link_valor = saldo pro qual o link foi gerado (pra avisar
--                     quando o saldo mudar e o link ficar defasado)
--   dite_link_em    = quando foi gerado
-- Aplicar via phpMyAdmin (banco u788472657_contditeads).
-- ============================================================

ALTER TABLE cobrancas
  ADD COLUMN dite_pay_url    VARCHAR(500)  NULL,
  ADD COLUMN dite_payment_id VARCHAR(120)  NULL,
  ADD COLUMN dite_link_valor DECIMAL(10,2) NULL,
  ADD COLUMN dite_link_em    DATETIME      NULL;
