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

## Sprint 1 — Catálogo + Onboarding + Reset senha · ✅ CONCLUÍDO

**Commits**: `95b54cc`, `e19d4ad`
**Migration aplicada**: 2026-05-25 (26 statements)
**Seed aplicado**: 2026-05-25 (catálogo + templates + régua)

**Telas novas/atualizadas**:
- `catalogo.php` — CRUD itens + composição de pacote
- `convites.php` (admin) + `convite.php?token=X` (público)
- `esqueci.php` + `redefinir.php?token=X` — reset senha via email
- `clientes.php` — schema novo (nome_empresa, moeda, link_grupo)
- `funcionarios.php` — cpf, wisetag, país, aceitando_clientes
- `dashboard.php` — cards de ações rápidas
- `perfil.php` — editar nome/senha
- `cobrancas.php` — stub "em construção"
- `servicos.php` removido (substituído por catalogo.php)
- Logo enviado pelo usuário em `assets/img/logo.png`

**Catálogo populado** com 30 itens reais da Dite Ads:
- 5 itens de criação de contas em redes sociais
- 4 sites (Landing Page, Portfólio, Ecommerce 50/100)
- 2 de infraestrutura (domínio, provedor)
- 4 e-books
- LogoTipo, ART
- Meta ADS, Google ADS (mensais)
- CTF, CTV, CTI (por unidade)
- 4 pacotes (ANÚNCIO, POSTAGEM 7D/5D/2D) com composição interna

**Para testar**:
- Logar como admin → ver dashboard com cards
- Catálogo → ver 30 itens populados; editar um pacote pra ver composição
- Convites → gerar link de cliente e funcionário
- Logout → "Esqueci minha senha" (depende do SMTP_USER no .env)

## Sprint 2 — Assinaturas + Cobrança recorrente · ✅ ENTREGUE

**Commits**: pendente (push agora)

**Decisões adotadas como padrão** (não estavam fechadas no BUILD_PLAN; ajusto se mudar):
- Vencimento default = `dia_cobranca + 5 dias`
- Item por unidade sem entregas no mês = não aparece na cobrança (cobra zero)
- Pausa/cancela no meio do mês = mantém cobrança já gerada (admin pode cancelar manualmente)
- Cron via Hostinger Cron Jobs, executável também via CLI local

**Entregues**:
- `lib/cobrancas.php` — lógica central: `ensure_dia_cobranca`, `gerar_cobranca_avulsa_unico` (item único), `gerar_cobranca_mensal`, `executar_geracao_diaria`
- `assinaturas.php` — admin atribui itens a clientes; auto-preenche preço da tabela conforme moeda; override permitido; troca de funcionário só vale mês seguinte; status ativa/pausada/cancelada
- `cobrancas.php` reescrita — lista + detalhe + botão "Gerar manualmente" (admin) para teste
- `cron/gerar_cobrancas.php` — script diário (CLI-only) que processa todos os clientes elegíveis
- Cliente/funcionário também veem cobranças (filtradas por escopo)
- Dashboard ganhou cards de Assinaturas e Cobranças

**Como configurar o cron na Hostinger** (uma vez):
1. hPanel → Avançado → Cron Jobs
2. Comando: `php /home/u788472657/domains/cont.diteads.com/public_html/cron/gerar_cobrancas.php`
3. Frequência: `0 5 * * *` (todo dia às 5h)

**Como testar agora** sem esperar cron:
1. Painel → Assinaturas → criar uma assinatura de item mensal/pacote
2. Painel → Cobranças → abrir "Gerar cobrança manualmente (teste)"
3. Escolher cliente + competência atual → "Gerar agora"
4. Cobrança aparece com items consolidados

## Sprint 3 — Entregas + Painel financeiro · ✅ ENTREGUE

**Decisões adotadas como padrão**:
- Cliente vê entregas em tempo real (SIM)
- Funcionário pode marcar/desmarcar a qualquer momento (SIM)
- Admin NÃO precisa aprovar marcações
- Pacote incompleto cobra cheio (cobrança usa valor da assinatura)
- Funcionário recebe fixo pelo pacote (mensal) e por unidade para por_unidade

**Entregues**:
- `lib/entregas.php` — helpers: 4 modos de UI (calendar/tally/single/info), CRUD de entregas, calendário do mês
- `agenda.php` — funcionário marca entregas. POSTAGEM = grid de calendário; CTF/CTV/CTI avulsos = tally (clique pra +1); itens únicos = 1 botão; Meta/Google ADS = só info
- `entregas.php` — cliente vê o mês (read-only) com mesmas visualizações
- `painel.php` — admin, 3 abas: Agenda (vencidas + próximas 7 dias + KPIs por moeda), Por cliente (totais cobrado/pago/em aberto por cliente), Por serviço (quantos clientes ativos por item do catálogo)
- Bottom nav admin agora aponta pra painel.php (era dashboard)

**Como testar**:
1. **Admin** → cria assinatura POSTAGEM 5D pro cliente, atribui a um funcionário
2. **Funcionário** → Agenda → clica nos dias no calendário pra marcar entregas
3. **Cliente** → Entregas → vê o mesmo calendário com os dias marcados
4. **Admin** → Painel → aba "Por cliente" e "Por serviço" → visão agregada

## Sprint 4+ — ⏳ A FAZER
