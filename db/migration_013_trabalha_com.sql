-- Migration 013 — funcionário em dupla
-- Permite vincular um funcionário a outro pra compartilhar agenda.
-- Pagamento continua indo só pro funcionário "principal" (o vinculado, não o subordinado).

ALTER TABLE usuarios
  ADD COLUMN trabalha_com_id INT UNSIGNED NULL AFTER aceitando_clientes,
  ADD CONSTRAINT fk_usuario_trabalha_com
    FOREIGN KEY (trabalha_com_id) REFERENCES usuarios(id) ON DELETE SET NULL;
