<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/email.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/totp.php';
$db = db();
$msg = null;
$tipo = 'ok';
// Modo: 'email' (default) ou '2fa'
$modo = $_GET['modo'] ?? $_POST['modo'] ?? 'email';
if (!in_array($modo, ['email', '2fa'], true)) $modo = 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));

    if ($modo === '2fa') {
        // Recuperação via 2FA: email + código TOTP/backup → login imediato
        $codigo = trim((string)($_POST['codigo'] ?? ''));
        if ($email === '' || $codigo === '') {
            $msg = 'Informe email e código.'; $tipo = 'err';
        } elseif (login_via_2fa($email, $codigo)) {
            audit_log('login.via_2fa', 'usuarios', (int)$_SESSION['user_id']);
            header('Location: ' . APP_BASE_URL . '/dashboard.php');
            exit;
        } else {
            $msg = 'Email ou código inválido. Confira no app autenticador (ou use um backup code).'; $tipo = 'err';
        }
    } else {
        // Recuperação tradicional via email
        if ($email === '') {
            $msg = 'Informe seu email.'; $tipo = 'err';
        } else {
            $stmt = $db->prepare('SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u) {
                $token = bin2hex(random_bytes(24));
                $exp = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
                $stmt = $db->prepare('INSERT INTO senha_resets (usuario_id, token, expira_em) VALUES (?,?,?)');
                $stmt->execute([(int)$u['id'], $token, $exp]);

                $link = APP_BASE_URL . '/redefinir.php?token=' . $token;
                $html = '<p>Olá, ' . htmlspecialchars($u['nome']) . '.</p>'
                      . '<p>Recebemos uma solicitação para redefinir sua senha no sistema Dite Ads.</p>'
                      . '<p><a href="' . $link . '">Clique aqui para criar uma nova senha</a></p>'
                      . '<p>Ou copie e cole este link no navegador:<br>' . $link . '</p>'
                      . '<p>O link é válido por 1 hora. Se você não solicitou, ignore este email.</p>'
                      . '<p>— Equipe Dite Ads</p>';
                $resultado = email_enviar($email, 'Redefinir sua senha — Dite Ads', $html);

                audit_log('senha.reset_solicitado', 'usuarios', (int)$u['id']);

                if ($resultado !== true) {
                    error_log('Email reset falhou: ' . (string)$resultado);
                }
            }
            // Mensagem genérica (não revela se o email existe)
            $msg = 'Se o email existir, enviamos um link para redefinir a senha. Cheque sua caixa de entrada (e spam).';
        }
    }
}

$page = 'Esqueci minha senha';
$hide_nav = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="logo-wrap">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
  </div>
  <h1>Esqueci minha senha</h1>

  <div class="spaced mb-3" style="gap:8px;">
    <a class="btn small <?= $modo==='email' ? '' : 'btn-ghost' ?>" href="?modo=email">📧 Via email</a>
    <a class="btn small <?= $modo==='2fa' ? '' : 'btn-ghost' ?>" href="?modo=2fa">🔐 Via 2FA</a>
  </div>

  <?php if ($msg): ?><div class="flash <?= e($tipo) ?>"><?= e($msg) ?></div><?php endif; ?>

  <?php if ($modo === '2fa'): ?>
    <p class="muted center mb-3">Tem 2FA ativo? Digite seu email + código do app (ou backup code) pra entrar direto e trocar a senha.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="modo" value="2fa">
      <div class="field"><label>Email</label><input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="field">
        <label>Código (6 dígitos do app OU 8 caracteres backup code)</label>
        <input name="codigo" required placeholder="000000 ou XXXX-XXXX" autocomplete="one-time-code">
        <div class="hint">Backup codes podem ter hífen no meio (XXXX-XXXX) — vale com ou sem.</div>
      </div>
      <button class="btn block" type="submit">Entrar</button>
      <p class="hint center mt-2">Após entrar, vá em <em>Minha conta → Perfil</em> pra trocar a senha.</p>
    </form>
  <?php else: ?>
    <p class="muted center mb-3">Informe seu email cadastrado e enviaremos um link pra redefinir.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="modo" value="email">
      <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
      <button class="btn block" type="submit">Enviar link de redefinição</button>
    </form>
  <?php endif; ?>

  <p class="center mt-5"><a href="<?= e(APP_BASE_URL) ?>/login.php" class="muted">← Voltar ao login</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
