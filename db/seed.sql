-- Usuário admin inicial
-- Senha padrão: TrocarAgora!2026   (gere outra com password_hash() depois)
-- Hash gerado com: php -r "echo password_hash('TrocarAgora!2026', PASSWORD_DEFAULT);"
INSERT INTO usuarios (nome, email, senha_hash, role, ativo)
VALUES (
  'Administrador',
  'admin@diteads.com',
  '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy',
  'admin',
  1
)
ON DUPLICATE KEY UPDATE email = email;
