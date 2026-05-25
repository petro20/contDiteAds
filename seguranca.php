<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/totp.php';
$u = require_login();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'gerar_secret') {
        $secret = totp_gerar_secret();
        $_SESSION['novo_totp_secret'] = $secret;
        header('Location: ' . APP_BASE_URL . '/seguranca.php'); exit;
    }

    if ($op === 'ativar_2fa') {
        $secret = $_SESSION['novo_totp_secret'] ?? '';
        $codigo = (string)($_POST['codigo'] ?? '');
        if (!$secret) { $flash = ['err','Gere uma chave primeiro.']; }
        elseif (!totp_verificar($secret, $codigo)) { $flash = ['err','Código incorreto. Tente de novo.']; }
        else {
            $stmt = $db->prepare('UPDATE usuarios SET totp_secret = ?, totp_enabled = 1 WHERE id = ?');
            $stmt->execute([$secret, (int)$u['id']]);
            unset($_SESSION['novo_totp_secret']);
            audit_log('2fa.ativado', 'usuarios', (int)$u['id']);
            $flash = ['ok','2FA ativado com sucesso.'];
        }
    }

    if ($op === 'desativar_2fa') {
        $codigo = (string)($_POST['codigo'] ?? '');
        $stmt = $db->prepare('SELECT totp_secret FROM usuarios WHERE id = ?');
        $stmt->execute([(int)$u['id']]);
        $sec = (string)$stmt->fetchColumn();
        if (!totp_verificar($sec, $codigo)) {
            $flash = ['err','Código incorreto.'];
        } else {
            $stmt = $db->prepare('UPDATE usuarios SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?');
            $stmt->execute([(int)$u['id']]);
            audit_log('2fa.desativado', 'usuarios', (int)$u['id']);
            $flash = ['ok','2FA desativado.'];
        }
    }
}

// Status atual
$stmt = $db->prepare('SELECT totp_enabled, totp_secret FROM usuarios WHERE id = ?');
$stmt->execute([(int)$u['id']]);
$current = $stmt->fetch();
$enabled = (int)($current['totp_enabled'] ?? 0) === 1;

$page = 'Segurança';
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Segurança · 2FA</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if ($enabled): ?>
  <div class="card success">
    <div class="title">✅ 2FA ativo</div>
    <div class="desc">Você precisa do código do app autenticador a cada login.</div>
  </div>

  <h2>Desativar 2FA</h2>
  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="desativar_2fa">
    <div class="field"><label>Digite o código atual do seu app</label><input name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required></div>
    <button class="btn btn-danger block" type="submit">Desativar 2FA</button>
  </form>

<?php elseif (!empty($_SESSION['novo_totp_secret'])):
    $secret = $_SESSION['novo_totp_secret'];
    $label = $u['email'];
    $uri = totp_otpauth_uri($label, $secret);
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($uri);
?>
  <h2>Passo 1 — Escanear no app</h2>
  <div class="card center">
    <p>Abra Google Authenticator, Authy ou 1Password e escaneie:</p>
    <img src="<?= e($qr_url) ?>" alt="QR Code 2FA" style="max-width:220px; border-radius:8px; margin:16px auto;">
    <p class="muted">Ou digite a chave manualmente:</p>
    <code style="display:block; padding:12px; background:var(--bg-input); border-radius:8px; word-break:break-all;"><?= e($secret) ?></code>
  </div>

  <h2>Passo 2 — Confirmar com código</h2>
  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="ativar_2fa">
    <div class="field"><label>Código de 6 dígitos do app</label><input name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus></div>
    <button class="btn block" type="submit">Ativar 2FA</button>
  </form>

<?php else: ?>
  <div class="card">
    <div class="title">2FA não está ativo</div>
    <div class="desc">Recomendado especialmente para administradores. Você precisará de um app autenticador (Google Authenticator, Authy, 1Password) no celular.</div>
  </div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="gerar_secret">
    <button class="btn block" type="submit">Configurar 2FA agora</button>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
