<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/despesas.php';
$me = require_sadmin();
$db = db();

$acao = $_GET['acao'] ?? 'lista';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

$categorias = ['Ferramentas/Software', 'Hospedagem', 'Marketing', 'Licenças', 'Impostos', 'Salários extras', 'Equipamentos', 'Outros'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'salvar') {
        $pid       = (int)($_POST['id'] ?? 0);
        $nome      = trim((string)($_POST['nome'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? '')) ?: null;
        $categoria = trim((string)($_POST['categoria'] ?? '')) ?: null;
        $valor     = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
        $moeda     = in_array($_POST['moeda'] ?? '', ['USD','BRL','EUR'], true) ? $_POST['moeda'] : 'BRL';
        $rec       = in_array($_POST['recorrencia'] ?? '', ['unica','mensal','anual'], true) ? $_POST['recorrencia'] : 'mensal';
        $data_ini  = $_POST['data_inicio'] ?? date('Y-m-d');
        $data_fim  = trim((string)($_POST['data_fim'] ?? '')) ?: null;
        $ativo     = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '' || $valor <= 0) {
            $flash = ['err', 'Nome e valor (>0) são obrigatórios.'];
            $acao = $pid ? 'editar' : 'novo'; $id = $pid;
        } elseif ($pid) {
            $stmt = $db->prepare('UPDATE despesas SET nome=?, descricao=?, categoria=?, valor=?, moeda=?, recorrencia=?, data_inicio=?, data_fim=?, ativo=? WHERE id=?');
            $stmt->execute([$nome,$descricao,$categoria,$valor,$moeda,$rec,$data_ini,$data_fim,$ativo,$pid]);
            audit_log('despesa.editada', 'despesas', $pid);
            header('Location: ' . APP_BASE_URL . '/despesas.php?ok=upd'); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO despesas (nome,descricao,categoria,valor,moeda,recorrencia,data_inicio,data_fim,ativo,criado_por) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$nome,$descricao,$categoria,$valor,$moeda,$rec,$data_ini,$data_fim,$ativo,(int)$me['id']]);
            audit_log('despesa.criada', 'despesas', (int)$db->lastInsertId());
            header('Location: ' . APP_BASE_URL . '/despesas.php?ok=add'); exit;
        }
    }
    if (($_POST['op'] ?? '') === 'apagar') {
        $pid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM despesas WHERE id = ?');
        $stmt->execute([$pid]);
        audit_log('despesa.apagada', 'despesas', $pid);
        header('Location: ' . APP_BASE_URL . '/despesas.php?ok=del'); exit;
    }
}

if (isset($_GET['ok'])) {
    $m = ['add'=>'Despesa criada.','upd'=>'Despesa atualizada.','del'=>'Despesa apagada.'];
    $flash = ['ok', $m[$_GET['ok']] ?? 'OK.'];
}

$page = 'Despesas';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';

if ($acao === 'novo' || $acao === 'editar') {
    $d = ['id'=>0,'nome'=>'','descricao'=>'','categoria'=>'','valor'=>'','moeda'=>'BRL','recorrencia'=>'mensal','data_inicio'=>date('Y-m-d'),'data_fim'=>'','ativo'=>1];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare('SELECT * FROM despesas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $d = array_merge($d, $row);
    }
    $page = $d['id'] ? 'Editar despesa' : 'Nova despesa';
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <div class="card">
        <div class="field"><label>Nome *</label><input name="nome" required value="<?= e($d['nome']) ?>" placeholder="Ex: Adobe Creative Cloud"></div>
        <div class="field"><label>Categoria</label>
          <select name="categoria">
            <option value="">— sem categoria —</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= e($c) ?>" <?= $d['categoria']===$c?'selected':'' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="field"><label>Valor *</label><input type="number" step="0.01" min="0.01" name="valor" required value="<?= $d['valor']!==''?e(number_format((float)$d['valor'],2,'.','')):'' ?>"></div>
          <div class="field"><label>Moeda</label>
            <select name="moeda">
              <option value="BRL" <?= $d['moeda']==='BRL'?'selected':'' ?>>R$ BRL</option>
              <option value="USD" <?= $d['moeda']==='USD'?'selected':'' ?>>$ USD</option>
              <option value="EUR" <?= $d['moeda']==='EUR'?'selected':'' ?>>€ EUR</option>
            </select>
          </div>
        </div>
        <div class="field"><label>Recorrência</label>
          <select name="recorrencia">
            <option value="mensal" <?= $d['recorrencia']==='mensal'?'selected':'' ?>>Mensal (todo mês)</option>
            <option value="anual"  <?= $d['recorrencia']==='anual'?'selected':'' ?>>Anual (1× por ano)</option>
            <option value="unica"  <?= $d['recorrencia']==='unica'?'selected':'' ?>>Única (gasto pontual)</option>
          </select>
        </div>
        <div class="grid-2">
          <div class="field"><label>Data de início *</label><input type="date" name="data_inicio" required value="<?= e($d['data_inicio']) ?>"></div>
          <div class="field"><label>Data de fim (opcional)</label><input type="date" name="data_fim" value="<?= e($d['data_fim'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Descrição</label><textarea name="descricao"><?= e($d['descricao'] ?? '') ?></textarea></div>
        <label class="check"><input type="checkbox" name="ativo" <?= $d['ativo']?'checked':'' ?>> Despesa ativa (entra no cálculo)</label>
      </div>
      <button class="btn block" type="submit">Salvar</button>
    </form>

    <?php if ($d['id']): ?>
      <form method="post" class="mt-5" onsubmit="return confirm('Apagar definitivamente esta despesa?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="apagar">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <button class="btn btn-danger block" type="submit">🗑 Apagar</button>
      </form>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

require __DIR__ . '/includes/header.php';
$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');
$impacto = despesas_do_mes($db, $competencia);
$todas = $db->query('SELECT * FROM despesas ORDER BY ativo DESC, categoria, nome')->fetchAll();

$dt = DateTime::createFromFormat('Y-m', $competencia);
$nome_mes = ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)$dt->format('n')] . ' de ' . $dt->format('Y');
?>
<h1 class="page-title">Despesas da empresa</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<a class="btn btn-brand block" href="?acao=novo">+ Nova despesa</a>

<h2>Impacto em <?= e($nome_mes) ?></h2>
<div class="grid-2">
  <?php foreach (['BRL','USD','EUR'] as $m): ?>
    <div class="kpi"><div class="v"><?= e(money_fmt($impacto['totais'][$m], $m)) ?></div><div class="l">Total <?= $m ?> / mês</div></div>
  <?php endforeach; ?>
</div>

<h2>Cadastradas (<?= count($todas) ?>)</h2>
<?php foreach ($todas as $d): ?>
  <a class="list-card" href="?acao=editar&id=<?= (int)$d['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($d['nome']) ?>
        <?php if (!$d['ativo']): ?><span class="status status-info">inativa</span><?php endif; ?>
        <?php if ($d['categoria']): ?><span class="status status-ia"><?= e($d['categoria']) ?></span><?php endif; ?>
      </div>
      <div class="sub muted"><?= e($d['recorrencia']) ?> desde <?= e(date('d/m/Y', strtotime($d['data_inicio']))) ?><?= $d['data_fim']?' até ' . e(date('d/m/Y', strtotime($d['data_fim']))):'' ?></div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt((float)$d['valor'], $d['moeda'])) ?></div>
    </div>
  </a>
<?php endforeach; ?>
<?php if (!$todas): ?><p class="muted center mt-5">Nenhuma despesa cadastrada.</p><?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
