<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/email.php';
require_once __DIR__ . '/lib/audit.php';
$db = db();
$msg = null;
$tipo = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
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

$page = 'Esqueci minha senha';
$hide_nav = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="logo-wrap">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
  </div>
  <h1>Esqueci minha senha</h1>
  <p class="muted center mb-3">Informe seu email cadastrado e enviaremos um link para redefinir.</p>
  <?php if ($msg): ?><div class="flash <?= e($tipo) ?>"><?= e($msg) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
    <button class="btn block" type="submit">Enviar link de redefinição</button>
    <p class="center mt-5"><a href="<?= e(APP_BASE_URL) ?>/login.php" class="muted">← Voltar ao login</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
