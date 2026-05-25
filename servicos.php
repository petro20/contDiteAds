<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'salvar') {
        $pid          = (int)($_POST['id'] ?? 0);
        $nome         = trim((string)($_POST['nome'] ?? ''));
        $descricao    = trim((string)($_POST['descricao'] ?? '')) ?: null;
        $valor_padrao = $_POST['valor_padrao'] === '' ? null : (float)str_replace(',', '.', (string)$_POST['valor_padrao']);
        $ativo        = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '') {
            $flash = ['err', 'Nome é obrigatório.'];
            $acao = $pid ? 'editar' : 'novo';
            $id = $pid;
        } elseif ($pid) {
            $stmt = $db->prepare('UPDATE servicos SET nome=?, descricao=?, valor_padrao=?, ativo=? WHERE id=?');
            $stmt->execute([$nome, $descricao, $valor_padrao, $ativo, $pid]);
            header('Location: ' . APP_BASE_URL . '/servicos.php?ok=upd'); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO servicos (nome, descricao, valor_padrao, ativo) VALUES (?,?,?,?)');
            $stmt->execute([$nome, $descricao, $valor_padrao, $ativo]);
            header('Location: ' . APP_BASE_URL . '/servicos.php?ok=add'); exit;
        }
    }
}

if (isset($_GET['ok'])) {
    $flash = ['ok', $_GET['ok'] === 'add' ? 'Serviço criado.' : 'Serviço atualizado.'];
}

$page = 'Serviços';
require __DIR__ . '/includes/header.php';

if ($acao === 'novo' || $acao === 'editar') {
    $s = ['id'=>0, 'nome'=>'', 'descricao'=>'', 'valor_padrao'=>'', 'ativo'=>1];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare('SELECT * FROM servicos WHERE id=?');
        $stmt->execute([$id]);
        $s = $stmt->fetch() ?: $s;
    }
    ?>
    <h1><?= $s['id'] ? 'Editar serviço' : 'Novo serviço' ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="salvar">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($s['nome']) ?>" placeholder="Gestão de tráfego, Criação de criativo..."></div>
        <div class="field"><label>Descrição</label><input name="descricao" value="<?= e($s['descricao']) ?>"></div>
        <div class="field"><label>Valor padrão (R$)</label><input type="number" step="0.01" min="0" name="valor_padrao" value="<?= $s['valor_padrao'] !== null ? e(number_format((float)$s['valor_padrao'], 2, '.', '')) : '' ?>" placeholder="opcional"></div>
        <div class="field"><label><input type="checkbox" name="ativo" <?= $s['ativo'] ? 'checked' : '' ?>> Ativo</label></div>
        <div class="actions">
          <button class="btn" type="submit">Salvar</button>
          <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/servicos.php">Cancelar</a>
        </div>
      </form>
    </div>
    <?php
} else {
    $servicos = $db->query('SELECT id, nome, descricao, valor_padrao, ativo FROM servicos ORDER BY nome')->fetchAll();
    ?>
    <h1>Serviços</h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <p><a class="btn" href="?acao=novo">+ Novo serviço</a></p>
    <table>
      <thead><tr><th>Nome</th><th>Descrição</th><th>Valor padrão</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($servicos as $s): ?>
        <tr>
          <td><?= e($s['nome']) ?></td>
          <td><?= e($s['descricao']) ?: '—' ?></td>
          <td><?= $s['valor_padrao'] !== null ? 'R$ ' . number_format((float)$s['valor_padrao'], 2, ',', '.') : '—' ?></td>
          <td><?= $s['ativo'] ? 'Ativo' : 'Inativo' ?></td>
          <td><a class="btn small" href="?acao=editar&id=<?= (int)$s['id'] ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$servicos): ?><tr><td colspan="5" class="muted">Nenhum serviço cadastrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php
}
require __DIR__ . '/includes/footer.php';
