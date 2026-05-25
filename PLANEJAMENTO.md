# Planejamento — Sistema interno Dite Ads

Documento de trabalho: você edita, eu leio e codifico.

**Como usar**:
1. Marque a prioridade de cada feature alterando o `[ ]` para `[x]` ou apagando.
2. Preencha os campos em branco e responda as dúvidas pendentes.
3. Adicione novas features no final como blocos extras.
4. Quando estiver pronto, me avisa "implementa X" — eu pego daqui e codo.

Legenda de prioridade: 🔥 urgente · ⭐ próxima · 💭 pensar depois

---

## Contexto do sistema

**O que é**: ferramenta interna de gestão da Dite Ads (agência de marketing
digital multilíngue — PT/EN/ES). Roda em `bus.diteads.com`.

**Quem usa**:
- **Admin** (sócios / gestão): cadastra tudo, vê tudo, gerencia financeiro
- **Funcionário** (equipe da Dite Ads): executa serviços para clientes,
  acompanha próprias remunerações, registra entregas
- **Cliente** (empresa que contratou a Dite Ads): loga para acompanhar
  cobranças, pagamentos e entregas — não paga pelo sistema

**Stack**:
- PHP vanilla (sem framework)
- Hospedagem Hostinger (compartilhada)
- Repo público (sem segredos comitados)

**Idioma**: **100% em português do Brasil (PT-BR)**, sem exceção.
Toda interface, mensagens de erro, emails automáticos, templates do
WhatsApp, conteúdo dos PDFs (recibos, comprovantes), labels de botão,
notificações — tudo em PT-BR. Mesmo que o sistema atenda clientes
estrangeiros em USD ou EUR, a interface e todas as comunicações são
em português. (Multilíngue não é prioridade nesta versão.)

---

## Design system

Identidade visual extraída do logo da Dite Ads Business, adaptada para
um sistema **mobile-first** com **tema escuro** e paleta masculina.

### Princípios

- **Mobile-first sempre**: tudo desenhado para celular primeiro, ponto.
  Desktop é adaptação posterior.
- **Tema escuro**: fundo preto/cinza-escuro, texto claro. Combina com
  o fundo preto do logo, dá identidade marcada e descansa a vista em
  uso prolongado.
- **Paleta masculina e séria**: azul royal e roxo escuro dominam,
  laranja como destaque. **Sem magenta, rosa ou tons pastéis.**
- **Vibrante mas controlado**: cor encode significado (azul = ação
  principal, verde = sucesso, vermelho = erro), não decoração.
- **Touch-friendly**: alvos mínimos 44×44px, sem hover, sem tooltip,
  sem coisas que dependem de mouse.

### Paleta de cores

**Cores de marca** (extraídas do logo):

| Uso | Nome | Hex |
|---|---|---|
| **Primária** — botões principais, links, ações | Azul royal | `#1E40AF` |
| **Primária clara** — hover/pressed | Azul claro | `#3B82F6` |
| **Secundária** — destaques, badges | Roxo escuro | `#5B21B6` |
| **Destaque** — alertas, atenção, notificações | Laranja âmbar | `#F59E0B` |

**Cores semânticas** (convenção UI, não estão no logo):

| Uso | Nome | Hex |
|---|---|---|
| Sucesso — "pago", "concluído", "ativo" | Verde | `#10B981` |
| Erro/perigo — "vencido", "erro", "cancelado" | Vermelho | `#DC2626` |
| Aviso — "atenção", "pendente" | Amarelo | `#EAB308` |
| Info — "neutro", "informacional" | Cinza azulado | `#64748B` |

**Neutros (tema escuro)**:

| Uso | Hex |
|---|---|
| Fundo principal da app | `#0A0A0F` (preto profundo) |
| Fundo de cards/superfícies elevadas | `#16161D` |
| Fundo de inputs/elementos interativos | `#1F1F2A` |
| Borda padrão | `#2A2A36` |
| Texto primário (alta hierarquia) | `#F5F5F7` |
| Texto secundário (descrições) | `#A0A0AB` |
| Texto terciário (placeholders, hints) | `#6B6B7B` |

### Tipografia

- **Fonte sans-serif moderna**: `Inter` (free, hospeda local ou Google
  Fonts) como primeira opção, `system-ui` como fallback.
- **Escala de tamanhos**:
  - `12px` — labels pequenas, metadados
  - `14px` — texto secundário, descrições
  - `16px` — texto padrão (base do sistema)
  - `18px` — títulos de seção
  - `22px` — títulos de tela
  - `28px` — números grandes (valores no painel financeiro)
- **Pesos**:
  - `400` — texto comum
  - `500` — labels, ênfase leve
  - `600` — títulos
  - `700` — números grandes em destaque

### Espaçamento e layout

- **Grid base 4px**: todos os espaçamentos múltiplos de 4 (4, 8, 12,
  16, 20, 24, 32, 48).
- **Padding padrão de tela**: 16px nas laterais.
- **Padding interno de cards**: 16px.
- **Gap entre cards numa lista**: 12px.
- **Bordas arredondadas**:
  - `8px` — botões, inputs, badges
  - `12px` — cards
  - `16px` — modais, bottom sheets

### Componentes principais

**Botão primário** — fundo azul royal, texto branco, 48px de altura,
peso 600.

**Botão secundário** — fundo transparente, borda azul royal 1px, texto
azul royal.

**Botão de perigo** — fundo vermelho, mesmas dimensões do primário,
usado em ações destrutivas (cancelar assinatura, deletar).

**Card** — fundo `#16161D`, borda 1px `#2A2A36`, padding 16px,
borda arredondada 12px.

**Input** — fundo `#1F1F2A`, borda 1px `#2A2A36`, borda foco azul royal,
padding 12px 16px, altura 48px.

**Checkbox** — 24×24px (visual maior pra dedo), borda 2px, fundo azul
quando marcado, ícone check branco.

**Badge de status** — pill 8px arredondado, padding 4px 8px, texto
12px peso 500. Cores conforme semântica (verde pago, vermelho vencido,
laranja pendente, etc.).

**Menu inferior fixo** (navegação principal) — fundo `#16161D`, altura
64px, 4-5 ícones grandes (24px), ícone ativo em azul royal, ícone
inativo em cinza.

**Modal/Bottom sheet** — sobe do bottom do celular, ocupa até 90% da
tela, header com título + botão X, scroll interno se necessário.

### Padrões mobile específicos

- **Listas em vez de tabelas**: dados tabulares viram lista de cards
  verticais. Tabela só em última instância (e nunca com mais de 3
  colunas).
- **Formulários verticais**: 1 campo por linha, sempre. Label acima
  do input.
- **Navegação por bottom tab bar**: 4 ícones principais (Home,
  Cobranças, Agenda, Perfil — adaptar por persona).
- **Pull to refresh**: gesto padrão pra atualizar listas.
- **Swipe actions opcionais**: deslizar card pra esquerda revela
  ação rápida (ex: marcar como pago).
- **Floating Action Button (FAB)** quando faz sentido — ação principal
  da tela num botão circular fixo no canto inferior direito.

### Padrões de comportamento (harmonização entre telas)

Estas convenções valem em **todas as telas** do sistema, para garantir
que o usuário aprenda uma vez e reconheça em qualquer lugar:

**Header (todas as telas têm)**:
- Título da tela à esquerda (peso 600, 22px em telas principais; 18px
  em subtelas)
- Subtítulo contextual abaixo do título quando aplicável (descrição,
  nome do cliente, mês de referência) — 13px peso 400, cinza secundário
- Botão **voltar** (ícone `ti-arrow-left`) no canto superior esquerdo
  em subtelas (não na tela inicial de cada persona)
- Ação contextual à direita: avatar (em telas iniciais), bell de
  notificações, busca, ou menu de 3 pontos
- Fundo `#16161D`, borda inferior 1px `#2A2A36`

**Estrutura de conteúdo (de cima para baixo)**:
1. **Totalizadores/resumo** primeiro (cards de KPI, valor total, status
   geral)
2. **Alertas/destaques** (cards com cor de fundo semântica — laranja
   pra atenção, vermelho pra problema)
3. **Listas detalhadas** depois, agrupadas por categoria com pequenos
   títulos em uppercase 13px cinza
4. **Ação principal** sempre no final ou fixa embaixo, botão azul royal
   grande

**Cards**:
- Fundo `#16161D`, borda 1px `#2A2A36`, radius 12px, padding 14px
- Quando precisa destaque, **borda colorida 1px** (laranja, vermelha,
  verde) sem mudar o fundo
- **Badge de status** sempre no canto superior direito do card,
  formato pill (radius 6px, padding 3-4px), texto 10-11px peso 500,
  uppercase. Cores semânticas:
  - Verde — paga, ativa, concluída, sucesso
  - Laranja — pendente, atenção, vence em breve
  - Vermelho — vencida, cancelada, erro
  - Roxo — informação, neutro
- **Valor monetário** sempre alinhado à direita, peso 700, tamanho
  destacado (15-22px conforme contexto)

**Botões**:
- **Primário** (azul royal `#1E40AF`): ação principal da tela, sempre
  largo (100% de largura), altura 48px, peso 600
- **Secundário** (transparente + borda cinza): ações alternativas
- **Outline colorido semântico** (verde, vermelho, etc.): ações
  específicas — WhatsApp em verde, deletar em vermelho
- Botões em pares dividem espaço 50/50 com gap 8px
- **Ícone à esquerda do texto** quando aplicável, gap 6px

**Formulários**:
- Label acima do input em uppercase pequeno (12px, peso 500, cinza
  `#A0A0AB`)
- Input com fundo `#1F1F2A`, borda 1px `#2A2A36`, radius 8px, altura
  48px, padding 12px
- **Borda azul royal** quando focado/editado pelo usuário
- Hint/descrição abaixo do input (11px cinza)
- Badge "CUSTOMIZADO" quando o valor difere do padrão (pill laranja)
- Ícone embutido no input quando faz sentido (olho pra senha, lupa
  pra busca, chevron pra dropdown)

**Modais e overlays**:
- **Sempre bottom sheet** no mobile: sobem de baixo, ocupam até 90%
  da tela com handle (barra horizontal pequena) no topo
- Fundo escurece o conteúdo atrás (overlay preto 60% opacidade)
- Botão fechar (X) no canto superior direito do sheet
- Conteúdo do sheet segue o mesmo padrão de cards/botões da tela
  principal

**Menu inferior fixo (bottom nav)**:
- Altura 64px, fundo `#16161D`, borda superior 1px `#2A2A36`
- 3-4 ícones por persona, distribuídos igualmente
- Ícone ativo: azul royal `#1E40AF` + label em azul royal peso 500
- Ícone inativo: cinza `#6B6B7B` + label cinza peso 400
- **Personas com menus diferentes**:
  - **Admin**: Painel, Clientes, Catálogo, Perfil
  - **Funcionário**: Agenda, Clientes, Pagamentos, Perfil
  - **Cliente**: Cobranças, Entregas, Perfil (apenas 3 — uso menos
    frequente)

**Notificações visuais**:
- Badge numérico no ícone de bell (canto superior direito) em vermelho
  `#DC2626`, número branco peso 600
- Dot colorido sem número para indicar "tem novidade" (sem contar
  quantas)

### Dúvidas / decisões pendentes

- **Fonte definitiva**: Inter é a sugestão. Quer outra? (Geist,
  Manrope, Plus Jakarta também são boas alternativas free.)
- **Modo claro**: precisa ter? Sistema interno usado em vários
  contextos pode precisar de tema claro (uso ao ar livre, por exemplo).
  Decisão atual: só escuro por enquanto, modo claro vira feature futura
  se houver demanda.
- **Logo**: usar o logo cheio (com texto "Dite Ads Business") em todo
  lugar, ou só o símbolo (sem texto) em espaços pequenos como header
  do app?
- **Idioma do design**: como a interface é só PT-BR por enquanto, todos
  os textos do design system (labels de botão, mensagens) estão em
  português.

---

## Modelo de negócio (regras que regem o sistema)

Estas regras são o "como a Dite Ads funciona" — toda feature precisa respeitar.

### Catálogo de serviços

O catálogo é **fixo**, gerenciado pelo admin em uma tela própria, e tem
3 tipos de cobrança:

- **Único** (one-shot): cobra uma vez quando contratado. Ex: criar site,
  e-book, logotipo, criar conta de rede social, compra de domínio.
- **Mensal** (recorrente): cobra todo mês até o cliente cancelar/pausar.
  Ex: gestão de anúncios (Meta ADS, Google ADS), pacotes de postagem.
- **Por unidade** (avulso): cobra conforme consumo. Cliente pede X criativos
  no mês, sistema cobra X × preço unitário. Ex: criativos avulsos (CTF,
  CTV, CTI).

Cada item do catálogo tem preços cadastrados em **3 moedas**: USD, BRL, EUR.
Sem conversão automática — admin precificou cada mercado separadamente, e
os preços **não são proporcionais entre moedas** (ex: Landing Page custa
$200 USD, mas R$300 BRL e €100 EUR — cada mercado tem sua realidade).

**Preço customizado por cliente**: o preço da tabela é o **padrão**, mas
admin pode sobrescrever o valor para um cliente específico no momento de
atribuir o item (ver Feature N3). Override vale para **qualquer item** do
catálogo (pacote, mensal, único, por unidade) e pode ser para cima
(cliente paga mais que a tabela) ou para baixo (desconto). O override
fica salvo por par cliente↔item — toda cobrança daquele cliente para
aquele item usa o valor customizado em vez do valor da tabela.

### Pacotes

Alguns itens do catálogo são **pacotes** (combos) — agrupam outros itens
do catálogo com preço promocional.

Pacote tem **composição interna explícita** (bill of materials): o sistema
sabe quais itens estão "dentro" do pacote. Isso é crítico para o
registro de entregas (ver Feature N12): se cliente contratou POSTAGEM 7D,
sistema sabe que esperam-se 7 criativos no mês, e funcionário registra
cada um.

### Preços ao funcionário (folha de pagamento)

- **Funcionário sempre recebe em USD**, independente da moeda do cliente.
- Pagamento é feito via **transferência bancária Wise** (WiseTag do
  funcionário).
- Valor pago é definido por par (funcionário + item do catálogo), em USD.
  - Para itens **mensais**: valor fixo por mês de serviço ativo.
  - Para itens **únicos**: valor único na entrega.
  - Para itens **por unidade**: valor × quantidade de entregas registradas
    no mês.
- Funcionário só recebe quando o cliente paga a cobrança correspondente
  (ver Feature N3).

### Moeda do cliente

- Cada cliente tem **uma moeda configurada pelo admin** (USD, BRL ou EUR)
  no momento do cadastro ou na ficha. Cliente não escolhe.
- Cliente sempre vê valores na moeda dele, pegando o preço cadastrado
  daquela moeda no catálogo.
- Sistema **nunca converte moedas**: totais financeiros são sempre
  apresentados separados por moeda (BRL, USD, EUR listados
  individualmente). Pagamento a funcionários aparece em USD à parte.

### Cobrança e cancelamento

- Atribuir um item **mensal** ou **por unidade** a um cliente cria uma
  **assinatura ativa**. Todo mês o sistema gera automaticamente a cobrança
  do cliente (soma dos itens mensais + soma dos itens por unidade ×
  quantidade entregue no mês).
- Itens **únicos** geram cobrança no momento da contratação, não recorrem.
- Assinaturas seguem ativas até admin marcar como **Pausada** ou
  **Cancelada**.

### Comunicação

- Conversas com clientes e da equipe acontecem **fora do sistema**
  (WhatsApp/Telegram).
- Sistema apenas armazena os links dos grupos para acesso rápido + monta
  mensagens prontas via `wa.me` (ver Feature N11).

### Outras decisões já tomadas

- Pagamento (do cliente para Dite Ads) é manual — sem gateway integrado.
- Cliente loga para acompanhar mas não paga pelo sistema.

---

## Catálogo atual de serviços

Esta é a referência viva — admin cadastra estes itens na tela de
"Gerenciar catálogo" (Feature N2). Lista pode mudar; sistema permite
adicionar/editar/desativar itens.

### Itens únicos (cobrança one-shot)

**Criação de contas em redes sociais** ($50 / R$250 / €50 cada):
- Criar Facebook
- Criar Instagram
- Criar TikTok
- Criar YouTube
- Google Business

**Sites**:
- Landing Page — $200 / R$300 / €100
- Portfólio — $300 / R$1.200 / €200
- Ecommerce até 50 produtos — $500 / R$2.000 / €300
- Ecommerce até 100 produtos — $800 / R$2.500 / €700

**Infraestrutura web**:
- Compra de domínio — $10 / R$30 / €10
- Provedor anual — $66 / R$330 / €55 (BRL/EUR convertidos do USD, admin
  pode ajustar)

**E-books** (preços BRL/EUR foram convertidos do USD com câmbio
aproximado USD≈R$5 e USD≈€0,86; admin pode ajustar):
- E-book 20 páginas — $30 / R$150 / €25
- E-book 40 páginas — $40 / R$200 / €35
- E-book 60 páginas — $60 / R$300 / €50
- E-book +60 páginas — a negociar (todas as moedas)

**Outros únicos**:
- LogoTipo (criação/melhoramento, formato 1x1) — $10 / R$50 / €10
- ART (arte para anúncio até 3 min, usado em pacotes) — $150 / R$1.500 / €150

### Itens mensais (cobrança recorrente)

- Meta ADS — $80 / R$400 / €80
- Google ADS — $70 / R$400 / €80

### Itens por unidade (cobrança avulsa)

- CTF — criativo de foto — $4 / R$10 / €3,50
- CTV — criativo de vídeo (30s a 1min30) — $5 / R$15 / €4,30
- CTI — criativo com IA (30s a 1min30) — $6 / R$25 / €6

### Pacotes (cobrança mensal, com composição interna)

Cada pacote tem **2 variantes** controladas por um checkbox **"com IA"**
no momento da contratação (escolhido por cliente/admin). A variante "com
IA" usa criativos do tipo CTI; a versão normal usa CTF/CTV.

| Pacote | Composição (mensal) | Preço normal (USD/BRL/EUR) | Preço com IA (USD/BRL/EUR) |
|---|---|---|---|
| **ANÚNCIO** | ART + Meta ADS + Google ADS | $150 / R$450 / €128 | — (não tem variante IA) |
| **POSTAGEM 7D** | ART (7) + 7 criativos | $150 / R$800 / €130 | $180 / R$900 / €170 |
| **POSTAGEM 5D** | ART (5) + 5 criativos | $120 / R$750 / €100 | $140 / R$800 / €120 |
| **POSTAGEM 2D** | ART (2) + 2 criativos | $80 / R$200 / €55 | $95 / R$240 / €75 |

**Decisão de modelagem**: pacotes POSTAGEM têm **checkbox "com IA"** no
cadastro do pacote no catálogo. Ao marcar:
- Preço do pacote muda para o preço "com IA"
- Composição troca criativos CTF/CTV por CTI

O pacote ANÚNCIO **não tem variante IA** (não envolve criativos de
postagem, só anúncio).

No fluxo de contratação (admin atribui pacote ao cliente — ver Feature
N3), o checkbox aparece novamente para escolher qual variante daquele
cliente em particular.

---

# Backlog de features

---

## Feature N1 — Onboarding por link de convite (cliente + funcionário) 🔥

Funcionalidade base — destrava o cadastro de quase tudo no sistema.

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: hoje admin precisa cadastrar cliente/funcionário manualmente
  com todos os dados. Com link de convite, a própria pessoa preenche.
- **Quem usa**: admin gera o link; cliente ou funcionário preenche

### Fluxo (igual para os dois tipos, com formulários diferentes)

1. Admin abre tela "Convidar" → escolhe tipo (cliente ou funcionário)
2. Sistema gera link único (ex: `bus.diteads.com/convite/<token>`)
3. Admin manda link por fora (WhatsApp, email — não é o sistema que envia)
4. Pessoa abre link, vê formulário, preenche dados e define senha
5. Conta é criada → pessoa já loga e entra no sistema

### Campos — convite de **cliente**

Cadastro simples:
- Nome da empresa
- Nome do contato
- Email (vira login)
- Telefone (com DDI, para WhatsApp — Feature N11)
- Endereço
- Senha

**Cadastrados pelo admin** (não no onboarding, na ficha do cliente depois):
- Moeda do cliente (USD/BRL/EUR)
- Link do grupo WhatsApp/Telegram do cliente
- Itens do catálogo contratados (ver Feature N3)

### Campos — convite de **funcionário**

Cadastro simples:
- Nome completo
- Email (vira login)
- Telefone
- Endereço
- CPF (opcional — equipe pode ter gente fora do Brasil)
- WiseTag (obrigatório — pagamento é via Wise em USD)
- Senha

**Cadastrados pelo admin** (na ficha do funcionário depois):
- País de residência (opcional)
- Itens do catálogo que o funcionário executa + valor que recebe em USD
  para cada item

### Dúvidas / decisões pendentes

- **Link**: uso único? Tempo de expiração (7d / 30d / nunca)? Admin pode
  revogar antes de ser usado?
- **Pré-preenchimento**: admin pode pré-preencher campos antes de gerar o
  link, ou link é sempre em branco?
- **CPF obrigatório ou opcional?** (decisão atual: opcional)
- **Permissões do funcionário**: vê valores financeiros dos clientes que
  atende, ou só dados operacionais (entregas, prazos)?
- **Aceite de responsabilidades**: cliente precisa marcar "Li e concordo"
  com as responsabilidades de cada serviço contratado?
- **Depende da Feature N5** (recuperação de senha): se cliente esquecer a
  senha depois do onboarding, hoje só admin reseta. Idealmente N5 sai
  junto ou antes.

---

## Feature N2 — Gerenciar catálogo de serviços 🔥

Tela onde admin cadastra/edita os itens do catálogo (ver seção "Catálogo
atual de serviços" acima).

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: catálogo é o coração comercial do sistema — sem ele, não
  tem o que cobrar nem o que entregar. E precisa poder mudar (preços
  reajustam, novos serviços são lançados, outros saem de linha).
- **Quem usa**: só admin (cria/edita); funcionário e cliente apenas leem.

### O que tem no cadastro de cada item

- Nome (ex: "POSTAGEM 7D", "Meta ADS", "Criar Instagram")
- Descrição (texto livre)
- Tipo de cobrança: Único / Mensal / Por unidade
- Preços nas 3 moedas: USD, BRL, EUR
  - Cada moeda pode estar vazia ("não vendemos isso nesse mercado") ou
    com valor
  - Suporta "a negociar" (mostra para o admin que valor é custom por
    cliente)
- Se for **pacote**: composição (lista de outros itens do catálogo +
  quantidade de cada)
- Responsabilidades (3 campos opcionais):
  - O que a agência entrega
  - O que o funcionário responsável faz
  - O que o cliente precisa fornecer
- Status: Ativo / Desativado (desativado não aparece para contratar mas
  mantém histórico)

### Dúvidas / decisões pendentes

- **Pacotes**: admin precisa explicitamente listar os componentes
  (ex: POSTAGEM 7D = 1×ART + 7×Criativo)? Como modelar isso na UI:
  campos repetidos, drag-and-drop, dropdown com adicionar?
- **Composição flexível por funcionário**: a composição do pacote é
  fixa (POSTAGEM 7D sempre = 1 ART + 7 criativos), ou pode variar por
  cliente (cliente X negociou 8 criativos em vez de 7)?
- **Responsabilidades**: 3 campos de texto livre, listas estruturadas
  (bullets), ou editor rico?
- **Customização por cliente**: responsabilidades são padrão do serviço
  ou podem ser editadas no momento que admin atribui ao cliente?

---

## Feature N3 — Cobrança recorrente automática 🔥

Coração financeiro do sistema.

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: cliente paga mensalmente pelos serviços contratados; gerar
  manualmente toda essa cobrança não escala.
- **Quem usa**: admin configura; sistema executa; cliente vê.

### Fluxo

- Admin atribui itens do catálogo a um cliente → cada atribuição é uma
  **assinatura**:
  - Itens **mensais** ou **pacotes**: assinatura ativa, cobra todo mês
  - Itens **por unidade**: assinatura ativa, cobra conforme entregas no
    mês (ver Feature N12)
  - Itens **únicos**: gera cobrança imediata, não recorre
- **Cada assinatura tem 1 funcionário responsável** (não suporta divisão
  de trabalho entre múltiplos funcionários no mesmo item). Se for
  necessário dividir, admin cria 2 assinaturas separadas do mesmo item
  para o mesmo cliente, cada uma com seu funcionário.
- Admin pode **trocar o funcionário responsável** em uma assinatura
  existente, mas a troca **só vale para o mês seguinte em diante** —
  histórico do funcionário anterior (entregas marcadas, valores devidos)
  fica preservado no mês corrente e nos passados.
- No momento de atribuir, sistema mostra o **preço da tabela** já
  preenchido no campo "valor cobrado", **mas admin pode editar** para
  cobrar mais ou menos que a tabela (override por cliente — ver "Modelo
  de negócio → Catálogo"). Override fica salvo na assinatura.
- Todo dia X do mês, sistema gera a cobrança consolidada do cliente:
  - Soma dos itens mensais + pacotes (usando preço customizado se houver,
    senão preço da tabela)
  - Soma dos itens por unidade × quantidade registrada no mês anterior
  - Valor na moeda do cliente (USD/BRL/EUR conforme cadastro)
- Para cada item cobrado, sistema "marca para pagar" o funcionário
  responsável daquele mês (valor em USD) — só "libera" para pagamento
  quando cliente paga a cobrança.
- Admin pode mudar status de cada assinatura: **Ativa / Pausada / Cancelada**

### Dia da cobrança (por cliente)

Cada cliente tem seu próprio dia de cobrança recorrente, determinado
por estas regras:

- **O dia é definido pela data da primeira assinatura do cliente**, não
  pela data de cadastro. Cliente cadastrado dia 15 mas que só contratou
  o primeiro serviço dia 22 → dia de cobrança dele é o **22** de todo
  mês. (Antes da primeira assinatura, ele não devia nada, então não
  fazia sentido começar o ciclo.)
- **Cobrança é consolidada**: serviços contratados depois entram na
  mesma cobrança mensal do dia do cliente. Cliente que contratou
  POSTAGEM 5D no dia 15 e Meta ADS no dia 22 recebe **uma cobrança só
  por mês, no dia 15**, somando os dois serviços.
- **Meses com menos dias**: se o dia do cliente é 31 e o mês tem 30
  dias (ou fevereiro), a cobrança cai no **último dia do mês** (30,
  28/02 ou 29/02 em ano bissexto).

### Dúvidas / decisões pendentes

- **Como rodar**: cron job (Hostinger oferece) ou trigger no primeiro
  acesso do dia?
- **Pular se duplicado**: se já existe cobrança aberta do mês para
  aquele cliente, pular geração?
- **Pausa/cancela no meio do mês**: o que acontece com a cobrança do
  mês atual já gerada? Mantém? Estorna? Admin decide na hora?
- **Itens por unidade — cobrança proporcional**: funcionário fez 5
  criativos no mês = cobra 5×$4 = $20. E se não fez nenhum? Não cobra
  nada? Cobra mínimo de pacote?
- **Estados extras**: além de Ativa/Pausada/Cancelada, faz sentido ter
  "Trial" ou "Em negociação"?
- **Notificar admin** quando geração mensal acontece?

---

## Feature N4 — Painel financeiro (agenda + por cliente + por serviço) 🔥

Visão consolidada — funde a "agenda", o "controle por cliente/serviço",
e a antiga F6 original (relatório mensal).

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: depois que a cobrança roda automática, admin precisa de
  uma tela única para enxergar o que está acontecendo financeiramente.
- **Quem acessa**: admin (visão completa). Funcionário talvez veja só o
  que é dele — a decidir.

### Aba 1 — Agenda

Painel/dashboard mostrando o que está pendente e próximo:

- Cobranças vencidas (vermelho)
- Cobranças vencendo nos próximos dias (amarelo)
- Pagamentos a funcionários liberados (cliente já pagou) — sempre em USD
- Resumo do mês: total a receber, total a pagar a funcionários, margem
  prevista
- **Totais sempre separados por moeda** (BRL, USD, EUR listados
  individualmente — nunca somar moedas diferentes nem converter
  automaticamente). Pagamento a funcionários aparece em USD à parte.

### Aba 2 — Por cliente

Detalhe de um cliente específico:

- Assinaturas ativas (itens do catálogo contratados) com preço cobrado,
  funcionário responsável, valor pago a ele, margem (na moeda do
  cliente)
- Histórico de cobranças (recebidas, em aberto, vencidas)
- Total acumulado: cobrado, recebido, em aberto

### Aba 3 — Por serviço (geral)

Visão agregada — quanto cada item do catálogo representa para a Dite Ads:

- Por item: número de clientes ativos, faturamento, total pago a
  funcionários, margem
- Permite responder: "Qual item dá mais margem?", "Onde estamos
  concentrados?"
- Cuidado: cliente em moedas diferentes — agregação precisa ser por
  moeda (não somar BRL com USD)

### Dúvidas / decisões pendentes

- **Filtros**: por período (mês atual, últimos 3 meses, customizado)?
- **Histórico**: só pendente, ou tudo com filtro de status?
- **Visão por funcionário**: adicionar quarta aba (quanto cada
  funcionário gerou em margem, quanto recebeu, quantos clientes)?
- **Comparativo entre meses**: maio vs abril, gráfico de evolução?
- **Exportação**: PDF? CSV/Excel?
- **Tela inicial do admin**: ao logar, admin cai direto na aba "Agenda"?

---

## Feature N5 — Recuperação de senha por email ⭐

(Era a Feature 1 original. Sobe em prioridade porque destrava o
onboarding via link — sem isso, qualquer cliente que esquecer a senha
precisa do admin.)

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: usuário (cliente ou funcionário) esquece a senha; sem
  fluxo de reset, só admin consegue resolver.
- **Quem usa**: [ ] admin [x] funcionário [x] cliente
- **Fluxo**:
  1. Usuário clica "Esqueci minha senha" na tela de login
  2. Digita email, sistema envia link de reset
  3. Usuário clica no link, define nova senha, faz login
- **Dúvidas**:
  - SMTP da Hostinger ou serviço externo (SendGrid, Resend, Mailgun)?
  - Tempo de expiração do link de reset (1h, 24h)?
  - Link de uso único?

---

## Feature N6 — Comprovante de pagamento (upload) ⭐

(Era a Feature 2 original. Mantida.)

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: cliente paga fora do sistema (PIX, transferência) e
  precisa comprovar para que admin registre o pagamento.
- **Quem usa**: [ ] admin [ ] funcionário [x] cliente
- **Fluxo**: cliente paga fora do sistema → entra no sistema → anexa
  comprovante (PDF/imagem) na cobrança aberta → admin confirma e
  registra o pagamento.
- **Dúvidas**:
  - Quem pode anexar: só cliente ou também admin?
  - Tamanho máximo (sugestão: 5MB)
  - Formatos: PDF, JPG, PNG?
  - Onde guardar: filesystem (`/uploads/comprovantes/`) ou DB?

---

## Feature N7 — Recibo / Nota em PDF ⭐

(Era a Feature 4 original. Mantida.)

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: cliente pede comprovante formal de pagamento.
- **Quem gera**: [ ] admin para cliente [ ] cliente baixa próprio
- **Conteúdo do PDF**:
  - Cabeçalho: logo Dite Ads, dados da empresa
  - Dados do cliente
  - Descrição (itens contratados + responsabilidades?), valor, pagamentos
  - Data, assinatura?
- **Biblioteca**: dompdf (não exige Composer pesado) ou mPDF?

---

## Feature N8 — Notificações por email 💭

(Era a Feature 5 original. Mantida.)

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Eventos** (marca os que quer):
  - [ ] Cobrança criada → cliente recebe
  - [ ] Pagamento registrado → cliente recebe confirmação
  - [ ] Vencimento próximo (3 dias antes) → cliente
  - [ ] Vencimento estourou → cliente + admin
  - [ ] Pagamento liberado → funcionário
- **Dúvidas**: mesmo SMTP da Feature N5?

---

## Feature N9 — Log de auditoria 💭

(Era a Feature 7 original. Mantida.)

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **O que registrar**:
  - [ ] Criação/edição de cobrança
  - [ ] Registro/remoção de pagamento
  - [ ] Alterações de cliente/funcionário
  - [ ] Pausa/cancelamento de assinatura
  - [ ] Edição do catálogo (preços, novos itens, itens desativados)
  - [ ] Login/logout
- **Por quê**: rastreabilidade caso surja dúvida sobre quem fez o quê.

---

## Feature N10 — 2FA para admin 💭

(Era a Feature 8 original. Mantida.)

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: proteção da conta admin (que pode ver/alterar dados
  financeiros de todos os clientes).
- **Tipo**: [ ] app autenticador (Google Auth / Authy — TOTP) [ ] email

---

## Feature N11 — Comunicação via WhatsApp (link wa.me) ⭐

Enviar cobranças e avisos pro cliente direto pelo WhatsApp, usando o
canal que a Dite Ads já usa no dia a dia.

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: hoje admin gera cobrança no sistema e tem que abrir o
  WhatsApp manualmente para copiar dados e mandar pro cliente.
  Automatizar a montagem da mensagem economiza tempo e padroniza.
- **Quem usa**: admin (dispara); cliente recebe no WhatsApp dele.

### Abordagem técnica: Caminho A — link `wa.me` (manual-assistido)

Sistema monta um link `https://wa.me/<telefone>?text=<mensagem pronta>`,
admin clica → abre WhatsApp Web/app com tudo preenchido → aperta enviar.

- **Custo**: zero (sem API, sem mensalidade)
- **Sem dependência externa**
- **Limitação aceita**: não é envio totalmente automático — admin
  precisa clicar para disparar.

### Fluxo

1. Admin abre a ficha de uma cobrança (ou lista de cobranças do mês)
2. Ao lado de cada cobrança, botão "💬 Enviar via WhatsApp"
3. Admin clica → sistema monta mensagem com base em template configurável
4. WhatsApp Web/app abre com mensagem pronta → admin revisa e envia

### Tipos de mensagem (templates)

- [ ] Cobrança nova gerada
- [ ] Lembrete antes do vencimento
- [ ] Cobrança vencida
- [ ] Confirmação de pagamento recebido
- [ ] Link do recibo PDF (depende de Feature N7)
- [ ] Outros: __________

### Templates configuráveis

- Admin tem uma tela "Templates de mensagem" onde edita os textos.
- Variáveis: `{nome_cliente}`, `{itens}`, `{valor}`, `{moeda}`,
  `{vencimento}`, `{mes_referencia}`, `{link_comprovante}`, `{link_recibo}`.

### Dúvidas / decisões pendentes

- **Quem pode disparar**: só admin, ou funcionário também (para os
  clientes que ele atende)?
- **Onde fica o botão**: só na tela de cobrança, ou também na agenda
  (N4), na ficha do cliente?
- **Histórico**: sistema registra "admin X clicou para enviar cobrança Y"?
- **Telefone**: pré-validar formato com DDI no cadastro.

---

## Feature N12 — Registro de entregas pelo funcionário (agenda com checkbox) 🔥

Funcionário entra no sistema, vê uma **agenda visual** com cada serviço
de cada cliente que ele atende, e **só marca o checkbox** do dia/unidade
que entregou. Sem formulário, sem link, sem anexo — apenas confirma "fiz".

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**:
  - Para itens **por unidade** (criativos avulsos): folha do funcionário
    depende de quantas entregas ele fez no mês.
  - Para **pacotes POSTAGEM** (7D/5D/2D): cliente sabe que está sendo
    entregue ("3 de 5 postagens essa semana"), admin sabe que o
    funcionário está em dia.
  - Visual claro do mês inteiro, com baixíssimo atrito (1 clique por
    entrega).
- **Quem usa**: funcionário marca; admin acompanha; cliente vê.

### Como o checkbox aparece, por tipo de item

O modelo de checkbox **varia conforme o tipo de cobrança** do item:

- **Pacotes POSTAGEM (7D/5D/2D)** → **checkbox por dia no calendário**.
  Cada semana tem N dias com checkbox (7/5/2 conforme o pacote).
  Funcionário marca os dias em que postou.

- **Itens por unidade** (CTF, CTV, CTI avulsos) → **checkbox por unidade**.
  Cliente pediu X criativos no mês — aparecem X checkboxes, funcionário
  vai marcando até completar.

- **Itens únicos** (criar site, criar Instagram, e-book) → **1 checkbox
  "entregue"**. Funcionário marca quando finaliza.

- **Itens mensais não-de-postagem** (Meta ADS, Google ADS, ANÚNCIO) →
  **sem checkbox** — é trabalho contínuo, não tem unidades discretas.
  Aparecem na agenda só como "ativo neste mês" para contexto, sem ação.

### Fluxo

1. Funcionário abre a aba "Minha agenda" — vê calendário do mês com
   cada cliente que atende. **Vê apenas os serviços onde ele é o
   funcionário responsável** — não vê serviços do mesmo cliente que
   estão atribuídos a outros funcionários (isolamento de visão por
   funcionário).
2. Para cada serviço daquele cliente, aparece a estrutura de checkbox
   apropriada ao tipo (ver acima)
3. Funcionário marca os checkboxes conforme entrega — **sem preencher
   nada além**, é só clicar
4. Sistema agrega:
   - **Cliente vê** o progresso de todos os serviços contratados (de
     todos os funcionários envolvidos — para o cliente é tudo "a Dite
     Ads entregando")
   - **Admin vê** tudo agrupado por cliente, com cada serviço e seu
     funcionário responsável
   - **Folha de pagamento** (itens por unidade × checkboxes marcados no
     mês), por funcionário

### Dúvidas / decisões pendentes

- **Cliente vê em tempo real?**: sim/não. Sim = transparência total, mas
  gera pressão ("por que ainda não postou hoje?"). Não = cliente vê só
  totalizador no fim do mês.
- **Funcionário pode desmarcar?**: sim sempre (errou, marcou no dia
  errado), ou só admin pode desmarcar depois de marcado?
- **Aprovação do admin?**: admin precisa aprovar/validar as marcações
  para entrarem na folha? Ou marcação do funcionário = válida
  automaticamente?
- **Pacote incompleto**: se POSTAGEM 5D combinou 5 postagens mas
  funcionário só marcou 3, cliente paga o pacote cheio ou proporcional?
  (Decisão padrão sugerida: pacote cheio — cliente combinou pelo
  pacote, agência se vira pra entregar; o "buraco" é problema interno.)
- **Funcionário recebe pelo pacote ou por checkbox?**: para pacotes
  POSTAGEM, valor pago ao funcionário é fixo pelo pacote (ele recebe
  pelos 5 dias do POSTAGEM 5D mesmo se marcou só 3), ou proporcional
  às marcações?
- **Atraso/falha**: sistema avisa o admin quando passa do dia X e
  funcionário não marcou?
- **Itens únicos com prazo**: tem campo "prazo de entrega" para item
  único? Sistema alerta quando próximo do prazo?

---

## Feature N13 — Capacidade declarada do funcionário ⭐

Cada funcionário declara no sistema quanto trabalho consegue absorver no
mês, e admin usa essa informação para distribuir clientes novos
inteligentemente.

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: hoje admin não sabe rapidamente quem tem espaço para mais
  clientes — chuta ou pergunta no WhatsApp. Com capacidade visível,
  admin distribui melhor o trabalho e equilibra a carga da equipe.
- **Quem usa**: funcionário declara; admin consulta na hora de atribuir.

### Modelo (combinação de 2 sinais)

**Sinal 1 — "Estou aceitando novos clientes" (binário)**

Funcionário tem um toggle simples na ficha dele: 🟢 Aceitando /
🔴 Cheio. Marca/desmarca quando quiser. Resolve o caso mais comum
("quem tá livre?").

**Sinal 2 — Capacidade declarada por tipo (números)**

Funcionário declara sua capacidade total mensal por tipo de entrega:
- "Posso fazer até X criativos no mês"
- "Posso atender até Y pacotes POSTAGEM"
- (outros tipos relevantes — a definir conforme o catálogo)

Sistema calcula automaticamente o **ocupado** (comprometido pelas
assinaturas ativas) vs o **declarado**, e mostra o saldo. Exemplo:

> *Pedro: 60/80 criativos comprometidos este mês (75% — sobra 20)*
> *Ana: 12/80 criativos comprometidos (15% — bastante espaço)*

### Onde aparece para o admin

**Lugar 1 — Na hora de atribuir serviço a cliente** (Feature N3):
ao escolher o funcionário responsável no dropdown, cada nome vem com
indicador inline do tipo:

> *Pedro 🔴 (Cheio · 60/80 criativos)*
> *Ana 🟢 (Aceitando · 12/80 criativos)*

**Lugar 2 — Tela "Capacidade da equipe"** (acessível pelo menu do admin):
visão panorâmica de toda a equipe, útil para planejamento de mais longo
prazo ("preciso contratar mais alguém?", "Ana está subaproveitada").

### Dúvidas / decisões pendentes

- **Quais tipos de capacidade declarar?**: criativos, pacotes POSTAGEM,
  sites/projetos únicos? A lista precisa fechar conforme o catálogo
  (alguns itens não fazem sentido contar — Meta ADS é trabalho contínuo,
  não "unidade").
- **Funcionário pode "trancar a porta" mesmo com espaço?**: sim, o
  toggle 🟢/🔴 é manual e independe do número (funcionário pode estar
  60/80 mas marcar 🔴 porque sabe que vai sair de férias).
- **Admin pode forçar atribuição mesmo se funcionário marcou 🔴?**:
  sim, é só um alerta — admin decide. Útil para casos de emergência.
- **Histórico de capacidade**: sistema guarda histórico mês a mês
  (Pedro declarou 80 em maio, subiu para 100 em junho), ou só o valor
  atual?
- **Quem mais vê isso?**: outros funcionários veem a capacidade dos
  colegas, ou só admin?

---

## Feature N14 — Régua de cobrança automática (lembretes de atraso) ⭐

Quando uma cobrança passa do vencimento, sistema dispara lembretes
automáticos em intervalos configurados pelo admin, parando quando o
cliente paga (ou quando admin silencia a cobrança específica).

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: hoje admin precisa lembrar manualmente de cobrar quem
  atrasou — escapa, esquece, gera prejuízo. Régua automática garante
  que ninguém é esquecido sem precisar de atenção contínua do admin.
- **Quem usa**: admin configura a régua; sistema executa; cliente
  recebe os lembretes; admin recebe alertas em casos extremos.

### Como funciona

Quando uma cobrança chega ao vencimento sem ter sido paga, ela entra
na régua. A cada intervalo configurado, sistema dispara o lembrete
daquela etapa. A régua para automaticamente quando:
- Cliente paga (admin marca como paga, ver Feature N6)
- Admin **silencia** a cobrança manualmente (cenário: negociação direta)
- Régua atinge o **limite máximo de tentativas** (decisão: 4 lembretes
  total — depois disso, só admin acompanha)

### Régua configurável (decisão: configurável pelo admin)

Admin tem uma tela "Régua de cobrança" onde edita os intervalos e os
textos. Régua é a mesma para todos os clientes (sem customização por
VIP nesta versão).

Exemplo de régua padrão (sugestão para começar — admin ajusta):

| Etapa | Quando | Ação |
|---|---|---|
| 0 | Dia do vencimento | Email "vence hoje" + WhatsApp na agenda do admin |
| 1 | +3 dias atraso | Email "atraso de 3 dias" + WhatsApp na agenda |
| 2 | +7 dias atraso | Email "atraso de 1 semana, urgente" + WhatsApp na agenda + alerta no painel do admin |
| 3 | +15 dias atraso | Email "atraso de 15 dias, possível suspensão" + WhatsApp na agenda + alerta no admin |
| (fim) | +30 dias atraso | **Para a régua**. Admin decide ação manualmente (pausar/cancelar serviço). |

### Canais: email automático + WhatsApp na agenda do admin

- **Email**: 100% automático. Sistema dispara pelo SMTP configurado
  (mesmo da Feature N5/N8). Cliente recebe sem intervenção humana.
- **WhatsApp**: **não é automático**. Como a Feature N11 usa `wa.me`
  (manual-assistido, requer clique do admin), o sistema **gera uma
  tarefa na agenda do admin** ("hoje você precisa enviar lembrete via
  WhatsApp para: Cliente X, Cliente Y, Cliente Z"). Admin abre, clica
  no botão WhatsApp de cada um, mensagem do template já vem pronta, só
  envia.

> Se no futuro a Feature N11 migrar para a API oficial da Meta (Caminho
> B, com custo recorrente), o WhatsApp também vira automático sem
> precisar mexer nesta feature.

### Silenciar uma cobrança específica

Admin abre a ficha da cobrança e clica em **"Silenciar lembretes
automáticos"**. A cobrança continua vencida e visível, mas sai da régua
(não dispara mais email nem entra na agenda de WhatsApp do admin).
Útil para casos de negociação direta com o cliente, evita ruído.

Admin pode "des-silenciar" depois se quiser que volte para a régua.

### Dúvidas / decisões pendentes

- **Templates dos lembretes**: usar os mesmos templates da Feature N11?
  Ou ter templates próprios da régua (mais "duros" conforme a etapa)?
- **Histórico**: cada envio (email automático ou WhatsApp marcado como
  enviado pelo admin) fica registrado na cobrança para conferência?
- **Cliente CC**: admin é copiado em todos os emails enviados ao
  cliente, ou só nas etapas mais críticas (2, 3)?
- **Pausa automática**: quando régua atinge o fim (+30 dias), sistema
  pausa automaticamente a assinatura, ou só alerta o admin e ele decide?
- **Mais de uma cobrança vencida do mesmo cliente**: envia 1 email por
  cobrança ou 1 email consolidado por cliente listando todas?

---

## Feature N15 — Pagamento ao funcionário (fila + comprovante + histórico) ⭐

Ciclo completo do pagamento ao funcionário, em 3 etapas conectadas:
**(1) liberação automática** quando cliente paga → **(2) alerta/fila** para
admin não esquecer de pagar → **(3) comprovante automático** quando admin
marca como pago.

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**:
  - Admin não pode esquecer de pagar funcionários quando entra dinheiro
    (gera atraso, desconfiança da equipe).
  - Funcionário precisa de transparência: o que tem a receber e o que
    já recebeu.
  - Histórico documental de cada pagamento (se houver questionamento,
    tem prova).
- **Quem usa**: sistema libera; admin vê alerta e paga; funcionário
  acompanha o que tem a receber e recebe comprovante.

### Etapa 1 — Liberação automática quando cliente paga

Quando admin confirma o comprovante de uma cobrança (Feature N6) → cobrança
vira "Paga" → **na mesma hora**, sistema "libera" os valores devidos aos
funcionários envolvidos naquela cobrança (sem delay, sem confirmação
manual).

Exemplo: Padaria do João paga $200 (POSTAGEM 5D do Pedro + Meta ADS da
Ana). Sistema libera imediatamente:
- $80 para Pedro
- $40 para Ana

Os valores ficam **acumulados por funcionário** até admin pagar.

### Etapa 2 — Fila de pagamentos pendentes (visão do admin)

No Painel financeiro (Feature N4) tem uma **seção persistente "Pagamentos
pendentes"** que mostra:

- **1 linha por funcionário** com valor a pagar acumulado (não 1 por
  cliente — admin paga 1× por funcionário pra economizar taxa Wise)
- Exemplo:

  > *Pedro Silva — $240 a pagar (de 3 clientes)*
  > *Ana Costa — $120 a pagar (de 2 clientes)*
  > *Total: $360 em pagamentos pendentes*

- Clicando em cada linha, admin vê o detalhamento (quais clientes,
  quais serviços, quantas entregas)
- Lista **fica visível até admin liberar** (não some sozinha, evita
  esquecimento)
- (Opcional) Badge no menu lateral: "Pagamentos pendentes (2)" para
  chamar atenção

### Etapa 3 — Admin paga e gera comprovante

1. Admin paga o funcionário via Wise (fora do sistema, valor acumulado)
2. Admin entra no sistema, na fila de pagamentos pendentes, e clica
   **"Marquei como pago"** no funcionário correspondente
3. Sistema imediatamente:
   - Gera o comprovante em PDF
   - Envia email ao funcionário com o PDF anexo
   - Salva o PDF/registro na ficha do funcionário (acessível depois)
   - Remove o funcionário da fila "Pagamentos pendentes"

### Conteúdo do PDF de comprovante

- **Cabeçalho**: logo Dite Ads, dados da empresa
- **Dados do funcionário**: nome, WiseTag/email para o qual foi pago
- **Data do pagamento** (data que admin marcou como pago)
- **Valor total pago em USD** (valor único transferido via Wise)
- **Detalhamento** — tabela com cada item incluído neste pagamento:
  - Cliente
  - Serviço (nome do item do catálogo)
  - Tipo (Mensal / Único / Por unidade)
  - Quantidade (1 para mensais/únicos; N para por unidade conforme
    checkboxes marcados — ver Feature N12)
  - Valor unitário (em USD que o funcionário recebe)
  - Subtotal
- **Total** somando tudo

### Granularidade: um comprovante por pagamento

Cada vez que admin marca um pagamento como feito, gera **um comprovante
próprio** — sistema não consolida em "comprovante mensal". Se admin paga
$200 hoje e $300 daqui a uma semana, são 2 comprovantes separados.
Cada comprovante reflete exatamente o que foi transferido naquele
momento.

### Tela "Meus pagamentos" (visão do funcionário)

Funcionário entra no menu e vê uma tela própria com 2 seções:

**A receber (liberado, ainda não pago)**:
- Total acumulado em USD
- Quebra por cliente (de onde vem cada parte)
- Funciona como espelho da fila do admin — funcionário sabe o que tem
  a receber

**Histórico (já pago)**:
- Lista de pagamentos recebidos: data, valor em USD, link para baixar
  o PDF do comprovante
- Filtro por período: este mês, últimos 3 meses, este ano, ou
  intervalo customizado
- Total acumulado no período filtrado
- (Opcional) gráfico simples de evolução mês a mês

### Dúvidas / decisões pendentes

- **Biblioteca de PDF**: mesma escolha da Feature N7 (dompdf ou mPDF)
  — fazer uma escolha só para os dois.
- **Email**: usa o mesmo SMTP da Feature N5/N8/N14.
- **Comprovante editável depois**: se admin errou ao marcar como pago
  (incluiu cliente errado, valor errado), pode estornar/cancelar o
  comprovante e gerar novo? Ou comprovante é imutável e admin emite um
  "comprovante de correção"?
- **Reenvio**: funcionário consegue clicar "reenviar comprovante por
  email" se perdeu o original?
- **Pagamento parcial**: admin pode pagar só parte do acumulado do
  funcionário (ex: pagar $200 de $240) e deixar resto na fila? Ou
  precisa pagar tudo de uma vez?
- **Notificação do funcionário quando libera**: além do PDF quando admin
  pagar, sistema também notifica funcionário quando um valor é liberado
  (cliente pagou) — ex: email "$80 foram liberados pra você do cliente
  Padaria do João"?

---

## Feature livre — descreva aqui o que você quer

### Nome:

- **Prioridade**:
- **Por quê**:
- **Quem usa**:
- **Fluxo**:
- **Dúvidas**:

---

## Ajustes e correções no que já existe

Liste aqui coisas que devem MUDAR no sistema atual (não são features novas).

- [ ]
- [ ]
- [ ]

---

## Resumo de mudanças nesta versão do planejamento

Esta versão é uma **reformulação grande** feita depois que recebemos o
catálogo real da Dite Ads (planilha + tabelas adicionais). As mudanças
principais:

- **Adicionada** seção "Catálogo atual de serviços" com a lista real de
  itens, preços nas 3 moedas, tipos de cobrança, e pacotes com
  composição.
- **Modelo de negócio reescrito** para refletir o catálogo real:
  - Catálogo é **fixo, gerenciado pelo admin** (não emerge de
    funcionários como na versão anterior).
  - 3 tipos de cobrança: Único / Mensal / Por unidade.
  - Pacotes têm composição interna (necessário para Feature N12).
  - 3 moedas: USD, BRL, EUR (sem GBP).
  - Preços por moeda não são proporcionais (mercados precificados
    separadamente).
  - Funcionário sempre recebe em USD via Wise (WiseTag obrigatório).
- **Feature N2** (cadastro de serviços) virou **"Gerenciar catálogo"** —
  agora reflete que o catálogo é entidade central, com preços em 3
  moedas, tipos de cobrança e pacotes com composição.
- **Feature N12 nova**: registro de entregas pelo funcionário. Base da
  folha de pagamento (itens por unidade) e da transparência ao cliente.
- **Feature N1** ajustada: funcionário agora informa CPF (opcional) e
  WiseTag (obrigatório).
- **Histórico do documento anterior**: as features N1–N11 vinham de uma
  reformulação anterior que ainda assumia "preço por par (funcionário +
  serviço)" e catálogo emergente. Foram ajustadas para casar com a nova
  realidade do catálogo.
- **Ajustes finais**:
  - Provedor anual ganhou preços em BRL (R$330) e EUR (€55) via conversão
    do USD.
  - E-book +60 páginas mantém "a negociar" nas 3 moedas (sem preço base
    para converter).
  - "Com IA" virou **checkbox no pacote** em vez de pacotes separados.
    Os 7 itens da tabela anterior viraram 4 pacotes (ANÚNCIO + 3
    POSTAGEM) com 2 variantes de preço cada — exceto ANÚNCIO, que não
    tem variante IA.
  - **Preço customizado por cliente**: admin pode sobrescrever o valor
    da tabela ao atribuir qualquer item a um cliente específico (para
    cima ou para baixo). Modelo de negócio e Feature N3 atualizados.
  - **Feature N12 reformulada**: trocou o modelo "formulário com
    data+link+anexo" por **checkbox no calendário/agenda**. Modelo de
    checkbox varia por tipo de item (dia da semana para POSTAGEM,
    por unidade para avulsos, "entregue" para únicos, sem checkbox
    para mensais não-postagem). Muito menos atrito para o funcionário,
    sem evidência de entrega (link/anexo) por enquanto.
  - **Regras de funcionário responsável por assinatura**:
    - 1 assinatura = 1 funcionário (sem divisão entre múltiplos).
      Se necessário dividir, admin cria 2 assinaturas separadas do
      mesmo item.
    - Admin pode trocar o funcionário responsável, mas a troca **só
      vale para o mês seguinte em diante** — histórico fica preservado.
    - Cada funcionário vê na agenda **apenas os serviços onde ele é
      responsável** (isolamento de visão).
  - **Feature N13 nova**: capacidade declarada do funcionário (toggle
    aceitando/cheio + números por tipo de entrega). Admin vê
    inline na hora de atribuir serviço a cliente, e também em uma tela
    panorâmica "Capacidade da equipe".
  - **Dia da cobrança por cliente** (Feature N3): determinado pela
    data da **primeira assinatura** do cliente; cobranças posteriores
    são **consolidadas** num único faturamento mensal nesse dia; em
    meses curtos (dia 31 vs fevereiro), cobra no **último dia do mês**.
  - **Feature N14 nova**: régua de cobrança automática para cobranças
    em atraso. Email totalmente automático; WhatsApp gera tarefa na
    agenda do admin (porque N11 é manual-assistida). Régua configurável
    pelo admin, com limite de 4 lembretes e opção de silenciar
    cobranças específicas (para casos de negociação direta).
  - **Feature N15 expandida**: cobre o **ciclo completo** de pagamento
    ao funcionário em 3 etapas. (1) Liberação automática quando cliente
    paga; (2) **Fila de pagamentos pendentes** no painel do admin,
    consolidada por funcionário (não por cliente — admin paga 1× por
    funcionário pra economizar taxa Wise); (3) Comprovante PDF
    automático quando admin marca como pago. Funcionário também passa
    a ver "A receber (liberado, ainda não pago)" em "Meus pagamentos",
    além do histórico.
  - **Design system adicionado**: nova seção depois do Contexto do
    sistema. Tema escuro, mobile-first, paleta masculina extraída do
    logo (azul royal primária, roxo escuro secundária, laranja âmbar
    destaque). Define paleta, tipografia, espaçamento, componentes
    e padrões mobile específicos.
  - **Idioma fechado em PT-BR único**: regra explícita no Contexto do
    sistema — toda interface, emails, templates WhatsApp, PDFs e
    comunicações em português, sem exceção. Dúvidas pendentes sobre
    idioma (em N11 e N15) foram removidas. Multilíngue fica para
    versão futura, se houver demanda.
  - **Padrões de comportamento harmonizados** adicionados ao design
    system. Define convenções repetíveis para todas as telas (header,
    estrutura de conteúdo, cards, botões, formulários, modais, menu
    inferior, notificações). Cada persona tem seu próprio bottom nav
    (admin: Painel/Clientes/Catálogo/Perfil; funcionário:
    Agenda/Clientes/Pagamentos/Perfil; cliente: Cobranças/Entregas/Perfil).
