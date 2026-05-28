-- Seed dos templates de cobrança com instruções completas de pagamento
-- (Zelle email + QR + link da cobrança)
--
-- Usa INSERT ... ON DUPLICATE KEY UPDATE pra atualizar se já existir o código+canal.
-- Pré-requisito: tabela templates_mensagem deve ter UNIQUE KEY (codigo, canal).

INSERT INTO templates_mensagem (codigo, canal, assunto, corpo, ativo) VALUES
(
  'cobranca_pagamento',
  'whatsapp',
  NULL,
  'Olá *{nome_cliente}*! 👋\n\nSua cobrança da Dite Ads:\n💵 Valor: *{valor} {moeda}*\n📅 Vencimento: *{vencimento}*\n📋 Mês: {mes_referencia}\n\n📦 *Itens:*\n{itens}\n\n══════════════\n💳 *COMO PAGAR via Zelle*\n══════════════\n\n1. Abra o app do *seu banco* (Bank of America, Chase, Wells Fargo, etc.)\n2. Procure a opção *Zelle*\n3. Envie pro email: {zelle_email}\n   ou escaneie o QR: {zelle_qr_url}\n4. Valor: *{valor} {moeda}*\n\n══════════════\n\n📤 Após pagar, envie o comprovante pelo link:\n{link_comprovante}\n\n{instrucoes_pagamento}\n\nQualquer dúvida, estamos por aqui! 🚀\n*Dite Ads*',
  1
),
(
  'cobranca_pagamento',
  'email',
  'Cobrança Dite Ads — {valor} {moeda} (vence {vencimento})',
  '<p>Olá <strong>{nome_cliente}</strong>,</p>\n\n<p>Segue sua cobrança da Dite Ads:</p>\n<ul>\n  <li><strong>Valor:</strong> {valor} {moeda}</li>\n  <li><strong>Vencimento:</strong> {vencimento}</li>\n  <li><strong>Mês de referência:</strong> {mes_referencia}</li>\n</ul>\n\n<p><strong>Itens:</strong></p>\n<pre style="background:#f4f4f4;padding:10px;border-radius:4px;">{itens}</pre>\n\n<h3 style="color:#9333EA;">💳 Como pagar via Zelle</h3>\n\n<ol>\n  <li>Abra o app do <strong>seu banco</strong> (Bank of America, Chase, Wells Fargo, etc.)</li>\n  <li>Procure a opção <strong>Zelle</strong></li>\n  <li>Envie para o email: <strong>{zelle_email}</strong></li>\n  <li>Ou escaneie o QR Code: <br><img src="{zelle_qr_url}" alt="QR Zelle" style="max-width:200px;margin-top:8px;"></li>\n  <li>Valor: <strong>{valor} {moeda}</strong></li>\n</ol>\n\n<hr>\n\n<p>📤 <strong>Após pagar</strong>, envie o comprovante pelo sistema:<br>\n<a href="{link_comprovante}">{link_comprovante}</a></p>\n\n<p>{instrucoes_pagamento}</p>\n\n<p>Qualquer dúvida, estamos à disposição.</p>\n<p>— <strong>Dite Ads</strong></p>',
  1
)
ON DUPLICATE KEY UPDATE
  assunto = VALUES(assunto),
  corpo   = VALUES(corpo),
  ativo   = VALUES(ativo);
