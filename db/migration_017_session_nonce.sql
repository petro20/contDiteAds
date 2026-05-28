-- Migration 017 — Session nonce pra invalidar sessões antigas
--
-- Resolve: após reset de senha, sessões ativas em outros dispositivos não eram
-- derrubadas. Atacante logado continuava logado.
--
-- Estratégia:
--   - Cada usuário tem um nonce de sessão (string aleatória)
--   - Na hora de validar sessão (auth.php), comparar o nonce na sessão PHP
--     com o nonce atual do banco; se diferente, força logout.
--   - Trocar senha (redefinir.php) atualiza o nonce, invalidando todas as
--     sessões antigas.

ALTER TABLE usuarios
    ADD COLUMN session_nonce VARCHAR(32) NULL AFTER senha_hash;

-- Inicializa nonce pros usuários existentes
UPDATE usuarios SET session_nonce = SUBSTRING(MD5(RAND()), 1, 32) WHERE session_nonce IS NULL;
