<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
$u  = require_login();
$db = db();

$page = 'Início';
$nav_active = is_admin() ? 'painel' : ($u['role'] === 'funcionario' ? 'agenda' : 'cobrancas');
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Olá, <?= e($u['nome']) ?></h1>

<?php if (is_admin()): ?>
  <?php
  // Conta básica — só tabelas que existem após migration_002
  $totClientes = (int)$db->query('SELECT COUNT(*) FROM clientes WHERE ativo=1')->fetchColumn();
  $totFunc     = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE role='funcionario' AND ativo=1")->fetchColumn();
  $totItens    = 0;
  try { $totItens = (int)$db->query('SELECT COUNT(*) FROM itens_catalogo WHERE ativo=1')->fetchColumn(); } catch (Throwable $e) {}
  ?>
  <div class="grid-2">
    <div class="kpi"><div class="v"><?= $totClientes ?></div><div class="l">Clientes ativos</div></div>
    <div class="kpi"><div class="v"><?= $totFunc ?></div><div class="l">Funcionários ativos</div></div>
    <div class="kpi"><div class="v"><?= $totItens ?></div><div class="l">Itens no catálogo</div></div>
    <div class="kpi"><div class="v brand">Sprint 1</div><div class="l">Painel financeiro completo: Sprint 3</div></div>
  </div>

  <div class="section-label">Ações rápidas</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/painel.php">
    <div class="title">📊 Painel financeiro</div>
    <div class="desc">Agenda · Por cliente · Por serviço (vencidos, recebido, em aberto)</div>
  </a>
  <?php if (is_sadmin()): ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/catalogo.php">
    <div class="title">📦 Catálogo</div>
    <div class="desc">Cadastrar e editar itens <span class="status status-destaque">sadmin</span></div>
  </a>
  <?php endif; ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/clientes.php">
    <div class="title">👥 Clientes</div>
    <div class="desc">Gerenciar clientes e gerar convites</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/funcionarios.php">
    <div class="title">🧑‍💼 Funcionários</div>
    <div class="desc">Gerenciar equipe e capacidades</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/assinaturas.php">
    <div class="title">📝 Assinaturas</div>
    <div class="desc">Atribuir itens do catálogo a clientes</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">
    <div class="title">💳 Cobranças</div>
    <div class="desc">Ver cobranças geradas e gerar manualmente para teste</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/pagamentos_funcionarios.php">
    <div class="title">💵 Pagamentos a funcionários</div>
    <div class="desc">Fila de valores liberados em USD para pagar via Wise</div>
  </a>
  <?php if (is_sadmin()): ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/regua.php">
    <div class="title">⏰ Régua de cobrança</div>
    <div class="desc">Etapas automáticas + tarefas WhatsApp pendentes <span class="status status-destaque">sadmin</span></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/templates.php">
    <div class="title">📝 Templates de mensagem</div>
    <div class="desc">Editar textos de email e WhatsApp <span class="status status-destaque">sadmin</span></div>
  </a>
  <?php endif; ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/capacidade.php">
    <div class="title">📊 Capacidade da equipe</div>
    <div class="desc">Quanto cada funcionário absorve por mês</div>
  </a>
  <?php if (is_sadmin()): ?>
  <div class="section-label">Super Admin</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/auditoria.php">
    <div class="title">🔍 Auditoria</div>
    <div class="desc">Histórico de tudo no sistema (só sadmin)</div>
  </a>
  <?php endif; ?>

  <div class="section-label">Sociedade</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/distribuicao.php">
    <div class="title">💰 Distribuição de lucro</div>
    <div class="desc">Receita − despesas dividida em quotas (sócios + empresa)</div>
  </a>
  <?php if (is_sadmin()): ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/despesas.php">
    <div class="title">💸 Despesas da empresa</div>
    <div class="desc">Cadastrar gastos (ferramentas, software, etc.) <span class="status status-destaque">sadmin</span></div>
  </a>
  <?php endif; ?>

  <div class="section-label">Minha área de execução (também trabalho nos serviços)</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/agenda.php">
    <div class="title">📅 Minha agenda</div>
    <div class="desc">Marcar entregas dos clientes que eu atendo (como funcionário)</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/meus_pagamentos.php">
    <div class="title">💵 Meus pagamentos</div>
    <div class="desc">O que tenho a receber em USD (quando o cliente paga)</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/convites.php">
    <div class="title">✉️ Gerar convite</div>
    <div class="desc">Link para cliente ou funcionário se cadastrar</div>
  </a>

<?php elseif ($u['role'] === 'funcionario'): ?>
  <div class="card">
    <div class="title">Bem-vindo!</div>
    <div class="desc">A agenda de entregas (Sprint 3) e seus pagamentos (Sprint 4) ainda estão sendo construídos. Sua conta já está ativa.</div>
  </div>

<?php else: /* cliente */ ?>
  <?php
    $cid = (int)($u['cliente_id'] ?? 0);
    if ($cid) {
      $stmt = $db->prepare("SELECT moeda, nome_empresa FROM clientes WHERE id = ?");
      $stmt->execute([$cid]);
      $cli = $stmt->fetch();
      $stmt = $db->prepare("SELECT COALESCE(SUM(valor_total),0) FROM cobrancas WHERE cliente_id = ? AND status='aberta'");
      $stmt->execute([$cid]);
      $em_aberto = (float)$stmt->fetchColumn();
      $stmt = $db->prepare("SELECT COUNT(*) FROM cobrancas WHERE cliente_id = ? AND status='aberta' AND vencimento < CURDATE()");
      $stmt->execute([$cid]);
      $vencidas = (int)$stmt->fetchColumn();
    } else { $cli = null; $em_aberto = 0; $vencidas = 0; }
  ?>

  <?php if (!$cli): ?>
    <div class="card attention"><div class="title">⚠ Conta sem empresa vinculada</div><div class="desc">Avise o admin pra ligar sua conta a um cliente cadastrado.</div></div>
  <?php else: ?>
    <div class="grid-2">
      <div class="kpi"><div class="v"><?= e(money_fmt($em_aberto, $cli['moeda'])) ?></div><div class="l">Em aberto</div></div>
      <div class="kpi <?= $vencidas?'':'' ?>" <?= $vencidas?'style="border-color:var(--c-danger);"':'' ?>><div class="v"><?= $vencidas ?></div><div class="l"><?= $vencidas?'<span style="color:var(--c-danger);">Vencidas</span>':'Vencidas' ?></div></div>
    </div>

    <div class="section-label">Acesso rápido</div>
    <a class="card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">
      <div class="title">💳 Minhas cobranças</div>
      <div class="desc">Ver, anexar comprovante de pagamento</div>
    </a>
    <a class="card" href="<?= e(APP_BASE_URL) ?>/entregas.php">
      <div class="title">✅ Minhas entregas</div>
      <div class="desc">Acompanhar o que foi entregue no mês</div>
    </a>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
