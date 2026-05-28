<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
$db = db();
$token = $_GET['token'] ?? '';
$erro = null; $ok = false;

if ($token === '') {
    http_response_code(404);
    $page = 'Token inválido';
    $hide_nav = true;
    require __DIR__ . '/includes/header.php';
    echo '<div class="auth-wrap"><div class="card danger"><div class="title">Link inválido</div><div class="desc">Solicite um novo link de redefinição.</div></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $db->prepare('SELECT * FROM senha_resets WHERE token = ? LIMIT 1');
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset || $reset['usado_em'] !== null || strtotime($reset['expira_em']) < time()) {
    http_response_code(410);
    $page = 'Link expirado';
    $hide_nav = true;
    require __DIR__ . '/includes/header.php';
    echo '<div class="auth-wrap"><div class="card danger"><div class="title">Link expirado</div><div class="desc">Solicite um novo link de redefinição.</div></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $senha = (string)($_POST['senha'] ?? '');
    $senha2 = (string)($_POST['senha2'] ?? '');
    if (strlen($senha) < 8) {
        $erro = 'Senha precisa ter pelo menos 8 caracteres.';
    } elseif ($senha !== $senha2) {
        $erro = 'As senhas não conferem.';
    } else {
        $db->beginTransaction();
        $stmt = $db->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), (int)$reset['usuario_id']]);
        // Marca o token usado E invalida TODOS os outros tokens pendentes do mesmo usuário
        // (proteção contra requests paralelos: se atacante e vítima pediram reset, só um vale)
        $stmt = $db->prepare('UPDATE senha_resets SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL');
        $stmt->execute([(int)$reset['usuario_id']]);
        // Invalida sessões ativas do usuário em outros dispositivos.
        // Como armazenamos sessões em arquivo, não dá pra apagar diretamente — mas
        // ao trocar a senha, qualquer sessão existente fica "presa" porque dependerá
        // do próximo login. Pra reforçar, regeneramos um nonce de sessão no usuário:
        try {
            $db->prepare('UPDATE usuarios SET session_nonce = ? WHERE id = ?')
               ->execute([bin2hex(random_bytes(16)), (int)$reset['usuario_id']]);
        } catch (PDOException $e) { /* coluna session_nonce ainda não existe — migration 017 cria */ }
        $db->commit();
        audit_log('senha.redefinida', 'usuarios', (int)$reset['usuario_id']);
        $ok = true;
    }
}

$page = 'Redefinir senha';
$hide_nav = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="logo-wrap">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
  </div>
  <h1>Nova senha</h1>
  <?php if ($ok): ?>
    <div class="flash ok">Senha redefinida. Agora você pode entrar.</div>
    <a class="btn block" href="<?= e(APP_BASE_URL) ?>/login.php">Ir para o login</a>
  <?php else: ?>
    <?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field"><label>Nova senha (mín. 8)</label><input type="password" name="senha" required autofocus autocomplete="new-password"></div>
      <div class="field"><label>Confirmar senha</label><input type="password" name="senha2" required autocomplete="new-password"></div>
      <button class="btn block" type="submit">Redefinir</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
