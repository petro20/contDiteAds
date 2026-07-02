<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/cobrancas.php';
$me = require_admin();
$db = db();

$acao = $_GET['acao'] ?? 'lista';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cliente_filter = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'apagar') {
        $pid = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare('DELETE FROM assinaturas WHERE id = ?');
            $stmt->execute([$pid]);
            audit_log('assinatura.apagada', 'assinaturas', $pid);
            header('Location: ' . APP_BASE_URL . '/assinaturas.php?ok=del'); exit;
        } catch (PDOException $e) {
            $flash = ['err', 'Não dá pra apagar: tem cobranças geradas vinculadas. Mude para "cancelada" se quiser parar de cobrar.'];
            $acao = 'editar'; $id = $pid;
        }
    }

    if ($op === 'salvar') {
        $pid       = (int)($_POST['id'] ?? 0);
        $cliente   = (int)($_POST['cliente_id'] ?? 0);
        $item      = (int)($_POST['item_id'] ?? 0);
        $func      = (int)($_POST['funcionario_id'] ?? 0) ?: null;
        $variante  = ($_POST['variante'] ?? 'normal') === 'ia' ? 'ia' : 'normal';
        $valor     = (float)str_replace(',', '.', (string)($_POST['valor_cobrado'] ?? '0'));
        $iniciada  = $_POST['iniciada_em'] ?? date('Y-m-d');
        $status    = $_POST['status'] ?? 'ativa';
        $fixo_mensal = isset($_POST['cobrar_fixo_mensal']) ? 1 : 0;
        $desconto = max(0.0, min(100.0, (float)str_replace(',', '.', (string)($_POST['desconto_pct'] ?? '0'))));
        // Colunas que dependem de migration — só entram no SQL se existirem no banco.
        $cols_opc = [];
        if (db_coluna_existe($db, 'assinaturas', 'cobrar_fixo_mensal')) $cols_opc['cobrar_fixo_mensal'] = $fixo_mensal;
        if (db_coluna_existe($db, 'assinaturas', 'desconto_pct'))       $cols_opc['desconto_pct']       = $desconto;
        $forcar_atribuicao = isset($_POST['forcar_func_lotado']);
        if (!in_array($status, ['ativa','pausada','cancelada'], true)) $status = 'ativa';

        // Aviso: funcionário marcado como NÃO aceitando clientes
        if ($func && !$forcar_atribuicao) {
            try {
                $stmt = $db->prepare('SELECT aceitando_clientes, nome FROM usuarios WHERE id = ?');
                $stmt->execute([$func]);
                $f = $stmt->fetch();
                if ($f && (int)$f['aceitando_clientes'] === 0) {
                    $flash = ['err', '🔴 ' . htmlspecialchars($f['nome']) . ' está marcado como NÃO aceitando novos clientes. Se for proposital, marque o checkbox de força abaixo e salve de novo.'];
                    $a = ['id'=>$pid,'cliente_id'=>$cliente,'item_id'=>$item,'funcionario_id'=>$func,'variante'=>$variante,'valor_cobrado'=>$valor,'status'=>$status,'iniciada_em'=>$iniciada,'cobrar_fixo_mensal'=>$fixo_mensal,'desconto_pct'=>$desconto];
                    $acao = $pid ? 'editar' : 'novo'; $id = $pid;
                    $mostrar_forcar = true;
                    goto fim_save_assinatura;
                }
            } catch (PDOException $e) {}
        }

        if (!$cliente || !$item || $valor <= 0) {
            $flash = ['err', 'Cliente, item e valor (>0) são obrigatórios.'];
            $acao = $pid ? 'editar' : 'novo'; $id = $pid;
        } else {
            try {
                if ($pid) {
                    $set  = 'item_id=?, funcionario_id=?, variante=?, valor_cobrado=?, status=?, iniciada_em=?';
                    $args = [$item, $func, $variante, $valor, $status, $iniciada];
                    foreach ($cols_opc as $c => $v) { $set .= ", $c=?"; $args[] = $v; }
                    $args[] = $pid;
                    $stmt = $db->prepare("UPDATE assinaturas SET $set WHERE id=?");
                    $stmt->execute($args);
                    audit_log('assinatura.editada', 'assinaturas', $pid);
                    header('Location: ' . APP_BASE_URL . '/assinaturas.php?id=' . $pid . '&ok=upd'); exit;
                } else {
                    $db->beginTransaction();
                    $cols = ['cliente_id','item_id','funcionario_id','variante','valor_cobrado','status','iniciada_em'];
                    $args = [$cliente, $item, $func, $variante, $valor, $status, $iniciada];
                    foreach ($cols_opc as $c => $v) { $cols[] = $c; $args[] = $v; }
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    $stmt = $db->prepare('INSERT INTO assinaturas (' . implode(',', $cols) . ") VALUES ($ph)");
                    $stmt->execute($args);
                    $newId = (int)$db->lastInsertId();
                    ensure_dia_cobranca($db, $cliente, $iniciada);
                    $db->commit();

                    // Se o item é único, gera cobrança avulsa imediata
                    $stmt = $db->prepare('SELECT tipo FROM itens_catalogo WHERE id = ?');
                    $stmt->execute([$item]);
                    $tipo = $stmt->fetchColumn();
                    $cobr_id = null;
                    if ($tipo === 'unico') {
                        try {
                            $cobr_id = gerar_cobranca_avulsa_unico($db, $newId, (int)$me['id']);
                        } catch (Throwable $e) {
                            error_log('Erro cobrança avulsa: ' . $e->getMessage());
                        }
                    }
                    audit_log('assinatura.criada', 'assinaturas', $newId);
                    $redir = APP_BASE_URL . '/assinaturas.php?id=' . $newId . '&ok=add';
                    if ($cobr_id) $redir .= '&cobranca=' . $cobr_id;
                    header('Location: ' . $redir); exit;
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $flash = ['err', 'Erro: ' . $e->getMessage()];
                $acao = $pid ? 'editar' : 'novo'; $id = $pid;
            }
        }
        fim_save_assinatura:;
    }
}
$mostrar_forcar = $mostrar_forcar ?? false;

if (isset($_GET['ok'])) {
    $msg = $_GET['ok'] === 'add' ? 'Assinatura criada.' : 'Assinatura atualizada.';
    if (isset($_GET['cobranca'])) $msg .= ' Cobrança avulsa gerada (#' . (int)$_GET['cobranca'] . ').';
    $flash = ['ok', $msg];
}

$page = 'Assinaturas';
$nav_active = '';

if ($acao === 'novo' || $acao === 'editar') {
    $show_back = true;
    $back_to = APP_BASE_URL . '/assinaturas.php' . ($cliente_filter ? '?cliente_id=' . $cliente_filter : '');

    $a = ['id'=>0,'cliente_id'=>$cliente_filter,'item_id'=>0,'funcionario_id'=>0,'variante'=>'normal','valor_cobrado'=>'','status'=>'ativa','iniciada_em'=>date('Y-m-d'),'cobrar_fixo_mensal'=>0,'desconto_pct'=>0];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare('SELECT * FROM assinaturas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $a = array_merge($a, $row);
    }

    $clientes = $db->query('SELECT id, nome_empresa, moeda FROM clientes WHERE ativo=1 ORDER BY nome_empresa')->fetchAll();
    $itens = $db->query('SELECT id, nome, tipo, preco_usd, preco_brl, preco_eur, tem_variante_ia, preco_ia_usd, preco_ia_brl, preco_ia_eur, e_pacote FROM itens_catalogo WHERE ativo=1 ORDER BY e_pacote DESC, nome')->fetchAll();
    $funcs = $db->query("SELECT id, nome, aceitando_clientes FROM usuarios WHERE ativo=1 AND role IN ('admin','funcionario') ORDER BY nome")->fetchAll();

    // Mapa JS de preços por item (para autopreencher valor)
    $jsItens = [];
    foreach ($itens as $it) {
        $jsItens[(int)$it['id']] = [
            'tipo' => $it['tipo'],
            'tem_variante_ia' => (int)$it['tem_variante_ia'],
            'usd' => $it['preco_usd'], 'brl' => $it['preco_brl'], 'eur' => $it['preco_eur'],
            'usd_ia' => $it['preco_ia_usd'], 'brl_ia' => $it['preco_ia_brl'], 'eur_ia' => $it['preco_ia_eur'],
        ];
    }
    $jsClientes = [];
    foreach ($clientes as $c) {
        $jsClientes[(int)$c['id']] = ['moeda' => strtolower($c['moeda'])];
    }

    $page = $a['id'] ? 'Editar assinatura' : 'Nova assinatura';
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">

      <div class="card">
        <div class="field">
          <label>Cliente *</label>
          <select name="cliente_id" id="cliente_id" required <?= $a['id']?'disabled':'' ?>>
            <option value="">— selecione —</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $a['cliente_id']==$c['id']?'selected':'' ?>><?= e($c['nome_empresa']) ?> (<?= e($c['moeda']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <?php if ($a['id']): ?><input type="hidden" name="cliente_id" value="<?= (int)$a['cliente_id'] ?>"><?php endif; ?>
        </div>

        <div class="field">
          <label>Item do catálogo *</label>
          <select name="item_id" id="item_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($itens as $it): ?>
              <option value="<?= (int)$it['id'] ?>" <?= $a['item_id']==$it['id']?'selected':'' ?>>
                <?= e($it['nome']) ?> · <?= e($it['tipo']) ?><?= $it['e_pacote']?' · pacote':'' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" id="field_variante" style="display:none;">
          <label>Variante</label>
          <select name="variante" id="variante">
            <option value="normal" <?= $a['variante']==='normal'?'selected':'' ?>>Normal</option>
            <option value="ia"     <?= $a['variante']==='ia'?'selected':'' ?>>Com IA</option>
          </select>
        </div>

        <div class="field">
          <label>Funcionário responsável</label>
          <select name="funcionario_id">
            <option value="">— (sem atribuição) —</option>
            <?php foreach ($funcs as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $a['funcionario_id']==$f['id']?'selected':'' ?>>
                <?= e($f['nome']) ?> <?= $f['aceitando_clientes'] ? '🟢' : '🔴' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Troca vale para o mês seguinte; histórico fica preservado.</div>
          <?php if ($mostrar_forcar): ?>
            <label class="check" style="margin-top:8px; color:var(--c-attention);">
              <input type="checkbox" name="forcar_func_lotado" value="1">
              Sim, atribuir mesmo que o funcionário esteja marcado como não aceitando clientes
            </label>
          <?php endif; ?>
        </div>

        <div class="field">
          <label>Desconto (%)</label>
          <input type="number" step="0.01" min="0" max="100" name="desconto_pct" id="desconto_pct" value="<?= e(number_format((float)($a['desconto_pct'] ?? 0), 2, '.', '')) ?>" oninput="aplicaDesconto()">
          <div class="hint">Opcional. Ex: <strong>10</strong> = 10% off. O "Valor cobrado" abaixo é recalculado a partir do preço de tabela.</div>
        </div>

        <div class="field">
          <label>Valor cobrado *</label>
          <input type="number" step="0.01" min="0.01" name="valor_cobrado" id="valor_cobrado" required value="<?= $a['valor_cobrado']!==''?e(number_format((float)$a['valor_cobrado'],2,'.','')):'' ?>">
          <div class="hint" id="preco_hint">Preenchido com preço da tabela; pode sobrescrever (override por cliente).</div>
        </div>

        <div class="field">
          <label class="check">
            <input type="checkbox" name="cobrar_fixo_mensal" value="1" <?= (int)($a['cobrar_fixo_mensal'] ?? 0) === 1 ? 'checked' : '' ?>>
            Cobrar valor fixo todo mês (ignora entregas)
          </label>
          <div class="hint">Para itens <strong>por unidade</strong>: quando marcado, entra na cobrança mensal com o valor cheio acima, sem depender das entregas do mês anterior. Itens mensais já são fixos — não precisa marcar.</div>
        </div>

        <div class="field">
          <label>Data de início *</label>
          <input type="date" name="iniciada_em" required value="<?= e($a['iniciada_em']) ?>">
          <?php if ($a['id']): ?><div class="hint">Mudar a data afeta de quais meses essa assinatura entra em cobrança.</div><?php endif; ?>
        </div>

        <?php if ($a['id']):
            // Calcula se ainda está dentro do período mínimo
            $stmt = $db->prepare('SELECT periodo_minimo_meses FROM itens_catalogo WHERE id = ?');
            $stmt->execute([(int)$a['item_id']]);
            $per_min = (int)($stmt->fetchColumn() ?: 0);
            $aviso_min = null;
            if ($per_min > 0 && !empty($a['iniciada_em'])) {
                $inicio = new DateTimeImmutable($a['iniciada_em']);
                $fim_min = $inicio->modify('+' . $per_min . ' months');
                $hoje = new DateTimeImmutable();
                if ($hoje < $fim_min) {
                    $dias_faltam = (int)$hoje->diff($fim_min)->format('%a');
                    $aviso_min = 'Período mínimo de ' . $per_min . ' meses. Faltam ' . $dias_faltam . ' dias (até ' . $fim_min->format('d/m/Y') . ').';
                }
            }
        ?>
        <?php if ($aviso_min): ?>
          <div class="card attention" style="margin:var(--s-2) 0;">
            <div class="title" style="color:var(--c-orange);">⚠ Dentro do período mínimo</div>
            <div class="desc"><?= e($aviso_min) ?> Cancelar/pausar agora pode gerar atrito com o cliente.</div>
          </div>
        <?php endif; ?>
        <div class="field">
          <label>Status</label>
          <select name="status">
            <option value="ativa"      <?= $a['status']==='ativa'?'selected':'' ?>>Ativa</option>
            <option value="pausada"    <?= $a['status']==='pausada'?'selected':'' ?>>Pausada</option>
            <option value="cancelada"  <?= $a['status']==='cancelada'?'selected':'' ?>>Cancelada</option>
          </select>
        </div>
        <?php endif; ?>
      </div>

      <button class="btn block" type="submit">Salvar</button>
    </form>

    <?php if ($a['id']): ?>
      <details class="mt-5">
        <summary class="muted" style="cursor:pointer; padding:var(--s-3);">⚠ Zona de perigo</summary>
        <form method="post" class="mt-3" onsubmit="return confirm('APAGAR DEFINITIVAMENTE esta assinatura?\n\nSó funciona se NÃO tiver cobranças geradas. Caso tenha, mude o status para cancelada.\n\nConfirmar?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="apagar">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-danger block" type="submit">🗑 Apagar definitivamente</button>
        </form>
      </details>
    <?php endif; ?>

    <script>
    const ITENS = <?= json_encode($jsItens, JSON_UNESCAPED_UNICODE) ?>;
    const CLIENTES = <?= json_encode($jsClientes, JSON_UNESCAPED_UNICODE) ?>;
    function _fmtNum(v){ return isNaN(v) ? '—' : v.toFixed(2); }
    function precoTabela() {
      const itemId = document.getElementById('item_id').value;
      const cliId  = document.getElementById('cliente_id').value;
      if (!itemId || !cliId) return null;
      const it = ITENS[itemId], cli = CLIENTES[cliId];
      if (!it || !cli) return null;
      const varianteIA = document.getElementById('variante').value === 'ia';
      const v = it[cli.moeda + (varianteIA ? '_ia' : '')];
      return (v !== null && v !== undefined) ? parseFloat(v) : null;
    }
    function _descPct() {
      let d = parseFloat(String(document.getElementById('desconto_pct').value || '0').replace(',', '.'));
      if (isNaN(d)) d = 0;
      return Math.max(0, Math.min(100, d));
    }
    function _moeda() {
      const cli = CLIENTES[document.getElementById('cliente_id').value];
      return cli ? cli.moeda.toUpperCase() : '';
    }
    function _hint(base, desc, finalv) {
      const h = document.getElementById('preco_hint'); if (!h) return;
      if (base === null) { h.textContent = 'Preenchido com preço da tabela; pode sobrescrever.'; return; }
      const m = _moeda();
      if (desc > 0) h.innerHTML = 'Tabela: <strong>' + m + ' ' + _fmtNum(base) + '</strong> → com <strong>' + desc + '%</strong> off → <strong>' + m + ' ' + _fmtNum(finalv) + '</strong>';
      else h.textContent = 'Preço de tabela: ' + m + ' ' + _fmtNum(base) + '. Pode sobrescrever.';
    }
    // Item/cliente/variante mudou: recomputa (respeita override manual).
    function atualizaPreco() {
      const itemId = document.getElementById('item_id').value;
      const it = itemId ? ITENS[itemId] : null;
      const fv = document.getElementById('field_variante');
      if (fv && it) fv.style.display = it.tem_variante_ia ? '' : 'none';
      const base = precoTabela(), desc = _descPct();
      const input = document.getElementById('valor_cobrado');
      const finalv = base !== null ? +(base * (1 - desc/100)).toFixed(2) : null;
      if (base !== null && !input.dataset.touched) input.value = finalv.toFixed(2);
      _hint(base, desc, finalv);
    }
    // Desconto mudou: passa a mandar no valor (recalcula do preço de tabela).
    function aplicaDesconto() {
      const base = precoTabela(), desc = _descPct();
      const input = document.getElementById('valor_cobrado');
      if (base === null) { _hint(null, desc, null); return; }
      const finalv = +(base * (1 - desc/100)).toFixed(2);
      input.value = finalv.toFixed(2);
      input.dataset.touched = '';   // recalculado pelo desconto, não é mais override manual
      _hint(base, desc, finalv);
    }
    document.getElementById('item_id')?.addEventListener('change', atualizaPreco);
    document.getElementById('cliente_id')?.addEventListener('change', atualizaPreco);
    document.getElementById('variante')?.addEventListener('change', atualizaPreco);
    document.getElementById('valor_cobrado')?.addEventListener('input', function(){ this.dataset.touched = '1'; });

    // Modo edição: se já tem valor salvo, marca como "tocado" pra não sobrescrever
    // o override gravado. O hint ainda mostra tabela/desconto.
    const _vci = document.getElementById('valor_cobrado');
    if (_vci && _vci.value !== '') _vci.dataset.touched = '1';
    atualizaPreco();
    </script>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Lista
require __DIR__ . '/includes/header.php';
$where = ['1=1']; $params = [];
if ($cliente_filter) { $where[] = 'a.cliente_id = ?'; $params[] = $cliente_filter; }
$sql = 'SELECT a.id, a.valor_cobrado, a.variante, a.status, a.iniciada_em,
               cl.id AS cliente_id, cl.nome_empresa, cl.moeda,
               i.nome AS item_nome, i.tipo,
               u.nome AS funcionario_nome
        FROM assinaturas a
        JOIN clientes cl ON cl.id = a.cliente_id
        JOIN itens_catalogo i ON i.id = a.item_id
        LEFT JOIN usuarios u ON u.id = a.funcionario_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.status, cl.nome_empresa, i.nome';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll();

$cliente_nome = null;
if ($cliente_filter) {
    $stmt = $db->prepare('SELECT nome_empresa FROM clientes WHERE id = ?');
    $stmt->execute([$cliente_filter]);
    $cliente_nome = $stmt->fetchColumn();
}
?>
<h1 class="page-title">Assinaturas<?= $cliente_nome ? ' — ' . e($cliente_nome) : '' ?></h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<a class="btn btn-brand block" href="?acao=novo<?= $cliente_filter ? '&cliente_id=' . $cliente_filter : '' ?>">+ Nova assinatura</a>

<div class="section-label mt-5">Total (<?= count($lista) ?>)</div>
<?php foreach ($lista as $a): ?>
  <a class="list-card" href="?acao=editar&id=<?= (int)$a['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($a['item_nome']) ?>
        <?php if ($a['variante']==='ia'): ?><span class="status status-ia">IA</span><?php endif; ?>
        <span class="status status-<?= e($a['status']) ?>"><?= e($a['status']) ?></span>
      </div>
      <div class="sub"><?= e($a['nome_empresa']) ?> · <?= e($a['funcionario_nome'] ?? 'sem responsável') ?> · <?= e($a['tipo']) ?></div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt((float)$a['valor_cobrado'], $a['moeda'])) ?></div>
    </div>
  </a>
<?php endforeach; ?>
<?php if (!$lista): ?>
  <p class="muted center mt-5">Nenhuma assinatura. Clique em "+ Nova".</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
