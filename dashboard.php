<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_login();

$db = db();

if (is_admin()) {
    $totClientes = (int)$db->query('SELECT COUNT(*) FROM clientes WHERE ativo = 1')->fetchColumn();
    $totFunc     = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE role='funcionario' AND ativo=1")->fetchColumn();
    $totAbertas  = (int)$db->query("SELECT COUNT(*) FROM tarefas WHERE status IN ('pendente','em_andamento')")->fetchColumn();
    $horasMes    = (float)$db->query("SELECT COALESCE(SUM(horas),0) FROM apontamentos WHERE YEAR(data)=YEAR(CURDATE()) AND MONTH(data)=MONTH(CURDATE())")->fetchColumn();

    $minhasTarefas = $db->query("
        SELECT t.id, t.titulo, t.status, t.prazo, c.nome AS cliente, u.nome AS funcionario
        FROM tarefas t
        JOIN clientes c ON c.id = t.cliente_id
        JOIN usuarios u ON u.id = t.funcionario_id
        WHERE t.status IN ('pendente','em_andamento')
        ORDER BY t.prazo IS NULL, t.prazo ASC, t.id DESC
        LIMIT 10
    ")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT COUNT(*) FROM tarefas WHERE funcionario_id = ? AND status IN ('pendente','em_andamento')");
    $stmt->execute([$u['id']]);
    $totAbertas = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(horas),0) FROM apontamentos WHERE funcionario_id = ? AND YEAR(data)=YEAR(CURDATE()) AND MONTH(data)=MONTH(CURDATE())");
    $stmt->execute([$u['id']]);
    $horasMes = (float)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT t.id, t.titulo, t.status, t.prazo, c.nome AS cliente
        FROM tarefas t
        JOIN clientes c ON c.id = t.cliente_id
        WHERE t.funcionario_id = ? AND t.status IN ('pendente','em_andamento')
        ORDER BY t.prazo IS NULL, t.prazo ASC, t.id DESC
        LIMIT 10
    ");
    $stmt->execute([$u['id']]);
    $minhasTarefas = $stmt->fetchAll();
}

$page = 'Início';
require __DIR__ . '/includes/header.php';
?>
<h1>Olá, <?= e($u['nome']) ?></h1>

<div class="grid-2">
  <?php if (is_admin()): ?>
    <div class="card"><strong><?= $totClientes ?></strong><div class="muted">Clientes ativos</div></div>
    <div class="card"><strong><?= $totFunc ?></strong><div class="muted">Funcionários ativos</div></div>
  <?php endif; ?>
  <div class="card"><strong><?= $totAbertas ?></strong><div class="muted">Tarefas abertas <?= is_admin() ? '(todas)' : '(minhas)' ?></div></div>
  <div class="card"><strong><?= number_format($horasMes, 2, ',', '.') ?>h</strong><div class="muted">Horas no mês <?= is_admin() ? '(todos)' : '(minhas)' ?></div></div>
</div>

<h2><?= is_admin() ? 'Tarefas abertas' : 'Minhas tarefas abertas' ?></h2>
<?php if (!$minhasTarefas): ?>
  <p class="muted">Nenhuma tarefa em aberto.</p>
<?php else: ?>
<table>
  <thead><tr>
    <th>Título</th><th>Cliente</th><?php if (is_admin()): ?><th>Funcionário</th><?php endif; ?><th>Status</th><th>Prazo</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($minhasTarefas as $t): ?>
    <tr>
      <td><?= e($t['titulo']) ?></td>
      <td><?= e($t['cliente']) ?></td>
      <?php if (is_admin()): ?><td><?= e($t['funcionario']) ?></td><?php endif; ?>
      <td><span class="status status-<?= e($t['status']) ?>"><?= e(str_replace('_',' ',$t['status'])) ?></span></td>
      <td><?= $t['prazo'] ? e(date('d/m/Y', strtotime($t['prazo']))) : '—' ?></td>
      <td><a class="btn small" href="<?= e(APP_BASE_URL) ?>/tarefas.php?id=<?= (int)$t['id'] ?>">Abrir</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
