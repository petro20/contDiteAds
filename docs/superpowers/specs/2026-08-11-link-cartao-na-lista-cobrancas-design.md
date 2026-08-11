# Botão de link de pagamento (cartão) na lista de cobranças

Data: 2026-08-11
Status: ✅ IMPLEMENTADO em 2026-08-11 (commit fc2c7bf). O CSS/JS ficaram **inline** em
`cobrancas.php` (não no `style.css`) pra não afetar o `.list-card` das outras 16 telas nem
cair no cache do service worker.
Relacionado: `2026-08-11-verificar-pagamento-cartao-baixa-design.md` — a zona de ação
`.lc-actions` desta linha **também** hospeda o botão **🔄 Verificar pagamento** daquele
spec. As duas features compartilham a query ampliada e o layout da linha; a coluna
`c.dite_payment_id` (necessária ao botão Verificar) entra na mesma SELECT.

## Problema

Hoje o admin gera o link de pagamento por cartão (Dite Gateway) **um por um, dentro do
detalhe de cada cobrança** (`cobrancas.php?id=X`). Para mandar o link de vários clientes
é preciso abrir cobrança por cobrança. Queremos o mesmo botão **direto na lista de
cobranças**, um por linha, sem entrar no detalhe.

## O que já existe (não mexer)

- `op=dite_gerar_link` em `cobrancas.php` (POST): chama o gateway, salva
  `dite_pay_url`, `dite_payment_id`, `dite_link_valor`, `dite_link_em` na cobrança e
  registra `audit_log('cobranca.dite_link_gerado', ...)`. Hoje redireciona para o
  **detalhe** (`?id=X`).
- `lib/dite.php` → `dite_habilitado()`, `dite_criar_pagamento()`.
- migration 023 criou as colunas `dite_pay_url`, `dite_payment_id`, `dite_link_valor`,
  `dite_link_em` em `cobrancas`.
- A tela de detalhe já mostra o link salvo, o aviso de "link defasado" (saldo mudou) e
  um botão de copiar (`copiarDiteLink`).

## Escopo

Adicionar um botão de ação **por linha** na listagem (`acao=lista`) de `cobrancas.php`.

**Fora de escopo (YAGNI):** geração em massa ("gerar todos"), AJAX/sem-reload.

## Desenho

### 1. Reestruturar a linha da lista

Hoje cada cobrança é um `<a class="list-card" href="?id=X">` clicável inteiro — não é
possível aninhar `<form>`/`<button>` dentro de um `<a>` (HTML inválido). A linha passa a
ser um contêiner com duas zonas:

```
<div class="list-card">
  <a class="lc-main" href="?id=X"> …info… …valor… </a>
  <div class="lc-actions"> …botão de link (condicional)… </div>
</div>
```

Ajustar o CSS de `.list-card` para acomodar a zona de ação (flex, alinhamento à
direita), seguindo o tema escuro atual. `.lc-main` mantém a aparência/hover do card
clicável de hoje.

### 2. Ampliar a query da lista

À SELECT que monta `$cobr` (hoje `id, competencia_mes, valor_total, moeda, vencimento,
status, nome_empresa`) acrescentar:

- **saldo pago**: subselect `COALESCE((SELECT SUM(valor_pago) FROM pagamentos_cliente p
  WHERE p.cobranca_id = c.id), 0) AS pago`. O saldo em aberto = `valor_total - pago`.
- **colunas de link** `c.dite_pay_url`, `c.dite_link_valor` — **somente** se
  `db_coluna_existe($db, 'cobrancas', 'dite_pay_url')` for verdadeiro. As colunas entram
  na lista de campos condicionalmente para não quebrar bancos sem a migration 023.

### 3. Estados do botão (por linha)

O botão só é renderizado quando **todas** as condições valem:

- `is_admin()`
- `dite_habilitado()`
- `db_coluna_existe($db, 'cobrancas', 'dite_pay_url')`
- status ∈ (`aberta`, `em_analise`)
- saldo em aberto > 0

Havendo essas condições, o estado depende do link salvo (`link = dite_pay_url`,
`valor_link = dite_link_valor`, `defasado = link != '' && abs(valor_link - saldo) > 0.01`):

| Situação | Botão |
|---|---|
| sem link (`link == ''`) | **🔗 Gerar link** — form POST |
| link salvo e casa com saldo (`!defasado`) | **📋 Copiar** — clipboard, sem reload |
| link salvo mas defasado | **♻ Novo link** — form POST |

Cobranças pagas, canceladas ou com saldo 0 → nenhum botão.

### 4. Reuso da ação + redirect condicional

Reusar `op=dite_gerar_link`. O form de cada linha inclui, além do `csrf`, `op` e `id`:

- `<input type="hidden" name="back" value="lista">`
- os filtros atuais da lista para preservação: `status` e `cliente_id` (valores de
  `$_GET['status']`/`$_GET['cliente_id']` correntes — os nomes reais usados por
  `$f_status`/`$f_cliente` na montagem da lista).

Na op, ao final (sucesso e erro), verificar `$_POST['back']`:

- `back === 'lista'` → `Location: cobrancas.php?acao=lista&status=<...>&cliente_id=<...>&ok=dite_link`
  (ou `&err=...`), preservando os filtros.
- caso contrário → comportamento atual (volta ao detalhe `?id=X`).

O flash de sucesso na lista: reusar/adaptar a mensagem "Link de pagamento gerado. Copie e
envie ao cliente." Após o reload, a linha correspondente já mostra o botão **📋 Copiar**
(pois `dite_pay_url` agora está preenchido).

### 5. Copiar (JS mínimo)

Função `copiarLinkLista(btn)` no rodapé da listagem: lê `btn.dataset.link`, usa
`navigator.clipboard.writeText` com fallback para seleção de um `<input>`/`textarea`
temporário. Feedback: troca o texto do botão para "Link copiado!" por ~1,5s e volta.
Não recarrega a página.

### 6. i18n

Reusar chaves existentes onde houver ("Gerar link de pagamento", "Link de pagamento
gerado. Copie e envie ao cliente."). Adicionar as novas chaves em `lang/en.php` e
`lang/es.php`:

- "Gerar link" / "Novo link" / "Copiar" / "Link copiado!"

(Chave = texto PT, conforme convenção do projeto.)

### 7. Segurança

Sem superfície nova: a op já faz `csrf_check()` (topo do handler POST) e `is_admin()`.
`dite_habilitado()` e `db_coluna_existe` blindam a renderização. Cada clique gera **um**
link (uma chamada real ao gateway) — sem lote.

## Arquivos afetados

- `cobrancas.php` — query da lista (§2), redirect condicional na op `dite_gerar_link`
  (§4), markup da linha + botão (§1, §3), JS de copiar (§5).
- CSS do tema (onde `.list-card` é definido) — zona de ação (§1).
- `lang/en.php`, `lang/es.php` — novas strings (§6).

## Critérios de aceite

1. Na lista, admin vê **🔗 Gerar link** nas cobranças abertas/em análise com saldo > 0 e
   sem link salvo.
2. Clicar gera o link no gateway, salva na cobrança, recarrega a lista **mantendo os
   filtros** e a linha passa a mostrar **📋 Copiar**.
3. Copiar coloca a URL na área de transferência sem recarregar, com feedback visual.
4. Quando o saldo muda depois de gerado, a linha mostra **♻ Novo link**.
5. O fluxo do **detalhe** da cobrança continua igual (redirect volta ao detalhe).
6. Funcionário e cliente **não** veem o botão; nada aparece se o gateway estiver
   desligado ou a migration 023 não aplicada.
7. Pagas, canceladas e saldo 0 não mostram botão.
