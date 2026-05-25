-- Seed do catálogo Dite Ads — itens reais conforme PLANEJAMENTO.md
-- Idempotente: usa INSERT ... ON DUPLICATE KEY UPDATE para preços.
-- Para rodar: execute uma vez após a migration_002.

SET NAMES utf8mb4;

-- Limpa composições antes de re-popular (evita duplicação)
DELETE FROM itens_pacote_composicao;

-- ============================================================
-- Itens únicos — Criação de contas em redes sociais
-- ============================================================
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur) VALUES
  ('Criar Facebook',       'unico', 50.00, 250.00, 50.00),
  ('Criar Instagram',      'unico', 50.00, 250.00, 50.00),
  ('Criar TikTok',         'unico', 50.00, 250.00, 50.00),
  ('Criar YouTube',        'unico', 50.00, 250.00, 50.00),
  ('Google Business',      'unico', 50.00, 250.00, 50.00)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur);

-- ============================================================
-- Sites
-- ============================================================
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur) VALUES
  ('Landing Page',                  'unico', 200.00,  300.00, 100.00),
  ('Portfólio',                     'unico', 300.00, 1200.00, 200.00),
  ('Ecommerce até 50 produtos',     'unico', 500.00, 2000.00, 300.00),
  ('Ecommerce até 100 produtos',    'unico', 800.00, 2500.00, 700.00)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur);

-- ============================================================
-- Infraestrutura web
-- ============================================================
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur) VALUES
  ('Compra de domínio',  'unico', 10.00,  30.00, 10.00),
  ('Provedor anual',     'unico', 66.00, 330.00, 55.00)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur);

-- ============================================================
-- E-books
-- ============================================================
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur, a_negociar) VALUES
  ('E-book 20 páginas',  'unico', 30.00, 150.00, 25.00, 0),
  ('E-book 40 páginas',  'unico', 40.00, 200.00, 35.00, 0),
  ('E-book 60 páginas',  'unico', 60.00, 300.00, 50.00, 0),
  ('E-book +60 páginas', 'unico', NULL,  NULL,   NULL,  1)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur), a_negociar=VALUES(a_negociar);

-- ============================================================
-- Outros únicos (LogoTipo, ART)
-- ============================================================
INSERT INTO itens_catalogo (nome, descricao, tipo, preco_usd, preco_brl, preco_eur) VALUES
  ('LogoTipo', 'Criação ou melhoramento, formato 1x1', 'unico',  10.00,   50.00,  10.00),
  ('ART',      'Arte para anúncio, até 3 minutos. Usada em pacotes.', 'unico', 150.00, 1500.00, 150.00)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur);

-- ============================================================
-- Itens mensais (não-pacote)
-- ============================================================
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur) VALUES
  ('Meta ADS',   'mensal', 80.00, 400.00, 80.00),
  ('Google ADS', 'mensal', 70.00, 400.00, 80.00)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur);

-- ============================================================
-- Itens por unidade (criativos)
-- ============================================================
INSERT INTO itens_catalogo (nome, descricao, tipo, preco_usd, preco_brl, preco_eur) VALUES
  ('CTF', 'Criativo de foto',                         'por_unidade', 4.00, 10.00, 3.50),
  ('CTV', 'Criativo de vídeo (30s a 1min30)',         'por_unidade', 5.00, 15.00, 4.30),
  ('CTI', 'Criativo com IA (30s a 1min30)',           'por_unidade', 6.00, 25.00, 6.00)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur);

-- ============================================================
-- Pacotes (mensais, com composição)
-- ============================================================

-- ANÚNCIO (sem variante IA)
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur, e_pacote, tem_variante_ia)
VALUES ('ANÚNCIO', 'mensal', 150.00, 450.00, 128.00, 1, 0)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur), e_pacote=1, tem_variante_ia=0;

-- POSTAGEM 7D
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur, preco_ia_usd, preco_ia_brl, preco_ia_eur, e_pacote, tem_variante_ia)
VALUES ('POSTAGEM 7D', 'mensal', 150.00, 800.00, 130.00, 180.00, 900.00, 170.00, 1, 1)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur),
                        preco_ia_usd=VALUES(preco_ia_usd), preco_ia_brl=VALUES(preco_ia_brl), preco_ia_eur=VALUES(preco_ia_eur),
                        e_pacote=1, tem_variante_ia=1;

-- POSTAGEM 5D
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur, preco_ia_usd, preco_ia_brl, preco_ia_eur, e_pacote, tem_variante_ia)
VALUES ('POSTAGEM 5D', 'mensal', 120.00, 750.00, 100.00, 140.00, 800.00, 120.00, 1, 1)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur),
                        preco_ia_usd=VALUES(preco_ia_usd), preco_ia_brl=VALUES(preco_ia_brl), preco_ia_eur=VALUES(preco_ia_eur),
                        e_pacote=1, tem_variante_ia=1;

-- POSTAGEM 2D
INSERT INTO itens_catalogo (nome, tipo, preco_usd, preco_brl, preco_eur, preco_ia_usd, preco_ia_brl, preco_ia_eur, e_pacote, tem_variante_ia)
VALUES ('POSTAGEM 2D', 'mensal', 80.00, 200.00, 55.00, 95.00, 240.00, 75.00, 1, 1)
ON DUPLICATE KEY UPDATE preco_usd=VALUES(preco_usd), preco_brl=VALUES(preco_brl), preco_eur=VALUES(preco_eur),
                        preco_ia_usd=VALUES(preco_ia_usd), preco_ia_brl=VALUES(preco_ia_brl), preco_ia_eur=VALUES(preco_ia_eur),
                        e_pacote=1, tem_variante_ia=1;

-- ============================================================
-- Composições dos pacotes (bill of materials)
-- ============================================================
-- ANÚNCIO = 1 ART + 1 Meta ADS + 1 Google ADS
INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante)
SELECT p.id, c.id, 1, 'normal'
FROM itens_catalogo p, itens_catalogo c
WHERE p.nome = 'ANÚNCIO' AND c.nome IN ('ART', 'Meta ADS', 'Google ADS');

-- POSTAGEM 7D normal = 7 ART + 7 CTF (ou CTV mixto — usaremos CTF como base; admin pode editar)
INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante)
SELECT p.id, c.id, 7, 'normal'
FROM itens_catalogo p, itens_catalogo c
WHERE p.nome = 'POSTAGEM 7D' AND c.nome IN ('ART', 'CTF');
-- POSTAGEM 7D IA = 7 ART + 7 CTI
INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante)
SELECT p.id, c.id, 7, 'ia'
FROM itens_catalogo p, itens_catalogo c
WHERE p.nome = 'POSTAGEM 7D' AND c.nome IN ('ART', 'CTI');

-- POSTAGEM 5D normal
INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante)
SELECT p.id, c.id, 5, 'normal'
FROM itens_catalogo p, itens_catalogo c
WHERE p.nome = 'POSTAGEM 5D' AND c.nome IN ('ART', 'CTF');
INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante)
SELECT p.id, c.id, 5, 'ia'
FROM itens_catalogo p, itens_catalogo c
WHERE p.nome = 'POSTAGEM 5D' AND c.nome IN ('ART', 'CTI');

-- POSTAGEM 2D normal
INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante)
SELECT p.id, c.id, 2, 'normal'
FROM itens_catalogo p, itens_catalogo c
WHERE p.nome = 'POSTAGEM 2D' AND c.nome IN ('ART', 'CTF');
INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante)
SELECT p.id, c.id, 2, 'ia'
FROM itens_catalogo p, itens_catalogo c
WHERE p.nome = 'POSTAGEM 2D' AND c.nome IN ('ART', 'CTI');

-- ============================================================
-- Templates de mensagem iniciais
-- ============================================================
INSERT INTO templates_mensagem (codigo, canal, assunto, corpo) VALUES
  ('cobranca_nova', 'email',
   'Nova cobrança — {mes_referencia}',
   'Olá {nome_cliente},\n\nGeramos sua cobrança de {mes_referencia} no valor de {moeda} {valor}, com vencimento em {vencimento}.\n\nItens:\n{itens}\n\nAcesse o sistema para acompanhar: https://cont.diteads.com\n\nEquipe Dite Ads'),

  ('cobranca_nova', 'whatsapp', NULL,
   'Olá {nome_cliente}! 👋\n\nSua cobrança de {mes_referencia} foi gerada:\n💰 Total: {moeda} {valor}\n📅 Vencimento: {vencimento}\n\nDetalhes no sistema. Qualquer dúvida estamos aqui!'),

  ('lembrete_vencendo', 'email',
   'Cobrança vence em breve',
   'Olá {nome_cliente},\n\nSua cobrança de {moeda} {valor} vence em {vencimento}. Conte com a gente em qualquer dúvida.\n\nEquipe Dite Ads'),

  ('lembrete_vencendo', 'whatsapp', NULL,
   '⏰ Olá {nome_cliente}! Lembrete amistoso: sua cobrança de {moeda} {valor} vence em {vencimento}.'),

  ('lembrete_vencida', 'email',
   'Cobrança em atraso',
   'Olá {nome_cliente},\n\nIdentificamos que sua cobrança de {moeda} {valor} (vencimento em {vencimento}) está em atraso. Por favor, regularize quando possível ou fale com a gente para combinar.\n\nEquipe Dite Ads'),

  ('lembrete_vencida', 'whatsapp', NULL,
   'Olá {nome_cliente}, sua cobrança de {moeda} {valor} venceu em {vencimento}. Conseguimos regularizar essa semana? 🙏'),

  ('pagamento_confirmado', 'email',
   'Pagamento confirmado',
   'Olá {nome_cliente},\n\nRecebemos seu pagamento de {moeda} {valor}. Muito obrigado!\n\nEquipe Dite Ads'),

  ('pagamento_confirmado', 'whatsapp', NULL,
   '✅ Pagamento de {moeda} {valor} recebido. Obrigado, {nome_cliente}!')
ON DUPLICATE KEY UPDATE corpo = VALUES(corpo), assunto = VALUES(assunto);

-- ============================================================
-- Régua de cobrança padrão (4 lembretes)
-- ============================================================
INSERT INTO regua_etapas (ordem, dias_apos_vencimento, template_email_id, template_whatsapp_id, ativa) VALUES
  (1, 0,
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencendo' AND canal='email'   LIMIT 1),
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencendo' AND canal='whatsapp' LIMIT 1),
   1),
  (2, 3,
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencida' AND canal='email'   LIMIT 1),
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencida' AND canal='whatsapp' LIMIT 1),
   1),
  (3, 7,
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencida' AND canal='email'   LIMIT 1),
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencida' AND canal='whatsapp' LIMIT 1),
   1),
  (4, 15,
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencida' AND canal='email'   LIMIT 1),
   (SELECT id FROM templates_mensagem WHERE codigo='lembrete_vencida' AND canal='whatsapp' LIMIT 1),
   1)
ON DUPLICATE KEY UPDATE dias_apos_vencimento=VALUES(dias_apos_vencimento);
