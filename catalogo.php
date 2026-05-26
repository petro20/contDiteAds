<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/audit.php';
require_sadmin();
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'salvar') {
        $pid       = (int)($_POST['id'] ?? 0);
        $nome      = trim((string)($_POST['nome'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? '')) ?: null;
        $tipo      = $_POST['tipo'] ?? 'unico';
        if (!in_array($tipo, ['unico','mensal','por_unidade'], true)) $tipo = 'unico';
        $a_negociar = isset($_POST['a_negociar']) ? 1 : 0;
        $e_pacote   = isset($_POST['e_pacote']) ? 1 : 0;
        $variante   = isset($_POST['tem_variante_ia']) ? 1 : 0;

        $valOrNull = fn($v) => ($v === '' || $v === null) ? null : (float)str_replace(',', '.', (string)$v);
        $preco_usd = $valOrNull($_POST['preco_usd'] ?? '');
        $preco_brl = $valOrNull($_POST['preco_brl'] ?? '');
        $preco_eur = $valOrNull($_POST['preco_eur'] ?? '');
        $preco_ia_usd = $valOrNull($_POST['preco_ia_usd'] ?? '');
        $preco_ia_brl = $valOrNull($_POST['preco_ia_brl'] ?? '');
        $preco_ia_eur = $valOrNull($_POST['preco_ia_eur'] ?? '');

        $resp_a = trim((string)($_POST['resp_agencia'] ?? '')) ?: null;
        $resp_f = trim((string)($_POST['resp_funcionario'] ?? '')) ?: null;
        $resp_c = trim((string)($_POST['resp_cliente'] ?? '')) ?: null;
        $ativo  = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '') {
            $flash = ['err', 'Nome é obrigatório.'];
            $acao = $pid ? 'editar' : 'novo'; $id = $pid;
        } elseif ($pid) {
            $stmt = $db->prepare('UPDATE itens_catalogo SET nome=?, descricao=?, tipo=?, preco_usd=?, preco_brl=?, preco_eur=?, a_negociar=?, e_pacote=?, tem_variante_ia=?, preco_ia_usd=?, preco_ia_brl=?, preco_ia_eur=?, resp_agencia=?, resp_funcionario=?, resp_cliente=?, ativo=? WHERE id=?');
            $stmt->execute([$nome,$descricao,$tipo,$preco_usd,$preco_brl,$preco_eur,$a_negociar,$e_pacote,$variante,$preco_ia_usd,$preco_ia_brl,$preco_ia_eur,$resp_a,$resp_f,$resp_c,$ativo,$pid]);
            audit_log('catalogo.editado', 'itens_catalogo', $pid);
            header('Location: ' . APP_BASE_URL . '/catalogo.php?acao=editar&id=' . $pid . '&ok=upd'); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO itens_catalogo (nome,descricao,tipo,preco_usd,preco_brl,preco_eur,a_negociar,e_pacote,tem_variante_ia,preco_ia_usd,preco_ia_brl,preco_ia_eur,resp_agencia,resp_funcionario,resp_cliente,ativo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$nome,$descricao,$tipo,$preco_usd,$preco_brl,$preco_eur,$a_negociar,$e_pacote,$variante,$preco_ia_usd,$preco_ia_brl,$preco_ia_eur,$resp_a,$resp_f,$resp_c,$ativo]);
            $newId = (int)$db->lastInsertId();
            audit_log('catalogo.criado', 'itens_catalogo', $newId);
            header('Location: ' . APP_BASE_URL . '/catalogo.php?acao=editar&id=' . $newId . '&ok=add'); exit;
        }
    }

    if ($op === 'comp_add') {
        $pacote_id = (int)($_POST['pacote_id'] ?? 0);
        $componente = (int)($_POST['componente_id'] ?? 0);
        $qtd = max(1, (int)($_POST['quantidade'] ?? 1));
        $var = $_POST['variante'] ?? 'normal';
        if (!in_array($var, ['normal','ia'], true)) $var = 'normal';
        if ($pacote_id && $componente && $pacote_id !== $componente) {
            try {
                $stmt = $db->prepare('INSERT INTO itens_pacote_composicao (pacote_id, componente_id, quantidade, variante) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE quantidade=VALUES(quantidade)');
                $stmt->execute([$pacote_id, $componente, $qtd, $var]);
            } catch (PDOException $e) {}
        }
        header('Location: ' . APP_BASE_URL . '/catalogo.php?acao=editar&id=' . $pacote_id . '#composicao'); exit;
    }

    if ($op === 'comp_remove') {
        $pacote_id = (int)($_POST['pacote_id'] ?? 0);
        $componente = (int)($_POST['componente_id'] ?? 0);
        $var = $_POST['variante'] ?? 'normal';
        $stmt = $db->prepare('DELETE FROM itens_pacote_composicao WHERE pacote_id=? AND componente_id=? AND variante=?');
        $stmt->execute([$pacote_id, $componente, $var]);
        header('Location: ' . APP_BASE_URL . '/catalogo.php?acao=editar&id=' . $pacote_id . '#composicao'); exit;
    }
}

if (isset($_GET['ok'])) {
    $flash = ['ok', $_GET['ok'] === 'add' ? 'Item criado.' : 'Item atualizado.'];
}

$page = 'Catálogo';
$nav_active = 'catalogo';

if ($acao === 'novo' || $acao === 'editar') {
    $show_back = true;
    $back_to = APP_BASE_URL . '/catalogo.php';
    $item = ['id'=>0,'nome'=>'','descricao'=>'','tipo'=>'unico','preco_usd'=>'','preco_brl'=>'','preco_eur'=>'','a_negociar'=>0,'e_pacote'=>0,'tem_variante_ia'=>0,'preco_ia_usd'=>'','preco_ia_brl'=>'','preco_ia_eur'=>'','resp_agencia'=>'','resp_funcionario'=>'','resp_cliente'=>'','ativo'=>1];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare('SELECT * FROM itens_catalogo WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $item = array_merge($item, $row);
    }
    $page = $item['id'] ? 'Editar item' : 'Novo item';
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

      <div class="card">
        <div class="field">
          <label>Nome do item *</label>
          <input name="nome" required value="<?= e($item['nome']) ?>" placeholder="Ex: POSTAGEM 7D, Meta ADS">
        </div>
        <div class="field">
          <label>Descrição</label>
          <input name="descricao" value="<?= e($item['descricao'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Tipo de cobrança *</label>
          <select name="tipo" required>
            <option value="unico"       <?= $item['tipo']==='unico'?'selected':'' ?>>Único (one-shot)</option>
            <option value="mensal"      <?= $item['tipo']==='mensal'?'selected':'' ?>>Mensal (recorrente)</option>
            <option value="por_unidade" <?= $item['tipo']==='por_unidade'?'selected':'' ?>>Por unidade</option>
          </select>
        </div>
        <label class="check"><input type="checkbox" name="ativo" <?= $item['ativo']?'checked':'' ?>> Item ativo (aparece para contratar)</label>
        <label class="check"><input type="checkbox" name="a_negociar" <?= $item['a_negociar']?'checked':'' ?>> Preço a negociar (cliente a cliente)</label>
        <label class="check"><input type="checkbox" name="e_pacote" <?= $item['e_pacote']?'checked':'' ?>> Este item é um pacote (combo de outros)</label>
        <label class="check"><input type="checkbox" name="tem_variante_ia" <?= $item['tem_variante_ia']?'checked':'' ?>> Tem variante "com IA" (preços alternativos)</label>
      </div>

      <h2>Preços por moeda</h2>
      <div class="card">
        <div class="section-label">Padrão</div>
        <div class="grid-2">
          <div class="field"><label>USD ($)</label><input type="number" step="0.01" min="0" name="preco_usd" value="<?= $item['preco_usd']!==null && $item['preco_usd']!==''?e(number_format((float)$item['preco_usd'],2,'.','')):'' ?>" placeholder="vazio = não vende"></div>
          <div class="field"><label>BRL (R$)</label><input type="number" step="0.01" min="0" name="preco_brl" value="<?= $item['preco_brl']!==null && $item['preco_brl']!==''?e(number_format((float)$item['preco_brl'],2,'.','')):'' ?>"></div>
          <div class="field"><label>EUR (€)</label><input type="number" step="0.01" min="0" name="preco_eur" value="<?= $item['preco_eur']!==null && $item['preco_eur']!==''?e(number_format((float)$item['preco_eur'],2,'.','')):'' ?>"></div>
        </div>
        <?php if ($item['tem_variante_ia']): ?>
        <div class="section-label">Variante "com IA"</div>
        <div class="grid-2">
          <div class="field"><label>USD ($)</label><input type="number" step="0.01" min="0" name="preco_ia_usd" value="<?= $item['preco_ia_usd']!==null && $item['preco_ia_usd']!==''?e(number_format((float)$item['preco_ia_usd'],2,'.','')):'' ?>"></div>
          <div class="field"><label>BRL (R$)</label><input type="number" step="0.01" min="0" name="preco_ia_brl" value="<?= $item['preco_ia_brl']!==null && $item['preco_ia_brl']!==''?e(number_format((float)$item['preco_ia_brl'],2,'.','')):'' ?>"></div>
          <div class="field"><label>EUR (€)</label><input type="number" step="0.01" min="0" name="preco_ia_eur" value="<?= $item['preco_ia_eur']!==null && $item['preco_ia_eur']!==''?e(number_format((float)$item['preco_ia_eur'],2,'.','')):'' ?>"></div>
        </div>
        <?php endif; ?>
      </div>

      <h2>Responsabilidades</h2>
      <div class="card">
        <div class="field"><label>O que a agência entrega</label><textarea name="resp_agencia"><?= e($item['resp_agencia'] ?? '') ?></textarea></div>
        <div class="field"><label>O que o funcionário faz</label><textarea name="resp_funcionario"><?= e($item['resp_funcionario'] ?? '') ?></textarea></div>
        <div class="field"><label>O que o cliente fornece</label><textarea name="resp_cliente"><?= e($item['resp_cliente'] ?? '') ?></textarea></div>
      </div>

      <button class="btn block" type="submit">Salvar item</button>
    </form>

    <?php if ($item['id'] && $item['e_pacote']):
        $stmt = $db->prepare('SELECT c.componente_id, c.quantidade, c.variante, i.nome FROM itens_pacote_composicao c JOIN itens_catalogo i ON i.id = c.componente_id WHERE c.pacote_id = ? ORDER BY c.variante, i.nome');
        $stmt->execute([$item['id']]);
        $componentes = $stmt->fetchAll();
        $outros = $db->query('SELECT id, nome FROM itens_catalogo WHERE e_pacote = 0 AND ativo = 1 ORDER BY nome')->fetchAll();
    ?>
    <h2 id="composicao">Composição do pacote</h2>
    <div class="card">
      <?php if ($componentes): ?>
        <?php foreach ($componentes as $c): ?>
          <div class="list-card">
            <div class="info">
              <div class="nome"><?= (int)$c['quantidade'] ?>× <?= e($c['nome']) ?></div>
              <div class="sub">Variante: <?= $c['variante']==='ia' ? '<span class="status status-ia">com IA</span>' : 'normal' ?></div>
            </div>
            <form method="post" style="margin:0;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="op" value="comp_remove">
              <input type="hidden" name="pacote_id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="componente_id" value="<?= (int)$c['componente_id'] ?>">
              <input type="hidden" name="variante" value="<?= e($c['variante']) ?>">
              <button class="btn btn-ghost small" type="submit">Remover</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="muted">Nenhum componente. Adicione abaixo.</p>
      <?php endif; ?>
    </div>

    <h2>Adicionar componente</h2>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="comp_add">
        <input type="hidden" name="pacote_id" value="<?= (int)$item['id'] ?>">
        <div class="field"><label>Item</label>
          <select name="componente_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($outros as $o): ?>
              <option value="<?= (int)$o['id'] ?>"><?= e($o['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid-2">
          <div class="field"><label>Quantidade</label><input type="number" min="1" name="quantidade" value="1" required></div>
          <div class="field"><label>Variante</label>
            <select name="variante">
              <option value="normal">Normal</option>
              <?php if ($item['tem_variante_ia']): ?><option value="ia">Com IA</option><?php endif; ?>
            </select>
          </div>
        </div>
        <button class="btn block" type="submit">Adicionar</button>
      </form>
    </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Lista
require __DIR__ . '/includes/header.php';
$f_tipo = $_GET['tipo'] ?? '';
$sql = 'SELECT id, nome, tipo, preco_usd, preco_brl, preco_eur, a_negociar, e_pacote, tem_variante_ia, ativo FROM itens_catalogo';
$params = [];
if (in_array($f_tipo, ['unico','mensal','por_unidade'], true)) {
    $sql .= ' WHERE tipo = ?'; $params[] = $f_tipo;
}
$sql .= ' ORDER BY e_pacote DESC, tipo, nome';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$itens = $stmt->fetchAll();
?>
<?php
$f_moeda = $_GET['moeda'] ?? 'BRL';
if (!in_array($f_moeda, ['BRL','USD','EUR'], true)) $f_moeda = 'BRL';
?>
<h1 class="page-title">Catálogo</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<a href="?acao=novo" class="btn btn-brand block">+ Novo item</a>

<nav class="tabs-bar mt-3">
  <?php foreach (['BRL'=>'R$ BRL','USD'=>'$ USD','EUR'=>'€ EUR'] as $m => $lbl): ?>
    <a class="<?= $f_moeda===$m?'active':'' ?>" href="?moeda=<?= $m ?><?= $f_tipo?'&tipo='.e($f_tipo):'' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</nav>

<form method="get" class="card">
  <input type="hidden" name="moeda" value="<?= e($f_moeda) ?>">
  <div class="field" style="margin:0;">
    <label>Tipo</label>
    <select name="tipo" onchange="this.form.submit()">
      <option value="">Todos os tipos</option>
      <option value="unico"       <?= $f_tipo==='unico'?'selected':'' ?>>Únicos</option>
      <option value="mensal"      <?= $f_tipo==='mensal'?'selected':'' ?>>Mensais</option>
      <option value="por_unidade" <?= $f_tipo==='por_unidade'?'selected':'' ?>>Por unidade</option>
    </select>
  </div>
</form>

<div class="section-label">Itens (<?= count($itens) ?>)</div>
<?php foreach ($itens as $it): ?>
  <a class="list-card" href="?acao=editar&id=<?= (int)$it['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($it['nome']) ?>
        <?php if ($it['e_pacote']): ?><span class="status status-ia">pacote</span><?php endif; ?>
        <?php if (!$it['ativo']): ?><span class="status status-info">inativo</span><?php endif; ?>
        <?php if ($it['tem_variante_ia']): ?><span class="status status-ia">com IA</span><?php endif; ?>
      </div>
      <div class="sub">
        <?= e(['unico'=>'único','mensal'=>'mensal','por_unidade'=>'por unidade'][$it['tipo']]) ?>
        <?php if ($it['a_negociar']): ?> · <span class="status status-warning">a negociar</span><?php endif; ?>
      </div>
    </div>
    <div class="right">
      <?php
        $col = 'preco_' . strtolower($f_moeda);
        $col_ia = 'preco_ia_' . strtolower($f_moeda);
        $val = $it[$col] ?? null;
        $val_ia = $it[$col_ia] ?? null;
      ?>
      <div class="money md"><?= $val !== null ? e(money_fmt((float)$val, $f_moeda)) : '—' ?></div>
      <?php if ($it['tem_variante_ia'] && $val_ia !== null): ?>
        <div class="muted" style="font-size:11px;">IA: <?= e(money_fmt((float)$val_ia, $f_moeda)) ?></div>
      <?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
<?php if (!$itens): ?>
  <p class="muted center">Nenhum item ainda. Clique em "+ Novo item" ou rode <code>db/seed_catalogo.sql</code>.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
