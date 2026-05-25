# PRD — contDiteAds

**Sistema de controle financeiro da Dite Ads**
Versão: 0.1 (MVP em produção) · Última atualização: 2026-05-25

---

## 1. Contexto

A Dite Ads precisa de um sistema interno para registrar serviços prestados, cobranças
geradas, pagamentos recebidos e comissões devidas aos funcionários. Os clientes
também devem poder entrar para acompanhar suas cobranças e histórico de pagamentos
— o pagamento em si acontece fora do sistema (Pix, transferência, etc.) e é
registrado manualmente por um administrador.

**URL de produção**: https://cont.diteads.com
**Repositório**: https://github.com/petro20/contDiteAds (público)

## 2. Personas e papéis

| Papel | Acesso | Principais ações |
|---|---|---|
| **Admin** | Total | Cadastra clientes, funcionários, serviços, cobranças e pagamentos. Define comissão de cada funcionário. |
| **Funcionário** | Suas cobranças e pagamentos | Vê apenas os clientes/cobranças onde está como responsável. Acompanha pagamentos recebidos e comissão acumulada. |
| **Cliente** | Suas cobranças e pagamentos | Loga (se o admin criar acesso) e vê cobranças em aberto e histórico de pagamentos próprios. Não paga pelo sistema. |

## 3. Funcionalidades entregues

### 3.1 Autenticação e segurança
- Login por email + senha (bcrypt via `password_hash`).
- Sessão PHP com cookie HTTPOnly, SameSite=Lax, regeneração de ID no login.
- Token CSRF em todos os formulários POST.
- Escape de saída via `htmlspecialchars` (helper `e()`).
- `.htaccess` força HTTPS, bloqueia listagem de diretório, headers de segurança
  (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`), nega acesso
  direto a `.env`, `.git*`, `includes/`, `db/`.

### 3.2 Cadastros (admin)
- **Clientes**: nome, documento, contato, endereço, observações, ativo/inativo.
  Pode opcionalmente ter login do sistema (cria `usuarios.role='cliente'`).
- **Funcionários/Admins**: nome, email, senha, perfil, **percentual de comissão**, ativo.
- **Serviços**: catálogo de tipos de trabalho (nome, descrição, valor padrão).
  Quando admin cria uma cobrança e seleciona um serviço, o valor padrão preenche
  o campo automaticamente.

### 3.3 Cobranças
- Admin cria cobrança vinculando: **cliente** (obrigatório), serviço, funcionário
  responsável (recebe comissão), descrição, valor, vencimento.
- Status: `aberta` → `paga` (automático quando pagamentos somam ≥ valor) ou
  `cancelada` (manual). Admin pode reabrir cobranças canceladas.
- Listagem com filtros por status, cliente e funcionário.
- Marca visualmente cobranças vencidas (em aberto + vencimento passado).
- Funcionário vê só as suas; cliente vê só as próprias.

### 3.4 Pagamentos
- Admin registra pagamentos contra uma cobrança: valor, data, método
  (Pix, transferência, boleto, dinheiro, cartão, outro), observação.
- Suporta pagamentos parciais — o status muda para `paga` só quando totaliza.
- Admin pode remover um pagamento (corrige status da cobrança automaticamente).
- Histórico de pagamentos visível para todos que têm acesso à cobrança.

### 3.5 Comissões
- Cada funcionário tem um `percentual_comissao` (0–100%).
- Cálculo: soma dos pagamentos confirmados das cobranças onde ele é responsável
  × percentual. Não há registro persistente de comissão — é sempre derivado
  dos pagamentos (fonte da verdade).
- Funcionário vê comissão do mês e acumulada no próprio dashboard.

### 3.6 Dashboard (por perfil)
- **Admin**: clientes ativos, funcionários ativos, **recebido no mês**,
  **em aberto** (com contador), **vencido** (com destaque vermelho se >0),
  lista das próximas 10 cobranças em aberto ordenadas por vencimento.
- **Funcionário**: clientes que atende, cobranças em aberto, recebido no mês
  (clientes dele), comissão do mês, recebido total, comissão acumulada, lista
  das próprias cobranças.
- **Cliente**: saldo em aberto, total pago, lista das próprias cobranças.

## 4. Modelo de dados

```
clientes (id, nome, documento, email, telefone, endereco, observacoes, ativo, criado_em)

usuarios (id, nome, email, senha_hash, role['admin'|'funcionario'|'cliente'],
          cliente_id → clientes.id (NULL exceto role=cliente),
          percentual_comissao DECIMAL(5,2), ativo, criado_em)

servicos (id, nome, descricao, valor_padrao, ativo, criado_em)

cobrancas (id, cliente_id → clientes, servico_id → servicos (NULL ok),
           funcionario_id → usuarios (NULL ok), descricao, valor, vencimento,
           status['aberta'|'paga'|'cancelada'], criado_por → usuarios,
           criado_em, atualizado_em)

pagamentos (id, cobranca_id → cobrancas (cascade delete),
            valor_pago, data_pagamento, metodo, observacao,
            registrado_por → usuarios, criado_em)
```

FKs com `ON DELETE RESTRICT` para preservar integridade financeira (não
permite apagar cliente/usuário que tem cobrança ou pagamento). Exceção:
`pagamentos.cobranca_id` é CASCADE — apagar cobrança apaga seus pagamentos.

## 5. Stack e infraestrutura

- **Backend**: PHP 8 vanilla (sem framework). PDO + prepared statements.
- **Banco**: MySQL 8 (Hostinger shared), charset `utf8mb4_unicode_ci`.
- **Frontend**: HTML + CSS vanilla. Sem build step, sem npm.
- **Hospedagem**: Hostinger shared hosting, `public_html` = raiz do repo.
- **Deploy**: GitHub push → webhook → Hostinger faz `git pull` automático.
- **Credenciais**: `.env` em `public_html` (não versionado), lido por
  `includes/config.php` no boot.

## 6. Fluxos principais

### 6.1 Onboarding de um cliente
1. Admin cria cliente em `/clientes.php`.
2. (Opcional) Cria login do cliente na mesma tela → email + senha inicial.
3. Admin cria cobrança em `/cobrancas.php` vinculando o cliente.
4. Cliente recebe credenciais (fora do sistema) e loga.

### 6.2 Recebimento de pagamento
1. Cliente paga fora do sistema (Pix, etc.).
2. Admin abre a cobrança em `/cobrancas.php?id=N`.
3. Preenche "Registrar pagamento" (valor, data, método).
4. Sistema atualiza status para `paga` se total ≥ valor.
5. Comissão do funcionário responsável reflete automaticamente no dashboard dele.

### 6.3 Funcionário acompanha trabalho
1. Funcionário loga.
2. Dashboard mostra clientes atendidos, cobranças em aberto e comissão.
3. Abre cobranças individuais para ver detalhes e pagamentos.

## 7. Convenções de código

- Português nos identificadores de negócio (cliente, cobranca, pagamento).
- `e()` para escape HTML, `csrf_check()` em todo POST.
- Helpers de auth em `includes/auth.php`: `current_user()`, `require_login()`,
  `require_admin()`, `is_admin()`.
- Sem ORM — queries diretas via `db()` (PDO singleton).
- Migrations em `db/migration_NNN_*.sql`; schema canônico em `db/schema.sql`.

## 8. Fora do escopo atual / Roadmap

Não implementado, candidato para próximas versões:

- [ ] Gateway de pagamento (Pix automático, cartão) — hoje pagamento é manual.
- [ ] Recibo / nota em PDF por cobrança.
- [ ] Cobranças recorrentes (mensalidades automáticas).
- [ ] Notificações por email (cobrança criada, pagamento confirmado, vencimento próximo).
- [ ] Logs de auditoria (quem alterou o quê).
- [ ] Exportação financeira (CSV/Excel) por período.
- [ ] Relatório de comissões fechado por mês com snapshot.
- [ ] Anexo de comprovante de pagamento (upload).
- [ ] 2FA para admin.
- [ ] Recuperação de senha por email.
