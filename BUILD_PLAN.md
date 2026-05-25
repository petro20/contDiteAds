# Build Plan — Sistema interno Dite Ads

Spec técnica do que precisa ser construído, derivado do `PLANEJAMENTO.md`.

> Esta versão substitui completamente o MVP atual (cobrancas/pagamentos
> simples). O esquema atual em produção foi um stub — vai ser refeito
> com migration destrutiva no banco vazio.

---

## 0. Domínio do site

- Hoje: `cont.diteads.com` (já em produção)
- Planejamento aponta `bus.diteads.com`

**Decisão pendente**: migrar para `bus.diteads.com` ou manter `cont.diteads.com`?
Mudar de domínio implica configurar DNS + apontar o site na Hostinger.
Sugestão: manter `cont.diteads.com` para o MVP e migrar quando estiver pronto pra equipe usar.

---

## 1. Stack consolidada

| Camada | Escolha | Por quê |
|---|---|---|
| Backend | PHP 8 vanilla | Continua o que já está rodando |
| DB | MySQL 8 (Hostinger) | Já configurado |
| Frontend | HTML + CSS (vars) + JS mínimo | Sem build step, deploy é `git pull` |
| Fonte | Inter (Google Fonts ou local) | Pedido no design system |
| PDF | **dompdf** | Mais leve que mPDF, instala sem extensões nativas |
| Email | **PHPMailer + SMTP da Hostinger** | Conta de email já existe no domínio; sem custo extra |
| Tarefas agendadas | **Cron do hPanel Hostinger** | Suportado nativo, sem dependências |
| Auth de cliente/func | Email + senha (bcrypt) + token de reset | Continua o auth atual, com fluxo de reset |

**Decisões pendentes** sobre stack: ver seção 7.

---

## 2. Schema do banco — reescrita completa

A migration vai **dropar** as tabelas atuais (estão vazias na prática)
e criar a estrutura nova.

```
clientes
  id, nome_empresa, nome_contato, documento, email, telefone, endereco,
  moeda ENUM('USD','BRL','EUR'),
  link_grupo VARCHAR(500) NULL,
  dia_cobranca TINYINT NULL,        -- preenchido na 1ª assinatura
  observacoes TEXT NULL,
  ativo, criado_em

usuarios
  id, nome, email UNIQUE, senha_hash,
  role ENUM('admin','funcionario','cliente'),
  cliente_id → clientes (NULL exceto role=cliente),
  cpf NULL, wisetag NULL, pais NULL,
  aceitando_clientes TINYINT DEFAULT 1,     -- toggle N13 sinal 1
  ativo, criado_em

convites                                   -- N1
  id, token UNIQUE, tipo ENUM('cliente','funcionario'),
  pre_preenchidos JSON NULL,
  criado_por → usuarios, expira_em, usado_em NULL, usado_por → usuarios NULL, criado_em

senha_resets                                -- N5
  id, usuario_id → usuarios, token UNIQUE, expira_em, usado_em NULL, criado_em

itens_catalogo                              -- N2
  id, nome, descricao TEXT NULL,
  tipo ENUM('unico','mensal','por_unidade'),
  preco_usd DECIMAL(10,2) NULL,
  preco_brl DECIMAL(10,2) NULL,
  preco_eur DECIMAL(10,2) NULL,
  a_negociar TINYINT DEFAULT 0,
  e_pacote TINYINT DEFAULT 0,
  tem_variante_ia TINYINT DEFAULT 0,        -- POSTAGEM com IA on/off
  preco_ia_usd, preco_ia_brl, preco_ia_eur DECIMAL(10,2) NULL,
  resp_agencia TEXT, resp_funcionario TEXT, resp_cliente TEXT,
  ativo, criado_em

itens_pacote_composicao                     -- N2 — bill of materials
  pacote_id → itens_catalogo,
  componente_id → itens_catalogo,
  quantidade INT,
  variante ENUM('normal','ia') DEFAULT 'normal'

func_servico_pagamento                      -- valor USD que func recebe por item
  funcionario_id → usuarios,
  item_id → itens_catalogo,
  valor_usd DECIMAL(10,2),
  PRIMARY KEY (funcionario_id, item_id)

capacidade_funcionario                      -- N13 sinal 2
  funcionario_id → usuarios,
  categoria VARCHAR(50),                    -- 'criativos', 'postagens', 'sites'
  capacidade_mensal INT,
  PRIMARY KEY (funcionario_id, categoria)

assinaturas                                 -- N3
  id, cliente_id → clientes, item_id → itens_catalogo,
  funcionario_id → usuarios NULL,
  variante ENUM('normal','ia') DEFAULT 'normal',
  valor_cobrado DECIMAL(10,2),              -- override por cliente (na moeda do cliente)
  status ENUM('ativa','pausada','cancelada'),
  iniciada_em DATE, encerrada_em DATE NULL, criado_em

cobrancas                                   -- N3
  id, cliente_id → clientes,
  competencia_mes CHAR(7),                  -- '2026-05'
  valor_total DECIMAL(10,2), moeda ENUM('USD','BRL','EUR'),
  vencimento DATE,
  status ENUM('aberta','paga','cancelada'),
  silenciada TINYINT DEFAULT 0,             -- N14
  criado_em, atualizado_em
  UNIQUE (cliente_id, competencia_mes)

cobranca_itens                              -- linha por item dentro da cobrança
  id, cobranca_id → cobrancas, assinatura_id → assinaturas,
  descricao, quantidade INT, valor_unitario, subtotal

pagamentos_cliente                          -- substitui o "pagamentos" atual
  id, cobranca_id → cobrancas, valor_pago, data_pagamento,
  metodo, observacao, comprovante_path NULL,   -- N6
  registrado_por → usuarios, criado_em

entregas                                    -- N12
  id, assinatura_id → assinaturas,
  competencia_mes CHAR(7),
  -- para POSTAGEM: data_marcada DATE; para por_unidade: indice INT
  data_marcada DATE NULL,
  indice INT NULL,
  funcionario_id → usuarios,                -- quem marcou (= responsável do mês)
  criado_em

pagamentos_funcionario                      -- N15
  id, funcionario_id → usuarios,
  valor_usd DECIMAL(10,2),
  data_pagamento DATE,
  comprovante_pdf_path VARCHAR(255) NULL,
  criado_por → usuarios, criado_em

pagamento_funcionario_itens                 -- detalhamento do PDF (N15)
  id, pagamento_id → pagamentos_funcionario,
  cobranca_item_id → cobranca_itens,        -- de onde veio
  descricao, quantidade, valor_unitario_usd, subtotal_usd

regua_etapas                                -- N14 configurável
  id, ordem INT,
  dias_apos_vencimento INT,
  template_email_id → templates_mensagem,
  template_whatsapp_id → templates_mensagem NULL,
  ativa TINYINT

regua_eventos                               -- log do que foi disparado
  id, cobranca_id → cobrancas, etapa_id → regua_etapas,
  canal ENUM('email','whatsapp'),
  enviado_em DATETIME NULL,                 -- null = pendente na agenda do admin
  marcado_por → usuarios NULL

templates_mensagem                          -- N11 + N14
  id, codigo VARCHAR(50) UNIQUE,            -- 'cobranca_nova', 'lembrete_3d'…
  canal ENUM('email','whatsapp'),
  assunto VARCHAR(255) NULL,
  corpo TEXT,
  ativo, criado_em

audit_log                                   -- N9
  id, usuario_id → usuarios NULL, acao VARCHAR(80),
  entidade VARCHAR(50), entidade_id INT,
  payload_antes JSON NULL, payload_depois JSON NULL,
  ip VARCHAR(45), criado_em
```

Migration vai em `db/migration_002_dite_full.sql`. Rodo via PHP local
(MySQL Remoto já configurado).

---

## 3. Mapa feature → arquivos

| # | Feature | Cria/edita | Depende de |
|---|---|---|---|
| **0** | Schema novo + design system base | `db/migration_002_*.sql`, `assets/css/style.css` (reescrita dark), `includes/header.php`, `includes/layout-mobile.php` | — |
| **N2** | Gerenciar catálogo | `catalogo.php` (lista, criar, editar item, gerenciar pacote/composição) | 0 |
| **N1** | Onboarding por link | `convites.php` (admin), `convite.php?token=X` (público) | 0 |
| **N3** | Assinaturas + cobrança recorrente | `assinaturas.php` (atribuir item ao cliente), `cobrancas.php` (refazer com novo schema), `cron/gerar_cobrancas.php` | N2 |
| **N12** | Entregas com checkbox | `agenda.php` (func), `clientes.php?id=N` aba "entregas" (cliente vê) | N3 |
| **N4** | Painel financeiro | `painel.php` com abas (Agenda, Por cliente, Por serviço) | N3 |
| **N6** | Comprovante upload | upload em `cobrancas.php?id=N`, view em `pagamentos.php` | N3 |
| **N5** | Reset senha email | `esqueci.php`, `redefinir.php?token=X`, integra com PHPMailer | 0 |
| **N7** | Recibo PDF | `recibo.php?cobranca_id=N` (dompdf) | N3 |
| **N8** | Notificações email | `lib/email.php` (PHPMailer wrapper), gatilhos nos endpoints existentes | N5 |
| **N11** | WhatsApp wa.me | botão em telas de cobrança/agenda, `templates.php` (admin) | N3 |
| **N13** | Capacidade declarada | campos no perfil do func, dropdown ornamentado em `assinaturas.php` | N3 |
| **N14** | Régua de cobrança | `regua.php` (admin config), `cron/regua_executar.php` | N6, N8 |
| **N15** | Pagamento ao funcionário | `pagamentos_funcionarios.php` (admin), `meus_pagamentos.php` (func), PDF + email | N6, N7 |
| **N9** | Audit log | `lib/audit.php` (gatilho central), `auditoria.php` (admin lista) | 0 |
| **N10** | 2FA admin | `2fa.php`, biblioteca TOTP simples (OTPHP via composer ou impl manual) | N5 |

---

## 4. Ordem de build sugerida (sprints)

### Sprint 0 — Foundation (1-2 dias de código)

- Migration `002_dite_full.sql` (schema completo)
- Design system: CSS dark/mobile-first, fonte Inter, header/footer novos,
  bottom nav por persona
- Helpers: `lib/email.php`, `lib/audit.php`, `lib/money.php` (formatação por moeda)
- Seed do catálogo com os itens reais da Dite Ads (script idempotente)

### Sprint 1 — Catálogo + Onboarding (🔥 N2 + N1 + N5)

- N2: tela `catalogo.php` completa, incluindo pacotes/composição
- N1: gerar convite, formulário público de convite, criar usuário
- N5: reset de senha (necessário pra cliente sobreviver depois do convite)

### Sprint 2 — Assinaturas + Cobranças (🔥 N3)

- Atribuir item ao cliente (assinaturas)
- Definir dia da cobrança automaticamente na 1ª assinatura
- Cron mensal gera cobranças consolidadas
- Cobrança avulsa pra item único na hora da assinatura

### Sprint 3 — Entregas + Painel (🔥 N12 + N4)

- Agenda do funcionário com 4 modos de checkbox
- Painel financeiro 3 abas (Agenda, Por cliente, Por serviço)

### Sprint 4 — Pagamentos (⭐ N6 + N15)

- Upload comprovante cliente → admin confirma
- Liberação automática → fila de pagamentos pendentes → PDF Wise

### Sprint 5 — Comunicação (⭐ N7 + N11 + N14)

- Recibo PDF
- Templates editáveis e botões wa.me
- Régua de cobrança automática (email + tarefa WhatsApp)

### Sprint 6 — Acabamento (⭐ N13 + N8 + 💭 N9 + N10)

- Capacidade declarada + indicador inline
- Notificações por email
- Audit log + tela de auditoria
- 2FA admin

---

## 5. Decisões já fixadas (pra não reabrir)

- 100% PT-BR.
- Tema escuro, mobile-first, paleta azul royal + roxo + laranja.
- 3 moedas (USD/BRL/EUR), sem conversão, sempre separadas.
- Funcionário recebe em USD via Wise.
- Pacote tem composição interna fixa; variante "com IA" como checkbox.
- 1 assinatura = 1 funcionário responsável.
- Troca de funcionário em assinatura ativa só vale para o mês seguinte.
- Cliente loga mas não paga pelo sistema.
- Pagamento manual (sem gateway).
- Dia de cobrança = data da 1ª assinatura; mês curto cai no último dia.
- Régua de cobrança: 4 lembretes, email automático + WhatsApp manual.
- Fila de pagamento de funcionário consolidada por funcionário (não por cliente).
- 1 comprovante de pagamento por transferência (não consolida mensal).

---

## 6. Decisões PENDENTES (preciso saber pra começar)

Pra não travar o Sprint 0, listadas em ordem de quem bloqueia mais cedo:

### Bloqueiam Sprint 0:
1. **Domínio do site**: manter `cont.diteads.com` ou migrar pra `bus.diteads.com`?
2. **SMTP**: tem conta de email `@diteads.com` na Hostinger pra usar como remetente
   automático? (ex: `nao-responda@diteads.com`)
3. **Logo**: tem o arquivo SVG/PNG do logo Dite Ads pra eu colocar no header e
   nos PDFs? Pode mandar.

### Bloqueiam Sprint 1 (Catálogo + Onboarding):
4. **Expiração do convite**: 7 dias / 30 dias / nunca?
5. **CPF do funcionário**: opcional confirmado, certo?
6. **Composição de pacote — UI**: dropdown "adicionar componente" + quantidade,
   ou outra coisa?
7. **Responsabilidades** do item: 3 campos de textarea livres (sugestão minha)?

### Bloqueiam Sprint 2 (Cobranças):
8. **Cron job**: tem como rodar cron na Hostinger? (Plano Premium+ tem; preciso
   verificar o seu)
9. **Pausa de assinatura no meio do mês**: estorna a cobrança já gerada,
   mantém, ou admin decide caso a caso?
10. **Item por unidade sem entregas no mês**: cobra zero ou tem mínimo?

### Bloqueiam Sprint 3 (Entregas + Painel):
11. **Cliente vê entregas em tempo real?**: minha sugestão é SIM —
    transparência > pressão. Confirma?
12. **Funcionário pode desmarcar checkbox?**: sim/não.
13. **Admin precisa aprovar marcações antes de virarem folha?**:
    minha sugestão é NÃO — marcação do func = válida. Confirma?
14. **Pacote incompleto** (POSTAGEM 5D com só 3 marcados): cliente
    paga cheio (sugestão), proporcional, ou admin decide?
15. **Funcionário recebe pelo pacote ou por checkbox marcado?**:
    minha sugestão para POSTAGEM é FIXO pelo pacote (USD definido em
    `func_servico_pagamento`), independente das marcações.

### Bloqueiam Sprint 4+:
16. **Tamanho máximo do comprovante de pagamento**: 5MB OK?
17. **Formatos aceitos**: PDF, JPG, PNG?
18. **Comprovante pode ser estornado depois?**: minha sugestão é
    imutável; correção = novo comprovante.
19. **Pagamento parcial ao funcionário**: permitir?
20. **Admin CC nos emails da régua**: copiar em todas as etapas ou
    só nas críticas?
21. **2FA**: TOTP (Google Authenticator) ou via email?

---

## 7. Plano de migração do MVP atual

Como o sistema atual está em produção mas vazio (só o admin cadastrado),
a transição é simples:

1. Rodar migration `002` (cria as tabelas novas em paralelo).
2. Manter login do admin atual (`admin@diteads.com`).
3. Drop das tabelas `cobrancas`, `pagamentos`, `servicos`, `apontamentos`,
   `tarefas` que ainda existem com schema antigo.
4. Push do novo código (sobrescreve `cobrancas.php`, `servicos.php`,
   `clientes.php`, `funcionarios.php`, `dashboard.php`).
5. Smoke test: login admin → cria item no catálogo → cria cliente
   via convite → atribui assinatura → vê cobrança gerada.

---

## 8. Como você acompanha

A cada sprint terminado, vou:
- Commitar com `feat: sprint N — descrição`
- Atualizar este arquivo marcando o sprint como `[x] CONCLUÍDO`
- Te avisar com 1 linha aqui no chat com link pro commit
- Te pedir pra testar uma coisa específica (ex: "loga como admin e cria
  um item no catálogo")

Quando todas as decisões pendentes da seção 6 estiverem fechadas, posso
começar pelo Sprint 0.
