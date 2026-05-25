# Sprint Log

Acompanhamento da execução do `BUILD_PLAN.md`.

## Sprint 0 — Foundation · ✅ EM REVISÃO

**Commit**: pendente
**O que entrou**:
- `db/migration_002_dite_full.sql` — schema completo (não executado ainda)
- `db/seed_catalogo.sql` — itens reais da Dite Ads + templates de mensagem + régua padrão
- `assets/css/style.css` — reescrita completa: tema escuro mobile-first, paleta vibrante do logo
- `includes/header.php` + `footer.php` — layout com bottom nav por persona
- `lib/money.php`, `lib/email.php`, `lib/audit.php` — helpers
- `.env.example` + `includes/config.php` — variáveis SMTP
- `login.php` — atualizado para novo design

**O que NÃO entrou** (próximas sprints):
- Telas novas (catálogo, convites, assinaturas, agenda, painel financeiro)
- Migração dos dados (a migration ainda não rodou — esperando Sprint 1)

**Estado do site em produção**:
- Login e MVP atual continuam funcionando com schema antigo.
- CSS novo aplicado: o site fica com cara nova mesmo nas telas velhas.
- Bottom nav aponta para páginas que ainda não existem (clientes/catalogo/agenda/etc) — clicar dá 404 até Sprint 1.

**Para testar agora**:
- Abrir `cont.diteads.com` → ver visual novo no login
- Logar como admin → dashboard com cara nova

## Sprint 1 — Catálogo + Onboarding + Reset senha · ⏳ A FAZER

Pendente.

## Sprint 2+ — ⏳ A FAZER
