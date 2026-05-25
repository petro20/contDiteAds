<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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
            header('Location: ' . APP_BASE_URL . '/clientes.php?acao=editar&id=' . $pid . '&ok=upd'); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO clientes (nome,documento,email,telefone,endereco,observacoes,ativo) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$nome,$doc,$email,$tel,$end,$obs,$ativo]);
            $newId = (int)$db->lastInsertId();
            header('Location: ' . APP_BASE_URL . '/clientes.php?acao=editar&id=' . $newId . '&ok=add'); exit;
        }
    }

    if ($op === 'criar_login') {
        $cid    = (int)($_POST['cliente_id'] ?? 0);
        $email  = trim((string)($_POST['login_email'] ?? ''));
        $senha  = (string)($_POST['login_senha'] ?? '');
        if (!$cid || $email === '' || $senha === '') {
            $flash = ['err', 'Email e senha são obrigatórios para criar login.'];
            $acao = 'editar'; $id = $cid;
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha_hash, role, cliente_id, ativo) SELECT nome, ?, ?, 'cliente', id, 1 FROM clientes WHERE id = ?");
                $stmt->execute([$email, password_hash($senha, PASSWORD_DEFAULT), $cid]);
                header('Location: ' . APP_BASE_URL . '/clientes.php?acao=editar&id=' . $cid . '&ok=login'); exit;
            } catch (PDOException $e) {
                $flash = ['err', (int)$e->errorInfo[1] === 1062 ? 'Já existe um usuário com este email.' : 'Erro ao criar login.'];
                $acao = 'editar'; $id = $cid;
            }
        }
    }

    if ($op === 'reset_senha_cliente') {
        $uid   = (int)($_POST['usuario_id'] ?? 0);
        $cid   = (int)($_POST['cliente_id'] ?? 0);
        $senha = (string)($_POST['nova_senha'] ?? '');
        if ($uid && $senha !== '') {
            $stmt = $db->prepare('UPDATE usuarios SET senha_hash=? WHERE id=? AND role=?');
            $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), $uid, 'cliente']);
        }
        header('Location: ' . APP_BASE_URL . '/clientes.php?acao=editar&id=' . $cid . '&ok=senha'); exit;
    }
}

if (isset($_GET['ok'])) {
    $msgs = ['add'=>'Cliente criado.','upd'=>'Cliente atualizado.','login'=>'Login do cliente criado.','senha'=>'Senha redefinida.'];
    $flash = ['ok', $msgs[$_GET['ok']] ?? 'OK.'];
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
    $userCliente = null;
    if ($c['id']) {
        $stmt = $db->prepare("SELECT id, email, ativo FROM usuarios WHERE cliente_id=? AND role='cliente' LIMIT 1");
        $stmt->execute([$c['id']]);
        $userCliente = $stmt->fetch() ?: null;
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
          <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/clientes.php">Voltar</a>
        </div>
      </form>
    </div>

    <?php if ($c['id']): ?>
    <h2>Acesso do cliente ao sistema</h2>
    <div class="card">
      <?php if ($userCliente): ?>
        <p>Login ativo: <strong><?= e($userCliente['email']) ?></strong></p>
        <form method="post" style="display:flex; gap:.7rem; align-items:end; flex-wrap:wrap;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="reset_senha_cliente">
          <input type="hidden" name="usuario_id" value="<?= (int)$userCliente['id'] ?>">
          <input type="hidden" name="cliente_id" value="<?= (int)$c['id'] ?>">
          <div class="field" style="margin:0;"><label>Nova senha</label><input type="password" name="nova_senha" required></div>
          <button class="btn" type="submit">Redefinir senha</button>
        </form>
      <?php else: ?>
        <p class="muted">Crie um login para que o cliente possa entrar no sistema e ver suas cobranças.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="criar_login">
          <input type="hidden" name="cliente_id" value="<?= (int)$c['id'] ?>">
          <div class="grid-2">
            <div class="field"><label>Email de login</label><input type="email" name="login_email" required value="<?= e($c['email']) ?>"></div>
            <div class="field"><label>Senha inicial</label><input type="password" name="login_senha" required></div>
          </div>
          <button class="btn" type="submit">Criar login</button>
        </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
} else {
    $clientes = $db->query('SELECT cl.id, cl.nome, cl.documento, cl.telefone, cl.ativo, (SELECT COUNT(*) FROM usuarios u WHERE u.cliente_id=cl.id AND u.role="cliente") AS tem_login FROM clientes cl ORDER BY cl.nome')->fetchAll();
    ?>
    <h1>Clientes</h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <p><a class="btn" href="?acao=novo">+ Novo cliente</a></p>
    <table>
      <thead><tr><th>Nome</th><th>Documento</th><th>Telefone</th><th>Login</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($clientes as $c): ?>
        <tr>
          <td><?= e($c['nome']) ?></td>
          <td><?= e($c['documento']) ?: '—' ?></td>
          <td><?= e($c['telefone']) ?: '—' ?></td>
          <td><?= $c['tem_login'] ? '✅' : '—' ?></td>
          <td><?= $c['ativo'] ? 'Ativo' : 'Inativo' ?></td>
          <td><a class="btn small" href="?acao=editar&id=<?= (int)$c['id'] ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clientes): ?><tr><td colspan="6" class="muted">Nenhum cliente cadastrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php
}
require __DIR__ . '/includes/footer.php';
