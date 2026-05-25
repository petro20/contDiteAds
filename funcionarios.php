<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_admin();
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'salvar') {
        $pid       = (int)($_POST['id'] ?? 0);
        $nome      = trim((string)($_POST['nome'] ?? ''));
        $email     = trim((string)($_POST['email'] ?? ''));
        $role      = ($_POST['role'] ?? 'funcionario') === 'admin' ? 'admin' : 'funcionario';
        $ativo     = isset($_POST['ativo']) ? 1 : 0;
        $senha     = (string)($_POST['senha'] ?? '');
        $comissao  = max(0.0, min(100.0, (float)str_replace(',', '.', (string)($_POST['percentual_comissao'] ?? '0'))));

        if ($nome === '' || $email === '') {
            $flash = ['err','Nome e email são obrigatórios.'];
            $acao = $pid ? 'editar' : 'novo';
            $id   = $pid;
        } elseif (!$pid && $senha === '') {
            $flash = ['err','Defina uma senha para o novo usuário.'];
            $acao = 'novo';
        } else {
            try {
                if ($pid) {
                    if ($senha !== '') {
                        $stmt = $db->prepare('UPDATE usuarios SET nome=?, email=?, role=?, ativo=?, percentual_comissao=?, senha_hash=? WHERE id=?');
                        $stmt->execute([$nome,$email,$role,$ativo,$comissao, password_hash($senha, PASSWORD_DEFAULT), $pid]);
                    } else {
                        $stmt = $db->prepare('UPDATE usuarios SET nome=?, email=?, role=?, ativo=?, percentual_comissao=? WHERE id=?');
                        $stmt->execute([$nome,$email,$role,$ativo,$comissao,$pid]);
                    }
                    header('Location: ' . APP_BASE_URL . '/funcionarios.php?ok=upd'); exit;
                } else {
                    $stmt = $db->prepare('INSERT INTO usuarios (nome,email,senha_hash,role,ativo,percentual_comissao) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$nome,$email, password_hash($senha, PASSWORD_DEFAULT), $role, $ativo, $comissao]);
                    header('Location: ' . APP_BASE_URL . '/funcionarios.php?ok=add'); exit;
                }
            } catch (PDOException $e) {
                if ((int)$e->errorInfo[1] === 1062) {
                    $flash = ['err','Já existe um usuário com este email.'];
                    $acao = $pid ? 'editar' : 'novo';
                    $id   = $pid;
                } else { throw $e; }
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash = ['ok', $_GET['ok'] === 'add' ? 'Usuário criado.' : 'Usuário atualizado.'];
}

$page = 'Funcionários';
require __DIR__ . '/includes/header.php';

if ($acao === 'novo' || $acao === 'editar') {
    $u = ['id'=>0,'nome'=>'','email'=>'','role'=>'funcionario','ativo'=>1,'percentual_comissao'=>'0.00'];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare('SELECT id, nome, email, role, ativo, percentual_comissao FROM usuarios WHERE id=? AND role IN (?, ?)');
        $stmt->execute([$id, 'admin', 'funcionario']);
        $u = $stmt->fetch() ?: $u;
    }
    ?>
    <h1><?= $u['id'] ? 'Editar usuário' : 'Novo usuário' ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="salvar">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <div class="grid-2">
          <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($u['nome']) ?>"></div>
          <div class="field"><label>Email *</label><input type="email" name="email" required value="<?= e($u['email']) ?>"></div>
          <div class="field"><label>Perfil</label>
            <select name="role">
              <option value="funcionario" <?= $u['role']==='funcionario'?'selected':'' ?>>Funcionário</option>
              <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
            </select>
          </div>
          <div class="field"><label>Comissão (%)</label><input type="number" step="0.01" min="0" max="100" name="percentual_comissao" value="<?= e(number_format((float)$u['percentual_comissao'], 2, '.', '')) ?>"></div>
          <div class="field"><label>Senha <?= $u['id'] ? '(deixe em branco para manter)' : '*' ?></label><input type="password" name="senha" <?= $u['id'] ? '' : 'required' ?> autocomplete="new-password"></div>
          <div class="field"><label><input type="checkbox" name="ativo" <?= $u['ativo'] ? 'checked' : '' ?>> Ativo</label></div>
        </div>
        <div class="actions">
          <button class="btn" type="submit">Salvar</button>
          <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/funcionarios.php">Cancelar</a>
        </div>
      </form>
    </div>
    <?php
} else {
    $users = $db->query("SELECT id, nome, email, role, ativo, percentual_comissao FROM usuarios WHERE role IN ('admin','funcionario') ORDER BY nome")->fetchAll();
    ?>
    <h1>Funcionários e administradores</h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <p><a class="btn" href="?acao=novo">+ Novo usuário</a></p>
    <table>
      <thead><tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Comissão</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['nome']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['role']) ?></td>
          <td><?= number_format((float)$u['percentual_comissao'], 2, ',', '.') ?>%</td>
          <td><?= $u['ativo'] ? 'Ativo' : 'Inativo' ?></td>
          <td><a class="btn small" href="?acao=editar&id=<?= (int)$u['id'] ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php
}
require __DIR__ . '/includes/footer.php';
