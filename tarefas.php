<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_login();
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

function carrega_tarefa(PDO $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM tarefas WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function pode_ver_tarefa(array $t, array $me): bool {
    return $me['role'] === 'admin' || (int)$t['funcionario_id'] === (int)$me['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'salvar_tarefa') {
        if (!is_admin()) { http_response_code(403); exit('Apenas admin pode criar/editar tarefas.'); }
        $pid       = (int)($_POST['id'] ?? 0);
        $cliente   = (int)($_POST['cliente_id'] ?? 0);
        $funci     = (int)($_POST['funcionario_id'] ?? 0);
        $titulo    = trim((string)($_POST['titulo'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? '')) ?: null;
        $status    = $_POST['status'] ?? 'pendente';
        $prazo     = trim((string)($_POST['prazo'] ?? '')) ?: null;
        $validos   = ['pendente','em_andamento','concluida','cancelada'];
        if (!in_array($status, $validos, true)) $status = 'pendente';

        if ($titulo === '' || !$cliente || !$funci) {
            $flash = ['err','Título, cliente e funcionário são obrigatórios.'];
            $acao = $pid ? 'editar' : 'novo';
            $id = $pid;
        } elseif ($pid) {
            $stmt = $db->prepare('UPDATE tarefas SET cliente_id=?, funcionario_id=?, titulo=?, descricao=?, status=?, prazo=? WHERE id=?');
            $stmt->execute([$cliente,$funci,$titulo,$descricao,$status,$prazo,$pid]);
            header('Location: ' . APP_BASE_URL . '/tarefas.php?id=' . $pid); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO tarefas (cliente_id,funcionario_id,titulo,descricao,status,prazo) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$cliente,$funci,$titulo,$descricao,$status,$prazo]);
            header('Location: ' . APP_BASE_URL . '/tarefas.php?id=' . $db->lastInsertId()); exit;
        }
    }

    if ($op === 'mudar_status') {
        $tid = (int)($_POST['id'] ?? 0);
        $t = carrega_tarefa($db, $tid);
        if (!$t || !pode_ver_tarefa($t, $me)) { http_response_code(403); exit('Acesso negado.'); }
        $novo = $_POST['status'] ?? '';
        if (in_array($novo, ['pendente','em_andamento','concluida','cancelada'], true)) {
            $stmt = $db->prepare('UPDATE tarefas SET status=? WHERE id=?');
            $stmt->execute([$novo, $tid]);
        }
        header('Location: ' . APP_BASE_URL . '/tarefas.php?id=' . $tid); exit;
    }

    if ($op === 'add_apontamento') {
        $tid = (int)($_POST['tarefa_id'] ?? 0);
        $t = carrega_tarefa($db, $tid);
        if (!$t || !pode_ver_tarefa($t, $me)) { http_response_code(403); exit('Acesso negado.'); }

        $data  = trim((string)($_POST['data'] ?? ''));
        $horas = (float)str_replace(',', '.', (string)($_POST['horas'] ?? '0'));
        $obs   = trim((string)($_POST['observacao'] ?? '')) ?: null;

        if (!$data) $data = date('Y-m-d');
        if ($horas <= 0 || $horas > 24) {
            $flash = ['err','Horas deve ser entre 0,01 e 24.'];
            $acao = 'ver'; $id = $tid;
        } else {
            $stmt = $db->prepare('INSERT INTO apontamentos (tarefa_id, funcionario_id, data, horas, observacao) VALUES (?,?,?,?,?)');
            $stmt->execute([$tid, $me['id'], $data, $horas, $obs]);
            header('Location: ' . APP_BASE_URL . '/tarefas.php?id=' . $tid); exit;
        }
    }
}

if ($id && $acao === 'lista') $acao = 'ver';

$page = 'Tarefas';
require __DIR__ . '/includes/header.php';

if ($acao === 'novo' || $acao === 'editar') {
    if (!is_admin()) { http_response_code(403); exit('Apenas admin pode criar/editar tarefas.'); }
    $t = ['id'=>0,'cliente_id'=>0,'funcionario_id'=>0,'titulo'=>'','descricao'=>'','status'=>'pendente','prazo'=>''];
    if ($acao === 'editar' && $id) $t = carrega_tarefa($db, $id) ?: $t;
    $clientes = $db->query('SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome')->fetchAll();
    $funcs    = $db->query("SELECT id, nome FROM usuarios WHERE ativo=1 ORDER BY nome")->fetchAll();
    ?>
    <h1><?= $t['id'] ? 'Editar tarefa' : 'Nova tarefa' ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="salvar_tarefa">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <div class="field"><label>Título *</label><input name="titulo" required value="<?= e($t['titulo']) ?>"></div>
        <div class="grid-2">
          <div class="field"><label>Cliente *</label><select name="cliente_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $t['cliente_id']==$c['id']?'selected':'' ?>><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="field"><label>Funcionário *</label><select name="funcionario_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($funcs as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $t['funcionario_id']==$f['id']?'selected':'' ?>><?= e($f['nome']) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="field"><label>Status</label><select name="status">
            <?php foreach (['pendente','em_andamento','concluida','cancelada'] as $s): ?>
              <option value="<?= $s ?>" <?= $t['status']===$s?'selected':'' ?>><?= str_replace('_',' ',$s) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="field"><label>Prazo</label><input type="date" name="prazo" value="<?= e($t['prazo']) ?>"></div>
        </div>
        <div class="field"><label>Descrição</label><textarea name="descricao"><?= e($t['descricao']) ?></textarea></div>
        <div class="actions">
          <button class="btn" type="submit">Salvar</button>
          <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/tarefas.php">Cancelar</a>
        </div>
      </form>
    </div>
    <?php
}
elseif ($acao === 'ver' && $id) {
    $t = carrega_tarefa($db, $id);
    if (!$t || !pode_ver_tarefa($t, $me)) { http_response_code(404); echo '<h1>Tarefa não encontrada.</h1>'; require __DIR__ . '/includes/footer.php'; exit; }
    $stmt = $db->prepare('SELECT c.nome AS cliente, u.nome AS funcionario FROM tarefas t JOIN clientes c ON c.id=t.cliente_id JOIN usuarios u ON u.id=t.funcionario_id WHERE t.id=?');
    $stmt->execute([$id]);
    $info = $stmt->fetch();

    $stmt = $db->prepare('SELECT a.*, u.nome AS funcionario FROM apontamentos a JOIN usuarios u ON u.id=a.funcionario_id WHERE a.tarefa_id=? ORDER BY a.data DESC, a.id DESC');
    $stmt->execute([$id]);
    $apont = $stmt->fetchAll();
    $total = array_sum(array_map(fn($a) => (float)$a['horas'], $apont));
    ?>
    <h1><?= e($t['titulo']) ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <div class="card">
      <div class="grid-2">
        <div><strong>Cliente:</strong> <?= e($info['cliente']) ?></div>
        <div><strong>Funcionário:</strong> <?= e($info['funcionario']) ?></div>
        <div><strong>Status:</strong> <span class="status status-<?= e($t['status']) ?>"><?= e(str_replace('_',' ',$t['status'])) ?></span></div>
        <div><strong>Prazo:</strong> <?= $t['prazo'] ? e(date('d/m/Y', strtotime($t['prazo']))) : '—' ?></div>
      </div>
      <?php if ($t['descricao']): ?>
        <p style="margin-top:1rem; white-space:pre-wrap;"><?= e($t['descricao']) ?></p>
      <?php endif; ?>

      <form method="post" style="margin-top:1rem; display:flex; gap:.5rem; align-items:end;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="mudar_status">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <div class="field" style="margin:0;">
          <label>Mudar status</label>
          <select name="status">
            <?php foreach (['pendente','em_andamento','concluida','cancelada'] as $s): ?>
              <option value="<?= $s ?>" <?= $t['status']===$s?'selected':'' ?>><?= str_replace('_',' ',$s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn" type="submit">Atualizar</button>
        <?php if (is_admin()): ?><a class="btn secondary" href="?acao=editar&id=<?= (int)$t['id'] ?>">Editar tarefa</a><?php endif; ?>
      </form>
    </div>

    <h2>Apontamentos (total: <?= number_format($total, 2, ',', '.') ?>h)</h2>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="add_apontamento">
        <input type="hidden" name="tarefa_id" value="<?= (int)$t['id'] ?>">
        <div class="grid-2">
          <div class="field"><label>Data</label><input type="date" name="data" value="<?= e(date('Y-m-d')) ?>" required></div>
          <div class="field"><label>Horas</label><input type="number" step="0.25" min="0.01" max="24" name="horas" required></div>
        </div>
        <div class="field"><label>Observação</label><textarea name="observacao" placeholder="O que foi feito?"></textarea></div>
        <button class="btn" type="submit">Registrar horas</button>
      </form>
    </div>

    <?php if ($apont): ?>
    <table>
      <thead><tr><th>Data</th><th>Funcionário</th><th>Horas</th><th>Observação</th></tr></thead>
      <tbody>
      <?php foreach ($apont as $a): ?>
        <tr>
          <td><?= e(date('d/m/Y', strtotime($a['data']))) ?></td>
          <td><?= e($a['funcionario']) ?></td>
          <td><?= number_format((float)$a['horas'], 2, ',', '.') ?></td>
          <td style="white-space:pre-wrap;"><?= e($a['observacao'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="muted">Nenhum apontamento ainda.</p>
    <?php endif; ?>
    <?php
}
else {
    $where  = ['1=1'];
    $params = [];
    if (!is_admin()) {
        $where[] = 't.funcionario_id = ?';
        $params[] = $me['id'];
    }
    $fStatus  = $_GET['status'] ?? '';
    $fCliente = (int)($_GET['cliente_id'] ?? 0);
    $fFunc    = (int)($_GET['funcionario_id'] ?? 0);
    if (in_array($fStatus, ['pendente','em_andamento','concluida','cancelada'], true)) {
        $where[] = 't.status = ?';
        $params[] = $fStatus;
    }
    if ($fCliente) { $where[] = 't.cliente_id = ?'; $params[] = $fCliente; }
    if (is_admin() && $fFunc) { $where[] = 't.funcionario_id = ?'; $params[] = $fFunc; }

    $sql = 'SELECT t.id, t.titulo, t.status, t.prazo, c.nome AS cliente, u.nome AS funcionario
            FROM tarefas t
            JOIN clientes c ON c.id = t.cliente_id
            JOIN usuarios u ON u.id = t.funcionario_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY t.status="concluida", t.prazo IS NULL, t.prazo ASC, t.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tarefas = $stmt->fetchAll();

    $clientes = $db->query('SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome')->fetchAll();
    $funcs    = is_admin() ? $db->query('SELECT id, nome FROM usuarios WHERE ativo=1 ORDER BY nome')->fetchAll() : [];
    ?>
    <h1>Tarefas</h1>
    <?php if (is_admin()): ?><p><a class="btn" href="?acao=novo">+ Nova tarefa</a></p><?php endif; ?>

    <form method="get" class="card" style="display:flex; gap:.7rem; align-items:end; flex-wrap:wrap;">
      <div class="field" style="margin:0;"><label>Status</label>
        <select name="status">
          <option value="">— todos —</option>
          <?php foreach (['pendente','em_andamento','concluida','cancelada'] as $s): ?>
            <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= str_replace('_',' ',$s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin:0;"><label>Cliente</label>
        <select name="cliente_id">
          <option value="0">— todos —</option>
          <?php foreach ($clientes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $fCliente==$c['id']?'selected':'' ?>><?= e($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (is_admin()): ?>
      <div class="field" style="margin:0;"><label>Funcionário</label>
        <select name="funcionario_id">
          <option value="0">— todos —</option>
          <?php foreach ($funcs as $f): ?>
            <option value="<?= (int)$f['id'] ?>" <?= $fFunc==$f['id']?'selected':'' ?>><?= e($f['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <button class="btn" type="submit">Filtrar</button>
      <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/tarefas.php">Limpar</a>
    </form>

    <table>
      <thead><tr>
        <th>Título</th><th>Cliente</th><?php if (is_admin()): ?><th>Funcionário</th><?php endif; ?><th>Status</th><th>Prazo</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($tarefas as $t): ?>
        <tr>
          <td><?= e($t['titulo']) ?></td>
          <td><?= e($t['cliente']) ?></td>
          <?php if (is_admin()): ?><td><?= e($t['funcionario']) ?></td><?php endif; ?>
          <td><span class="status status-<?= e($t['status']) ?>"><?= e(str_replace('_',' ',$t['status'])) ?></span></td>
          <td><?= $t['prazo'] ? e(date('d/m/Y', strtotime($t['prazo']))) : '—' ?></td>
          <td><a class="btn small" href="?id=<?= (int)$t['id'] ?>">Abrir</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tarefas): ?><tr><td colspan="<?= is_admin()?6:5 ?>" class="muted">Nenhuma tarefa.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php
}
require __DIR__ . '/includes/footer.php';
