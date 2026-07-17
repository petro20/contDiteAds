-- ============================================================
-- Migration 022 — Funcionário responsável em item avulso
-- ------------------------------------------------------------
-- Itens avulsos (cobranca_itens sem assinatura) não tinham como
-- vincular o funcionário que fez o serviço, então nunca entravam
-- na fila de pagamento da equipe. Estas colunas resolvem isso:
--   funcionario_id      = quem fez o serviço avulso
--   pagamento_func_usd  = quanto pagar a ele (USD, POR UNIDADE)
-- Para itens de assinatura essas colunas ficam NULL (o vínculo
-- continua vindo da assinatura + func_servico_pagamento).
-- ============================================================

ALTER TABLE cobranca_itens
  ADD COLUMN funcionario_id     INT UNSIGNED  NULL AFTER assinatura_id,
  ADD COLUMN pagamento_func_usd DECIMAL(10,2) NULL AFTER funcionario_id,
  ADD KEY ix_cobit_funcionario (funcionario_id),
  ADD CONSTRAINT fk_cobit_func FOREIGN KEY (funcionario_id)
      REFERENCES usuarios(id) ON DELETE SET NULL;
