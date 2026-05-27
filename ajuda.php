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
    <div class="desc">Acesso total: gerencia equipe, catálogo, finanças, distribuição de lucro, despesas e segurança. É o único que pode apagar dados em cascata e promover usuários.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Card de Previsão do mês</strong> (no topo) — mostra 3 linhas: <em>Recebimento</em> (recebido + cobranças + assinaturas ativas), <em>Pagamento</em> (despesas + funcionários) e <em>Lucro previsto</em>. Sempre baseado no mês corrente.</p>
    <p><strong>Alertas automáticos</strong> (só aparecem se houver ocorrência):</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li>🔔 <strong>Comprovantes em análise</strong> — clientes esperando aprovação</li>
      <li>💢 <strong>Cobranças vencidas</strong> — em vermelho, com totais por moeda</li>
      <li>💳 <strong>Funcionários sem WiseTag</strong> mas com USD a receber</li>
      <li>📊 <strong>Funcionários sobrecarregados</strong> — acima da capacidade declarada</li>
      <li>💎 <strong>Lucro pendente de distribuir</strong> — após dia 5 do mês</li>
    </ul>
    <p><strong>KPIs financeiros</strong> (clicáveis): Recebido, A receber, A pagar funcionários (USD), Lucro do mês.</p>
    <p><strong>Ações rápidas + Sociedade + Minha área de execução</strong>: atalhos pra tudo.</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>Cadastrar:</strong> botão <em>+ Novo</em> ou <em>✉️ Convidar</em> (manda link de cadastro por email).</p>
    <p><strong>Editar:</strong> clique no card. Lá você gerencia assinaturas, cobranças e dados.</p>
    <p><strong>Assinaturas:</strong> defina serviço, valor, moeda, dia de cobrança. Cobranças mensais são geradas automaticamente 7 dias antes do vencimento.</p>
    <p><strong>⚠ Apagar em cascata (só sadmin):</strong> deleta cliente + todas as cobranças, assinaturas, entregas e o login dele. Use só se realmente precisar — para preservar histórico, prefira desativar.</p>
  </div>

  <h2>📦 Catálogo</h2>
  <div class="card">
    <p>Itens vendidos pela empresa (criativos, postagens, sites, pacotes). Cada item tem preço em BRL/USD/EUR e tipo:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>unico</strong> — setup feito uma vez (logotipo, landing page, branding)</li>
      <li><strong>mensal</strong> — serviços recorrentes (ads, postagens)</li>
      <li><strong>por_unidade</strong> — variáveis (criativos avulsos)</li>
    </ul>
    <p>Itens <em>inativos</em> ficam ocultos para novas assinaturas, mas continuam funcionando nas existentes.</p>
  </div>

  <h2>💼 Funcionários, Sócios e Equipe</h2>
  <div class="card">
    <p><strong>+ Novo:</strong> cadastra usuário. Defina:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Perfil</strong>: funcionário / admin (vira sócio na divisão) / sadmin</li>
      <li><strong>WiseTag</strong>: obrigatório pra receber em USD</li>
      <li><strong>Capacidade mensal</strong>: quantos itens por categoria ele absorve</li>
      <li><strong>Valor que recebe (USD) por item</strong>: tabela ordenada UNICO → MENSAL → POR_UNIDADE (jornada do cliente)</li>
    </ul>
    <p><strong>⚠ Apagar em cascata (só sadmin):</strong> deleta funcionário + pagamentos recebidos, entregas executadas, capacidades e valores. Cobranças/despesas que ele criou são reatribuídas a você.</p>
    <p><strong>Trava:</strong> não dá pra apagar a si mesmo nem o último sadmin ativo.</p>
  </div>

  <h2>💸 Despesas da empresa</h2>
  <div class="card">
    <p>Cadastra gastos (ferramentas, software, marketing) com 3 tipos de recorrência: <em>única, mensal, anual</em>. Entram automaticamente no cálculo de lucro do mês e na previsão.</p>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p><strong>3 abas:</strong> Agenda (entregas do mês) · Por cliente · Por serviço (só itens com cliente ativo).</p>
    <p><strong>Gráfico de saúde:</strong> botões 1m / 3m / 6m / 1a / Tudo. 4 séries: Receita (verde), Despesa (vermelho), Pago sócios (roxo), Lucro (linha azul).</p>
    <p><strong>Cards por moeda (BRL/USD/EUR):</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Entradas:</strong> Recebido · Em análise · A receber</li>
      <li><strong>Saídas:</strong> Despesas · Pagos a funcionários</li>
      <li><strong>💎 Lucro líquido (antes de distribuir)</strong></li>
      <li><strong>Distribuição já paga:</strong> sócio por sócio + total + saldo após distribuição</li>
    </ul>
  </div>

  <h2>💎 Distribuição de lucro</h2>
  <div class="card">
    <p>Cada admin/sadmin = 1 quota. Empresa também recebe 1 quota (retenção). Total = N+1 quotas.</p>
    <p><strong>Pagar:</strong> botão por sócio, define valor/moeda/data/observação.</p>
    <p><strong>⚠ Apagar lançamentos (só sadmin):</strong> seção "Pagamentos lançados em [mês]" tem botão 🗑 em cada linha pra corrigir lançamentos errados.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Geração automática:</strong> cron diário às 5h emite cobranças 7 dias antes do vencimento.</p>
    <p><strong>Pagamento manual:</strong> botão <em>Marcar como paga</em> no detalhe.</p>
    <p><strong>Comprovante do cliente:</strong> quando cliente sobe foto/PDF, vai pra <em>Em análise</em> + alerta no dashboard. Aceite ou rejeite.</p>
    <p><strong>Avulsa:</strong> botão <em>Nova cobrança avulsa</em> permite cobrar fora do ciclo mensal.</p>
  </div>

  <h2>💵 Pagamentos a funcionários</h2>
  <div class="card">
    <p>Fila com itens entregues e prontos pra pagar (USD). Selecione, marque data e confirme.</p>
    <p>Email automático com link do comprovante PDF é enviado ao funcionário.</p>
    <p><strong>⚠ Apagar pagamento (só sadmin):</strong> na tela de detalhe tem botão "🗑 Apagar este pagamento" na Zona de perigo. Os itens voltam pra fila como pendentes.</p>
  </div>

  <h2>🔐 Segurança e auditoria</h2>
  <div class="card">
    <p><strong>2FA:</strong> ative em <em>Perfil → Configurar 2FA</em>. Fortemente recomendado.</p>
    <p><strong>Auditoria:</strong> menu Sadmin → 🔍 Auditoria. Tudo que admins fazem fica registrado.</p>
  </div>

<?php elseif ($role === 'admin'): ?>

  <div class="card brand">
    <div class="title">⚙ Você é Admin</div>
    <div class="desc">Gerencia clientes, cobranças, agenda e painel financeiro. Recebe sua parte do lucro como sócio. Para apagar dados em cascata e configurar templates/régua, fale com o sadmin.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Card de Previsão do mês:</strong> recebimento previsto − pagamento previsto = lucro previsto. Atualiza automático conforme assinaturas e despesas.</p>
    <p><strong>Alertas automáticos</strong> (no topo, só aparecem se houver):</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li>🔔 Comprovantes em análise — clientes esperando aprovação</li>
      <li>💢 Cobranças vencidas (vermelho)</li>
      <li>💳 Funcionários sem WiseTag mas com USD a receber</li>
      <li>📊 Funcionários sobrecarregados</li>
      <li>💎 Lucro pendente de distribuir</li>
    </ul>
    <p><strong>KPIs:</strong> Recebido este mês · A receber · A pagar funcionários · Lucro do mês.</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>+ Novo:</strong> cadastra direto. <strong>Convidar:</strong> envia link por email.</p>
    <p>No detalhe do cliente: assinaturas, histórico de cobranças, dados de contato.</p>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p>3 abas: <strong>Agenda</strong> (entregas do mês), <strong>Por cliente</strong>, <strong>Por serviço</strong>.</p>
    <p><strong>Gráfico:</strong> 1m / 3m / 6m / 1a / Tudo. Mostra Receita · Despesa · Pago sócios · Lucro.</p>
    <p><strong>Cards por moeda:</strong> Entradas (recebido + em análise + a receber) − Saídas (despesas + pag funcionários) = Lucro líquido. Abaixo, distribuição já paga + saldo.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Geração:</strong> automática 7 dias antes da data. Manual: ir na assinatura → <em>Gerar agora</em>.</p>
    <p><strong>Comprovante:</strong> quando cliente sobe, fica <em>Em análise</em>. Aceite (vira <em>Paga</em>) ou rejeite (volta a <em>Aberta</em>).</p>
    <p><strong>WhatsApp:</strong> botão que abre conversa com cliente usando template editável.</p>
  </div>

  <h2>💵 Pagar funcionários</h2>
  <div class="card">
    <p><strong>Pagamentos funcionários:</strong> fila com itens entregues e prontos pra pagar (USD). Marca a data, confirma. Funcionário recebe email com link do comprovante PDF.</p>
    <p>Itens só entram na fila quando a cobrança correspondente está paga.</p>
  </div>

  <h2>💎 Distribuição de lucro</h2>
  <div class="card">
    <p>Sua quota = lucro do mês ÷ (nº de sócios + 1). Botão <strong>Pagar</strong> registra o recebimento. Quem não recebeu fica como <em>Pendente</em>.</p>
  </div>

  <h2>🔐 Segurança</h2>
  <div class="card">
    <p>Ative 2FA em <em>Perfil → Configurar 2FA</em>. Fortemente recomendado.</p>
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
    <p><strong>Acesso rápido:</strong> Agenda · Meus clientes · Meus pagamentos.</p>
  </div>

  <h2>📅 Agenda</h2>
  <div class="card">
    <p>Lista dos itens pra entregar no mês, agrupados por cliente. Checkbox em cada um → marque quando concluir.</p>
    <p><strong>Importante:</strong> só itens marcados como concluídos contam pro seu pagamento.</p>
    <p>Indicador 🟢/🔴 mostra se você está dentro da capacidade declarada ou cheio.</p>
  </div>

  <h2>💵 Meus pagamentos</h2>
  <div class="card">
    <p>Histórico do que o admin já te pagou + previsão das entregas do mês ainda não pagas.</p>
    <p>Cada pagamento tem comprovante PDF (baixe e guarde).</p>
  </div>

  <h2>👤 Perfil</h2>
  <div class="card">
    <p><strong>WiseTag:</strong> obrigatório pra receber. Sem isso, o admin não consegue te pagar.</p>
    <p><strong>Aceitando clientes:</strong> ative quando tiver capacidade pra mais; desative quando estiver cheio.</p>
    <p><strong>Senha:</strong> mude se desconfiar. Mínimo 8 caracteres.</p>
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
    <p><strong>Pagar:</strong> abra a cobrança, use o método disponível e envie comprovante (foto/PDF). O admin confirma e a cobrança vira <em>Paga</em>.</p>
    <p><strong>Status:</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Aberta:</strong> aguardando seu pagamento</li>
      <li><strong>Em análise:</strong> você enviou comprovante, esperando aprovação</li>
      <li><strong>Paga:</strong> tudo certo, pagamento confirmado</li>
      <li><strong>Vencida:</strong> passou da data — regularize o quanto antes</li>
    </ul>
    <p><strong>Recibo:</strong> depois de paga, baixe o PDF no detalhe da cobrança.</p>
  </div>

  <h2>✅ Entregas</h2>
  <div class="card">
    <p>Lista do que está sendo produzido pra você no mês (criativos, postagens, sites, etc.). Cada item mostra se já foi entregue ou está em andamento.</p>
  </div>

  <h2>👤 Perfil</h2>
  <div class="card">
    <p>Atualize seu nome e troque a senha. Email é fixo (use o que cadastrou).</p>
  </div>

  <h2>💬 Precisa de ajuda?</h2>
  <div class="card">
    <p>Entre em contato pelo email <a href="mailto:contact@diteads.com">contact@diteads.com</a> ou WhatsApp da Dite Ads. A gente responde rápido!</p>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
