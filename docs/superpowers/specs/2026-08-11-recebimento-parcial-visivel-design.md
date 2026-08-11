# Recebimento parcial mais visível

Data: 2026-08-11
Status: aprovado (design), aguardando implementação

## Problema

O sistema **já suporta** recebimento parcial: no detalhe da cobrança, o form de registrar
pagamento aceita um valor menor que o total, e `atualiza_status_cobranca()` mantém a
cobrança **aberta** (com saldo) enquanto a soma dos pagamentos não alcança o valor total —
só marca `paga` quando quita. O problema é de **visibilidade**: esse form está escondido
dentro de um `<details>` ("Pagamento detalhado (parcial, com comprovante, etc.)") e o
estado "recebi parte, falta o resto" não fica explícito.

## O que já existe (não mexer na lógica)

- `registrar_pagamento_admin` (op em `cobrancas.php`) → `registrar_pagamento_cliente()`
  aceita qualquer valor (parcial ou total).
- `atualiza_status_cobranca()` (`lib/pagamentos.php`): marca `paga` só quando
  `pago_confirmado >= valor_total`; parcial → `aberta` (ou `em_analise` se pendente).
- Header do detalhe já mostra `Pago: X · Saldo: Y` quando `pago > 0` e status ≠ paga
  (`cobrancas.php` ~583).
- Botão `✓ Marcar como paga` (op `marcar_paga`) continua para quitar tudo de uma vez.

**Requisito reforçado pelo usuário:** parcial **nunca** dá a cobrança como paga — ela fica
em aberto, mostrando quanto falta, até receber o valor todo. (Já é o comportamento; não
alterar.)

## Escopo

Melhorar a **visibilidade** do recebimento parcial na tela de detalhe (`cobrancas.php`).
Sem mudar a lógica de status.

**Fora de escopo:** parcial via cartão/gateway (o link do Dite cobra o saldo total);
cliente registrar parcial. (Podem virar specs próprios depois.)

## Desenho

### 1. Card de recebimento sempre visível (admin)

Tirar o form de registro de pagamento de dentro do `<details>` escondido e apresentá-lo
como um card visível **"💵 Registrar recebimento"** (só admin, cobrança aberta/em análise).
Mantém os campos atuais: Valor (default = saldo, editável), Data, Método, Observação,
Comprovante. Acrescentar uma linha de ajuda (`.hint`/`.muted`):

> "Pode registrar um valor **parcial** — a cobrança continua em aberto e mostrando o saldo
> até receber tudo."

O botão `✓ Marcar como paga` permanece (quita o saldo inteiro de uma vez).

### 2. Selo "PARCIAL · falta $X"

No bloco de status do topo (`cobrancas.php` ~571-585), quando `0 < pago < valor_total` e o
status não for `paga`/`cancelada`, exibir o selo como **"PARCIAL · falta \<saldo\>"** (com
`money_fmt($saldo, moeda)`), em vez de só "VENCIDA"/"PENDENTE". A linha `Pago / Saldo`
abaixo continua. Um cliente que pagou parte e está vencido pode manter também a marca de
vencido — decisão de exibição: priorizar "PARCIAL · falta $X" (mais informativo) e manter a
linha Pago/Saldo.

### 3. (Opcional) Selo "parcial" na lista

Na listagem (`acao=lista`), marcar as cobranças com recebimento parcial (0 < pago < total)
com um selo discreto "parcial", pra ver de fora quais já receberam parte. Exige trazer o
`pago`/saldo na query da lista — a **mesma** ampliação de query prevista no spec
`2026-08-11-link-cartao-na-lista-cobrancas-design.md`. Sincronizar com aquele spec se as
duas features forem feitas juntas.

### 4. i18n

Reusar chaves existentes ("Registrar pagamento", "Valor", "Data", "Método",
"Observação", "Comprovante (opcional)"). Novas chaves em `lang/en.php` e `lang/es.php`:

- "Registrar recebimento"
- "Pode registrar um valor parcial — a cobrança continua em aberto e mostrando o saldo até receber tudo."
- "PARCIAL · falta" (ou compor: `t('PARCIAL · falta') . ' ' . money_fmt(...)`)

## Arquivos afetados

- `cobrancas.php` — detalhe: promover o form (§1), selo de status parcial (§2), e
  opcionalmente o selo na lista (§3).
- `lang/en.php`, `lang/es.php` — novas strings (§4).

## Critérios de aceite

1. No detalhe (admin, cobrança aberta), o form de registrar recebimento aparece **sem
   precisar abrir** um `<details>`, com a dica sobre valor parcial.
2. Registrar um valor **menor** que o saldo mantém a cobrança **aberta**, com o saldo
   restante atualizado — **não** marca como paga.
3. Registrar o valor que completa o total marca como **paga** (comportamento atual).
4. Havendo recebimento parcial, o topo mostra **"PARCIAL · falta $X"**.
5. Nenhuma mudança na lógica de `atualiza_status_cobranca()`.
6. (Se §3 incluído) a lista destaca cobranças parciais.
