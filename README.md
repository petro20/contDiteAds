# contDiteAds

Sistema de controle de clientes, funcionários, tarefas e apontamentos de horas.

- **Admin**: gerencia clientes, funcionários e todas as tarefas; vê apontamentos de todos.
- **Funcionário**: vê apenas as tarefas atribuídas a ele e registra horas/observações.

## Stack
- PHP 8 + MySQL (Hostinger shared, document root = repo root)
- HTML + CSS vanilla, sem build step → push = deploy

## Estrutura
```
.
├── index.php           # redireciona para login ou dashboard
├── login.php / logout.php
├── dashboard.php       # cards e tarefas em aberto
├── clientes.php        # CRUD clientes (admin)
├── funcionarios.php    # CRUD usuários (admin)
├── tarefas.php         # listagem, criação, detalhe + apontamentos
├── includes/           # config, db, auth, layout  (acesso direto bloqueado)
├── assets/css|js/      # estáticos
├── db/                 # schema.sql, seed.sql, gerar_hash.php
└── .htaccess           # HTTPS, headers segurança, bloqueia .env/.git
```

## Deploy inicial (Hostinger)

1. **Banco MySQL** (hPanel → Databases → MySQL):
   - Criar banco e usuário (anote nome, user, senha).
   - Importar `db/schema.sql` via phpMyAdmin.
   - Gerar um hash de senha forte para o admin:
     ```
     php db/gerar_hash.php 'SuaSenhaForte'
     ```
   - Substituir o hash em `db/seed.sql` e importar (ou editar direto via phpMyAdmin).

2. **Repositório no GitHub** (GitHub Desktop):
   - Add → Add Existing Repository → selecionar `F:\Sistemas Git\contDiteAds`
   - Publish repository → marcar **Private** → Publish.

3. **Deploy automático na Hostinger** (hPanel → Avançado → GIT):
   - **Repositório**: `git@github.com:SEU-USUARIO/contDiteAds.git`
   - **Ramo**: `master` (ou `main`, conforme o GitHub Desktop criou)
   - **Diretório**: em branco (vai para `public_html`)
   - Copiar a chave SSH mostrada no painel.
   - GitHub → Settings do repo → Deploy keys → Add deploy key → colar a chave (somente leitura).
   - Voltar e clicar **Criar**.

4. **Configurar `.env` no servidor** (via File Manager ou SSH):
   - Copiar `.env.example` para `.env` no `public_html`.
   - Preencher `DB_NAME`, `DB_USER`, `DB_PASS` com as credenciais do passo 1.
   - `APP_BASE_URL=https://cont.diteads.com`

5. **Acessar**: https://cont.diteads.com → login com `admin@diteads.com` + senha do passo 1.

## Atualizar depois (workflow)

1. Editar local → commit no GitHub Desktop → Push origin.
2. hPanel → GIT → **Implantar do GitHub** (pull manual; o Hostinger não faz auto-pull sem webhook).
3. Alternativa com webhook: usar o endpoint que a Hostinger exibe na tela do GIT e cadastrar como webhook no GitHub (Settings → Webhooks).
