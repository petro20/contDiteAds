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
  <?php
    require_once __DIR__ . '/lib/pagamentos.php';
    $competencia_now = date('Y-m');

    $stmt = $db->prepare("SELECT COUNT(DISTINCT cliente_id) FROM assinaturas WHERE funcionario_id=? AND status='ativa'");
    $stmt->execute([(int)$u['id']]);
    $tot_clientes = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM assinaturas WHERE funcionario_id=? AND status='ativa'");
    $stmt->execute([(int)$u['id']]);
    $tot_assin = (int)$stmt->fetchColumn();

    $pendentes = itens_pendentes_funcionario($db, (int)$u['id']);
    $a_receber = (float)array_sum(array_column($pendentes, 'subtotal_usd'));

    $stmt = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE funcionario_id=? AND YEAR(data_pagamento)=YEAR(CURDATE()) AND MONTH(data_pagamento)=MONTH(CURDATE())");
    $stmt->execute([(int)$u['id']]);
    $recebido_mes = (float)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM entregas WHERE funcionario_id=? AND competencia_mes=?");
    $stmt->execute([(int)$u['id'], $competencia_now]);
    $tot_entregas = (int)$stmt->fetchColumn();
  ?>
  <div class="grid-2">
    <div class="kpi"><div class="v"><?= $tot_clientes ?></div><div class="l">Clientes que atendo</div></div>
    <div class="kpi"><div class="v"><?= $tot_assin ?></div><div class="l">Serviços ativos</div></div>
    <div class="kpi"><div class="v"><?= $tot_entregas ?></div><div class="l">Entregas este mês</div></div>
    <div class="kpi" <?= $a_receber>0?'style="border-color:var(--c-success);"':'' ?>>
      <div class="v">$<?= e(number_format($a_receber, 2, '.', ',')) ?></div>
      <div class="l">A receber (USD)</div>
    </div>
  </div>

  <?php if ($recebido_mes > 0): ?>
    <div class="card success">
      <div class="title">💵 $<?= e(number_format($recebido_mes, 2, '.', ',')) ?> USD recebidos este mês</div>
    </div>
  <?php endif; ?>

  <div class="section-label">Acesso rápido</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/agenda.php">
    <div class="title">📅 Minha agenda</div>
    <div class="desc">Marcar entregas dos meus clientes</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/clientes.php">
    <div class="title">👥 Meus clientes</div>
    <div class="desc">Lista de quem eu atendo</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/meus_pagamentos.php">
    <div class="title">💵 Meus pagamentos</div>
    <div class="desc">A receber + histórico em USD</div>
  </a>

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

    <?php
      $stmt = $db->prepare("
        SELECT a.id, a.variante, a.valor_cobrado, a.status, a.iniciada_em,
               i.nome AS item_nome, i.tipo, i.e_pacote,
               u.nome AS func_nome
        FROM assinaturas a
        JOIN itens_catalogo i ON i.id = a.item_id
        LEFT JOIN usuarios u ON u.id = a.funcionario_id
        WHERE a.cliente_id = ? AND a.status = 'ativa'
        ORDER BY i.nome
      ");
      $stmt->execute([$cid]);
      $minhas_assin = $stmt->fetchAll();
    ?>
    <div class="section-label">Meus serviços contratados</div>
    <?php if (!$minhas_assin): ?>
      <div class="card"><div class="desc muted">Nenhum serviço ativo. Fale com a Dite Ads pra contratar.</div></div>
    <?php else: foreach ($minhas_assin as $a): ?>
      <div class="card">
        <div class="spaced">
          <div>
            <div class="title" style="color:var(--txt-1);">
              <?= e($a['item_nome']) ?>
              <?php if ($a['e_pacote']): ?><span class="status status-ia">pacote</span><?php endif; ?>
              <?php if ($a['variante']==='ia'): ?><span class="status status-destaque">com IA</span><?php endif; ?>
            </div>
            <div class="sub muted">
              <?= e(['unico'=>'único','mensal'=>'mensal','por_unidade'=>'por unidade'][$a['tipo']] ?? $a['tipo']) ?>
              · ativo desde <?= e(date('d/m/Y', strtotime($a['iniciada_em']))) ?>
              <?php if ($a['func_nome']): ?> · com <?= e($a['func_nome']) ?><?php endif; ?>
            </div>
          </div>
          <div class="money md"><?= e(money_fmt((float)$a['valor_cobrado'], $cli['moeda'])) ?></div>
        </div>
      </div>
    <?php endforeach; endif; ?>

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
