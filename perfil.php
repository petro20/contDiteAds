<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
require_once __DIR__ . '/lib/audit.php';
$u = require_login();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome = trim((string)($_POST['nome'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    if ($nome === '') {
        $flash = ['err','Nome obrigatório.'];
    } elseif ($senha !== '' && strlen($senha) < 8) {
        $flash = ['err','Nova senha precisa ter 8+ caracteres.'];
    } else {
        if ($senha !== '') {
            $stmt = $db->prepare('UPDATE usuarios SET nome=?, senha_hash=? WHERE id=?');
            $stmt->execute([$nome, password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
        } else {
            $stmt = $db->prepare('UPDATE usuarios SET nome=? WHERE id=?');
            $stmt->execute([$nome, $u['id']]);
        }
        audit_log('perfil.editado', 'usuarios', (int)$u['id']);
        $flash = ['ok','Salvo.'];
    }
}

$page = 'Perfil';
$nav_active = 'perfil';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Minha conta</h1>
<?php render_group_tabs('conta', 'perfil'); ?>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <div class="field"><label>Nome</label><input name="nome" required value="<?= e($u['nome']) ?>"></div>
  <div class="field"><label>Email</label><input value="<?= e($u['email']) ?>" disabled></div>
  <div class="field"><label>Perfil</label><input value="<?= e($u['role']) ?>" disabled></div>
  <div class="field"><label>Nova senha (opcional)</label><input type="password" name="senha" autocomplete="new-password" placeholder="deixe em branco para manter"></div>
  <button class="btn block" type="submit">Salvar alterações</button>
</form>

<a class="btn btn-ghost block mt-5" href="<?= e(APP_BASE_URL) ?>/logout.php">Sair</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
