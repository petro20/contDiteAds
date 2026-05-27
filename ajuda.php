<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
$u = require_login();

$page = 'Ajuda';
$nav_active = '';
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';

$role = $u['role'];
$role_label = [
    'sadmin'      => 'Super Admin',
    'admin'       => 'Admin',
    'funcionario' => 'Funcionário',
    'cliente'     => 'Cliente',
][$role] ?? $role;
?>
<h1 class="page-title">Minha conta</h1>
<?php render_group_tabs('conta', 'ajuda'); ?>
<h2>Como usar o sistema</h2>
<p class="muted">Guia para o perfil <strong><?= e($role_label) ?></strong>. Olá, <?= e($u['nome']) ?>!</p>

<?php if ($role === 'sadmin'): ?>

  <div class="card brand">
    <div class="title">👑 Você é Super Admin</div>
    <div class="desc">Acesso total: gerencia equipe, catálogo, finanças, distribuição de lucro, despesas, comunicação e segurança. É o único que pode apagar dados em cascata e promover usuários.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão do mês</strong> (card no topo): mostra <em>Recebimento</em> previsto, <em>Pagamento</em> previsto (despesas + funcionários) e <em>Lucro previsto</em>. Tudo baseado em assinaturas ativas + cobranças + despesas cadastradas.</p>
    <p><strong>Alertas automáticos</strong> (só aparecem se houver):</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li>🔔 Comprovantes em análise</li>
      <li>💢 Cobranças vencidas (com totais por moeda)</li>
      <li>💳 Funcionários sem WiseTag com USD a receber</li>
      <li>📊 Funcionários acima da capacidade declarada</li>
      <li>💎 Lucro pendente de distribuir após dia 5</li>
    </ul>
    <p><strong>KPIs clicáveis:</strong> Recebido · A receber · A pagar funcionários · Lucro do mês.</p>
    <p><strong>📋 Link do sistema:</strong> card com botão Copiar pra usar nas mensagens. Nos templates use a variável <code>{link_sistema}</code>.</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>+ Novo</strong> ou <strong>✉️ Convidar</strong> (link de cadastro por email).</p>
    <p><strong>Moeda do cliente:</strong> ao trocar (ex: USD → BRL), o sistema recalcula automaticamente o <code>valor_cobrado</code> de todas as assinaturas ativas usando a cotação do dia. Mensagem de sucesso mostra a taxa usada.</p>
    <p><strong>⚠ Apagar em cascata (só sadmin):</strong> deleta cliente + cobranças + assinaturas + entregas + login. Sem volta — prefira desativar pra preservar histórico.</p>
  </div>

  <h2>📦 Catálogo</h2>
  <div class="card">
    <p><strong>USD é a moeda mestre.</strong> Você preenche só o preço em USD. O sistema calcula BRL e EUR automaticamente usando a cotação do dia, arredondando pra cima (ceil — sem centavos).</p>
    <p><strong>📊 Simulador de preço</strong> (ao lado de + Novo item): lista os custos do serviço, define margem (%), e o sistema sugere o preço final. Botão "Criar item no catálogo" leva pra novo item pré-preenchido.</p>
    <p><strong>🔄 Recalcular preços:</strong> botão que percorre todos os itens e atualiza BRL/EUR com a cotação atual (de uma vez).</p>
    <p><strong>Tipos de item:</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>unico</strong> — setup (logotipo, landing page, branding)</li>
      <li><strong>mensal</strong> — serviços recorrentes (ads, postagens)</li>
      <li><strong>por_unidade</strong> — variáveis (criativos avulsos)</li>
    </ul>
  </div>

  <h2>🧑‍💼 Equipe (3 abas)</h2>
  <div class="card">
    <p><strong>👥 Lista:</strong> cadastra usuários. Defina perfil (funcionário/admin/sadmin), WiseTag (obrigatório pra USD), capacidade mensal e o valor USD que ele recebe por item (ordenado UNICO → MENSAL → POR_UNIDADE pela jornada do cliente).</p>
    <p><strong>📊 Capacidade:</strong> overview de quanto cada funcionário absorve por mês vs. ocupação atual.</p>
    <p><strong>💵 Pagamentos:</strong> fila com itens entregues prontos pra pagar (USD). Email automático com PDF do comprovante é enviado ao funcionário.</p>
    <p><strong>⚠ Apagar em cascata (só sadmin):</strong> deleta pagamentos recebidos, entregas, capacidades. Cobranças/despesas que ele criou são reatribuídas a você. Trava: não dá pra apagar a si mesmo nem o último sadmin.</p>
  </div>

  <h2>💰 Finanças (3 abas)</h2>
  <div class="card">
    <p><strong>💸 Despesas:</strong> gastos da empresa (ferramentas, software, marketing) com recorrência única/mensal/anual. Entram no cálculo de lucro + previsão.</p>
    <p><strong>💎 Distribuição:</strong> cada admin/sadmin = 1 quota; empresa também = 1 quota. Total = N+1 quotas. Botão "Pagar" registra recebimento por sócio/moeda. Sadmin pode apagar lançamentos errados.</p>
    <p><strong>💳 Formas de pagamento:</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>💱 Cotação USD:</strong> mostra USD→BRL e USD→EUR atual, com botão de atualizar agora. Fonte: AwesomeAPI (cache diário).</li>
      <li><strong>Zelle:</strong> email + QR Code (upload de imagem PNG/JPG até 2MB).</li>
      <li><strong>Wise:</strong> link público de pagamento.</li>
      <li><strong>Instruções extras:</strong> texto livre que aparece na cobrança.</li>
    </ul>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p><strong>3 abas:</strong> Agenda · Por cliente · Por serviço.</p>
    <p><strong>Gráfico de saúde:</strong> 1m / 3m / 6m / 1a / Tudo. 4 séries: Receita (verde), Despesa (vermelho), Pago sócios (roxo), Lucro (linha azul).</p>
    <p><strong>Cards por moeda:</strong> Entradas − Saídas = Lucro líquido. Abaixo, distribuição já paga + saldo final.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Geração automática:</strong> cron diário às 5h emite cobranças 7 dias antes do vencimento.</p>
    <p><strong>Manual:</strong> botão <em>Marcar como paga</em> ou <em>Nova cobrança avulsa</em>.</p>
    <p><strong>Detalhe da cobrança</strong> (cliente vê): mostra QR Zelle + email + link Wise com passo a passo de pagamento e botão de copiar.</p>
    <p><strong>Comprovante:</strong> cliente sobe foto/PDF → cobrança vai pra "Em análise" → alerta no seu dashboard pra aceitar/rejeitar.</p>
  </div>

  <h2>💬 Comunicação (3 abas)</h2>
  <div class="card">
    <p><strong>⏰ Etapas:</strong> régua automática. Configure quando dispara cada etapa usando dias relativos ao vencimento:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>negativo</strong> = antes do vencimento (ex: −3 = 3 dias antes)</li>
      <li><strong>zero</strong> = no dia do vencimento</li>
      <li><strong>positivo</strong> = após vencer</li>
    </ul>
    <p>Botões ↑↓ pra reordenar etapas com 1 clique.</p>
    <p><strong>📤 Tarefas:</strong> fila WhatsApp pendente. Botão "Abrir WhatsApp" com mensagem já renderizada.</p>
    <p><strong>📝 Templates:</strong> CRUD de templates email/whatsapp. Botão <strong>✨ Instalar templates de pagamento</strong> cria templates padrão completos com Zelle + Wise + QR. Sadmin pode apagar templates (régua se desconecta automaticamente).</p>
  </div>

  <h2>🔐 Segurança e auditoria</h2>
  <div class="card">
    <p><strong>2FA:</strong> ative em <em>Minha conta → 🔐 Segurança</em>. Fortemente recomendado.</p>
    <p><strong>🔍 Auditoria:</strong> histórico de tudo (criação, edição, deleção) feito pelos usuários. Só sadmin acessa.</p>
  </div>

<?php elseif ($role === 'admin'): ?>

  <div class="card brand">
    <div class="title">⚙ Você é Admin</div>
    <div class="desc">Gerencia clientes, cobranças, agenda e painel financeiro. Recebe sua parte do lucro como sócio. Pra apagar dados em cascata e configurar templates/régua, fale com o sadmin.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão do mês:</strong> recebimento previsto − pagamento previsto = lucro previsto. Atualiza automático conforme assinaturas e despesas.</p>
    <p><strong>Alertas no topo:</strong> comprovantes em análise · cobranças vencidas · funcionários sem WiseTag · sobrecarga · distribuição pendente.</p>
    <p><strong>KPIs:</strong> Recebido · A receber · A pagar funcionários · Lucro do mês.</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>+ Novo</strong> ou <strong>Convidar</strong>. No detalhe do cliente: assinaturas, cobranças, dados.</p>
    <p>Ao trocar moeda do cliente, sistema converte automaticamente os valores das assinaturas ativas usando cotação do dia.</p>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p>3 abas: Agenda · Por cliente · Por serviço. Gráfico com 4 séries e seletor 1m/3m/6m/1a/Tudo. Cards por moeda com lucro líquido + distribuição.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p>Geração automática 7 dias antes da data. Manual via assinatura → <em>Gerar agora</em>. Comprovante do cliente vai pra "Em análise" → você aceita ou rejeita.</p>
    <p>WhatsApp: botão direto com template editável (sadmin configura).</p>
  </div>

  <h2>🧑‍💼 Equipe (Pagamentos)</h2>
  <div class="card">
    <p><strong>💵 Pagamentos a funcionários:</strong> fila de itens prontos pra pagar (USD). Marca a data, confirma. Funcionário recebe email com PDF do comprovante.</p>
    <p>Itens só entram na fila quando a cobrança correspondente está paga.</p>
  </div>

  <h2>💎 Distribuição de lucro</h2>
  <div class="card">
    <p>Sua quota = lucro do mês ÷ (nº de sócios + 1). Botão <strong>Pagar</strong> registra o recebimento. Pendentes ficam visíveis até pagar.</p>
  </div>

  <h2>🔐 Segurança</h2>
  <div class="card">
    <p>Ative 2FA em <em>Minha conta → 🔐 Segurança</em>. Fortemente recomendado.</p>
  </div>

<?php elseif ($role === 'funcionario'): ?>

  <div class="card brand">
    <div class="title">💼 Você é Funcionário</div>
    <div class="desc">Executa entregas dos serviços que estão na sua agenda. Recebe em USD via WiseTag.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão de recebimento:</strong> total USD que você vai receber este mês baseado nas suas assinaturas ativas. <em>Se a assinatura está ativa, o valor já é certo.</em></p>
    <p><strong>KPIs:</strong> clientes que atende · serviços ativos · entregas do mês · a receber (USD).</p>
  </div>

  <h2>📅 Agenda</h2>
  <div class="card">
    <p>Lista de itens pra entregar no mês, agrupados por cliente. Checkbox em cada item — marque quando concluir.</p>
    <p><strong>Importante:</strong> só itens marcados como concluídos contam pro seu pagamento.</p>
    <p>Indicador 🟢/🔴 mostra se você está dentro da capacidade declarada.</p>
  </div>

  <h2>💵 Meus pagamentos</h2>
  <div class="card">
    <p>Histórico do que já recebeu + previsão das entregas do mês ainda não pagas. Cada pagamento tem PDF do comprovante (baixe e guarde).</p>
  </div>

  <h2>👤 Minha conta</h2>
  <div class="card">
    <p><strong>WiseTag:</strong> obrigatório pra receber em USD.</p>
    <p><strong>Aceitando clientes:</strong> ative/desative no seu perfil quando estiver cheio.</p>
    <p><strong>🔐 Segurança:</strong> ative 2FA (recomendado).</p>
    <p><strong>❓ Ajuda:</strong> esta tela.</p>
  </div>

<?php else: // cliente ?>

  <div class="card brand">
    <div class="title">🤝 Bem-vindo cliente!</div>
    <div class="desc">Aqui você acompanha cobranças, envia comprovantes de pagamento, vê entregas e o status do que contratou.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão de gastos:</strong> total que você vai pagar este mês (já pago + em aberto + em análise + assinaturas ativas).</p>
    <p><strong>KPIs:</strong> Em aberto · Vencidas (em vermelho se houver).</p>
    <p><strong>Meus serviços contratados:</strong> lista das assinaturas ativas com quem executa cada uma.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Como pagar:</strong> abra a cobrança e veja as opções configuradas pela Dite Ads:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>💜 Zelle:</strong> QR Code pra escanear no app do banco + email pra copiar</li>
      <li><strong>🌍 Wise:</strong> link clicável que abre página de pagamento</li>
    </ul>
    <p>Depois envie o <strong>comprovante</strong> (foto/PDF) pelo botão. A cobrança vai pra "Em análise" e o admin confirma.</p>
    <p><strong>Status:</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Aberta:</strong> aguardando pagamento</li>
      <li><strong>Em análise:</strong> você enviou comprovante, esperando aprovação</li>
      <li><strong>Paga:</strong> tudo certo (PDF de recibo disponível)</li>
      <li><strong>Vencida:</strong> passou da data — regularize</li>
    </ul>
  </div>

  <h2>✅ Entregas</h2>
  <div class="card">
    <p>Lista do que está sendo produzido pra você no mês (criativos, postagens, sites). Cada item mostra se já foi entregue ou está em andamento.</p>
  </div>

  <h2>👤 Minha conta</h2>
  <div class="card">
    <p>Atualize seu nome e senha. Email é fixo.</p>
  </div>

  <h2>💬 Precisa de ajuda?</h2>
  <div class="card">
    <p>Entre em contato pelo email <a href="mailto:contact@diteads.com">contact@diteads.com</a> ou WhatsApp da Dite Ads. A gente responde rápido!</p>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
