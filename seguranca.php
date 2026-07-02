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
        if (!$secret) { $flash = ['err',t('Gere uma chave primeiro.')]; }
        elseif (!totp_verificar($secret, $codigo)) { $flash = ['err',t('Código incorreto. Tente de novo.')]; }
        else {
            $stmt = $db->prepare('UPDATE usuarios SET totp_secret = ?, totp_enabled = 1 WHERE id = ?');
            $stmt->execute([$secret, (int)$u['id']]);
            unset($_SESSION['novo_totp_secret']);
            // Gera backup codes na ativação — mostra UMA vez na tela
            $codigos_backup = totp_gerar_backup_codes($db, (int)$u['id'], 8);
            $_SESSION['backup_codes_mostrar'] = $codigos_backup;
            audit_log('2fa.ativado', 'usuarios', (int)$u['id']);
            header('Location: ' . APP_BASE_URL . '/seguranca.php?ok=ativado'); exit;
        }
    }

    if ($op === 'regerar_backup_codes') {
        // Exige código TOTP atual pra autorizar regeneração
        $codigo = (string)($_POST['codigo'] ?? '');
        $stmt = $db->prepare('SELECT totp_secret FROM usuarios WHERE id = ?');
        $stmt->execute([(int)$u['id']]);
        $sec = (string)$stmt->fetchColumn();
        if (!totp_verificar($sec, $codigo)) {
            $flash = ['err',t('Código incorreto. Os códigos antigos continuam válidos.')];
        } else {
            $codigos_backup = totp_gerar_backup_codes($db, (int)$u['id'], 8);
            $_SESSION['backup_codes_mostrar'] = $codigos_backup;
            audit_log('2fa.backup_codes_regerados', 'usuarios', (int)$u['id']);
            header('Location: ' . APP_BASE_URL . '/seguranca.php?ok=regerados'); exit;
        }
    }

    if ($op === 'desativar_2fa') {
        $codigo = (string)($_POST['codigo'] ?? '');
        $stmt = $db->prepare('SELECT totp_secret FROM usuarios WHERE id = ?');
        $stmt->execute([(int)$u['id']]);
        $sec = (string)$stmt->fetchColumn();
        if (!totp_verificar($sec, $codigo)) {
            $flash = ['err',t('Código incorreto.')];
        } else {
            $stmt = $db->prepare('UPDATE usuarios SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?');
            $stmt->execute([(int)$u['id']]);
            // Apaga backup codes ao desativar 2FA
            try {
                $db->prepare('DELETE FROM totp_backup_codes WHERE usuario_id = ?')->execute([(int)$u['id']]);
            } catch (PDOException $e) {}
            audit_log('2fa.desativado', 'usuarios', (int)$u['id']);
            $flash = ['ok',t('2FA desativado.')];
        }
    }
}

if (isset($_GET['ok'])) {
    $msgs = ['ativado' => t('2FA ativado. SALVE OS BACKUP CODES ABAIXO em local seguro — eles só aparecem uma vez!'),
             'regerados' => t('Backup codes regerados. Salve a nova lista; os códigos antigos foram invalidados.')];
    $flash = ['ok', $msgs[$_GET['ok']] ?? t('OK.')];
}

// Status atual
$stmt = $db->prepare('SELECT totp_enabled, totp_secret FROM usuarios WHERE id = ?');
$stmt->execute([(int)$u['id']]);
$current = $stmt->fetch();
$enabled = (int)($current['totp_enabled'] ?? 0) === 1;

$page = t('Segurança');
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/grupos.php';
?>
<h1 class="page-title"><?= e(t('Minha conta')) ?></h1>
<?php render_group_tabs('conta', 'seguranca'); ?>
<h2><?= e(t('Segurança · 2FA')) ?></h2>

<div class="card brand">
  <div class="title">ℹ <?= e(t('Pra que serve o 2FA aqui')) ?></div>
  <div class="desc">
    <?= t('O 2FA <strong>não é exigido no login normal</strong> — você entra só com email + senha.') ?><br>
    <?= t('Ele serve como <strong>meio de recuperação</strong>: se esquecer a senha e não receber o email de reset, vai em "Esqueci minha senha" → aba <strong>🔐 Via 2FA</strong> e entra direto com o código do app autenticador (ou um backup code).') ?>
  </div>
</div>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php
// Exibe backup codes recém-gerados (UMA vez — depois suma da sessão)
if (!empty($_SESSION['backup_codes_mostrar'])):
    $codigos_mostrar = $_SESSION['backup_codes_mostrar'];
    unset($_SESSION['backup_codes_mostrar']);
?>
  <div class="card danger">
    <div class="title">🔑 <?= e(t('SEUS BACKUP CODES — SALVE AGORA')) ?></div>
    <div class="desc">
      <?= t('<strong>Estes códigos só serão mostrados UMA vez.</strong> Imprima, anote, ou salve num gerenciador de senhas (1Password, Bitwarden). Cada código serve pra UM login se você perder acesso ao app autenticador.') ?>
    </div>
    <pre style="background:var(--bg-input); padding:16px; border-radius:8px; font-family:monospace; font-size:16px; line-height:1.8; margin-top:var(--s-3); text-align:center; white-space:pre-line;"><?= e(implode("\n", $codigos_mostrar)) ?></pre>
    <button type="button" class="btn small block" onclick="navigator.clipboard.writeText(`<?= e(implode("\n", $codigos_mostrar)) ?>`).then(()=>{this.innerHTML='✅ <?= e(t('Copiado!')) ?>'})">📋 <?= e(t('Copiar todos')) ?></button>
  </div>
<?php endif; ?>

<?php if ($enabled):
    $restantes = totp_backup_codes_restantes($db, (int)$u['id']);
?>
  <div class="card success">
    <div class="title">✅ <?= e(t('2FA ativo')) ?></div>
    <div class="desc"><?= e(t('Você precisa do código do app autenticador a cada login.')) ?></div>
  </div>

  <div class="card <?= $restantes <= 2 ? 'danger' : ($restantes <= 4 ? 'attention' : '') ?>">
    <div class="title">🔑 <?= e(t('Backup codes restantes:')) ?> <?= $restantes ?> / 8</div>
    <?php if ($restantes === 0): ?>
      <div class="desc" style="color:var(--c-danger);"><?= t('<strong>⚠ Você não tem backup codes!</strong> Se perder o celular, ficará trancado. Gere novos agora.') ?></div>
    <?php elseif ($restantes <= 2): ?>
      <div class="desc"><?= e(t('Poucos códigos restantes. Considere regerar uma nova lista.')) ?></div>
    <?php else: ?>
      <div class="desc"><?= e(t('Use estes códigos pra entrar se perder acesso ao app autenticador. Cada código vale UM login.')) ?></div>
    <?php endif; ?>
    <details style="margin-top:var(--s-3);">
      <summary style="cursor:pointer; padding:8px 0; color:var(--c-primary-2);"><?= e(t('Regerar novos backup codes')) ?></summary>
      <form method="post" style="margin-top:var(--s-2);">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="regerar_backup_codes">
        <div class="field">
          <label><?= e(t('Digite o código atual do app pra autorizar')) ?></label>
          <input name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="000000">
          <div class="hint">⚠ <?= e(str_replace('%d', (string)$restantes, t('Os %d códigos atuais serão invalidados.'))) ?></div>
        </div>
        <button class="btn small block" type="submit"><?= e(t('Gerar 8 novos backup codes')) ?></button>
      </form>
    </details>
  </div>

  <h2><?= e(t('Desativar 2FA')) ?></h2>
  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="desativar_2fa">
    <div class="field"><label><?= e(t('Digite o código atual do seu app')) ?></label><input name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required></div>
    <div class="hint"><?= e(t('Os backup codes serão apagados ao desativar.')) ?></div>
    <button class="btn btn-danger block" type="submit"><?= e(t('Desativar 2FA')) ?></button>
  </form>

<?php elseif (!empty($_SESSION['novo_totp_secret'])):
    $secret = $_SESSION['novo_totp_secret'];
    $label = $u['email'];
    $uri = totp_otpauth_uri($label, $secret);
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($uri);
?>
  <h2><?= e(t('Passo 1 — Escanear no app')) ?></h2>
  <div class="card center">
    <p><?= e(t('Abra Google Authenticator, Authy ou 1Password e escaneie:')) ?></p>
    <img src="<?= e($qr_url) ?>" alt="QR Code 2FA" style="max-width:220px; border-radius:8px; margin:16px auto;">
    <p class="muted"><?= e(t('Ou digite a chave manualmente:')) ?></p>
    <code style="display:block; padding:12px; background:var(--bg-input); border-radius:8px; word-break:break-all;"><?= e($secret) ?></code>
  </div>

  <h2><?= e(t('Passo 2 — Confirmar com código')) ?></h2>
  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="ativar_2fa">
    <div class="field"><label><?= e(t('Código de 6 dígitos do app')) ?></label><input name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus></div>
    <button class="btn block" type="submit"><?= e(t('Ativar 2FA')) ?></button>
  </form>

<?php else: ?>
  <div class="card">
    <div class="title"><?= e(t('2FA não está ativo')) ?></div>
    <div class="desc"><?= e(t('Recomendado especialmente para administradores. Você precisará de um app autenticador (Google Authenticator, Authy, 1Password) no celular.')) ?></div>
  </div>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="gerar_secret">
    <button class="btn block" type="submit"><?= e(t('Configurar 2FA agora')) ?></button>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
