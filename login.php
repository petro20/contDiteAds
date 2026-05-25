<?php
require_once __DIR__ . '/includes/auth.php';

$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    if ($email === '' || $senha === '') {
        $erro = 'Informe email e senha.';
    } elseif (login($email, $senha)) {
        header('Location: ' . APP_BASE_URL . '/dashboard.php');
        exit;
    } else {
        $erro = 'Credenciais inválidas.';
    }
}

if (current_user()) {
    header('Location: ' . APP_BASE_URL . '/dashboard.php');
    exit;
}

$page = 'Entrar';
require __DIR__ . '/includes/header.php';
?>
<div class="login-wrap">
  <div class="card">
    <h1>Entrar</h1>
    <?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Senha</label>
        <input type="password" name="senha" required>
      </div>
      <button class="btn" type="submit">Entrar</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
