<?php
require_once __DIR__ . '/includes/auth.php';
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
<h1 class="page-title">Como usar o sistema</h1>
<p class="muted">Guia para o perfil <strong><?= e($role_label) ?></strong>. Olá, <?= e($u['nome']) ?>!</p>

<?php if ($role === 'sadmin'): ?>

  <div class="card brand">
    <div class="title">👑 Você é Super Admin</div>
    <div class="desc">Acesso total: gerencia equipe, catálogo, finanças, distribuição de lucro e configurações.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p>Visão geral do dia: alertas de comprovantes em análise, KPIs (clientes ativos, cobranças do mês, a receber), atalhos para as áreas mais usadas e bloco "Sociedade" com a posição dos lucros.</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>Cadastrar:</strong> botão <em>+ Novo</em> ou <em>✉️ Convidar</em> (manda link de cadastro por email).</p>
    <p><strong>Editar:</strong> clique no card do cliente. Lá você gerencia assinaturas, cobranças e dados.</p>
    <p><strong>Assinaturas:</strong> cada cliente pode ter várias. Defina serviço, valor, moeda, dia de cobrança. As cobranças mensais são geradas automaticamente 7 dias antes do vencimento.</p>
  </div>

  <h2>📦 Catálogo</h2>
  <div class="card">
    <p>Cadastra os itens vendidos pela Dite Ads (criativos, postagens, sites, pacotes). Cada item tem preço em BRL/USD/EUR e tipo (mensal/unitário).</p>
    <p>Itens marcados como <em>inativos</em> ficam ocultos para novas assinaturas, mas continuam funcionando nas existentes.</p>
  </div>

  <h2>💼 Funcionários e Sócios</h2>
  <div class="card">
    <p><strong>Cadastrar funcionário:</strong> menu Funcionários → <em>+ Novo</em>. Defina capacidade mensal (criativos / postagens / sites) e o valor que ele recebe (em USD) por cada item executado.</p>
    <p><strong>Promover a Admin/Sadmin:</strong> só Super Admin pode mudar perfil. Admin entra na divisão de lucro como sócio.</p>
    <p><strong>Apagar:</strong> só Sadmin. Se o usuário tiver dados vinculados, prefira desativar.</p>
  </div>

  <h2>💸 Despesas da empresa</h2>
  <div class="card">
    <p>Cadastra gastos recorrentes (ferramentas, anúncios, salários fixos) com 3 tipos de recorrência: <em>única, mensal, anual</em>. Entram automaticamente no cálculo de lucro do mês.</p>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p><strong>Gráfico:</strong> 6 meses por padrão, com botões 1m / 3m / 6m / 1a / Tudo.</p>
    <p><strong>Cards por moeda (BRL/USD/EUR):</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><span style="color:var(--c-success);">Entradas</span>: pagamentos confirmados + em análise + a receber</li>
      <li><span style="color:var(--c-danger);">Saídas</span>: despesas + pagamentos a funcionários</li>
      <li><strong>💎 Lucro líquido (antes de distribuir)</strong>: entradas − saídas</li>
      <li><strong>Distribuição já paga</strong>: o que você pagou aos sócios neste mês</li>
      <li><strong>💰 Saldo após distribuição</strong>: o que sobra em caixa</li>
    </ul>
  </div>

  <h2>💎 Distribuição de lucro</h2>
  <div class="card">
    <p>Tela <strong>Distribuição</strong>: cada sócio recebe 1 quota, a empresa também recebe 1 quota (retenção). Total = N+1 quotas onde N = nº de sócios admin/sadmin.</p>
    <p>Use o botão <strong>Pagar</strong> em cada linha para registrar o pagamento (define data, moeda e observação). Sócios já pagos viram <em>Pago</em>; faltantes ficam como <em>Pendente</em>.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Geração automática:</strong> cron diário às 5h emite cobranças 7 dias antes do vencimento.</p>
    <p><strong>Pagamento manual:</strong> botão <em>Marcar como paga</em> no detalhe da cobrança.</p>
    <p><strong>Comprovante do cliente:</strong> quando o cliente sobe foto/PDF, a cobrança vai pra <em>Em análise</em> e aparece no seu dashboard. Aceite ou rejeite.</p>
    <p><strong>Avulsa:</strong> botão <em>Nova cobrança avulsa</em> permite cobrança fora do ciclo mensal.</p>
  </div>

  <h2>🔐 Segurança</h2>
  <div class="card">
    <p><strong>2FA:</strong> ative em <em>Perfil → Configurar 2FA</em>. Recomendado para sadmin/admin.</p>
    <p><strong>Audit log:</strong> tudo que admins fazem é registrado. Acesso via <em>auditoria.php</em>.</p>
  </div>

<?php elseif ($role === 'admin'): ?>

  <div class="card brand">
    <div class="title">⚙ Você é Admin</div>
    <div class="desc">Gerencia clientes, cobranças, agenda e painel financeiro. Recebe sua parte do lucro como sócio.</div>
  </div>

  <h2>🏠 Início</h2>
  <div class="card">
    <p>KPIs do mês, alertas de comprovantes em análise (clientes que enviaram pagamento esperando sua aprovação), atalhos rápidos e bloco da sociedade.</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>+ Novo:</strong> cadastra cliente direto. <strong>Convidar:</strong> envia link por email.</p>
    <p>No detalhe do cliente você cria/edita assinaturas, vê histórico de cobranças e dados de contato.</p>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p>3 abas: <strong>Agenda</strong> (entregas do mês), <strong>Por cliente</strong> (cobranças por cliente), <strong>Por serviço</strong> (quantos clientes ativos por item).</p>
    <p>Em <em>Agenda</em>: gráfico de saúde + cards de receita/despesa/lucro por moeda. Botões de período: 1m / 3m / 6m / 1a / Tudo.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Geração:</strong> automática 7 dias antes da data. Para gerar manual, vá na assinatura e clique em <em>Gerar agora</em>.</p>
    <p><strong>Comprovante:</strong> quando o cliente sobe, fica <em>Em análise</em>. Aceite (vira <em>Paga</em>) ou rejeite (volta a <em>Aberta</em>).</p>
    <p><strong>WhatsApp:</strong> botão direto que abre conversa com o cliente usando template editável.</p>
  </div>

  <h2>💵 Pagar funcionários</h2>
  <div class="card">
    <p>Menu <strong>Pagamentos funcionários</strong>: fila com tudo que está pendente. Selecione, marque a data do pagamento e o valor (USD) calculado a partir das entregas confirmadas.</p>
  </div>

  <h2>💎 Distribuição de lucro</h2>
  <div class="card">
    <p>Aqui você vê sua quota no lucro mensal e registra o recebimento. Cada admin/sadmin recebe 1 quota + a empresa 1 quota.</p>
  </div>

  <h2>🔐 Segurança</h2>
  <div class="card">
    <p>Ative 2FA em <em>Perfil → Configurar 2FA</em>. Recomendado para qualquer admin.</p>
  </div>

<?php elseif ($role === 'funcionario'): ?>

  <div class="card brand">
    <div class="title">💼 Você é Funcionário</div>
    <div class="desc">Executa as entregas dos serviços que estão na sua agenda. Recebe em USD.</div>
  </div>

  <h2>🏠 Início</h2>
  <div class="card">
    <p>Resumo: clientes na sua carteira, serviços ativos, entregas do mês e quanto você tem a receber (USD). Atalhos rápidos para Agenda e Pagamentos.</p>
  </div>

  <h2>📅 Agenda</h2>
  <div class="card">
    <p>Lista dos itens que você tem que entregar no mês, agrupados por cliente. Cada item tem checkbox: marque quando concluir.</p>
    <p><strong>Importante:</strong> só itens marcados como concluídos contam para o seu pagamento.</p>
    <p>O sistema mostra 🟢/🔴 para indicar se sua agenda está dentro da capacidade declarada ou não.</p>
  </div>

  <h2>💵 Meus pagamentos</h2>
  <div class="card">
    <p>Histórico de tudo que o admin já pagou + previsão do que você vai receber pelas entregas do mês corrente.</p>
    <p>Cada pagamento tem comprovante (PDF) gerado automaticamente — pode baixar e guardar.</p>
  </div>

  <h2>👤 Perfil</h2>
  <div class="card">
    <p><strong>WiseTag:</strong> seu tag do Wise para receber o pagamento em USD. Sem isso, o admin não consegue te pagar.</p>
    <p><strong>Aceitando clientes:</strong> ative quando estiver com capacidade pra pegar mais; desative quando estiver cheio.</p>
    <p><strong>Senha:</strong> mude se desconfiar. Mínimo 8 caracteres.</p>
  </div>

<?php else: // cliente ?>

  <div class="card brand">
    <div class="title">🤝 Bem-vindo cliente!</div>
    <div class="desc">Aqui você acompanha suas cobranças, paga online, vê entregas em andamento e o status do que contratou.</div>
  </div>

  <h2>🏠 Início</h2>
  <div class="card">
    <p>Mostra cobranças <strong>em aberto</strong> e <strong>vencidas</strong> no topo (se houver), seus serviços contratados e atalhos rápidos.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Pagar:</strong> abra a cobrança e use o método disponível. Você pode enviar comprovante (foto/PDF) — depois admin confirma e a cobrança vira <em>Paga</em>.</p>
    <p><strong>Status:</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Aberta</strong>: aguardando seu pagamento</li>
      <li><strong>Em análise</strong>: você enviou comprovante, esperando aprovação</li>
      <li><strong>Paga</strong>: tudo certo, pagamento confirmado</li>
      <li><strong>Vencida</strong>: passou da data, regularize o quanto antes</li>
    </ul>
    <p><strong>Recibo:</strong> depois de paga, baixe o PDF do recibo no detalhe da cobrança.</p>
  </div>

  <h2>✅ Entregas</h2>
  <div class="card">
    <p>Lista do que está sendo produzido para você no mês: criativos, postagens, sites, etc. Cada item mostra se já foi entregue ou está em andamento.</p>
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

<a class="btn btn-secondary block mt-5" href="<?= e(APP_BASE_URL) ?>/perfil.php">← Voltar ao perfil</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
