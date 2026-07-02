<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
$me = require_admin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'gerar') {
        $tipo = $_POST['tipo'] === 'cliente' ? 'cliente' : 'funcionario';
        $dias = max(1, (int)($_POST['expira_em_dias'] ?? 7));
        $token = bin2hex(random_bytes(24));
        $expira = (new DateTimeImmutable("+$dias days"))->format('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO convites (token, tipo, criado_por, expira_em) VALUES (?,?,?,?)');
        $stmt->execute([$token, $tipo, $me['id'], $expira]);
        $newId = (int)$db->lastInsertId();
        audit_log('convite.gerado', 'convites', $newId, null, ['tipo'=>$tipo,'expira_em'=>$expira]);
        header('Location: ' . APP_BASE_URL . '/convites.php?gerado=' . $token); exit;
    }
    if (($_POST['op'] ?? '') === 'revogar') {
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE convites SET expira_em = NOW() WHERE id=? AND usado_em IS NULL");
        $stmt->execute([$cid]);
        audit_log('convite.revogado', 'convites', $cid);
        header('Location: ' . APP_BASE_URL . '/convites.php'); exit;
    }
}

$gerado = $_GET['gerado'] ?? null;
$convites = $db->query("
    SELECT c.id, c.token, c.tipo, c.expira_em, c.usado_em, u.nome AS usado_por_nome, c.criado_em
    FROM convites c
    LEFT JOIN usuarios u ON u.id = c.usado_por
    ORDER BY c.id DESC LIMIT 50
")->fetchAll();

$page = t('Convites');
$show_back = true;
$nav_active = '';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= e(t('Convidar pessoa')) ?></h1>

<?php if ($gerado):
    $url = APP_BASE_URL . '/convite.php?token=' . $gerado;
?>
<div class="card success">
  <div class="title">✅ <?= e(t('Convite gerado')) ?></div>
  <div class="desc"><?= e(t('Envie o link abaixo para a pessoa (WhatsApp, email, etc.)')) ?></div>
  <div class="field mt-3">
    <input type="text" readonly value="<?= e($url) ?>" onclick="this.select()">
  </div>
  <button class="btn btn-secondary block" onclick="navigator.clipboard.writeText('<?= e($url) ?>').then(()=>alert('<?= e(t('Link copiado!')) ?>'))">📋 <?= e(t('Copiar link')) ?></button>
</div>
<?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="op" value="gerar">
  <div class="field">
    <label><?= e(t('Tipo de convite')) ?></label>
    <select name="tipo" required>
      <option value="cliente"><?= e(t('Cliente (empresa contratante)')) ?></option>
      <option value="funcionario"><?= e(t('Funcionário (equipe Dite Ads)')) ?></option>
    </select>
  </div>
  <div class="field">
    <label><?= e(t('Validade (dias)')) ?></label>
    <input type="number" min="1" max="90" name="expira_em_dias" value="7" required>
    <div class="hint"><?= e(t('Após este prazo o link expira mesmo sem uso.')) ?></div>
  </div>
  <button class="btn block" type="submit"><?= e(t('Gerar convite')) ?></button>
</form>

<h2><?= e(t('Histórico')) ?></h2>
<?php foreach ($convites as $c):
    $usado = $c['usado_em'] !== null;
    $expirado = !$usado && strtotime($c['expira_em']) < time();
?>
<div class="list-card">
  <div class="info">
    <div class="nome"><?= e(ucfirst($c['tipo'])) ?> · <?= e(substr($c['token'],0,12)) ?>…</div>
    <div class="sub">
      <?php if ($usado): ?>
        <span class="status status-paga"><?= e(t('usado')) ?></span> <?= e(t('por')) ?> <?= e($c['usado_por_nome'] ?? '?') ?> <?= e(t('em')) ?> <?= e(date('d/m/Y H:i', strtotime($c['usado_em']))) ?>
      <?php elseif ($expirado): ?>
        <span class="status status-cancelada"><?= e(t('expirado')) ?></span>
      <?php else: ?>
        <span class="status status-aberta"><?= e(t('ativo')) ?></span> · <?= e(t('expira')) ?> <?= e(date('d/m/Y H:i', strtotime($c['expira_em']))) ?>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$usado && !$expirado): ?>
    <form method="post" style="margin:0;" onsubmit="return confirm('<?= e(t('Revogar este convite?')) ?>');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="revogar">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button class="btn btn-ghost small" type="submit"><?= e(t('Revogar')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
