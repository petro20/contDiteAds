<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/pagamentos.php';
$u = require_login();
if ($u['role'] !== 'funcionario' && !is_admin()) {
    header('Location: ' . APP_BASE_URL . '/dashboard.php'); exit;
}
$db = db();

$funcionario_id = (int)$u['id'];
if (is_admin() && isset($_GET['funcionario_id'])) {
    $funcionario_id = (int)$_GET['funcionario_id'];
}

$pendentes = itens_pendentes_funcionario($db, $funcionario_id);
$total_pendente = (float)array_sum(array_column($pendentes, 'subtotal_usd'));

$de  = $_GET['de']  ?? null;
$ate = $_GET['ate'] ?? null;
$historico = historico_pagamentos_funcionario($db, $funcionario_id, $de, $ate);
$total_historico = (float)array_sum(array_column($historico, 'valor_usd'));

$page = t('Meus pagamentos');
$nav_active = 'pagamentos';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= e(t('Meus pagamentos')) ?></h1>

<div class="grid-2">
  <div class="kpi"><div class="v" style="color:var(--c-success);">$<?= e(number_format($total_pendente, 2, '.', ',')) ?></div><div class="l"><?= e(t('A receber (liberado)')) ?></div></div>
  <div class="kpi"><div class="v">$<?= e(number_format($total_historico, 2, '.', ',')) ?></div><div class="l"><?= e(t('Recebido no período')) ?></div></div>
</div>

<h2><?= e(t('A receber')) ?></h2>
<?php if (!$pendentes): ?>
  <p class="muted"><?= e(t('Nada pendente no momento.')) ?></p>
<?php else: foreach ($pendentes as $it): ?>
  <div class="card">
    <div class="spaced">
      <div>
        <div class="title"><?= e($it['item_nome']) ?></div>
        <div class="sub muted"><?= e($it['nome_empresa']) ?> · <?= e($it['competencia_mes']) ?> · <?= (int)$it['quantidade'] ?>× $<?= e(number_format((float)$it['valor_unitario_usd'], 2, '.', ',')) ?></div>
      </div>
      <div class="money md">$<?= e(number_format((float)$it['subtotal_usd'], 2, '.', ',')) ?></div>
    </div>
  </div>
<?php endforeach; endif; ?>

<h2><?= e(t('Histórico de pagamentos recebidos')) ?></h2>
<form method="get" class="card">
  <div class="grid-2">
    <div class="field"><label><?= e(t('De')) ?></label><input type="date" name="de" value="<?= e($de ?? '') ?>"></div>
    <div class="field"><label><?= e(t('Até')) ?></label><input type="date" name="ate" value="<?= e($ate ?? '') ?>"></div>
  </div>
  <button class="btn block" type="submit"><?= e(t('Filtrar')) ?></button>
</form>

<?php if (!$historico): ?>
  <p class="muted"><?= e(t('Nenhum pagamento recebido no período.')) ?></p>
<?php else: foreach ($historico as $h): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/comprovante_funcionario.php?id=<?= (int)$h['id'] ?>" target="_blank">
    <div class="info">
      <div class="nome"><?= e(t('Pagamento')) ?> #<?= (int)$h['id'] ?></div>
      <div class="sub"><?= e(date('d/m/Y', strtotime($h['data_pagamento']))) ?></div>
    </div>
    <div class="right">
      <div class="money md">$<?= e(number_format((float)$h['valor_usd'], 2, '.', ',')) ?></div>
      <div class="muted" style="font-size:11px;">📄 <?= e(t('comprovante')) ?></div>
    </div>
  </a>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
