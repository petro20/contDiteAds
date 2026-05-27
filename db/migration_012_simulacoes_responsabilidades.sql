-- Migration 012 — adiciona campos de responsabilidades na simulação
-- (espelha resp_agencia, resp_funcionario, resp_cliente do itens_catalogo)

ALTER TABLE simulacoes_preco
  ADD COLUMN resp_agencia     TEXT NULL AFTER margem_pct,
  ADD COLUMN resp_funcionario TEXT NULL AFTER resp_agencia,
  ADD COLUMN resp_cliente     TEXT NULL AFTER resp_funcionario;
