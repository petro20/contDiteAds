# contDiteAds — Dite Ads · Controle e Gestão

Sistema interno de gestão da **Dite Ads** (agência de marketing digital multilíngue).
Cobre o ciclo comercial completo: catálogo de serviços, assinaturas, cobrança mensal
automática, recebimento (incluindo cartão via gateway), folha de pagamento da equipe em
USD, distribuição de lucro entre sócios, despesas, entregas e comunicação com o cliente.

- **URL de produção**: https://cont.diteads.com
- **Repositório**: https://github.com/petro20/contDiteAds

> **Nota sobre a documentação histórica**: `PLANEJAMENTO.md` e `BUILD_PLAN.md` são
> documentos de projeto (planejamento e spec de build). Boa parte já foi implementada e
> alguns pontos evoluíram (ver decisões corrigidas no topo daqueles arquivos). Este README,
> a `PRD.md` e o `SPRINT_LOG.md` refletem o **estado atual**.

---

## Papéis (personas)

| Papel | Acesso |
|---|---|
| **sadmin** (super admin) | Tudo. Único que gerencia catálogo, régua/templates, despesas, distribuição de lucro, auditoria, backups e convites. |
| **admin** | Operação: clientes, funcionários, assinaturas, cobranças, painel financeiro, pagamentos. Não acessa catálogo/config sensível. |
| **funcionario** | Sua agenda de entregas, "Meus pagamentos", clientes que atende (leitura). Recebe em USD via Wise. |
| **cliente** | Suas cobranças e entregas. Loga para acompanhar; **não paga pelo sistema** (paga por fora: cartão via gateway, Pix, Zelle, Wise). |

Helpers de autorização em `includes/auth.php`: `current_user()`, `require_login()`,
`require_admin()`, `require_sadmin()`, `is_admin()`, `is_sadmin()`.

---

## Stack

- **Backend**: PHP 8 vanilla, sem framework. PDO + prepared statements (`PDO::ATTR_EMULATE_PREPARES=false`).
- **Banco**: MySQL 8 (Hostinger shared), `utf8mb4_unicode_ci`.
- **Frontend**: HTML + CSS vanilla, tema escuro mobile-first, JS mínimo. **Sem build step** → deploy é `git pull`.
- **PWA**: instalável no celular (`manifest.json` + `sw.js`), push via **OneSignal**.
- **i18n**: PT (idioma-fonte) / EN / ES — função `t()`, cookie `idioma` (ver abaixo).
- **PDF**: geração via HTML print-to-PDF (recibos, comprovantes) — sem biblioteca externa.
- **Email**: SMTP da Hostinger (`lib/email.php`).
- **Integrações externas**: Dite Gateway (cartão), Wise (webhook + CSV), Anthropic Claude
  (simulador de preço por IA), APIs de câmbio (AwesomeAPI → Frankfurter).

---

## Funcionalidades

**Comercial & financeiro**
- **Catálogo** (`catalogo.php`, só sadmin): itens `único` / `mensal` / `por unidade`,
  pacotes com composição (bill of materials), variante "com IA", período mínimo de contrato,
  responsabilidades (agência/funcionário/cliente). **USD é a moeda-mestre**; BRL e EUR são
  calculados automaticamente pela cotação do dia (`ceil`).
- **Simulador de preço** (`simulador_preco.php`) com sugestão por **IA (Claude)**: estima
  custos, margem e responsabilidades antes de criar o item no catálogo.
- **Assinaturas** (`assinaturas.php`): atribui item a cliente com preço da tabela pré-preenchido
  (override permitido), **desconto %** por N meses, cobrança fixa mensal para itens por-unidade,
  1 funcionário responsável por assinatura.
- **Cobrança mensal automática** (`cron/gerar_cobrancas.php`): consolida as assinaturas do
  cliente numa cobrança por mês (janela de 7 dias, idempotente). Item único gera cobrança
  avulsa na hora. Status: `aberta` / `em_analise` / `paga` / `cancelada`.
- **Recebimento**: cliente envia comprovante (→ `em_analise` → admin confirma), ou paga por
  **cartão via Dite Gateway** (confirma sozinho por webhook), ou por Zelle/Wise. Conciliação
  Wise por **webhook em tempo real** e por **upload de CSV**.
- **Folha da equipe** (`pagamentos_funcionarios.php`): fila em USD por funcionário, valor por
  par (funcionário × item), comprovante e email ao pagar via Wise. Funcionário vê em
  `meus_pagamentos.php`. Itens **avulsos** (sem assinatura) também podem ter funcionário
  responsável + valor USD próprio, entrando na mesma fila (migration 022).
- **Distribuição de lucro** (`distribuicao.php`): lucro = receita − despesas − pagamentos à
  equipe, dividido em quotas (N sócios + 1 quota "Empresa"). Por moeda + consolidado em US$.
- **Despesas** (`despesas.php`): categorizadas, recorrência única/mensal/anual.
- **Painel financeiro** (`painel.php`): agenda (vencidas/próximas + KPIs por moeda), por cliente, por serviço.

**Operação & entregas**
- **Agenda de entregas** (`agenda.php`): funcionário marca entregas em 4 modos conforme o
  tipo do item — calendário (pacotes POSTAGEM), tally por unidade (criativos), único, ou só
  info (Meta/Google ADS). Cliente vê em `entregas.php` (read-only).
- **Duplas** (`trabalha_com_id`): dois funcionários compartilham agenda; pagamento vai ao principal.
- **Capacidade** (`capacidade.php`): capacidade declarada por categoria vs. ocupação real.
- **Acompanhamento geral** (`agenda_geral.php`) e **alertas de postagem** (`alertas.php`, cron Qua/Sex).

**Comunicação**
- **Régua de cobrança** + **templates** unificados em "Comunicação" (`regua.php`):
  lembretes por email (automáticos) e WhatsApp (`wa.me`, manual-assistido). Suporta lembretes
  antes e depois do vencimento; permite silenciar cobranças específicas.
- **Recibos** (`recibo.php`) e comprovantes de pagamento à equipe (`comprovante_funcionario.php`).

**Onboarding & conta**
- **Convites** (`convites.php` / `convite.php?token=`): link único para auto-cadastro de cliente/funcionário.
- **Reset de senha** por email (`esqueci.php` / `redefinir.php`).
- **2FA** (TOTP + backup codes) usado como **meio de recuperação** (`seguranca.php`).
- **Perfil** (`perfil.php`): funcionário edita própria capacidade e toggle "aceitando clientes".

**Plataforma**
- **Busca global** (Ctrl+K, `busca.php`), **centro de notificações** no header.
- **Export CSV** (`export.php`) de cobranças/despesas/distribuição/clientes/funcionários/entregas.
- **Backup automático** do banco (`cron/backup_db.php`, PHP puro + gzip, rotação 14 dias).
- **Limpeza mensal** automática de logs antigos (`cron/limpeza_mensal.php`).
- **Auditoria** (`auditoria.php`) e **matriz de acesso** imprimível (`acessos_pdf.php`).
- **Ajuda** contextual por persona (`ajuda.php`).

---

## Estrutura do repositório

```
.
├── *.php                    # telas (raiz = document root; ~40 páginas)
├── api/plans.php            # lista pública de planos mensais (JSON) pro Dite Gateway
├── webhooks/dite.php        # webhook do Dite Gateway (rota /webhooks/dite)
├── wise_webhook.php         # webhook da Wise (conciliação em tempo real)
├── includes/                # config, db, auth, header, footer, grupos (guard contra acesso direto)
├── lib/                     # regras de negócio: cobrancas, pagamentos, distribuicao, despesas,
│                            #   cotacao, dite, i18n, entregas, regua, whatsapp, totp, audit, etc.
├── lang/                    # dicionários en.php, es.php (PT é o idioma-fonte, sem arquivo)
├── cron/                    # gerar_cobrancas, regua_executar, backup_db, alerta_postagens, limpeza_mensal
├── db/                      # schema.sql + migration_001..021 + seeds
├── assets/                  # css, js, img (logo em vários tamanhos)
├── push/ + OneSignalSDKWorker.js + sw.js + manifest.json   # PWA / push
├── uploads/                 # comprovantes, QR Zelle, backups (.htaccess Require all denied)
└── .htaccess                # HTTPS, rotas limpas, headers de segurança
```

---

## Banco de dados

Schema canônico em `db/schema.sql`; evolução em `db/migration_001..022`. Principais tabelas:
`usuarios`, `clientes`, `convites`, `itens_catalogo` (+ `itens_pacote_composicao`),
`assinaturas`, `cobrancas` (+ `cobranca_itens`), `pagamentos_cliente`,
`func_servico_pagamento`, `pagamentos_funcionario` (+ `_itens`), `pagamentos_socio`,
`despesas`, `entregas`, `capacidade_funcionario`, `templates_mensagem`, `regua_etapas`
(+ `regua_eventos`), `wise_eventos`, `dite_eventos`, `simulacoes_preco`, `configuracoes`,
`audit_log`, `senha_resets`, `totp_backup_codes`.

> ⚠️ **`schema.sql` está levemente defasado**: não inclui as colunas de `assinaturas`
> adicionadas nas migrations 018/020/021 (`cobrar_fixo_mensal`, `desconto_pct`,
> `desconto_meses`) nem as de `cobranca_itens` da 022 (`funcionario_id`, `pagamento_func_usd`).
> Para instalação do zero, rode as migrations em ordem, ou atualize o
> `schema.sql`. Migration destrutiva: **`002_dite_full`** (recriou todo o schema a partir do MVP antigo).

Seeds: `seed.sql` (admin inicial), `seed_catalogo.sql` (catálogo real + templates + régua de
4 etapas), `seed_templates_pagamento.sql` (instruções de pagamento).

---

## Cron jobs (hPanel → Avançado → Cron Jobs)

| Script | Frequência | Para quê |
|---|---|---|
| `cron/gerar_cobrancas.php` | `0 5 * * *` | Gera cobranças mensais consolidadas |
| `cron/regua_executar.php` | `0 6 * * *` | Régua: email automático + tarefa WhatsApp na agenda do admin |
| `cron/backup_db.php` | `0 4 * * *` | Backup do banco (gzip, rotação 14 dias) |
| `cron/alerta_postagens.php` | `0 9 * * 3,5` | Alerta funcionário sem POSTAGEM marcada na semana (Qua/Sex) |
| `cron/limpeza_mensal.php` | `0 3 1 * *` | Purga logs/tokens antigos + OPTIMIZE TABLE |

Caminho no servidor: `php /home/u788472657/domains/cont.diteads.com/public_html/cron/<script>.php`.

---

## Configuração (`.env`)

Copie `.env.example` para `.env` no `public_html` (não versionado). Além de `DB_*`, `APP_*`
e `SMTP_*`, os **segredos** das integrações ficam aqui (as chaves públicas/defaults têm
fallback no `includes/config.php`):

- `ONESIGNAL_APP_ID` (público, já tem default) / `ONESIGNAL_REST_KEY` (secreta — push server-side)
- `DITE_BASE_URL` (default `https://pay.diteads.com`) / `DITE_API_KEY` / `DITE_WEBHOOK_SECRET` (secretas)

A **chave da API Anthropic** (simulador por IA) e as instruções de pagamento (Zelle/Wise/QR)
ficam na tabela `configuracoes` (editáveis em `config_pagamento.php`), não no `.env`.

---

## Deploy

Fluxo Hostinger (shared, `public_html` = raiz do repo):

1. Editar local → commit → push (`master`).
2. **hPanel → Avançado → GIT → Implantar** (pull manual — o GitHub Desktop **não** publica;
   o site só atualiza clicando em "Implantar").
3. **Migrations são manuais**: rode o `.sql` novo via phpMyAdmin quando um push incluir migration.

Setup inicial: importar `schema.sql` (ou rodar migrations em ordem) + seeds via phpMyAdmin,
gerar hash do admin com `php db/gerar_hash.php 'SuaSenha'`, preencher `.env`, acessar com
`admin@diteads.com`.

---

## Segurança (resumo)

Sessão com cookie HTTPOnly + SameSite=Lax + regeneração de ID no login; **session nonce**
(reset de senha derruba sessões em outros dispositivos); CSRF (`csrf_check()`) em todo POST;
escape de saída via `e()`; prepared statements em tudo; 2FA TOTP com backup codes; `.htaccess`
força HTTPS, bloqueia `.env`/`.git`/etc. e aplica headers de segurança. Uploads servidos via
PHP com autenticação (pasta negada por `.htaccess`). Ver `security-review` para auditoria detalhada.
