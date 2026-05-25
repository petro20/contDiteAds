<?php
require_once __DIR__ . '/includes/auth.php';

$erro = null;
$modo_2fa = login_pendente_2fa();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($modo_2fa) {
        $codigo = trim((string)($_POST['codigo'] ?? ''));
        if (verificar_2fa_e_logar($codigo)) {
            header('Location: ' . APP_BASE_URL . '/dashboard.php');
            exit;
        }
        $erro = 'Código 2FA incorreto.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');
        if ($email === '' || $senha === '') {
            $erro = 'Informe email e senha.';
        } elseif (login($email, $senha)) {
            $modo_2fa = login_pendente_2fa();
            if (!$modo_2fa) {
                header('Location: ' . APP_BASE_URL . '/dashboard.php');
                exit;
            }
        } else {
            $erro = 'Credenciais inválidas.';
        }
    }
}

if (current_user()) {
    header('Location: ' . APP_BASE_URL . '/dashboard.php');
    exit;
}

$page = $modo_2fa ? 'Código 2FA' : 'Entrar';
$hide_nav = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="logo-wrap">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
  </div>

  <?php if ($modo_2fa): ?>
    <h1>Código 2FA</h1>
    <p class="muted center mb-3">Digite o código de 6 dígitos do seu app autenticador.</p>
    <?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label>Código</label>
        <input name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus autocomplete="one-time-code">
      </div>
      <button class="btn block" type="submit">Confirmar</button>
    </form>
  <?php else: ?>
    <h1>Entrar</h1>
    <?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
      </div>
      <div class="field">
        <label>Senha</label>
        <input type="password" name="senha" required autocomplete="current-password">
      </div>
      <button class="btn block" type="submit">Entrar</button>
      <p class="center mt-5"><a href="<?= e(APP_BASE_URL) ?>/esqueci.php" class="muted">Esqueci minha senha</a></p>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
