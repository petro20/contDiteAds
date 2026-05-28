<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
require_once __DIR__ . '/lib/audit.php';
$u = require_login();
$db = db();
$flash = null;

// Funcionários e admins podem editar wisetag/cpf/país/aceitando_clientes do próprio perfil
$pode_editar_wisetag = in_array($u['role'], ['funcionario','admin','sadmin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Salvar capacidade — handler separado (campo distinto, fluxo separado)
    if (($_POST['op'] ?? '') === 'salvar_capacidade_perfil') {
        if (in_array($u['role'], ['funcionario','admin','sadmin'], true)) {
            $cap = $_POST['cap'] ?? [];
            if (is_array($cap)) {
                foreach ($cap as $categoria => $valor) {
                    $categoria = preg_replace('/[^a-z_]/', '', (string)$categoria);
                    $v = (int)$valor;
                    if ($v <= 0) {
                        $stmt = $db->prepare('DELETE FROM capacidade_funcionario WHERE funcionario_id=? AND categoria=?');
                        $stmt->execute([(int)$u['id'], $categoria]);
                    } else {
                        $stmt = $db->prepare('INSERT INTO capacidade_funcionario (funcionario_id, categoria, capacidade_mensal) VALUES (?,?,?) ON DUPLICATE KEY UPDATE capacidade_mensal = VALUES(capacidade_mensal)');
                        $stmt->execute([(int)$u['id'], $categoria, $v]);
                    }
                }
                audit_log('perfil.capacidade_atualizada', 'usuarios', (int)$u['id']);
                $flash = ['ok', 'Capacidade atualizada.'];
            }
        }
        goto fim_perfil_post;
    }

    $nome = trim((string)($_POST['nome'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $wisetag = $pode_editar_wisetag ? trim((string)($_POST['wisetag'] ?? '')) : null;
    $cpf = $pode_editar_wisetag ? trim((string)($_POST['cpf'] ?? '')) : null;
    $pais = $pode_editar_wisetag ? trim((string)($_POST['pais'] ?? '')) : null;
    $aceitando = $pode_editar_wisetag ? (isset($_POST['aceitando_clientes']) ? 1 : 0) : null;
    if ($nome === '') {
        $flash = ['err','Nome obrigatório.'];
    } elseif ($senha !== '' && strlen($senha) < 8) {
        $flash = ['err','Nova senha precisa ter 8+ caracteres.'];
    } else {
        // UPDATE adapta colunas conforme persona
        if ($pode_editar_wisetag) {
            if ($senha !== '') {
                $stmt = $db->prepare('UPDATE usuarios SET nome=?, senha_hash=?, wisetag=?, cpf=?, pais=?, aceitando_clientes=? WHERE id=?');
                $stmt->execute([$nome, password_hash($senha, PASSWORD_DEFAULT), $wisetag ?: null, $cpf ?: null, $pais ?: null, $aceitando, $u['id']]);
            } else {
                $stmt = $db->prepare('UPDATE usuarios SET nome=?, wisetag=?, cpf=?, pais=?, aceitando_clientes=? WHERE id=?');
                $stmt->execute([$nome, $wisetag ?: null, $cpf ?: null, $pais ?: null, $aceitando, $u['id']]);
            }
        } else {
            if ($senha !== '') {
                $stmt = $db->prepare('UPDATE usuarios SET nome=?, senha_hash=? WHERE id=?');
                $stmt->execute([$nome, password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
            } else {
                $stmt = $db->prepare('UPDATE usuarios SET nome=? WHERE id=?');
                $stmt->execute([$nome, $u['id']]);
            }
        }
        audit_log('perfil.editado', 'usuarios', (int)$u['id']);
        $flash = ['ok','Salvo.'];
        // Recarrega dados do usuário pra refletir mudanças
        $stmt = $db->prepare('SELECT * FROM usuarios WHERE id = ?');
        $stmt->execute([$u['id']]);
        $u = array_merge($u, $stmt->fetch() ?: []);
    }
    fim_perfil_post:;
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

  <?php if ($pode_editar_wisetag): ?>
    <div class="field">
      <label>WiseTag <?= empty($u['wisetag']) ? '<span style="color:var(--c-danger); font-size:11px;">⚠ obrigatório pra receber pagamento</span>' : '' ?></label>
      <input name="wisetag" value="<?= e($u['wisetag'] ?? '') ?>" placeholder="@seu-wisetag">
      <div class="hint">Sem WiseTag você fica fora da fila de pagamentos automáticos da Dite Ads.</div>
    </div>
    <div class="field">
      <label>CPF (opcional)</label>
      <input name="cpf" value="<?= e($u['cpf'] ?? '') ?>" placeholder="000.000.000-00">
    </div>
    <div class="field">
      <label>País (opcional)</label>
      <input name="pais" value="<?= e($u['pais'] ?? '') ?>" placeholder="Brasil">
    </div>
    <div class="field">
      <label class="check" style="font-weight:600;">
        <input type="checkbox" name="aceitando_clientes" value="1" <?= !empty($u['aceitando_clientes']) ? 'checked' : '' ?>>
        <?= !empty($u['aceitando_clientes']) ? '🟢' : '🔴' ?> Aceitando novos clientes
      </label>
      <div class="hint">Desmarque quando estiver no limite — admin vai ver alerta antes de te atribuir cliente novo.</div>
    </div>
  <?php endif; ?>

  <div class="field"><label>Nova senha (opcional)</label><input type="password" name="senha" autocomplete="new-password" placeholder="deixe em branco para manter"></div>
  <button class="btn block" type="submit">Salvar alterações</button>
</form>

<?php if (in_array($u['role'], ['funcionario','admin','sadmin'], true)):
    // Capacidade declarada — funcionário pode atualizar a própria
    $cap_categorias = ['criativos','postagens','sites_projetos'];
    $cap_labels = ['criativos'=>'Criativos (CTF/CTV/CTI)','postagens'=>'Pacotes POSTAGEM','sites_projetos'=>'Sites/projetos únicos'];
    $cap_map = [];
    try {
        $stmt = $db->prepare('SELECT categoria, capacidade_mensal FROM capacidade_funcionario WHERE funcionario_id = ?');
        $stmt->execute([(int)$u['id']]);
        foreach ($stmt->fetchAll() as $r) $cap_map[$r['categoria']] = (int)$r['capacidade_mensal'];
    } catch (PDOException $e) {}
?>
  <h2 class="mt-5">📊 Capacidade mensal declarada</h2>
  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="salvar_capacidade_perfil">
    <p class="muted" style="font-size:13px;">Quantos itens você consegue absorver por mês. Sistema usa pra mostrar 🟢/🔴 quando admin tenta te atribuir um cliente. <strong>Mantenha atualizado.</strong></p>
    <?php foreach ($cap_categorias as $cat): ?>
      <div class="field">
        <label><?= e($cap_labels[$cat]) ?></label>
        <input type="number" min="0" name="cap[<?= e($cat) ?>]" value="<?= isset($cap_map[$cat]) ? (int)$cap_map[$cat] : '' ?>" placeholder="0">
      </div>
    <?php endforeach; ?>
    <button class="btn block" type="submit">Salvar capacidade</button>
  </form>
<?php endif; ?>

<a class="btn btn-ghost block mt-5" href="<?= e(APP_BASE_URL) ?>/logout.php">Sair</a>
<?php require __DIR__ . '/includes/footer.php'; ?>
