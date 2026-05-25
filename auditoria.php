<?php
require_once __DIR__ . '/includes/auth.php';
require_sadmin();
$db = db();

$f_usuario = (int)($_GET['usuario_id'] ?? 0);
$f_entidade = trim((string)($_GET['entidade'] ?? ''));
$f_acao = trim((string)($_GET['acao'] ?? ''));
$f_de  = $_GET['de'] ?? '';
$f_ate = $_GET['ate'] ?? '';

$where = ['1=1']; $params = [];
if ($f_usuario) { $where[] = 'a.usuario_id = ?'; $params[] = $f_usuario; }
if ($f_entidade !== '') { $where[] = 'a.entidade = ?'; $params[] = $f_entidade; }
if ($f_acao !== '') { $where[] = 'a.acao LIKE ?'; $params[] = $f_acao . '%'; }
if ($f_de  !== '') { $where[] = 'a.criado_em >= ?'; $params[] = $f_de . ' 00:00:00'; }
if ($f_ate !== '') { $where[] = 'a.criado_em <= ?'; $params[] = $f_ate . ' 23:59:59'; }

$sql = 'SELECT a.id, a.acao, a.entidade, a.entidade_id, a.criado_em, a.ip,
               u.nome AS usuario_nome
        FROM audit_log a
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.criado_em DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$usuarios = $db->query('SELECT id, nome FROM usuarios ORDER BY nome')->fetchAll();
$entidades = $db->query('SELECT DISTINCT entidade FROM audit_log WHERE entidade IS NOT NULL ORDER BY entidade')->fetchAll(PDO::FETCH_COLUMN);

$page = 'Auditoria';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Auditoria</h1>

<form method="get" class="card">
  <div class="grid-2">
    <div class="field"><label>Usuário</label>
      <select name="usuario_id" onchange="this.form.submit()">
        <option value="0">Todos</option>
        <?php foreach ($usuarios as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $f_usuario==$u['id']?'selected':'' ?>><?= e($u['nome']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Entidade</label>
      <select name="entidade" onchange="this.form.submit()">
        <option value="">Todas</option>
        <?php foreach ($entidades as $en): ?><option value="<?= e($en) ?>" <?= $f_entidade===$en?'selected':'' ?>><?= e($en) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>De</label><input type="date" name="de" value="<?= e($f_de) ?>"></div>
    <div class="field"><label>Até</label><input type="date" name="ate" value="<?= e($f_ate) ?>"></div>
  </div>
  <div class="field"><label>Ação (prefix)</label><input name="acao" value="<?= e($f_acao) ?>" placeholder="ex: cobranca, cliente, pagamento"></div>
  <button class="btn block" type="submit">Filtrar</button>
</form>

<div class="section-label mt-5">Eventos (<?= count($logs) ?>)</div>
<?php foreach ($logs as $l): ?>
  <div class="card">
    <div class="spaced">
      <div>
        <div class="title"><?= e($l['acao']) ?></div>
        <div class="sub muted">
          <?= e($l['usuario_nome'] ?? 'sistema') ?> ·
          <?= e($l['entidade'] ?? '—') ?><?= $l['entidade_id'] ? ' #' . (int)$l['entidade_id'] : '' ?>
          <?php if ($l['ip']): ?> · <?= e($l['ip']) ?><?php endif; ?>
        </div>
      </div>
      <div class="muted" style="font-size:12px; text-align:right;">
        <?= e(date('d/m/Y', strtotime($l['criado_em']))) ?><br>
        <?= e(date('H:i:s', strtotime($l['criado_em']))) ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$logs): ?><p class="muted center mt-5">Nenhum evento.</p><?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
