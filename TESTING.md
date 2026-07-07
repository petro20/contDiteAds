# Roteiro de testes — contDiteAds

Checklist para validar o sistema antes de colocar em uso real.
Marca cada item com `[x]` conforme passa.

**URL**: https://cont.diteads.com
**Credenciais iniciais**: `admin@diteads.com` (perfil sadmin)

---

## 🧪 Fase 1 — Setup (sadmin)

Login com sua conta sadmin e prepare o terreno:

- [ ] **Catálogo** está populado (26 itens reais carregados no seed)
- [ ] **Templates de mensagem** estão lá (cobranca_nova, lembrete_vencendo, pagamento_confirmado, etc.) — vai em `/templates.php`
- [ ] **Régua de cobrança** está configurada (4 etapas: 0, 3, 7, 15 dias) — vai em `/regua.php`
- [ ] Editou pelo menos **1 template** e salvou (ex: mudou uma frase)
- [ ] **Cron jobs** configurados no hPanel (gerar_cobrancas 5h, regua_executar 6h, backup_db 4h, alerta_postagens Qua/Sex 9h, limpeza_mensal dia 1 3h)
- [ ] **Cotação USD**: em `/config_pagamento.php` clicou "Atualizar cotação" → BRL/EUR do catálogo recalculam
- [ ] **Idioma**: trocou PT → EN → ES no seletor do header e a interface mudou (cookie `idioma` persiste)

## 🧪 Fase 2 — Onboarding (sadmin/admin)

- [ ] Gerou um **convite de funcionário** em `/convites.php`
- [ ] Abriu o link em janela anônima → preencheu o formulário → conta criada
- [ ] Logou com a conta recém-criada → caiu na tela do funcionário (não no painel admin)
- [ ] Voltou pra sadmin → gerou **convite de cliente**
- [ ] Cadastrou um cliente direto (sem convite) em `/clientes.php` definindo moeda BRL/USD/EUR
- [ ] Na ficha do cliente, criou um **login** pra ele (email + senha)
- [ ] Logou como cliente → viu o painel do cliente (cobranças/entregas vazios)

## 🧪 Fase 3 — Operação financeira (sadmin)

- [ ] Editou um funcionário → cadastrou **valor USD** que ele recebe pelo menos pra 1 item do catálogo (ex: POSTAGEM 5D = $80)
- [ ] Editou o mesmo funcionário → definiu **capacidade** (ex: 30 criativos / 5 pacotes / 2 sites)
- [ ] Criou uma **assinatura** em `/assinaturas.php` (cliente + item + funcionário + valor)
- [ ] Confirmou que o **dropdown de funcionário** mostra 🟢 ou 🔴 + ocupação
- [ ] Item ÚNICO: ao criar a assinatura, o sistema gerou cobrança avulsa imediata (verificou em `/cobrancas.php`)
- [ ] Item MENSAL: foi em `/cobrancas.php` → "Gerar manualmente" → escolheu cliente + mês → cobrança apareceu
- [ ] Abriu uma cobrança → visual bate com o mockup (VALOR TOTAL grande + itens + ações)
- [ ] Cliente logado: vê a cobrança em `/cobrancas.php`
- [ ] Cliente: anexou um **comprovante** (PDF/JPG) na cobrança em aberto

## 🧪 Fase 4 — Confirmação de pagamento e folha

- [ ] Sadmin/admin: abriu a cobrança e clicou **"✓ Marcar como paga"** (botão único)
- [ ] Cobrança virou status `paga`
- [ ] Cliente recebeu email automático de confirmação (cheque caixa de entrada/spam de `contact@diteads.com`)
- [ ] Em `/pagamentos_funcionarios.php`: o funcionário responsável apareceu na fila com o valor USD
- [ ] Clicou "Pagar [funcionário]" → confirmou itens → "Marquei como pago"
- [ ] Funcionário recebeu email com link do comprovante
- [ ] Funcionário logado: vê em **Meus pagamentos** o histórico
- [ ] Clicou no comprovante → abriu a tela print-friendly → Ctrl+P salvou como PDF

## 🧪 Fase 5 — Operação de funcionário (entregas)

- [ ] Logou como funcionário
- [ ] Em **Agenda**: viu cliente + serviço atribuído a ele
- [ ] Pacote POSTAGEM: viu calendário com checkboxes por dia → marcou alguns
- [ ] Por unidade (CTF/CTV/CTI): clicou "+ Entreguei mais um" várias vezes
- [ ] Item único: clicou "Marcar como entregue"
- [ ] Cliente logado: foi em **Entregas** → vê os mesmos dias marcados (read-only)
- [ ] Sadmin: foi em `/painel.php` → aba "Por cliente" → vê resumo financeiro

## 🧪 Fase 6 — Comunicação (sadmin/admin)

- [ ] Abriu uma cobrança em aberto → clicou **💬 WhatsApp** → abriu wa.me com mensagem pronta
- [ ] Mesma cobrança → clicou **✉ Email** → abriu cliente de email padrão com assunto + corpo
- [ ] Editou um template em `/templates.php` → salvou → conferiu na próxima cobrança que a mensagem mudou
- [ ] Em `/regua.php`: viu tarefas WhatsApp pendentes (se houver cobranças vencidas)
- [ ] Silenciou uma cobrança específica e voltou pra `/cobrancas.php?id=N` → o botão virou "Reativar lembretes"

## 🧪 Fase 7 — Despesas e distribuição de lucro

- [ ] Sadmin: foi em `/despesas.php` → cadastrou 1 despesa mensal (ex: "Adobe Creative Cloud — $30 USD")
- [ ] Foi em `/distribuicao.php` → conferiu:
  - Receita do mês por moeda
  - **Despesas** subtraídas (USD ficou negativo se não tem receita USD)
  - Lucro líquido por moeda
  - Quota por sócio = lucro_líquido / (N sócios + 1 empresa)
- [ ] A "Empresa" aparece como uma quota separada

## 🧪 Fase 7.5 — Integrações de pagamento

- [ ] **Dite Gateway (cartão)**: com `DITE_API_KEY`/`DITE_WEBHOOK_SECRET` no `.env`, abriu uma
  cobrança → botão de pagar com cartão → redirecionou pro gateway → após pagar, o webhook
  `/webhooks/dite` marcou a cobrança como **paga** automaticamente (conferir em `/wise_eventos.php`/log)
- [ ] `/api/plans` retorna JSON com os itens mensais do catálogo (formato `{ data: [...] }`)
- [ ] **Wise webhook**: colou a chave pública RSA em `/wise_eventos.php`; crédito recebido
  aparece como pagamento **pendente** para o admin confirmar
- [ ] **Wise CSV**: em `/wise_sync.php` subiu um CSV exportado da Wise → casou pagamentos por moeda+valor
- [ ] **Simulador de preço com IA**: em `/simulador_preco.php` gerou sugestão via IA (precisa da
  chave Anthropic em `configuracoes`) e converteu numa simulação salva
- [ ] **Backup**: em `/backups.php` clicou "Gerar backup agora" → baixou o `.sql.gz`
- [ ] **Export**: em `/export.php` baixou CSV de cobranças e de distribuição (abre no Excel com acento correto)

## 🧪 Fase 8 — Segurança

- [ ] Saiu do sistema e clicou **"Esqueci minha senha"** em `/login.php`
- [ ] Digitou email do usuário → mensagem genérica apareceu (sem revelar se email existe)
- [ ] Cheque a caixa de email — recebeu link de reset?
- [ ] Clicou no link → definiu nova senha → logou
- [ ] Em **Perfil → Configurar 2FA**: gerou QR Code → escaneou com Google Authenticator/Authy
- [ ] Confirmou 2FA com código de 6 dígitos
- [ ] Logout + login: agora pede senha + código 2FA
- [ ] Em `/auditoria.php`: vê histórico das suas ações (login, edições, etc.)
- [ ] Tentou apagar **a si mesmo** ou rebaixar o último sadmin → sistema bloqueou
- [ ] Logou como admin comum (não sadmin): tentou abrir `/catalogo.php` ou `/templates.php` → 403 acesso negado

## 🧪 Fase 9 — Permissões

- [ ] **Sadmin**: vê todos os cards no dashboard, incluindo Catálogo/Régua/Templates/Auditoria/Despesas
- [ ] **Admin** (comum): NÃO vê Catálogo/Régua/Templates/Auditoria/Despesas. Vê Painel financeiro, Clientes, Funcionários, Assinaturas, Cobranças
- [ ] **Funcionário**: bottom nav mostra Agenda / Clientes / Pagamentos / Perfil. Não acessa nenhuma URL de admin (testa digitando /clientes.php — deve dar 403)
- [ ] **Cliente**: bottom nav mostra Cobranças / Entregas / Perfil. Não acessa páginas de admin

## 🧪 Fase 10 — Edge cases

- [ ] Tentou criar 2 usuários com o **mesmo email** → erro claro "Já existe usuário com este email"
- [ ] Tentou criar cobrança com **valor negativo ou zero** → erro
- [ ] Tentou abrir uma URL de outro cliente (ex: `/cobrancas.php?id=999`) → 404 acesso negado
- [ ] Cron rodou às 5h (vai conferir amanhã em "Ver resultado" no hPanel) — não deu erro
- [ ] Cobrança com cliente sem telefone: botão WhatsApp aparece desabilitado
- [ ] Cobrança com cliente sem email: botão Email aparece desabilitado

---

## ✅ Quando terminar

Se tudo passou, o sistema está pronto pra uso real.

Se algo falhou, me reporta:
1. Qual fase / item específico
2. O que esperava vs o que aconteceu
3. Print se possível

Eu corrijo na sequência.

---

## 🚀 Próximos passos sugeridos (não obrigatórios)

- Convidar a equipe real (gerar links de convite para funcionários reais)
- Cadastrar clientes existentes manualmente OU gerar convites
- Marcar primeiras assinaturas das relações cliente↔funcionário↔serviço atuais
- Esperar o ciclo do mês fechar com cobrança automática gerada pelo cron
