# PRD — contDiteAds (Dite Ads · Controle e Gestão)

**Sistema interno de gestão da Dite Ads** — agência de marketing digital multilíngue.
Estado: **em produção, evoluindo continuamente**. · Última atualização: 2026-07-07.

- **URL de produção**: https://cont.diteads.com
- **Repositório**: https://github.com/petro20/contDiteAds

> Este documento descreve o **produto como ele existe hoje**. Para a visão de projeto
> original e o backlog conceitual, ver `PLANEJAMENTO.md` (histórico) e `BUILD_PLAN.md`.

---

## 1. Contexto

A Dite Ads presta serviços de marketing digital (gestão de anúncios, criativos, sites,
e-books, criação de contas) para clientes em três mercados/moedas (BRL, USD, EUR). O sistema
centraliza: catálogo de serviços, contratação (assinaturas), cobrança recorrente, recebimento,
folha de pagamento da equipe (em USD via Wise), distribuição de lucro entre sócios, despesas,
acompanhamento de entregas e comunicação com o cliente.

O pagamento do cliente pode acontecer **dentro** do sistema (cartão via Dite Gateway, que
confirma por webhook) ou **fora** (Pix, Zelle, Wise) com registro manual/conciliação.

## 2. Personas e papéis

| Papel | Acesso | Ações principais |
|---|---|---|
| **sadmin** | Total | Catálogo, régua/templates, despesas, distribuição de lucro, auditoria, backups, convites, além de tudo do admin. |
| **admin** | Operação | Clientes, funcionários, assinaturas, cobranças, painel financeiro, pagamentos à equipe. |
| **funcionário** | Próprio escopo | Agenda de entregas (só onde é responsável), "Meus pagamentos" (USD), clientes que atende (leitura), capacidade/disponibilidade. |
| **cliente** | Próprio escopo | Suas cobranças (pode pagar por cartão ou anexar comprovante) e entregas. Não paga folha nem vê dados de terceiros. |

## 3. Internacionalização (PT/EN/ES)

> **Mudança relevante vs. planejamento original**: o sistema deixou de ser "100% PT-BR" e
> hoje é **multilíngue**.

- Idiomas: **Português (fonte)**, **English**, **Español**.
- Motor `t($textoPT, $vars)` em `lib/i18n.php`: a própria string PT é a chave; traduções em
  `lang/en.php` e `lang/es.php`; fallback para PT quando falta tradução. Placeholders `{nome}`.
- Idioma escolhido no seletor do header (`?lang=xx`), persistido no cookie `idioma` (1 ano).

## 4. Catálogo e precificação

- **Tipos de item**: `único` (one-shot), `mensal` (recorrente), `por unidade` (avulso).
- **Pacotes** com composição interna (bill of materials) e **variante "com IA"** (preço/composição alternativos).
- **Período mínimo de contrato** (meses) por item.
- **Responsabilidades**: o que a agência entrega, o que o funcionário faz, o que o cliente fornece.
- **Moeda-mestre USD**: admin preenche USD; **BRL e EUR são derivados pela cotação do dia**
  (`ceil`), atualizável a qualquer momento. Cotação via AwesomeAPI com fallback Frankfurter,
  cacheada em `configuracoes`.
- **Simulador de preço** com **IA (Claude)**: estima custos por linha, margem e responsabilidades;
  simulações salvas podem virar item do catálogo.

## 5. Assinaturas

- Admin atribui item do catálogo a um cliente → assinatura com **1 funcionário responsável**.
- Preço da tabela pré-preenchido na moeda do cliente; **override** permitido (para cima/baixo).
- **Desconto percentual** aplicável, com **duração em N meses** (0 = permanente).
- Item `por unidade` pode ser marcado como **cobrança fixa mensal** (ignora contagem de entregas).
- Trocar o funcionário responsável só vale do mês seguinte em diante.
- Status: `ativa` / `pausada` / `cancelada`. Mudar a moeda do cliente recalcula `valor_cobrado`.
- **Dia de cobrança** do cliente = dia da 1ª assinatura (mês curto cai no último dia).

## 6. Cobranças

- Uma cobrança **consolidada por cliente por mês**, somando as assinaturas ativas.
  Item `por unidade` entra por quantidade de entregas do mês (zero entregas = não cobra,
  salvo cobrança fixa). Item `único` gera **cobrança avulsa** imediata.
- Geração **automática** via `cron/gerar_cobrancas.php` (janela de 7 dias antes do vencimento,
  idempotente, com lock). Também gerável manualmente para teste.
- Status: `aberta`, `em_analise` (comprovante aguardando confirmação), `paga`, `cancelada`.
  Cobrança que zera é **deletada** automaticamente.
- Cada cobrança mostra instruções de pagamento (Zelle e-mail/QR, link Wise) e botões
  **💬 WhatsApp**, **✉ Email** e **📄 Recibo**.

## 7. Recebimento e conciliação

- **Cliente**: paga por **cartão (Dite Gateway)** — redirecionado, e o webhook `/webhooks/dite`
  confirma automaticamente (assinatura HMAC-SHA256, idempotente via `dite_eventos`) — ou
  **anexa comprovante** (PDF/JPG/PNG ≤5MB) → status `em_analise` → admin confirma/rejeita.
- **Admin**: registra pagamento direto (com ou sem comprovante); pode remover pagamento.
- **Wise**: `wise_webhook.php` registra créditos recebidos em tempo real (casando por moeda+valor,
  como `pendente` até o admin confirmar em `wise_eventos.php`); alternativa por **upload de CSV**
  (`wise_sync.php`). Pagamento por gateway/webhook validado entra já confirmado.

## 8. Folha de pagamento da equipe

- Funcionário **sempre recebe em USD** via Wise (WiseTag obrigatório).
- Valor por par **(funcionário × item)** em `func_servico_pagamento`.
- Quando a cobrança fica `paga`, os valores devidos entram na **fila de pagamentos pendentes**
  (`pagamentos_funcionarios.php`), consolidada **por funcionário** (1 pagamento por funcionário).
- Ao marcar como pago: registra o pagamento, gera **comprovante** (HTML print-to-PDF) e envia
  email ao funcionário. Funcionário acompanha "a receber" e histórico em `meus_pagamentos.php`.

## 9. Distribuição de lucro

- **Lucro do mês** = receita (por moeda) − despesas (por moeda) − pagamentos à equipe (USD).
- Dividido em **quotas iguais**: N sócios (sadmin/admin) + **1 quota "Empresa"**.
- Visão por moeda + **consolidado em US$** (via cotação). Registro de pagamento por sócio
  (`pagamentos_socio`) com trava de quota disponível. Simulador de divisão interativo.

## 10. Despesas

- Categorizadas (ferramentas/software, hospedagem, marketing, etc.), moeda BRL/USD/EUR,
  recorrência `única`/`mensal`/`anual`, com janela de vigência. Entram no cálculo de lucro do mês.

## 11. Entregas

- Funcionário marca entregas na **agenda** (`agenda.php`) em 4 modos conforme o item:
  **calendário** (pacotes POSTAGEM, checkbox por dia), **tally** (criativos por unidade),
  **único** (1 botão "entregue"), **info** (Meta/Google ADS — só contexto, sem ação). AJAX, sem reload.
- **Cliente** vê o progresso em `entregas.php` (read-only). Admin acompanha em `agenda_geral.php`.
- **Duplas** (`trabalha_com_id`): funcionários compartilham a agenda; pagamento vai ao principal.
- **Capacidade** declarada por categoria vs. ocupação real (`capacidade.php`); alerta de POSTAGEM
  não marcada (`alertas.php`, cron Qua/Sex).

## 12. Comunicação

- **Régua de cobrança** configurável (etapas antes/depois do vencimento) + **templates** de
  mensagem (email/WhatsApp) unificados em "Comunicação" (`regua.php`; `templates.php` redireciona).
- Email automático via SMTP; WhatsApp por `wa.me` (manual-assistido — gera tarefa na agenda do admin).
- Silenciar/reativar lembretes por cobrança. Variáveis nos templates (`{nome_cliente}`, `{valor}`, etc.).

## 13. Plataforma e operação

- **PWA** instalável (manifest + service worker) e **push via OneSignal**.
- **Busca global** (Ctrl+K) e **centro de notificações** no header (ações pendentes por persona).
- **Export CSV** de cobranças/despesas/distribuição/clientes/funcionários/entregas.
- **Backup automático** (PHP puro + gzip, rotação 14 dias) e **limpeza mensal** de logs.
- **Auditoria** de ações com IP; **matriz de acesso** imprimível; **ajuda** por persona.

## 14. Segurança

- Login email+senha (bcrypt), regeneração de sessão, **session nonce** (reset de senha invalida
  sessões em outros dispositivos), cookie HTTPOnly + SameSite=Lax.
- **2FA (TOTP + backup codes)** como meio de **recuperação** (não é exigido no login normal).
- Reset de senha por email (token de uso único, expira em 1h).
- CSRF em todo POST; `e()` no output; prepared statements; `.htaccess` (HTTPS, headers, bloqueio de arquivos).
- Trava contra rebaixar/apagar o último sadmin e contra auto-exclusão.
- **Lacunas conhecidas**: sem rate limiting/CAPTCHA no login; sem criptografia em repouso de PII (CPF/WiseTag).

## 15. Modelo de dados (visão)

Núcleo: `usuarios`, `clientes`, `itens_catalogo` (+`itens_pacote_composicao`), `assinaturas`,
`cobrancas` (+`cobranca_itens`), `pagamentos_cliente`. Folha/sócios: `func_servico_pagamento`,
`pagamentos_funcionario` (+`_itens`), `pagamentos_socio`, `despesas`. Operação: `entregas`,
`capacidade_funcionario`. Comunicação: `templates_mensagem`, `regua_etapas` (+`regua_eventos`).
Integrações: `wise_eventos`, `dite_eventos`, `configuracoes`. Onboarding/segurança: `convites`,
`senha_resets`, `totp_backup_codes`. Auditoria: `audit_log`. Ver `db/schema.sql` + migrations.

## 16. Fora do escopo / próximos passos

- Cobrança de assinaturas via gateway (recorrência de cartão) — hoje o gateway trata pagamento avulso.
- Pagamento automático à equipe via API da Wise (hoje: transferência manual + conciliação).
- Relatórios financeiros mais ricos (comparativo entre meses, gráficos de evolução).
- Cobertura de testes automatizados (hoje o roteiro é manual — ver `TESTING.md`).
