<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
$db = db();
$token = $_GET['token'] ?? '';
$erro = null;

if ($token === '') {
    http_response_code(404);
    $page = 'Convite inválido';
    $hide_nav = true;
    require __DIR__ . '/includes/header.php';
    echo '<div class="auth-wrap"><div class="card danger"><div class="title">Convite inválido</div><div class="desc">Link incorreto ou expirado.</div></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = $db->prepare('SELECT * FROM convites WHERE token=? LIMIT 1');
$stmt->execute([$token]);
$convite = $stmt->fetch();

if (!$convite || $convite['usado_em'] !== null || strtotime($convite['expira_em']) < time()) {
    http_response_code(410);
    $page = 'Convite expirado';
    $hide_nav = true;
    require __DIR__ . '/includes/header.php';
    echo '<div class="auth-wrap"><div class="card danger"><div class="title">Convite expirado</div><div class="desc">Este link não está mais ativo. Solicite um novo convite.</div></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nome     = trim((string)($_POST['nome'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $senha    = (string)($_POST['senha'] ?? '');
    $senha2   = (string)($_POST['senha2'] ?? '');
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $endereco = trim((string)($_POST['endereco'] ?? '')) ?: null;

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif ($senha !== $senha2) {
        $erro = 'As senhas não conferem.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } else {
        try {
            $db->beginTransaction();
            if ($convite['tipo'] === 'cliente') {
                $nome_empresa = trim((string)($_POST['nome_empresa'] ?? '')) ?: $nome;
                $stmt = $db->prepare('INSERT INTO clientes (nome, nome_empresa, nome_contato, email, telefone, endereco, moeda, ativo) VALUES (?,?,?,?,?,?,?,1)');
                $stmt->execute([$nome_empresa, $nome_empresa, $nome, $email, $telefone, $endereco, 'BRL']);
                $cliente_id = (int)$db->lastInsertId();
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha_hash, role, cliente_id, ativo) VALUES (?,?,?,?,?,1)");
                $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), 'cliente', $cliente_id]);
                $uid = (int)$db->lastInsertId();
            } else {
                $cpf = trim((string)($_POST['cpf'] ?? '')) ?: null;
                $wisetag = trim((string)($_POST['wisetag'] ?? ''));
                if ($wisetag === '') {
                    throw new RuntimeException('WiseTag é obrigatória para funcionários.');
                }
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha_hash, role, cpf, wisetag, ativo) VALUES (?,?,?, 'funcionario', ?, ?, 1)");
                $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $cpf, $wisetag]);
                $uid = (int)$db->lastInsertId();
            }
            $stmt = $db->prepare('UPDATE convites SET usado_em = NOW(), usado_por = ? WHERE id = ?');
            $stmt->execute([$uid, $convite['id']]);
            $db->commit();
            audit_log('convite.usado', 'usuarios', $uid);

            // Auto login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            header('Location: ' . APP_BASE_URL . '/dashboard.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $erro = ((int)$e->errorInfo[1] === 1062) ? 'Já existe usuário com este email.' : 'Erro ao salvar: ' . $e->getMessage();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $erro = $e->getMessage();
        }
    }
}

$page = $convite['tipo'] === 'cliente' ? 'Cadastro de cliente' : 'Cadastro de funcionário';
$hide_nav = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="logo-wrap">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
  </div>
  <h1><?= e($page) ?></h1>
  <p class="muted center mb-3">Preencha seus dados para criar sua conta.</p>
  <?php if ($erro): ?><div class="flash err"><?= e($erro) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <?php if ($convite['tipo'] === 'cliente'): ?>
      <div class="field"><label>Nome da empresa *</label><input name="nome_empresa" required value="<?= e($_POST['nome_empresa'] ?? '') ?>"></div>
      <div class="field"><label>Seu nome (contato) *</label><input name="nome" required value="<?= e($_POST['nome'] ?? '') ?>"></div>
    <?php else: ?>
      <div class="field"><label>Nome completo *</label><input name="nome" required value="<?= e($_POST['nome'] ?? '') ?>"></div>
      <div class="field"><label>CPF (opcional)</label><input name="cpf" value="<?= e($_POST['cpf'] ?? '') ?>"></div>
      <div class="field"><label>WiseTag *</label><input name="wisetag" required value="<?= e($_POST['wisetag'] ?? '') ?>" placeholder="@seuwisetag"><div class="hint">Você recebe pagamentos em USD via Wise.</div></div>
    <?php endif; ?>

    <div class="field"><label>Email (será seu login) *</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email"></div>
    <div class="field"><label>Telefone (com DDI)</label><input name="telefone" value="<?= e($_POST['telefone'] ?? '') ?>" placeholder="+55 11 99999-9999"></div>
    <div class="field"><label>Endereço</label><input name="endereco" value="<?= e($_POST['endereco'] ?? '') ?>"></div>
    <div class="field"><label>Senha (mínimo 8 caracteres) *</label><input type="password" name="senha" required autocomplete="new-password"></div>
    <div class="field"><label>Confirmar senha *</label><input type="password" name="senha2" required autocomplete="new-password"></div>

    <button class="btn block" type="submit">Criar minha conta</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
