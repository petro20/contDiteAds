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

## Sprint 4 — Pagamentos · ✅ ENTREGUE

**Decisões adotadas como padrão**:
- Comprovante max 5MB, formatos PDF/JPG/PNG
- Pagamento parcial ao funcionário: permitido via seleção de itens (admin marca quais incluir)
- Comprovante imutável (admin pode remover pagamento inteiro; "corrige" criando novo)
- Comprovante de pagamento ao funcionário: HTML print-to-PDF (sem lib externa)

**Entregues**:
- `lib/pagamentos.php` — atualização de status, fila pendente, criação de pagamentos
- `cobrancas.php` reescrita — cliente envia comprovante; admin registra pagamento direto; remove pagamento
- `funcionarios.php` (edição) — admin define valor USD por item para o funcionário
- `pagamentos_funcionarios.php` — fila admin com total USD por funcionário, formulário pra marcar pago
- `meus_pagamentos.php` — funcionário vê "a receber" e histórico
- `comprovante_funcionario.php` — página printable (Ctrl+P → Save as PDF)
- `uploads/` — diretório com .htaccess Require all denied (serve via PHP com auth)
- Email automático ao funcionário quando admin marca como pago (link pro comprovante)

**Como testar (ciclo completo)**:
1. Admin cria assinatura com funcionário responsável
2. Admin edita funcionário → "Quanto recebe (USD)" → define valor pro item
3. Admin → Cobranças → gera cobrança manual → registra pagamento (com ou sem comprovante)
4. Admin → Pagamentos a funcionários → vê fila com total USD
5. Clica em "Pagar [func]" → confirma itens → "Marquei como pago"
6. Email é enviado pro funcionário (se SMTP configurado)
7. Funcionário → "Pagamentos" → vê o histórico → clica pra abrir comprovante → "Imprimir / Salvar PDF"

## Sprint 5 — Comunicação (WhatsApp + Régua + Recibos) · ✅ ENTREGUE

**Entregues**:
- `recibo.php?cobranca=N` — recibo HTML printable (admin OU cliente da cobrança acessam)
- `lib/whatsapp.php` — helpers: `wa_link`, `wa_render`, `wa_template`, `wa_vars_cobranca`
- `templates.php` — admin edita templates de mensagem (canal: email/whatsapp)
- `cobrancas.php` — botão "💬 Enviar pelo WhatsApp" + "📄 Ver recibo" em cada cobrança (admin escolhe template padrão baseado em status: paga/vencida/normal)
- `lib/regua.php` — execução da régua + tarefas pendentes
- `regua.php` — admin configura etapas + lista tarefas WhatsApp pendentes (com botão wa.me) + permite silenciar cobranças específicas
- `cron/regua_executar.php` — script diário (CLI-only): envia emails automáticos, cria tarefas WhatsApp pendentes na agenda do admin

**Cron na Hostinger** (uma vez):
- hPanel → Avançado → Cron Jobs
- Comando: `php /home/u788472657/domains/cont.diteads.com/public_html/cron/regua_executar.php`
- Frequência: `0 6 * * *` (todo dia às 6h)

**Régua padrão** (já seedada):
- Etapa 1: dia 0 (vencimento) — email "vence hoje" + WhatsApp na agenda
- Etapa 2: +3 dias — lembrete email + WhatsApp
- Etapa 3: +7 dias — urgente
- Etapa 4: +15 dias — última automática

**Como testar**:
1. Crie cobrança com vencimento de ontem (data passada)
2. Cron pode ser rodado manualmente local: `php cron/regua_executar.php`
3. Email é enviado se SMTP configurado; WhatsApp aparece em "Tarefas WhatsApp pendentes" em /regua.php
4. Admin clica "Abrir WhatsApp" → wa.me com mensagem preenchida → revisa, envia, marca como enviado

## Sprint 6 — Capacidade + Audit + Notificações + 2FA · ✅ ENTREGUE

**Entregues**:
- `capacidade.php` — admin vê capacidade declarada vs ocupação real por funcionário
- `funcionarios.php` (editar) — admin define capacidade mensal por categoria (criativos / postagens / sites_projetos)
- `auditoria.php` — admin filtra histórico de audit_log por usuário, entidade, ação, data
- `lib/cobrancas.php` — gatilho `notificar_cliente_email('cobranca_nova')` quando gera cobrança
- `lib/pagamentos.php` — gatilho `notificar_cliente_email('pagamento_confirmado')` quando cobrança fica paga
- `db/migration_003_2fa.sql` — adiciona `totp_secret` + `totp_enabled` em usuarios (pendente de aplicar)
- `lib/totp.php` — implementação TOTP RFC 6238 (compatível Google Auth / Authy / 1Password)
- `seguranca.php` — usuário ativa/desativa 2FA com QR Code (api.qrserver.com)
- `login.php` — fluxo de 2 passos: senha → código TOTP (se ativo)
- `perfil.php` — link "Configurar 2FA"
- `auth.php login()` — tolera ausência das colunas TOTP (migration_003 ainda não aplicada)

**Cron na Hostinger** (todos os crons configurados):
- `cron/gerar_cobrancas.php` → diário 05:00
- `cron/regua_executar.php` → diário 06:00

**Migration pendente**: `db/migration_003_2fa.sql` precisa ser aplicada pra 2FA funcionar. Site continua operando normalmente sem ela (login funciona, todas as outras telas funcionam). Só `seguranca.php` falhará até aplicar.

## Status final do projeto

✅ Sprint 0 — Foundation (schema, design system, helpers)
✅ Sprint 1 — Catálogo + Onboarding + Reset senha
✅ Sprint 2 — Assinaturas + Cobrança recorrente
✅ Sprint 3 — Entregas + Painel financeiro
✅ Sprint 4 — Pagamentos (comprovante, fila funcionários, recibo USD)
✅ Sprint 5 — WhatsApp + Régua + Recibos
✅ Sprint 6 — Capacidade + Audit + Notificações + 2FA (migration_003 pendente)

**Sistema entregue conforme BUILD_PLAN.md**. Roadmap original 100% atendido.
