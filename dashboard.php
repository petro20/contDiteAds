<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
$u  = require_login();
$db = db();

$page = 'Início';
$nav_active = $u['role'] === 'admin' ? 'painel' : ($u['role'] === 'funcionario' ? 'agenda' : 'cobrancas');
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Olá, <?= e($u['nome']) ?></h1>

<?php if ($u['role'] === 'admin'): ?>
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
  <a class="card" href="<?= e(APP_BASE_URL) ?>/catalogo.php">
    <div class="title">📦 Catálogo</div>
    <div class="desc">Cadastrar e editar itens (serviços e pacotes)</div>
  </a>
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
  <div class="card">
    <div class="title">Bem-vindo!</div>
    <div class="desc">As cobranças e entregas (Sprint 2 e 3) ainda estão sendo construídas. Sua conta já está ativa.</div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
