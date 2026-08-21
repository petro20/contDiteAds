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
   ⚠️ **O repo PRECISA ficar PÚBLICO** — a Hostinger puxa por HTTPS sem autenticação. Se virar privado, o deploy quebra silenciosamente (painel diz "sucesso" mas nada sobe). Não há segredo no repo, então público é seguro.
3. **Migrations NÃO são automáticas**: ao subir uma `db/migration_*.sql`, rode o `.sql` manualmente no phpMyAdmin.
4. Service worker NÃO cacheia `.php` — se uma página "não mudou", o suspeito é deploy/migration, não cache.

## Segredos
`.env` no `public_html` (não versionado): `DB_*`, `SMTP_*`, `ONESIGNAL_REST_KEY`, `DITE_API_KEY`,
`DITE_WEBHOOK_SECRET`. **Nunca** commitar segredo nem colar no chat. Chave da IA (simulador) e instruções de
pagamento ficam na tabela `configuracoes`, não no `.env`.

## Estado atual
Em produção desde 2026-05. Implementado: todo o fluxo comercial/financeiro, entregas, régua,
2FA-recuperação, backups, auditoria, PWA+push, integração Dite Gateway (cartão) e Wise (webhook+CSV),
e **i18n completo PT/EN/ES** (interface + relatórios/PDFs). Item **avulso** de cobrança pode ter
**funcionário responsável + valor USD** próprio, entrando na fila de pagamento da equipe igual às
assinaturas (migration 022). O Dite Gateway gera **link de pagamento por cobrança** (copiável pelo
admin, migration 023). O **Painel** tem aba **Caixa** — a conta do lucro do mês aberta (entrou × saiu),
por moeda e em US$. A **distribuição de lucro** (`lib/distribuicao.php`) usa **saldo acumulado** não
distribuído de meses anteriores (com sinal, piso maio/2026), trata **despesas como custo em USD**
(gastos em real/euro convertidos, descontam só do lado USD) e mostra **"falta distribuir"** (tira o já
pago no mês). `schema.sql` está levemente defasado das migrations 018/020/021/022/023 —
para instalar do zero, rode as migrations em ordem.

**Correção crítica (2026-08-11) — baixa automática por webhook:** os webhooks `webhooks/dite.php`
(cartão) e `wise_webhook.php` gravavam o pagamento com `registrado_por=0`, violando a FK
`fk_pagcli_user` (erro 1452) — **nenhum** pagamento por cartão/Wise baixava sozinho. Corrigido com o
helper **`autor_sistema($db)`** (`lib/pagamentos.php`), que escolhe um admin/sadmin ativo; os dois
webhooks o usam. Além disso: a **migration_019 (`dite_eventos`) nunca tinha sido aplicada em produção**
— foi aplicada neste dia (rodaram a 023 mas pularam a 019). E o webhook Dite passou a usar
`vpag = min(amount, saldo)` pra não duplicar baixa em reenvio.

**Features de cartão (2026-08-11, no ar):** (1) **link de cartão na lista** de cobranças — botão por linha
🔗 Gerar / 📋 Copiar / ♻ Novo link (reusa `op=dite_gerar_link` com `back=lista`); (2) **Verificar pagamento** —
`dite_consultar_pagamento()` faz `GET /api/v1/payments/{id}` (endpoint REST confirmado), `op=dite_verificar_pagamento`
dá baixa se pago; botão 🔄 no detalhe e na lista; (3) **recebimento parcial mais visível** — o form de registrar
pagamento saiu do `<details>` escondido pra um card "💵 Registrar recebimento", e o topo mostra selo
**"PARCIAL · falta $X"** quando 0<pago<total (parcial mantém a cobrança **aberta** até quitar; só vira `paga`
quando a soma alcança o total — lógica de status inalterada).

**Lista de cobranças reorganizada (2026-08-18, no ar):** a tela `cobrancas.php` deixou de ser uma lista
plana. Agora as cobranças caem em três baldes (partição em PHP, render por um closure `$render_card`
reusado): **⚠ Atrasados** (`aberta` + vencida, vermelho, aberto por padrão, mais vencidas primeiro) →
**🟡 Abertos** (`aberta`/`em_analise` ainda no prazo, âmbar, aberto) → **blocos por mês** recolhíveis
(`<details class="cob-mes">`, nome do mês localizado + contagem, **recolhidos**) com o histórico
**pago/cancelado**. Ao pagar, a cobrança sai de Atrasados/Abertos e cai no mês pertinente (é só
consequência do status, sem lógica extra). A **query** ordena por `competencia_mes DESC` e traz
`SUM(valor_pago) AS pago` por cobrança; a lista mostra **"Pago: X · Falta: Y"** no card quando é parcial.
Layout em **2 colunas** (`.cob-grid`, 1 coluna no mobile ≤640px); o **menu do dashboard** também
(`.menu-grid` em `dashboard.php`, 1 coluna ≤560px). As **ações de cartão** viraram **botões-ícone**
(♻/🔗 link, 🔄 verificar, 📋 copiar) no **canto superior direito do card**, com `title`/`aria-label`
(tooltip no hover) — as regras `.cob-row`/`.lc-actions` foram pro `style.css`. Chaves i18n novas:
`Atrasados`, `Abertos`, `Falta:`. ⚠ Dívida técnica: `var(--c-attention)` (usado no detalhe da cobrança,
`cobrancas.php` ~L790) **não existe** no CSS — trocar por `var(--c-warning)`.

**Ícones de redes sociais na Agenda (2026-08-21, no ar):** o calendário (`agenda.php`, modo `calendar`,
`e_pacote=1`) ganhou uma **paleta de 8 redes** por card (IG, FB, TikTok, YouTube, LinkedIn, X, **Meta
Business Suite** e **Google Ads**) — SVGs inline com cor da marca em `lib/entregas.php`
(`entregas_redes_defs()`). Fluxo: **clica nos ícones pra selecionar** (estado só no navegador, salvo em
`localStorage` por assinatura `cont_paleta_<id>`) → **clica no dia** e ele carimba as redes ativas;
**clicar num dia marcado desmarca** (limpa). Persistência: coluna aditiva **`entregas.redes VARCHAR(60)`**
(CSV de slugs; comporta os 8) — **migration_024, já aplicada em produção**; leitura/escrita protegidas por
`entregas_coluna_existe()` (helper local, não acopla a `lib/cobrancas`); `redes` do POST sempre
normalizada no servidor (whitelist via `entregas_redes_normaliza()`). `entregas_toggle_dia()` ganhou 5º
parâmetro `$redes`; o AJAX de `op=toggle_dia` envia as redes e a resposta as devolve pro JS renderizar sem
reload. Os ícones (11px) aparecem read-only pro cliente em `entregas.php` também. CSS (`.paleta-redes`,
`.rede-btn`, `.dia-btn`, `.dia-redes`) no `style.css`; o botão do dia saiu de estilo inline pra classe
`.dia-btn`. Chaves i18n novas: `Redes:`, `Selecione as redes e clique no dia`. Dias marcados com a paleta
vazia ficam verdes sem ícones (compatível com o comportamento antigo).

Auto-deploy da Hostinger confirmado ativo (push no `master` publica sozinho). Specs (com status de
implementação): `docs/superpowers/specs/`.
