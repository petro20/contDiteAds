<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$db = db();

$acao = $_GET['acao'] ?? 'lista';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    if ($op === 'salvar') {
        $pid       = (int)($_POST['id'] ?? 0);
        $nome      = trim((string)($_POST['nome'] ?? ''));
        $doc       = trim((string)($_POST['documento'] ?? '')) ?: null;
        $email     = trim((string)($_POST['email'] ?? '')) ?: null;
        $tel       = trim((string)($_POST['telefone'] ?? '')) ?: null;
        $end       = trim((string)($_POST['endereco'] ?? '')) ?: null;
        $obs       = trim((string)($_POST['observacoes'] ?? '')) ?: null;
        $ativo     = isset($_POST['ativo']) ? 1 : 0;
        if ($nome === '') {
            $flash = ['err','Nome é obrigatório.'];
            $acao = $pid ? 'editar' : 'novo';
            $id = $pid;
        } elseif ($pid) {
            $stmt = $db->prepare('UPDATE clientes SET nome=?, documento=?, email=?, telefone=?, endereco=?, observacoes=?, ativo=? WHERE id=?');
            $stmt->execute([$nome,$doc,$email,$tel,$end,$obs,$ativo,$pid]);
            header('Location: ' . APP_BASE_URL . '/clientes.php?ok=upd'); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO clientes (nome,documento,email,telefone,endereco,observacoes,ativo) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$nome,$doc,$email,$tel,$end,$obs,$ativo]);
            header('Location: ' . APP_BASE_URL . '/clientes.php?ok=add'); exit;
        }
    }
}

if (isset($_GET['ok'])) {
    $flash = ['ok', $_GET['ok'] === 'add' ? 'Cliente criado.' : 'Cliente atualizado.'];
}

$page = 'Clientes';
require __DIR__ . '/includes/header.php';

if ($acao === 'novo' || $acao === 'editar') {
    $c = ['id'=>0,'nome'=>'','documento'=>'','email'=>'','telefone'=>'','endereco'=>'','observacoes'=>'','ativo'=>1];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare('SELECT * FROM clientes WHERE id=?');
        $stmt->execute([$id]);
        $c = $stmt->fetch() ?: $c;
    }
    ?>
    <h1><?= $c['id'] ? 'Editar cliente' : 'Novo cliente' ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="salvar">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="grid-2">
          <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($c['nome']) ?>"></div>
          <div class="field"><label>Documento (CNPJ/CPF)</label><input name="documento" value="<?= e($c['documento']) ?>"></div>
          <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($c['email']) ?>"></div>
          <div class="field"><label>Telefone</label><input name="telefone" value="<?= e($c['telefone']) ?>"></div>
        </div>
        <div class="field"><label>Endereço</label><input name="endereco" value="<?= e($c['endereco']) ?>"></div>
        <div class="field"><label>Observações</label><textarea name="observacoes"><?= e($c['observacoes']) ?></textarea></div>
        <div class="field"><label><input type="checkbox" name="ativo" <?= $c['ativo'] ? 'checked' : '' ?>> Ativo</label></div>
        <div class="actions">
          <button class="btn" type="submit">Salvar</button>
          <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/clientes.php">Cancelar</a>
        </div>
      </form>
    </div>
    <?php
} else {
    $clientes = $db->query('SELECT id, nome, documento, telefone, ativo FROM clientes ORDER BY nome')->fetchAll();
    ?>
    <h1>Clientes</h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <p><a class="btn" href="?acao=novo">+ Novo cliente</a></p>
    <table>
      <thead><tr><th>Nome</th><th>Documento</th><th>Telefone</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($clientes as $c): ?>
        <tr>
          <td><?= e($c['nome']) ?></td>
          <td><?= e($c['documento']) ?: '—' ?></td>
          <td><?= e($c['telefone']) ?: '—' ?></td>
          <td><?= $c['ativo'] ? 'Ativo' : 'Inativo' ?></td>
          <td><a class="btn small" href="?acao=editar&id=<?= (int)$c['id'] ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clientes): ?><tr><td colspan="5" class="muted">Nenhum cliente cadastrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php
}
require __DIR__ . '/includes/footer.php';
