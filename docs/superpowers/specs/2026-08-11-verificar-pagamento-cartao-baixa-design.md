# Verificar pagamento no gateway e dar baixa (fallback do webhook)

Data: 2026-08-11
Status: ✅ IMPLEMENTADO em 2026-08-11 (commit 7f69a32). Endpoint `GET /api/v1/payments/{id}`
**confirmado funcionando**. Desvio do design: a idempotência ficou **apenas pelo saldo**
(registra só o saldo em aberto; saldo 0 → nada a fazer) — a trava sintética
`manual_<payment_id>` em `dite_eventos` foi dispensada, porque o `min(amount, saldo)` já
aplicado no webhook cobre a colisão webhook × verificação manual.
Relacionado: `2026-08-11-link-cartao-na-lista-cobrancas-design.md` (compartilha a zona de
ação `.lc-actions` na lista).

## Problema

A baixa de pagamento por cartão é automática via webhook (`webhooks/dite.php`, evento
`payment.paid`). Quando o webhook **não chega** (ou demora), a cobrança fica aberta mesmo
o cliente tendo pago. Falta um caminho manual para o admin **conferir no gateway** se um
link foi pago e **dar baixa na hora**.

## O que já existe

- `lib/dite.php`: `dite_habilitado()`, `dite_api_post()`, `dite_criar_pagamento()`. **Não
  há** consulta de status (só criação).
- `webhooks/dite.php`: no `payment.paid`, calcula `saldo = valor_total - pago` e registra
  `vpag = amount > 0 ? amount : saldo` via `registrar_pagamento_cliente(...)`. Idempotência
  só por `event_id` (tabela `dite_eventos`, coluna `event_id` UNIQUE).
- `lib/pagamentos.php`: `registrar_pagamento_cliente()` insere o pagamento e chama
  `atualiza_status_cobranca()` (que usa `SELECT ... FOR UPDATE`, seguro contra corrida
  webhook × admin).
- migration 023: colunas `dite_pay_url`, `dite_payment_id`, `dite_link_valor`,
  `dite_link_em` em `cobrancas`.

## Escopo

Botão **🔄 Verificar pagamento** que consulta o gateway e dá baixa se estiver pago.
Aparece no **detalhe** (seção "Link de pagamento (cartão)") e na **lista** de cobranças.

## Decisão de API

O gateway expõe consulta em `GET /api/v1/payments/{payment_id}` (padrão REST, confirmado
com o dono do gateway).

## Desenho

### 1. Consulta no gateway — `lib/dite.php`

- `dite_api_get(string $path): array` — espelho de `dite_api_post`, método GET, mesmos
  headers (`X-Api-Key`), mesmo tratamento de erro/`data`.
- `dite_consultar_pagamento(string $paymentId): array` — chama
  `GET /api/v1/payments/{paymentId}`. Normaliza o status: considera **pago** quando o campo
  de status da resposta ∈ {`paid`, `pago`, `approved`, `completed`, `succeeded`} (case
  -insensitive). Retorna:
  ```php
  ['paid' => bool, 'status' => string, 'amount' => float, 'currency' => string, 'raw' => array]
  ```

### 2. Op `dite_verificar_pagamento` — `cobrancas.php` (POST)

Guardas: `csrf_check()`, `is_admin()`, `dite_habilitado()`,
`db_coluna_existe($db,'cobrancas','dite_payment_id')`.

Fluxo:

1. Carrega a cobrança pelo `id`. Se não tem `dite_payment_id` → redirect com
   `err=dite_sem_link` ("gere o link primeiro").
2. `dite_consultar_pagamento($cob['dite_payment_id'])` dentro de try/catch. Falha de
   conexão → `err=dite_erro`.
3. **Não pago** → redirect com aviso informando o status retornado
   (`err=dite_nao_pago` + status via querystring, exibido no flash).
4. **Pago** → dar baixa de forma idempotente (ver §3).

Redirect respeita `back` (igual à op `dite_gerar_link`): `back=lista` volta pra lista com
filtros preservados (`status`, `cliente_id`); senão volta ao detalhe `?id=X`.

### 3. Idempotência da baixa (dois sentidos)

**a) Trava por `payment_id` (clique repetido / verificar após webhook):**
Usa o UNIQUE de `dite_eventos.event_id` como trava determinística.

1. `INSERT INTO dite_eventos (event_id, event_type, status, cobranca_id, valor, moeda)
   VALUES ('manual_<payment_id>', 'payment.paid.manual', 'verificando', <cob_id>, ...)`.
   Se falhar por **duplicate key** → já foi processado antes → redirect
   "já estava baixado", sem novo pagamento.
2. Inseriu com sucesso → calcula `saldo = valor_total - SUM(pagamentos_cliente confirmados)`.
   - `saldo > 0` → `registrar_pagamento_cliente($db, $cob_id, $saldo, hoje, 'Cartão (Dite)',
     'Dite Gateway (verificação manual) · '.$payment_id, null, $me['id'], false)`.
     Depois `UPDATE dite_eventos SET status='pago', pagamento_id=?, valor=? WHERE id=<sintético>`.
     `audit_log('cobranca.dite_verificado_pago', 'cobrancas', $cob_id)`.
   - `saldo <= 0` → `UPDATE dite_eventos SET status='sem_saldo'`; flash "já estava paga".
3. Redirect `ok=dite_pago`.

**b) Webhook chegar depois da verificação manual — correção em `webhooks/dite.php`:**
Trocar `$vpag = $valor > 0 ? $valor : $saldo;` por
`$vpag = min($valor > 0 ? $valor : $saldo, $saldo);`.
Assim o webhook **nunca registra mais que o saldo em aberto**; se a verificação manual já
quitou, `saldo <= 0` → `vpag <= 0` → não registra (status `sem_saldo`). Corrige também um
risco de baixa dupla que já existe hoje (webhook usando `amount` mesmo com saldo 0). Não
afeta pagamentos legítimos: um link é gerado para o saldo, então `amount == saldo` no caso
normal, e `min(amount, saldo) == amount`.

### 4. UI

**Detalhe** (`cobrancas.php?id=X`, seção "🔗 Link de pagamento (cartão)"): quando há
`dite_payment_id` e a cobrança **não** está paga, exibir o botão **🔄 Verificar pagamento**
(form POST, `op=dite_verificar_pagamento`, sem `back`).

**Lista** (`acao=lista`): na zona de ação `.lc-actions` de cada linha (introduzida pelo
spec do link na lista), quando há `dite_payment_id` e a cobrança não está paga, incluir o
mesmo botão com `back=lista` + filtros. A query da lista precisa trazer também
`c.dite_payment_id` (além das colunas já previstas no outro spec), protegida por
`db_coluna_existe`. Layout mobile-first: no máximo 2 botões compactos por linha
(ex.: `[📋 Copiar] [🔄 Verificar]` ou `[♻ Novo] [🔄 Verificar]`).

### 5. i18n

Novas chaves em `lang/en.php` e `lang/es.php` (chave = texto PT):

- "Verificar pagamento"
- "Pagamento confirmado e baixado."
- "Ainda não consta pago no gateway (status: %s)."
- "Gere o link de pagamento primeiro."
- "Esta cobrança já estava paga."

## Arquivos afetados

- `lib/dite.php` — `dite_api_get()`, `dite_consultar_pagamento()`.
- `cobrancas.php` — op `dite_verificar_pagamento`, botão no detalhe e na lista, coluna
  `dite_payment_id` na query da lista.
- `webhooks/dite.php` — `min(amount, saldo)` (1 linha).
- `lang/en.php`, `lang/es.php` — novas strings.

## Critérios de aceite

1. No detalhe, com link gerado e cobrança não paga, o admin vê **🔄 Verificar pagamento**.
2. Clicar consulta `GET /api/v1/payments/{id}`; se pago, registra o saldo como "Cartão
   (Dite)", a cobrança vira **paga** e mostra flash de confirmação.
3. Clicar de novo (ou webhook chegando depois) **não** duplica a baixa — trava por
   `manual_<payment_id>` e `min(amount, saldo)` no webhook.
4. Se não estiver pago, mostra o status atual sem registrar nada.
5. O mesmo botão aparece na lista e, na lista, o redirect preserva os filtros.
6. Funcionário/cliente não veem o botão; nada aparece com gateway desligado, migration 023
   ausente, ou sem `dite_payment_id`.
7. Erro de conexão com o gateway mostra flash de erro e não altera a cobrança.
