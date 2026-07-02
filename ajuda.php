<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
$u = require_login();

$page = t('Ajuda');
$nav_active = '';
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';

$role = $u['role'];
$role_label = [
    'sadmin'      => t('Super Admin'),
    'admin'       => t('Admin'),
    'funcionario' => t('Funcionário'),
    'cliente'     => t('Cliente'),
][$role] ?? $role;
?>
<h1 class="page-title"><?= e(t('Minha conta')) ?></h1>
<?php render_group_tabs('conta', 'ajuda'); ?>
<h2><?= e(t('Como usar o sistema')) ?></h2>
<p class="muted"><?= e(t('Guia para o perfil')) ?> <strong><?= e($role_label) ?></strong>. <?= e(t('Olá')) ?>, <?= e($u['nome']) ?>!</p>

<?php if ($role === 'sadmin'): ?>

  <div class="card brand">
    <div class="title">👑 <?= e(t('Você é Super Admin')) ?></div>
    <div class="desc"><?= e(t('Acesso total: equipe, catálogo, finanças, distribuição, comunicação, IA e segurança. Único que pode apagar dados em cascata, promover usuários e configurar APIs.')) ?></div>
  </div>

  <h2>🏠 <?= e(t('Início (dashboard)')) ?></h2>
  <div class="card">
    <p><strong>🔮 <?= e(t('Previsão do mês')) ?></strong> — <?= e(t('recebimento + pagamento + lucro previstos, atualizam conforme assinaturas e despesas.')) ?></p>
    <p><strong><?= e(t('Alertas automáticos')) ?></strong>: <?= e(t('comprovantes em análise · cobranças vencidas · funcionários sem WiseTag · sobrecarga · distribuição pendente.')) ?></p>
    <p><strong><?= e(t('KPIs clicáveis:')) ?></strong> <?= e(t('Recebido · A receber · A pagar funcionários · Lucro do mês.')) ?></p>
    <p><strong>📋 <?= e(t('Link do sistema:')) ?></strong> <?= e(t('botão Copiar pra usar em mensagens (variável')) ?> <code>{link_sistema}</code> <?= e(t('nos templates).')) ?></p>
  </div>

  <h2>👥 <?= e(t('Clientes')) ?></h2>
  <div class="card">
    <p><strong>+ <?= e(t('Novo')) ?></strong> <?= e(t('direto ou')) ?> <strong>✉️ <?= e(t('Convidar')) ?></strong> (<?= e(t('link por email')) ?>).</p>
    <p><strong><?= e(t('Mudou moeda do cliente?')) ?></strong> <?= e(t('Sistema recalcula automaticamente o valor de todas as assinaturas ativas dele usando a cotação USD do dia.')) ?></p>
    <p><strong>⚠ <?= e(t('Apagar em cascata:')) ?></strong> <?= e(t('deleta cliente + cobranças + assinaturas + entregas + login.')) ?> <strong><?= e(t('BLOQUEADO se houver pagamentos confirmados')) ?></strong> <?= e(t('em cima (protege histórico financeiro e distribuição a sócios). Use "Desativar" nesses casos.')) ?></p>
  </div>

  <h2>📦 <?= e(t('Catálogo (form em 5 passos)')) ?></h2>
  <div class="card">
    <p><strong><?= e(t('USD = moeda mestre.')) ?></strong> <?= e(t('Você só preenche USD; BRL/EUR são calculados via cotação do dia (arredondado pra cima, sem centavos).')) ?></p>
    <p><strong><?= e(t('Form organizado em 5 passos:')) ?></strong></p>
    <ol style="padding-left:20px; color:var(--txt-2);">
      <li><strong>1️⃣ <?= e(t('Identificação')) ?></strong> — <?= e(t('nome + descrição + botão')) ?> <strong>✨ <?= e(t('Preencher/Refinar com IA')) ?></strong> (<?= e(t('gera tudo a partir de poucas palavras')) ?>)</li>
      <li><strong>2️⃣ <?= e(t('Tipo de cobrança')) ?></strong> — <?= e(t('único/mensal/por_unidade + período mínimo (se mensal)')) ?></li>
      <li><strong>3️⃣ <?= e(t('Preço')) ?></strong> — <?= e(t('USD com preview BRL/EUR ao vivo, opção "preço a negociar" e "variante com IA"')) ?></li>
      <li><strong>4️⃣ <?= e(t('Responsabilidades')) ?></strong> — <?= e(t('o que a agência entrega, o funcionário faz, o cliente fornece (a IA preenche)')) ?></li>
      <li><strong>5️⃣ <?= e(t('Opções')) ?></strong> — <?= e(t('item ativo, é pacote')) ?></li>
    </ol>
    <p><strong>⚠ <?= e(t('Variante IA:')) ?></strong> <?= e(t('se marcar "tem variante com IA", o preço IA em USD vira obrigatório. Sistema bloqueia o save sem ele pra evitar assinaturas com valor zero.')) ?></p>
    <p><strong>📊 <?= e(t('Simulador de preço:')) ?></strong> <?= e(t('botão no topo do catálogo — descreva o serviço, IA sugere custos + margem + responsabilidades. Salve simulações pra editar depois.')) ?></p>
    <p><strong>🔄 <?= e(t('Recalcular preços:')) ?></strong> <?= e(t('atualiza BRL/EUR de TODOS os itens com cotação atual.')) ?></p>
    <p><strong>⚠ <?= e(t('Apagar item:')) ?></strong> <?= e(t('só funciona se NÃO houver assinaturas vinculadas. Caso contrário, desative o item.')) ?></p>
  </div>

  <h2>📊 <?= e(t('Simulador de preço')) ?></h2>
  <div class="card">
    <p><?= e(t('Acessível em')) ?> <strong>📦 <?= e(t('Catálogo → 📊 Simulador de preço')) ?></strong>.</p>
    <p><strong><?= e(t('Funcionalidades:')) ?></strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>✨ IA</strong> — <?= e(t('descreve serviço em 1 linha, IA preenche tudo (custos, margem, responsabilidades, período)')) ?></li>
      <li><strong>🔍 <?= e(t('Pesquisa preço')) ?></strong> — <?= e(t('botão em cada linha de custo abre Google buscando o preço atual')) ?></li>
      <li><strong>💼 <?= e(t('Atalhos')) ?></strong> — <?= e(t('botões pra adicionar softwares populares (Canva, Adobe, ChatGPT, etc.)')) ?></li>
      <li><strong><?= e(t('Rateio')) ?></strong> — <?= e(t('cada custo tem "valor ÷ dividir = por unidade" (ex: \$174 ÷ 36 vídeos)')) ?></li>
      <li><strong>💾 <?= e(t('Salvar simulação')) ?></strong> — <?= e(t('fica salva pra editar depois (lista com todas no topo)')) ?></li>
      <li><strong>✓ <?= e(t('Criar item no catálogo')) ?></strong> — <?= e(t('leva tudo preenchido')) ?></li>
    </ul>
    <p><strong><?= e(t('Pré-requisito IA:')) ?></strong> <?= e(t('chave Anthropic em')) ?> <em><?= e(t('Finanças → Pagamentos → 🤖 IA')) ?></em>.</p>
  </div>

  <h2>🧑‍💼 <?= e(t('Equipe (3 abas)')) ?></h2>
  <div class="card">
    <p><strong>👥 <?= e(t('Lista:')) ?></strong> <?= e(t('cadastra usuários (funcionário/admin/sadmin). Define WiseTag, capacidade mensal, valor USD por item.')) ?> <strong><?= e(t('Funcionário também edita o próprio WiseTag/CPF/País/capacidade/aceitando clientes')) ?></strong> <?= e(t('no perfil dele — você só precisa criar a conta.')) ?></p>
    <p><strong>👥 <?= e(t('Duplas:')) ?></strong> <?= e(t('overview "Duplas configuradas" no topo + badge "👥 dupla com X" em cada card. Pra criar: edita um funcionário → "Trabalha em dupla com". Ambos veem a mesma agenda, mas pagamento vai todo pro principal.')) ?></p>
    <p><strong>📊 <?= e(t('Capacidade:')) ?></strong> <?= e(t('overview de quanto cada um absorve por mês vs ocupação atual. Funcionário declara no próprio perfil.')) ?></p>
    <p><strong>🔴 <?= e(t('Atribuição com aviso:')) ?></strong> <?= e(t('ao tentar atribuir nova assinatura a funcionário marcado como "não aceitando clientes", sistema bloqueia e mostra checkbox "Sim, atribuir mesmo assim" pra confirmar exceção.')) ?></p>
    <p><strong>💵 <?= e(t('Pagamentos:')) ?></strong> <?= e(t('fila de itens entregues prontos pra pagar (USD). Email automático com PDF do comprovante.')) ?></p>
    <p><strong>⚠ <?= e(t('Apagar em cascata:')) ?></strong> <?= e(t('deleta pagamentos recebidos, entregas, capacidades. Cobranças/despesas reatribuídas a você. Trava: não apaga a si mesmo nem o último sadmin.')) ?></p>
  </div>

  <h2>📋 <?= e(t('Acompanhamento geral (todos os funcionários)')) ?></h2>
  <div class="card">
    <p><?= e(t('Dashboard →')) ?> <strong>📋 <?= e(t('Acompanhamento geral')) ?></strong>. <?= e(t('Visão consolidada do que cada funcionário e admin executa no mês.')) ?></p>
    <p><strong><?= e(t('3 vistas (botões no topo):')) ?></strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>📋 <?= e(t('Lista:')) ?></strong> <?= e(t('resumo por pessoa (contagem de assinaturas/entregas/clientes)')) ?></li>
      <li><strong>📅 <?= e(t('Por pessoa:')) ?></strong> <?= e(t('expande cada um mostrando suas assinaturas + status de progresso')) ?></li>
      <li><strong>🗓 <?= e(t('Calendário:')) ?></strong> <?= e(t('grid mensal com todas as entregas de todos coloridas por funcionário')) ?></li>
    </ul>
    <p><?= e(t('Clica em qualquer card pra ir pra agenda detalhada daquela pessoa.')) ?></p>
  </div>

  <h2>🔐 <?= e(t('Matriz de acesso (PDF)')) ?></h2>
  <div class="card">
    <p><?= e(t('Dashboard →')) ?> <strong>🔐 <?= e(t('Matriz de acesso (PDF)')) ?></strong>. <?= e(t('Documento completo com quem pode o quê no sistema (16 seções: navegação, perfil, pessoas, catálogo, cobranças, Wise, agenda, finanças, comunicação, alertas, manutenção, segurança, crons, travas globais e comportamentos automáticos). Botão "🖨 Imprimir / Salvar como PDF" salva como arquivo. Útil pra compliance/onboarding/contador.')) ?></p>
  </div>

  <h2>⏰ <?= e(t('Crons em produção (6 agendados)')) ?></h2>
  <div class="card">
    <p><?= e(t('Sistema roda sozinho com 6 tarefas agendadas no Hostinger:')) ?></p>
    <table style="width:100%; font-size:13px; border-collapse:collapse;">
      <thead><tr style="border-bottom:1px solid var(--border);">
        <th style="text-align:left; padding:6px;"><?= e(t('Quando')) ?></th>
        <th style="text-align:left; padding:6px;"><?= e(t('Script')) ?></th>
        <th style="text-align:left; padding:6px;"><?= e(t('O quê')) ?></th>
      </tr></thead>
      <tbody>
        <tr><td style="padding:6px;"><?= e(t('Dia 1 · 03:00')) ?></td><td><code>limpeza_mensal.php</code></td><td><?= e(t('Apaga logs antigos + OPTIMIZE TABLE')) ?></td></tr>
        <tr><td style="padding:6px;"><?= e(t('Todo dia · 04:00')) ?></td><td><code>backup_db.php</code></td><td><?= e(t('Backup gzip (retém 14 dias)')) ?></td></tr>
        <tr><td style="padding:6px;"><?= e(t('Todo dia · 05:00')) ?></td><td><code>gerar_cobrancas.php</code></td><td><?= e(t('Gera cobranças mensais')) ?></td></tr>
        <tr><td style="padding:6px;"><?= e(t('Todo dia · 06:00')) ?></td><td><code>regua_executar.php</code></td><td><?= e(t('Régua de cobrança')) ?></td></tr>
        <tr><td style="padding:6px;"><?= e(t('Qua + Sex · 09:00')) ?></td><td><code>alerta_postagens.php</code></td><td><?= e(t('Lembrete POSTAGEM')) ?></td></tr>
      </tbody>
    </table>
    <p class="hint"><?= e(t('Todos os crons têm')) ?> <code>flock</code> <?= e(t('pra evitar execuções concorrentes. Audit_log registra cada execução.')) ?></p>
  </div>

  <h2>💰 <?= e(t('Finanças (3 abas)')) ?></h2>
  <div class="card">
    <p><strong>💸 <?= e(t('Despesas:')) ?></strong> <?= e(t('ferramentas, software, marketing — recorrência única/mensal/anual.')) ?></p>
    <p><strong>💎 <?= e(t('Distribuição:')) ?></strong> <?= e(t('cada sócio = 1 quota; empresa = 1 quota. Botão Pagar registra cada lançamento. Sadmin pode apagar lançamentos errados.')) ?> <strong><?= e(t('Trava:')) ?></strong> <?= e(t('valor pago não pode exceder a quota disponível na competência (sistema mostra quota total / já pago / disponível).')) ?></p>
    <p><strong>💳 <?= e(t('Pagamentos:')) ?></strong> <?= e(t('esta aba concentra:')) ?></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>💱 <?= e(t('Cotação USD:')) ?></strong> <?= e(t('USD→BRL e USD→EUR atual + botão "Atualizar agora" (AwesomeAPI, cache diário)')) ?></li>
      <li><strong>💜 Zelle:</strong> <?= e(t('email + QR Code (upload)')) ?></li>
      <li><strong>🌍 Wise:</strong> <?= e(t('link público')) ?></li>
      <li><strong>📝 <?= e(t('Instruções extras')) ?></strong></li>
      <li><strong>🤖 <?= e(t('API key da Anthropic')) ?></strong> (<?= e(t('pra IA do simulador e do catálogo funcionar')) ?>)</li>
    </ul>
  </div>

  <h2>📊 <?= e(t('Painel financeiro')) ?></h2>
  <div class="card">
    <p><strong><?= e(t('3 abas:')) ?></strong> <?= e(t('Agenda · Por cliente · Por serviço.')) ?></p>
    <p><strong><?= e(t('Gráfico:')) ?></strong> <?= e(t('1m/3m/6m/1a/Tudo, 4 séries (Receita/Despesa/Pago sócios/Lucro).')) ?></p>
    <p><strong><?= e(t('Cards por moeda:')) ?></strong> <?= e(t('Entradas − Saídas = Lucro líquido. Distribuição já paga + saldo final.')) ?></p>
  </div>

  <h2>💳 <?= e(t('Cobranças')) ?></h2>
  <div class="card">
    <p><strong><?= e(t('Geração automática:')) ?></strong> <?= e(t('cron diário às 5h, 7 dias antes do vencimento.')) ?></p>
    <p><strong><?= e(t('Manual:')) ?></strong> <?= e(t('"Marcar como paga" ou "Nova cobrança avulsa" (não aceita vencimento no passado).')) ?></p>
    <p><strong><?= e(t('Detalhe (cliente vê):')) ?></strong> <?= e(t('QR Zelle + email + link Wise Quick Pay com passo a passo, botão copiar.')) ?></p>
    <p><strong><?= e(t('Comprovante do cliente:')) ?></strong> <?= e(t('vai pra "Em análise" → alerta no dashboard → aceita/rejeita.')) ?></p>
    <p><strong>🪝 <?= e(t('Pagamento via Wise:')) ?></strong> <?= e(t('webhook detecta automaticamente. Vai como "pendente" pra você confirmar em')) ?> <em>wise_eventos.php</em> <?= e(t('ou pelo card laranja no dashboard.')) ?></p>
    <p><strong><?= e(t('Travas:')) ?></strong>
      <ul style="padding-left:20px; color:var(--txt-2); margin-top:4px;">
        <li><?= e(t('Cobrança paga não pode ser cancelada (estorne primeiro)')) ?></li>
        <li><?= e(t('Cobrança zerada (sem itens) vira "cancelada" automático')) ?></li>
        <li><?= e(t('Régua respeita saldo: se pagou parcial, não recobra o total')) ?></li>
      </ul>
    </p>
  </div>

  <h2>🪝 <?= e(t('Wise — Webhook + Reconciliação')) ?></h2>
  <div class="card">
    <p><?= e(t('Em')) ?> <em><?= e(t('Finanças → Pagamentos → 🪝 Webhook')) ?></em> (<?= e(t('ou direto em')) ?> <code>/wise_eventos.php</code>):</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong><?= e(t('URL do webhook')) ?></strong> <?= e(t('pra colar no painel Wise')) ?></li>
      <li><strong><?= e(t('Validação RSA SHA256')) ?></strong> <?= e(t('ligada em produção (sempre obrigatória)')) ?></li>
      <li><strong><?= e(t('Chave pública da Wise')) ?></strong> <?= e(t('salva no sistema')) ?></li>
      <li><strong><?= e(t('Lista de pagamentos pendentes')) ?></strong> <?= e(t('com botão Confirmar/Rejeitar')) ?></li>
      <li><strong><?= e(t('Histórico dos últimos 100 eventos')) ?></strong></li>
    </ul>
    <p><?= e(t('Pagamentos chegam como')) ?> <strong><?= e(t('pendentes')) ?></strong> <?= e(t('propositalmente — você revisa antes de marcar como paga e liberar comissão.')) ?></p>
  </div>

  <h2>💬 <?= e(t('Comunicação (3 abas)')) ?></h2>
  <div class="card">
    <p><strong>⏰ <?= e(t('Etapas:')) ?></strong> <?= e(t('régua automática com dias relativos ao vencimento:')) ?></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong><?= e(t('negativo')) ?></strong> = <?= e(t('antes (ex: −3 = 3 dias antes)')) ?></li>
      <li><strong><?= e(t('zero')) ?></strong> = <?= e(t('no dia')) ?></li>
      <li><strong><?= e(t('positivo')) ?></strong> = <?= e(t('após vencer')) ?></li>
    </ul>
    <p><?= e(t('Botões ↑↓ pra reordenar etapas.')) ?></p>
    <p><strong>📤 <?= e(t('Tarefas:')) ?></strong> <?= e(t('fila WhatsApp pendente, botão "Abrir WhatsApp" com mensagem renderizada.')) ?></p>
    <p><strong>📝 <?= e(t('Templates:')) ?></strong> <?= e(t('CRUD de templates.')) ?> <strong>✨ <?= e(t('Instalar templates de pagamento')) ?></strong> <?= e(t('cria templates padrão completos com Zelle + Wise + QR.')) ?></p>
  </div>

  <h2>🔐 <?= e(t('Segurança e auditoria')) ?></h2>
  <div class="card">
    <p><strong><?= e(t('2FA (meio de recuperação):')) ?></strong> <?= e(t('ativa em')) ?> <em><?= e(t('Minha conta → 🔐 Segurança')) ?></em>. <strong><?= e(t('NÃO é exigido no login normal')) ?></strong> — <?= e(t('serve apenas como alternativa ao email pra recuperar acesso quando você esquece a senha (em')) ?> <em><?= e(t('Esqueci minha senha → 🔐 Via 2FA')) ?></em>, <?= e(t('entra direto com código do app).')) ?></p>
    <p><strong>🔑 <?= e(t('Backup codes:')) ?></strong> <?= e(t('ao ativar 2FA, sistema gera 8 códigos one-time-use.')) ?> <strong><?= e(t('Salve em local seguro')) ?></strong> (<?= e(t('impressão + gerenciador de senhas')) ?>). <?= e(t('Cada código serve como alternativa ao TOTP se você perder o celular. Você pode regerar a qualquer momento.')) ?></p>
    <p><strong><?= e(t('Reset de senha:')) ?></strong> <?= e(t('ao trocar senha (via email ou após entrar pelo 2FA), todas as sessões em outros dispositivos são invalidadas automaticamente.')) ?></p>
    <p><strong>🔍 <?= e(t('Auditoria:')) ?></strong> <?= e(t('histórico de tudo no sistema. Só sadmin acessa.')) ?></p>
  </div>

  <h2>💾 <?= e(t('Backup do banco')) ?></h2>
  <div class="card">
    <p><?= e(t('Em')) ?> <code>/backups.php</code>: <?= e(t('dump comprimido (gzip) do MySQL inteiro.')) ?></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong><?= e(t('Cron diário às 04:00')) ?></strong> (<?= e(t('antes de gerar cobranças e régua')) ?>)</li>
      <li><strong><?= e(t('Retenção:')) ?></strong> <?= e(t('últimos 14 backups; mais antigos são apagados')) ?></li>
      <li><strong><?= e(t('Pasta protegida:')) ?></strong> <code>uploads/.backups/</code> <?= e(t('com')) ?> <code>.htaccess Require all denied</code> — <?= e(t('só baixa via tela do admin')) ?></li>
      <li><strong><?= e(t('Botão "Gerar backup agora"')) ?></strong> <?= e(t('pra forçar fora do cron')) ?></li>
      <li><strong><?= e(t('Restauração:')) ?></strong> <?= e(t('baixa, descompacta (')) ?><code>gunzip</code><?= e(t('), importa no phpMyAdmin')) ?></li>
    </ul>
    <p class="hint" style="color:var(--c-attention);">💡 <?= e(t('Recomendado: baixe manualmente 1x por mês pra um lugar fora da Hostinger (Drive, Dropbox, HD).')) ?></p>
  </div>

  <h2>🔔 <?= e(t('Alertas automáticos pra funcionários')) ?></h2>
  <div class="card">
    <p><strong><?= e(t('Postagens sem marcação:')) ?></strong> <?= e(t('cron quarta e sexta às 09:00 verifica funcionários com assinaturas de POSTAGEM ativas que não marcaram nenhuma entrega nessa semana.')) ?></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><?= e(t("Critério: item do catálogo com 'POSTAGEM' no nome + 0 entregas com data_marcada entre seg e hoje")) ?></li>
      <li><?= e(t('Email vai pro funcionário direto com lista dos clientes pendentes + botão pra agenda')) ?></li>
      <li><?= e(t('Audit log registra cada execução')) ?></li>
      <li><strong><?= e(t('Testar / disparar manual:')) ?></strong> <?= e(t('tela')) ?> <code>/alertas.php</code> <?= e(t('com botões "Dry-run" (só lista) e "Rodar agora" (envia emails)')) ?></li>
    </ul>
  </div>

  <h2>🧹 <?= e(t('Limpeza mensal automática')) ?></h2>
  <div class="card">
    <p><?= e(t('Em')) ?> <code>/limpeza.php</code>: <?= e(t('cron mensal apaga registros antigos pra manter banco ágil + recupera espaço com OPTIMIZE TABLE.')) ?></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong><?= e(t('Cron mensal dia 1 às 03:00')) ?></strong> (<?= e(t('antes do backup das 04:00')) ?>)</li>
      <li><strong>audit_log</strong> <?= e(t('> 18 meses ·')) ?> <strong>wise_eventos</strong> <?= e(t('processados > 6 meses ·')) ?> <strong>regua_eventos</strong> <?= e(t('> 1 ano')) ?></li>
      <li><strong>senha_resets</strong> <?= e(t('usados > 30 dias ·')) ?> <strong>totp_backup_codes</strong> <?= e(t('usados > 90 dias ·')) ?> <strong>convites</strong> <?= e(t('aceitos > 30 dias')) ?></li>
      <li><strong><?= e(t('Tabela com tamanho atual')) ?></strong> <?= e(t('de cada tabela (linhas + KB) pra você ver o que tá crescendo')) ?></li>
      <li><strong><?= e(t('Botão "Rodar limpeza agora"')) ?></strong> <?= e(t('pra forçar fora do cron')) ?></li>
    </ul>
    <p class="hint" style="color:var(--c-success);">✓ <?= e(t('Dados financeiros (cobranças, pagamentos, distribuição) NUNCA são apagados.')) ?></p>
  </div>

<?php elseif ($role === 'admin'): ?>

  <div class="card brand">
    <div class="title">⚙ <?= e(t('Você é Admin')) ?></div>
    <div class="desc"><?= e(t('Gerencia clientes, cobranças, equipe e painel financeiro. Recebe lucro como sócio. Configurações sensíveis (templates, formas de pagto, IA) são feitas pelo sadmin.')) ?></div>
  </div>

  <h2>🏠 <?= e(t('Início (dashboard)')) ?></h2>
  <div class="card">
    <p><strong>🔮 <?= e(t('Previsão do mês')) ?></strong> + <?= e(t('alertas + KPIs financeiros.')) ?></p>
    <p><strong>📋 <?= e(t('Link do sistema')) ?></strong> <?= e(t('copiável pra enviar a clientes.')) ?></p>
  </div>

  <h2>👥 <?= e(t('Clientes')) ?></h2>
  <div class="card">
    <p><strong>+ <?= e(t('Novo')) ?></strong> <?= e(t('ou')) ?> <strong><?= e(t('Convidar')) ?></strong>. <?= e(t('Detalhe do cliente tem assinaturas, cobranças, dados.')) ?></p>
    <p><?= e(t('Ao trocar moeda do cliente, valores das assinaturas se convertem automaticamente.')) ?></p>
    <p><strong><?= e(t('Apagar:')) ?></strong> <?= e(t('bloqueado se há pagamentos confirmados. Use "Desativar" pra preservar histórico.')) ?></p>
  </div>

  <h2>📦 <?= e(t('Catálogo')) ?></h2>
  <div class="card">
    <p><?= e(t('USD é moeda mestre — só preencha USD, BRL/EUR são calculados.')) ?></p>
    <p><strong>📊 <?= e(t('Simulador de preço')) ?></strong> <?= e(t('com IA pra te ajudar a precificar serviços novos.')) ?></p>
    <p><strong>⚠ <?= e(t('Variante IA:')) ?></strong> <?= e(t('se marcar "tem variante com IA", o preço IA em USD vira obrigatório (sistema bloqueia o save sem ele).')) ?></p>
  </div>

  <h2>📊 <?= e(t('Painel financeiro')) ?></h2>
  <div class="card">
    <p><?= e(t('3 abas (Agenda · Por cliente · Por serviço), gráfico de saúde + cards por moeda com lucro líquido.')) ?></p>
  </div>

  <h2>💳 <?= e(t('Cobranças')) ?></h2>
  <div class="card">
    <p><?= e(t('Geração automática 7 dias antes. Comprovante do cliente vai pra "Em análise" → aceita/rejeita. WhatsApp com template editável.')) ?></p>
    <p><strong>🪝 <?= e(t('Pagamentos via Wise:')) ?></strong> <?= e(t('webhook detecta automaticamente e marca como pendente. Reconcilia no card laranja do dashboard ou em')) ?> <code>/wise_eventos.php</code> (<?= e(t('Confirmar/Rejeitar')) ?>).</p>
    <p><strong><?= e(t('Cobrança paga')) ?></strong> <?= e(t('não pode ser cancelada (estorne primeiro).')) ?> <strong><?= e(t('Cobrança avulsa')) ?></strong> <?= e(t('não aceita vencimento no passado.')) ?></p>
  </div>

  <h2>🧑‍💼 <?= e(t('Equipe')) ?></h2>
  <div class="card">
    <p><strong>💵 <?= e(t('Pagamentos:')) ?></strong> <?= e(t('fila pra liberar pagamento em USD aos funcionários. Email automático com PDF.')) ?></p>
    <p><strong><?= e(t('Funcionário se autorregula:')) ?></strong> <?= e(t('ele edita capacidade mensal e flag "aceitando novos clientes" no próprio perfil. Ao tentar atribuir cliente a alguém marcado 🔴, sistema bloqueia e pede confirmação explícita.')) ?></p>
  </div>

  <h2>📋 <?= e(t('Acompanhamento geral')) ?></h2>
  <div class="card">
    <p><?= e(t('Visão consolidada do que cada funcionário está executando. 3 vistas: Lista · Por pessoa · 🗓 Calendário (grid mensal colorido por funcionário).')) ?></p>
  </div>

  <h2>🔔 <?= e(t('Alertas automáticos pra funcionários')) ?></h2>
  <div class="card">
    <p><?= e(t('Sistema envia')) ?> <strong><?= e(t('email automático')) ?></strong> <?= e(t('pros funcionários toda quarta e sexta às 09:00 quando eles têm assinaturas de POSTAGEM ativas sem nenhuma entrega marcada na semana.')) ?> <em><?= e(t('Você não precisa fazer nada')) ?></em> — <?= e(t('o cron cuida disso. Audit_log registra cada execução.')) ?></p>
    <p><?= e(t('Se quiser disparar fora dos horários (ex: terça-feira), pode pedir pro sadmin rodar pela tela')) ?> <code>/alertas.php</code>.</p>
  </div>

  <h2>💎 <?= e(t('Distribuição de lucro')) ?></h2>
  <div class="card">
    <p><?= e(t('Sua quota = lucro do mês ÷ (nº sócios + 1). Botão Pagar registra recebimento.')) ?></p>
    <p><strong><?= e(t('Trava:')) ?></strong> <?= e(t('sistema bloqueia valor maior que a quota disponível na competência (mostra quota total / já pago / disponível no erro).')) ?></p>
  </div>

  <h2>🔐 <?= e(t('Segurança')) ?></h2>
  <div class="card">
    <p><strong><?= e(t('2FA (recuperação):')) ?></strong> <?= e(t('ative em')) ?> <em><?= e(t('Minha conta → 🔐 Segurança')) ?></em>. <?= e(t('NÃO é pedido no login normal — serve como alternativa ao email se você esquecer a senha (use "Esqueci minha senha → Via 2FA").')) ?></p>
    <p><strong>🔑 <?= e(t('Backup codes:')) ?></strong> <?= e(t('ao ativar 2FA o sistema gera 8 códigos one-time-use. Salve em local seguro pra usar caso perca o celular.')) ?></p>
  </div>

<?php elseif ($role === 'funcionario'): ?>

  <div class="card brand">
    <div class="title">💼 <?= e(t('Você é Funcionário')) ?></div>
    <div class="desc"><?= e(t('Executa entregas dos serviços na sua agenda. Recebe em USD via WiseTag.')) ?></div>
  </div>

  <h2>🏠 <?= e(t('Início (dashboard)')) ?></h2>
  <div class="card">
    <p><strong>🔮 <?= e(t('Previsão de recebimento:')) ?></strong> <?= e(t('USD que vai receber este mês pelas assinaturas ativas.')) ?></p>
    <p><strong>KPIs:</strong> <?= e(t('clientes que atende · serviços ativos · entregas do mês · a receber.')) ?></p>
  </div>

  <h2>📅 <?= e(t('Agenda')) ?></h2>
  <div class="card">
    <p><?= e(t('Itens pra entregar no mês, agrupados por cliente. Checkbox em cada um — marque ao concluir.')) ?></p>
    <p><?= e(t('Indicador 🟢/🔴 mostra se está dentro da capacidade declarada por você no perfil.')) ?></p>
    <p><strong>👥 <?= e(t('Trabalha em dupla?')) ?></strong> <?= e(t('Se você foi vinculado a alguém pelo admin, vê dois botões no topo:')) ?>
      <ul style="padding-left:20px; color:var(--txt-2);">
        <li><strong><?= e(t('Minha agenda')) ?></strong> — <?= e(t('clientes atribuídos diretamente a você')) ?></li>
        <li><strong><?= e(t('Agenda do [parceiro]')) ?></strong> — <?= e(t('agenda da dupla (pagamento vai todo pro principal; combinem off-platform)')) ?></li>
      </ul>
    </p>
  </div>

  <h2>📊 <?= e(t('Painel')) ?></h2>
  <div class="card">
    <p><?= e(t('Em')) ?> <em><?= e(t('Painel')) ?></em> <?= e(t('você vê suas entregas dos últimos meses, valor recebido e previsão.')) ?></p>
  </div>

  <h2>📧 <?= e(t('Lembretes automáticos por email')) ?></h2>
  <div class="card">
    <p><?= e(t('Toda')) ?> <strong><?= e(t('quarta e sexta às 09:00')) ?></strong>, <?= e(t('o sistema verifica se você tem assinaturas de')) ?> <strong><?= e(t('POSTAGEM ativas sem nenhuma entrega marcada nessa semana')) ?></strong>. <?= e(t('Se houver, você recebe um email com:')) ?></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><?= e(t('Lista dos clientes pendentes')) ?></li>
      <li><?= e(t('Botão direto pra abrir sua agenda')) ?></li>
    </ul>
    <p class="hint"><?= e(t('Pra não receber o email: marque suas entregas na agenda durante a semana (mesmo que parcial). Se você já entregou tudo e marcou, o sistema sabe que tá em dia e não envia nada.')) ?></p>
  </div>

  <h2>🧭 <?= e(t('Navegação')) ?></h2>
  <div class="card">
    <p><strong>← <?= e(t('Voltar:')) ?></strong> <?= e(t('em qualquer tela (exceto dashboard), o botão ← no canto superior esquerdo volta pra tela anterior (usa o histórico do navegador). Funciona como o "voltar" do navegador.')) ?></p>
    <p><strong>🏠 <?= e(t('Logo:')) ?></strong> <?= e(t('no dashboard, o logo aparece no canto. Em outras telas, clica em "Início" no menu inferior pra voltar pro dashboard.')) ?></p>
  </div>

  <h2>💵 <?= e(t('Meus pagamentos')) ?></h2>
  <div class="card">
    <p><?= e(t('Histórico do que já recebeu + previsão das entregas. Cada pagamento tem PDF de comprovante.')) ?></p>
  </div>

  <h2>👤 <?= e(t('Minha conta')) ?></h2>
  <div class="card">
    <p><strong>WiseTag:</strong> <?= e(t('obrigatório pra receber pagamento.')) ?> <strong><?= e(t('Você edita em')) ?> <em><?= e(t('Perfil')) ?></em></strong> (<?= e(t('campos WiseTag/CPF/País')) ?>). <?= e(t('Se estiver vazio, aparece alerta vermelho avisando.')) ?></p>
    <p><strong>📊 <?= e(t('Capacidade mensal:')) ?></strong> <?= e(t('também no')) ?> <em><?= e(t('Perfil')) ?></em>, <?= e(t('abaixo dos dados pessoais. Informe quantos Criativos, Pacotes POSTAGEM e Sites/Projetos você absorve por mês. Sistema usa pra mostrar 🟢/🔴 quando admin tentar te atribuir um cliente.')) ?> <strong><?= e(t('Mantenha atualizado.')) ?></strong></p>
    <p><strong>🟢/🔴 <?= e(t('Aceitando novos clientes:')) ?></strong> <?= e(t('checkbox no')) ?> <em><?= e(t('Perfil')) ?></em>. <?= e(t('Marque quando estiver com capacidade, desmarque quando estiver no limite. Sistema avisa o admin com alerta antes de tentar te atribuir novo cliente — admin pode forçar a exceção se quiser.')) ?></p>
    <p><strong>🔐 <?= e(t('2FA + backup codes (recuperação):')) ?></strong> <?= e(t('ative em')) ?> <em><?= e(t('Minha conta → Segurança')) ?></em>. <?= e(t('Não é pedido no login — serve pra recuperar conta se esquecer a senha. Os 8 backup codes te salvam se perder o celular — salva eles em local seguro.')) ?></p>
  </div>

<?php else: // cliente ?>

  <div class="card brand">
    <div class="title">🤝 <?= e(t('Bem-vindo cliente!')) ?></div>
    <div class="desc"><?= e(t('Acompanha cobranças, envia comprovantes, vê entregas e o que contratou.')) ?></div>
  </div>

  <h2>🏠 <?= e(t('Início (dashboard)')) ?></h2>
  <div class="card">
    <p><strong>🔮 <?= e(t('Previsão de gastos:')) ?></strong> <?= e(t('total deste mês (pago + aberto + análise + assinaturas).')) ?></p>
    <p><strong><?= e(t('Meus serviços contratados')) ?></strong> <?= e(t('com quem executa cada um.')) ?></p>
  </div>

  <h2>💳 <?= e(t('Cobranças')) ?></h2>
  <div class="card">
    <p><strong><?= e(t('Como pagar:')) ?></strong> <?= e(t('abra a cobrança e veja:')) ?></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>💜 Zelle:</strong> <?= e(t('QR Code (escaneia no app do banco) + email pra copiar.')) ?> <em><?= e(t('Envie comprovante depois pelo botão.')) ?></em></li>
      <li><strong>🌍 Wise:</strong> <?= e(t('botão "Abrir Wise" — preenche o valor exato da cobrança e paga.')) ?> <strong><?= e(t('Não precisa enviar comprovante')) ?></strong> — <?= e(t('o sistema detecta automaticamente.')) ?></li>
    </ul>
    <p><?= e(t('Comprovante (Zelle) vai pra "Em análise" e a Dite Ads confirma em até 1 dia útil.')) ?></p>
  </div>

  <h2>✅ <?= e(t('Entregas')) ?></h2>
  <div class="card">
    <p><?= e(t('O que está sendo produzido pra você no mês (criativos, postagens, sites). Cada item mostra se já foi entregue.')) ?></p>
  </div>

  <h2>👤 <?= e(t('Minha conta')) ?></h2>
  <div class="card">
    <p><?= e(t('Atualize nome e senha. Email é fixo (avise a Dite Ads se precisar trocar).')) ?></p>
    <p><strong>🔐 <?= e(t('2FA (opcional, recuperação):')) ?></strong> <?= e(t('ative em')) ?> <em><?= e(t('Minha conta → Segurança')) ?></em> <?= e(t('pra ter um app autenticador como alternativa ao email caso você esqueça a senha. Sistema gera 8 backup codes pra guardar caso perca o celular.')) ?></p>
    <p><strong><?= e(t('Esqueceu a senha?')) ?></strong> <?= e(t('Use "Esqueci a senha" no login — pode escolher: receber link por email OU entrar direto com código do app 2FA (se tiver ativo).')) ?></p>
  </div>

  <h2>🧭 <?= e(t('Navegação')) ?></h2>
  <div class="card">
    <p><strong>← <?= e(t('Voltar:')) ?></strong> <?= e(t('em qualquer tela tem o botão ← no canto superior esquerdo que volta pra tela anterior.')) ?></p>
    <p><strong><?= e(t('Menu inferior:')) ?></strong> <?= e(t('Início · Cobranças · Entregas · Perfil — atalho rápido pras 4 áreas principais.')) ?></p>
    <p><strong>🔍 <?= e(t('Busca (Ctrl+K):')) ?></strong> <?= e(t('achar rápido qualquer cobrança ou entrega digitando o nome ou número.')) ?></p>
  </div>

  <h2>💬 <?= e(t('Precisa de ajuda?')) ?></h2>
  <div class="card">
    <p><?= e(t('Email')) ?> <a href="mailto:contact@diteads.com">contact@diteads.com</a> <?= e(t('ou WhatsApp da Dite Ads.')) ?></p>
    <p><?= e(t('Se algo no sistema não funcionar como esperado, manda print pra gente resolver rápido.')) ?></p>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
