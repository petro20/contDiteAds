<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page = 'Cobranças';
$nav_active = 'cobrancas';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Cobranças</h1>
<div class="card attention">
  <div class="title">⚙️ Em construção</div>
  <div class="desc">O módulo de cobranças (criação automática, listagem, pagamentos com comprovante) será entregue no Sprint 2.</div>
</div>
<a class="btn block btn-ghost" href="<?= e(APP_BASE_URL) ?>/dashboard.php">← Voltar ao painel</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
