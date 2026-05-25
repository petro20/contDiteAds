<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_admin();
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'salvar') {
        $pid = (int)($_POST['id'] ?? 0);
        $nome  = trim((string)($_POST['nome'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role  = ($_POST['role'] ?? 'funcionario') === 'admin' ? 'admin' : 'funcionario';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $aceit = isset($_POST['aceitando_clientes']) ? 1 : 0;
        $senha = (string)($_POST['senha'] ?? '');
        $cpf     = trim((string)($_POST['cpf'] ?? '')) ?: null;
        $wisetag = trim((string)($_POST['wisetag'] ?? '')) ?: null;
        $pais    = trim((string)($_POST['pais'] ?? '')) ?: null;
        $tel     = trim((string)($_POST['telefone'] ?? '')) ?: null;

        if ($nome === '' || $email === '') {
            $flash = ['err','Nome e email são obrigatórios.'];
            $acao = $pid ? 'editar' : 'novo'; $id = $pid;
        } elseif (!$pid && $senha === '') {
            $flash = ['err','Defina uma senha.']; $acao = 'novo';
        } else {
            try {
                if ($pid) {
                    if ($senha !== '') {
                        $stmt = $db->prepare('UPDATE usuarios SET nome=?, email=?, role=?, ativo=?, senha_hash=?, cpf=?, wisetag=?, pais=?, aceitando_clientes=? WHERE id=?');
                        $stmt->execute([$nome,$email,$role,$ativo,password_hash($senha, PASSWORD_DEFAULT),$cpf,$wisetag,$pais,$aceit,$pid]);
                    } else {
                        $stmt = $db->prepare('UPDATE usuarios SET nome=?, email=?, role=?, ativo=?, cpf=?, wisetag=?, pais=?, aceitando_clientes=? WHERE id=?');
                        $stmt->execute([$nome,$email,$role,$ativo,$cpf,$wisetag,$pais,$aceit,$pid]);
                    }
                    audit_log('funcionario.editado', 'usuarios', $pid);
                    header('Location: ' . APP_BASE_URL . '/funcionarios.php?ok=upd'); exit;
                } else {
                    $stmt = $db->prepare("INSERT INTO usuarios (nome,email,senha_hash,role,ativo,cpf,wisetag,pais,aceitando_clientes) VALUES (?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$nome,$email,password_hash($senha, PASSWORD_DEFAULT),$role,$ativo,$cpf,$wisetag,$pais,$aceit]);
                    audit_log('funcionario.criado', 'usuarios', (int)$db->lastInsertId());
                    header('Location: ' . APP_BASE_URL . '/funcionarios.php?ok=add'); exit;
                }
            } catch (PDOException $e) {
                if ((int)$e->errorInfo[1] === 1062) {
                    $flash = ['err','Já existe usuário com este email.'];
                    $acao = $pid ? 'editar' : 'novo'; $id = $pid;
                } else { throw $e; }
            }
        }
    }
}
if (isset($_GET['ok'])) $flash = ['ok', $_GET['ok'] === 'add' ? 'Criado.' : 'Atualizado.'];

$page = 'Funcionários';
$nav_active = '';

if ($acao === 'novo' || $acao === 'editar') {
    $show_back = true;
    $back_to = APP_BASE_URL . '/funcionarios.php';
    $u = ['id'=>0,'nome'=>'','email'=>'','role'=>'funcionario','ativo'=>1,'cpf'=>'','wisetag'=>'','pais'=>'','aceitando_clientes'=>1,'telefone'=>''];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare("SELECT id, nome, email, role, ativo, cpf, wisetag, pais, aceitando_clientes FROM usuarios WHERE id=? AND role IN ('admin','funcionario')");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $u = array_merge($u, $row);
    }
    $page = $u['id'] ? 'Editar funcionário' : 'Novo funcionário';
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
      <div class="card">
        <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($u['nome']) ?>"></div>
        <div class="field"><label>Email (login) *</label><input type="email" name="email" required value="<?= e($u['email']) ?>"></div>
        <div class="field"><label>Perfil</label>
          <select name="role">
            <option value="funcionario" <?= $u['role']==='funcionario'?'selected':'' ?>>Funcionário</option>
            <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
          </select>
        </div>
        <div class="field"><label>CPF (opcional)</label><input name="cpf" value="<?= e($u['cpf']) ?>"></div>
        <div class="field"><label>WiseTag (recebe USD)</label><input name="wisetag" value="<?= e($u['wisetag']) ?>" placeholder="@wisetag"></div>
        <div class="field"><label>País</label><input name="pais" value="<?= e($u['pais']) ?>"></div>
        <div class="field"><label>Senha <?= $u['id'] ? '(deixe em branco para manter)' : '*' ?></label><input type="password" name="senha" <?= $u['id']?'':'required' ?> autocomplete="new-password"></div>
        <label class="check"><input type="checkbox" name="ativo" <?= $u['ativo']?'checked':'' ?>> Ativo</label>
        <label class="check"><input type="checkbox" name="aceitando_clientes" <?= $u['aceitando_clientes']?'checked':'' ?>> Aceitando novos clientes</label>
      </div>
      <button class="btn block" type="submit">Salvar</button>
    </form>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

require __DIR__ . '/includes/header.php';
$users = $db->query("SELECT id, nome, email, role, ativo, wisetag, aceitando_clientes FROM usuarios WHERE role IN ('admin','funcionario') ORDER BY role DESC, nome")->fetchAll();
?>
<h1 class="page-title">Funcionários</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<div class="btn-pair">
  <a href="?acao=novo" class="btn btn-brand">+ Novo</a>
  <a href="<?= e(APP_BASE_URL) ?>/convites.php" class="btn btn-secondary">✉️ Convidar</a>
</div>
<div class="section-label mt-5">Equipe (<?= count($users) ?>)</div>
<?php foreach ($users as $u): ?>
  <a class="list-card" href="?acao=editar&id=<?= (int)$u['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($u['nome']) ?>
        <?php if ($u['role'] === 'admin'): ?><span class="status status-ia">admin</span><?php endif; ?>
        <?php if (!$u['ativo']): ?><span class="status status-info">inativo</span><?php endif; ?>
      </div>
      <div class="sub">
        <?= e($u['email']) ?>
        <?php if ($u['wisetag']): ?> · <?= e($u['wisetag']) ?><?php endif; ?>
        <?php if ($u['role'] === 'funcionario'): ?> · <?= $u['aceitando_clientes'] ? '<span class="status status-paga">🟢 aceitando</span>' : '<span class="status status-vencida">🔴 cheio</span>' ?><?php endif; ?>
      </div>
    </div>
  </a>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
