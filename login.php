<?php
require_once __DIR__ . '/includes/auth.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    if ($email === '' || $senha === '') {
        $erro = t('Informe email e senha.');
    } elseif (login($email, $senha)) {
        header('Location: ' . APP_BASE_URL . '/dashboard.php');
        exit;
    } else {
        $erro = t('Credenciais inválidas.');
    }
}

if (current_user()) {
    header('Location: ' . APP_BASE_URL . '/dashboard.php');
    exit;
}

$page = t('Entrar');
$hide_nav = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="logo-wrap">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
  </div>

  <h1><?= e(t('Entrar')) ?></h1>
  <?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label><?= e(t('Email')) ?></label>
      <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
    </div>
    <div class="field">
      <label><?= e(t('Senha')) ?></label>
      <div class="field-password">
        <input type="password" name="senha" required autocomplete="current-password">
        <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="<?= e(t('Mostrar senha')) ?>">👁</button>
      </div>
    </div>
    <button class="btn block" type="submit"><?= e(t('Entrar')) ?></button>
    <p class="center mt-5"><a href="<?= e(APP_BASE_URL) ?>/esqueci.php" class="muted"><?= e(t('Esqueci minha senha')) ?></a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
