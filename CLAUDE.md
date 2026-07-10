# CLAUDE.md — contDiteAds

Guia curto pra trabalhar neste repo. Detalhe exaustivo está no [README.md](README.md).

## O que é
Sistema interno de gestão da **Dite Ads** (agência de marketing). Cobre o ciclo comercial:
catálogo → assinaturas → cobrança mensal automática → recebimento (inclui cartão via gateway) →
folha da equipe em USD → distribuição de lucro entre sócios, mais despesas, entregas e
comunicação com o cliente. Produção: https://cont.diteads.com

## Stack
- **PHP 8 vanilla, sem framework, sem build step.** MySQL 8 (Hostinger shared), `utf8mb4`.
- PDO + prepared statements (`ATTR_EMULATE_PREPARES=false`). Frontend HTML/CSS vanilla, tema
  escuro mobile-first, JS mínimo. PWA (`manifest.json`+`sw.js`) + push OneSignal.
- Sem Composer/npm — nada de dependências instaláveis. Toda "lib" é arquivo em `lib/`.

## Estrutura
- **`*.php` na raiz** = telas (a raiz É o document root; ~40 páginas).
- **`includes/`** — `config.php` (env+sessão+i18n), `db.php`, `auth.php`, `header.php`, `footer.php`, `grupos.php`.
- **`lib/`** — regras de negócio (cobrancas, pagamentos, distribuicao, cotacao, dite, i18n, regua, whatsapp, totp…).
- **`lang/`** — `en.php`/`es.php` (PT é o idioma-fonte, sem arquivo). **`db/`** — `schema.sql` + `migration_001..NNN` + seeds.
- **`cron/`** — jobs (gerar_cobrancas, regua_executar, backup_db…). **`api/`**, **`webhooks/`** — endpoints externos.

## Convenções (seguir SEMPRE)
- **Toda tela** começa com `require_once includes/auth.php` (ou header.php) e chama
  `require_login()` / `require_admin()` / `require_sadmin()` conforme o papel.
- **Saída**: escapar com `e($valor)`. **Todo POST**: `csrf_check()` no topo do handler; formulário inclui `csrf_token()`.
- **SQL**: sempre prepared statement via `db()`. Nunca concatenar entrada em query.
- **i18n**: texto visível vai em `t('texto em português')` — **a chave é o próprio PT**; sem tradução, cai no PT.
  Ao adicionar texto novo, acrescente a entrada em `lang/en.php` e `lang/es.php`. Idioma no cookie `idioma` (pt/en/es).
- **Moeda**: **USD é a moeda-mestre** do catálogo; BRL/EUR são derivados pela cotação do dia (`ceil`). Conversão via `lib/cotacao.php`.
- **Migrations aditivas**: quando uma coluna pode não existir ainda em produção, proteja a leitura com
  `db_coluna_existe($db,$tabela,$coluna)` — assim o código pode subir antes da migration rodar.
- **Papéis**: `sadmin` (tudo: catálogo, distribuição, auditoria, backups) > `admin` (operação) > `funcionario` / `cliente`.

## Deploy (importante)
1. Editar → commit → **push no `master`**.
2. **Auto-deploy ligado**: um webhook do GitHub → Hostinger publica sozinho em segundos. Não precisa mais clicar "Implantar".
3. **Migrations NÃO são automáticas**: ao subir uma `db/migration_*.sql`, rode o `.sql` manualmente no phpMyAdmin.
4. Service worker NÃO cacheia `.php` — se uma página "não mudou", o suspeito é deploy/migration, não cache.

## Segredos
`.env` no `public_html` (não versionado): `DB_*`, `SMTP_*`, `ONESIGNAL_REST_KEY`, `DITE_API_KEY`,
`DITE_WEBHOOK_SECRET`. **Nunca** commitar segredo nem colar no chat. Chave da IA (simulador) e instruções de
pagamento ficam na tabela `configuracoes`, não no `.env`.

## Estado atual
Em produção desde 2026-05. Implementado: todo o fluxo comercial/financeiro, entregas, régua,
2FA-recuperação, backups, auditoria, PWA+push, integração Dite Gateway (cartão) e Wise (webhook+CSV),
e **i18n completo PT/EN/ES** (interface + relatórios/PDFs). `schema.sql` está levemente defasado das
migrations 018/020/021 — para instalar do zero, rode as migrations em ordem.
