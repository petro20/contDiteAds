# Ícones de redes sociais na Agenda — design

**Data:** 2026-08-21
**Tela:** `agenda.php` (marcação) e `entregas.php` (visão read-only do cliente)
**Status:** proposto

## Objetivo

Hoje, no calendário da Agenda (modo `calendar`, assinaturas com `e_pacote=1`), marcar
um dia só pinta a célula de verde. Queremos registrar **em quais redes sociais** o
conteúdo daquele dia foi postado, com ícones coloridos fáceis de identificar de relance.

## Fluxo (decidido com o usuário)

1. Cada card de assinatura (calendário) tem uma **barra de ícones das redes** no topo.
2. O usuário **clica nos ícones** para selecioná-los (liga/desliga; estado só no navegador,
   com destaque visual). Isso é a "paleta ativa".
3. O usuário **clica num dia** → o dia é marcado (verde) **e recebe os ícones que estão
   selecionados na paleta naquele momento**. Os ícones aparecem pequenos na célula do dia.
4. Clicar num **dia já marcado** → **desmarca** (limpa o dia por completo). Para trocar as
   redes de um dia: desmarca e marca de novo com a paleta nova.
5. Paleta vazia + clicar num dia → marca o dia verde **sem** ícones (comportamento atual,
   mantém compatibilidade).

## Redes suportadas

Seis redes, definidas num único lugar (`lib/entregas.php`), cada uma com slug, nome e
cor da marca (para identificação visual imediata):

| slug      | nome       | cor / visual                    |
|-----------|------------|---------------------------------|
| `ig`      | Instagram  | gradiente rosa→roxo→laranja     |
| `fb`      | Facebook   | azul `#1877F2`                  |
| `tiktok`  | TikTok     | preto com detalhe ciano/rosa    |
| `youtube` | YouTube    | vermelho `#FF0000`              |
| `linkedin`| LinkedIn   | azul `#0A66C2`                  |
| `x`       | X (Twitter)| preto `#000`                    |

Nomes são marcas → **não** entram em `t()`. Ícones renderizados como SVG inline (não
dependem de rede externa; PWA offline continua funcionando) com a cor da marca.

## Dados

Migration aditiva **`db/migration_024_entregas_redes.sql`**:

```sql
ALTER TABLE entregas ADD COLUMN redes VARCHAR(60) NULL AFTER data_marcada;
```

- Um dia marcado continua sendo **uma linha** em `entregas`; a coluna `redes` guarda os
  slugs separados por vírgula na ordem canônica, ex.: `ig,fb,tiktok`.
- Leitura protegida com `db_coluna_existe($db,'entregas','redes')` — o código pode subir
  **antes** da migration rodar; se a coluna não existir, trata como "sem redes".
- Migration roda manual no phpMyAdmin (não é automática — convenção do projeto).

## Componentes / mudanças

### `lib/entregas.php`
- **`REDES_SOCIAIS`**: array canônico slug → `['nome'=>..., 'cor'=>..., 'svg'=>...]`.
- **`entregas_redes_normaliza(string $csv): string`**: recebe o CSV enviado pelo cliente,
  mantém só slugs válidos, na ordem canônica, sem duplicar → CSV limpo (defesa contra
  entrada arbitrária, já que vem do POST).
- **`entregas_redes_svg(string $slug): string`**: devolve o `<svg>` inline da rede.
- **`entregas_toggle_dia(...)`** ganha parâmetro `string $redes = ''`. No INSERT grava
  `redes` normalizado (só se a coluna existir). O DELETE (desmarcar) segue igual.
- **`entregas_do_mes(...)`**: passa a selecionar `redes` também (SELECT condicional à
  existência da coluna, senão devolve `redes => null`).

### `agenda.php`
- Handler POST `op=toggle_dia`: lê `$_POST['redes']`, normaliza e repassa a
  `entregas_toggle_dia`. Resposta JSON passa a incluir `redes` do dia recém-marcado
  (para o JS renderizar os ícones sem reload).
- Render do calendário:
  - **Barra de paleta** acima da tabela: 6 botões-ícone (`type=button`), com
    `data-rede="<slug>"`. Estado ativo via classe CSS. `data-assin` para escopo por card.
  - Cada célula de dia marcado renderiza os ícones (12–14px) numa fileira sob o número,
    a partir do CSV em `redes`.
  - O `<form>`/botão do dia ganha um campo oculto `redes` preenchido pelo JS no submit
    a partir da paleta ativa daquele card.

### JS (bloco no fim de `agenda.php`)
- Clique num ícone da paleta → alterna classe ativa; persiste a seleção em
  `localStorage` por assinatura (`chave: cont_paleta_<assin>`), restaurada no load.
- No submit do dia (que já é interceptado pra AJAX), preenche `redes` com os slugs ativos.
- Na resposta AJAX: se `action==='added'`, injeta os `<svg>` dos ícones na célula; se
  `removed`, limpa. Reaproveita `applyDay()` existente.

### `entregas.php` (cliente, read-only)
- `entregas_do_mes` já traz `redes`; a célula do dia marcado renderiza os mesmos ícones
  pequenos (sem paleta, sem clique). Só exibição.

### i18n
- Só rótulos de interface entram em `t()` (ex.: `t('Redes')`, tooltip
  `t('Selecione as redes e clique no dia')`). Acrescentar as chaves em `lang/en.php` e
  `lang/es.php`.

### CSS
- Regras da paleta e dos ícones do dia vão pro `style.css` (`.paleta-redes`,
  `.rede-btn`, `.rede-btn.ativa`, `.dia-redes`, `.dia-redes svg`), seguindo o padrão de
  mover estilos reutilizados pro CSS (como foi feito com `.cob-row`).

## Fora de escopo (YAGNI)
- Publicação/integração real com as redes; horário do post; métricas/engajamento.
- Escolher redes por dia via mini-modal (a paleta ativa já resolve).
- Redes fixas por assinatura (o usuário preferiu paleta por dia).

## Compatibilidade / riscos
- Dias já marcados antes da migration ficam com `redes = NULL` → renderizam verdes sem
  ícones. Nenhum dado perdido.
- Se a migration não rodar em produção, `db_coluna_existe` evita erro; feature fica
  invisível até rodar (grava sem redes). Igual ao padrão dos avulsos/desconto.
- Entrada `redes` vem do POST → sempre normalizada no servidor (whitelist de slugs).
- `csrf_check()` já cobre o POST; nada muda na autorização.
```
