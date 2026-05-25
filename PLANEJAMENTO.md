# Planejamento — próximas features

Documento de trabalho: você edita, eu leio e codifico.

**Como usar**:
1. Marque a prioridade de cada feature alterando o `[ ]` para `[x]` ou apagando.
2. Preencha os campos em branco (ou apague o que não interessa).
3. Adicione novas features no final como blocos extras.
4. Quando estiver pronto, me avisa "implementa X" — eu pego daqui e codo.

Legenda de prioridade: 🔥 urgente · ⭐ próxima · 💭 pensar depois

---

## Feature 1 — Recuperação de senha por email

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê** (problema que resolve):
  _ex: usuário esquece senha e hoje só admin consegue resetar_
- **Quem usa**: [ ] admin [ ] funcionário [ ] cliente
- **Fluxo**:
  1.
  2.
  3.
- **Dúvidas / decisões pendentes**:
  - Vamos usar SMTP da Hostinger ou serviço externo (SendGrid/Resend)?
  - Tempo de expiração do link de reset?

---

## Feature 2 — Comprovante de pagamento (upload)

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**:
- **Quem usa**: [ ] admin [ ] funcionário [ ] cliente
- **Fluxo**:
  - Cliente paga fora do sistema → entra no sistema → anexa comprovante PDF/imagem na cobrança aberta → admin confirma e registra o pagamento.
- **Dúvidas**:
  - Quem pode anexar: só cliente ou também admin?
  - Tamanho máximo do arquivo? (sugestão: 5MB)
  - Formatos: PDF, JPG, PNG?
  - Onde guardar: filesystem (`/uploads/comprovantes/`) ou DB?

---

## Feature 3 — Mensalidade recorrente

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**:
- **Fluxo**:
  - Admin define no cliente: "valor mensal R$ X, dia de vencimento Y, serviço Z".
  - No dia 1 de cada mês (ou no dia configurado), sistema gera cobrança automaticamente.
- **Dúvidas**:
  - Como rodar? Cron job (Hostinger tem) ou trigger no primeiro acesso do dia?
  - Pular geração se já existe cobrança aberta com mesma descrição no mês?
  - Notificar admin quando gerar?

---

## Feature 4 — Recibo / Nota em PDF

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**:
- **Quem gera**: [ ] admin para cliente [ ] cliente baixa próprio
- **Conteúdo do PDF**:
  - Cabeçalho: logo Dite Ads, dados da empresa
  - Dados do cliente
  - Descrição, valor, pagamentos
  - Data, assinatura?
- **Biblioteca**: dompdf (não exige Composer pesado) ou mPDF?

---

## Feature 5 — Notificações por email

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Eventos** (marca os que quer):
  - [ ] Cobrança criada → cliente recebe
  - [ ] Pagamento registrado → cliente recebe confirmação
  - [ ] Vencimento próximo (3 dias antes) → cliente
  - [ ] Vencimento estourou → cliente + admin
  - [ ] Comissão fechada do mês → funcionário
- **Dúvidas**: SMTP de qual provedor?

---

## Feature 6 — Relatório financeiro mensal

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**:
- **Métricas no relatório**:
  - Total recebido no mês
  - Total em aberto / vencido
  - Quebra por cliente
  - Quebra por serviço
  - Comissões a pagar (por funcionário)
- **Formato**: [ ] tela [ ] PDF [ ] CSV/Excel
- **Quem acessa**: [ ] só admin

---

## Feature 7 — Log de auditoria

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **O que registrar**:
  - [ ] Criação/edição de cobrança
  - [ ] Registro/remoção de pagamento
  - [ ] Alterações de cliente/funcionário
  - [ ] Login/logout
- **Por quê**: rastreabilidade caso surja dúvida sobre quem fez o quê.

---

## Feature 8 — 2FA para admin

- **Prioridade**: [ ] 🔥 [ ] ⭐ [ ] 💭
- **Por quê**: proteção da conta admin (que pode ver/alterar dados financeiros).
- **Tipo**: [ ] app autenticador (Google Auth / Authy — TOTP) [ ] email

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

## Decisões já tomadas (referência)

- Repo é público (sem segredos comitados).
- Pagamento é manual (sem gateway agora).
- Comissão é percentual fixo por funcionário, calculado sobre pagamentos confirmados.
- Cliente loga mas não paga pelo sistema.
- Sem framework PHP — código vanilla.
