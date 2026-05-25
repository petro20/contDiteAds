<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_admin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'salvar') {
        $id      = (int)($_POST['id'] ?? 0);
        $codigo  = trim((string)($_POST['codigo'] ?? ''));
        $canal   = $_POST['canal'] ?? 'email';
        if (!in_array($canal, ['email','whatsapp'], true)) $canal = 'email';
        $assunto = trim((string)($_POST['assunto'] ?? '')) ?: null;
        $corpo   = trim((string)($_POST['corpo'] ?? ''));
        $ativo   = isset($_POST['ativo']) ? 1 : 0;

        if ($codigo === '' || $corpo === '') {
            $flash = ['err', 'Código e corpo obrigatórios.'];
        } else {
            try {
                if ($id) {
                    $stmt = $db->prepare('UPDATE templates_mensagem SET codigo=?, canal=?, assunto=?, corpo=?, ativo=? WHERE id=?');
                    $stmt->execute([$codigo, $canal, $assunto, $corpo, $ativo, $id]);
                } else {
                    $stmt = $db->prepare('INSERT INTO templates_mensagem (codigo, canal, assunto, corpo, ativo) VALUES (?,?,?,?,?)');
                    $stmt->execute([$codigo, $canal, $assunto, $corpo, $ativo]);
                    $id = (int)$db->lastInsertId();
                }
                audit_log('template.salvo', 'templates_mensagem', $id);
                header('Location: ' . APP_BASE_URL . '/templates.php?ok=1'); exit;
            } catch (PDOException $e) {
                $flash = ['err', (int)$e->errorInfo[1] === 1062 ? 'Já existe template com este código+canal.' : $e->getMessage()];
            }
        }
    }
}

if (isset($_GET['ok'])) $flash = ['ok', 'Template salvo.'];

$page = 'Templates de mensagem';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$novo = isset($_GET['novo']);

if ($id || $novo) {
    $t = ['id'=>0,'codigo'=>'','canal'=>'whatsapp','assunto'=>'','corpo'=>'','ativo'=>1];
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM templates_mensagem WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $t = array_merge($t, $row);
    }
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <div class="card">
        <div class="field"><label>Código (identificador único)</label>
          <input name="codigo" required value="<?= e($t['codigo']) ?>" placeholder="ex: cobranca_nova, lembrete_vencendo">
        </div>
        <div class="field"><label>Canal</label>
          <select name="canal">
            <option value="whatsapp" <?= $t['canal']==='whatsapp'?'selected':'' ?>>WhatsApp</option>
            <option value="email"    <?= $t['canal']==='email'?'selected':'' ?>>Email</option>
          </select>
        </div>
        <div class="field"><label>Assunto (só email)</label><input name="assunto" value="<?= e($t['assunto'] ?? '') ?>"></div>
        <div class="field">
          <label>Corpo</label>
          <textarea name="corpo" required rows="10"><?= e($t['corpo']) ?></textarea>
          <div class="hint">Variáveis: <code>{nome_cliente}</code>, <code>{nome_empresa}</code>, <code>{valor}</code>, <code>{moeda}</code>, <code>{vencimento}</code>, <code>{mes_referencia}</code>, <code>{itens}</code>, <code>{link_recibo}</code>, <code>{link_comprovante}</code></div>
        </div>
        <label class="check"><input type="checkbox" name="ativo" <?= $t['ativo']?'checked':'' ?>> Ativo</label>
      </div>
      <button class="btn block" type="submit">Salvar</button>
    </form>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tpls = $db->query('SELECT * FROM templates_mensagem ORDER BY canal, codigo')->fetchAll();
?>
<h1 class="page-title">Templates</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<a class="btn btn-brand block" href="?novo=1">+ Novo template</a>

<div class="section-label mt-5">Cadastrados (<?= count($tpls) ?>)</div>
<?php foreach ($tpls as $t): ?>
  <a class="list-card" href="?id=<?= (int)$t['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($t['codigo']) ?>
        <span class="status status-<?= $t['canal']==='whatsapp'?'paga':'info' ?>"><?= e($t['canal']) ?></span>
        <?php if (!$t['ativo']): ?><span class="status status-info">inativo</span><?php endif; ?>
      </div>
      <div class="sub muted"><?= e(mb_substr($t['corpo'], 0, 80)) ?>...</div>
    </div>
  </a>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
