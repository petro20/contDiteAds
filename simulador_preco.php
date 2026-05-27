<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/cotacao.php';
require_once __DIR__ . '/lib/audit.php';
$me = require_sadmin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'salvar_sim') {
        $sim_id = (int)($_POST['sim_id'] ?? 0);
        $nome = trim((string)($_POST['nome'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? '')) ?: null;
        $tipo = $_POST['tipo'] ?? 'mensal';
        if (!in_array($tipo, ['unico','mensal','por_unidade'], true)) $tipo = 'mensal';
        $periodo = max(0, min(60, (int)($_POST['periodo_minimo_meses'] ?? 0)));
        $margem = max(0, min(500, (float)($_POST['margem_pct'] ?? 50)));
        $ia = isset($_POST['tem_variante_ia']) ? 1 : 0;
        $custos_json = (string)($_POST['custos_json'] ?? '[]');
        // Valida que é JSON válido
        $decoded = json_decode($custos_json, true);
        if (!is_array($decoded)) $custos_json = '[]';

        if ($nome === '') {
            $flash = ['err', 'Nome é obrigatório pra salvar.'];
        } else {
            try {
                if ($sim_id) {
                    $stmt = $db->prepare('UPDATE simulacoes_preco SET nome=?, descricao=?, tipo=?, periodo_minimo_meses=?, margem_pct=?, tem_variante_ia=?, custos_json=? WHERE id=?');
                    $stmt->execute([$nome, $descricao, $tipo, $periodo, $margem, $ia, $custos_json, $sim_id]);
                    audit_log('simulacao.editada', 'simulacoes_preco', $sim_id);
                } else {
                    $stmt = $db->prepare('INSERT INTO simulacoes_preco (nome, descricao, tipo, periodo_minimo_meses, margem_pct, tem_variante_ia, custos_json, criado_por) VALUES (?,?,?,?,?,?,?,?)');
                    $stmt->execute([$nome, $descricao, $tipo, $periodo, $margem, $ia, $custos_json, (int)$me['id']]);
                    $sim_id = (int)$db->lastInsertId();
                    audit_log('simulacao.criada', 'simulacoes_preco', $sim_id);
                }
                header('Location: ' . APP_BASE_URL . '/simulador_preco.php?sim_id=' . $sim_id . '&ok=1'); exit;
            } catch (PDOException $e) {
                $flash = ['err', 'Erro: ' . $e->getMessage()];
            }
        }
    }

    if ($op === 'apagar_sim') {
        $sim_id = (int)($_POST['sim_id'] ?? 0);
        if ($sim_id) {
            $db->prepare('DELETE FROM simulacoes_preco WHERE id = ?')->execute([$sim_id]);
            audit_log('simulacao.apagada', 'simulacoes_preco', $sim_id);
            header('Location: ' . APP_BASE_URL . '/simulador_preco.php?ok=del'); exit;
        }
    }
}

if (isset($_GET['ok'])) {
    $flash = ['ok', $_GET['ok'] === 'del' ? 'Simulação apagada.' : 'Simulação salva.'];
}

// Carrega simulação se ?sim_id=N
$sim_id = (int)($_GET['sim_id'] ?? 0);
$sim = [
    'id' => 0, 'nome' => '', 'descricao' => '',
    'tipo' => 'mensal', 'periodo_minimo_meses' => 3,
    'margem_pct' => 50, 'tem_variante_ia' => 0,
    'custos_json' => '[]',
];
if ($sim_id) {
    $stmt = $db->prepare('SELECT * FROM simulacoes_preco WHERE id = ?');
    $stmt->execute([$sim_id]);
    $row = $stmt->fetch();
    if ($row) $sim = array_merge($sim, $row);
}

// Lista de simulações salvas
$lista = $db->query('SELECT id, nome, tipo, atualizado_em FROM simulacoes_preco ORDER BY atualizado_em DESC LIMIT 50')->fetchAll();

$page = 'Simulador de preço';
$show_back = true;
$back_to = APP_BASE_URL . '/catalogo.php';
require __DIR__ . '/includes/header.php';

$cot = cotacao_atual($db);
?>
<h1 class="page-title">📊 Simulador de preço</h1>
<p class="muted">Liste os custos do serviço (em USD), defina a margem desejada, e o sistema calcula o preço final pra cadastrar no catálogo. Pode salvar pra editar depois.</p>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if ($lista): ?>
<details class="card mt-3">
  <summary class="muted" style="cursor:pointer; padding:8px 0;">📂 Simulações salvas (<?= count($lista) ?>)</summary>
  <div class="mt-2">
    <?php foreach ($lista as $s): ?>
      <a class="list-card" href="?sim_id=<?= (int)$s['id'] ?>" style="<?= (int)$s['id'] === (int)$sim['id'] ? 'border-color:var(--c-primary-2);' : '' ?>">
        <div class="info">
          <div class="nome"><?= e($s['nome']) ?> <span class="status status-info"><?= e($s['tipo']) ?></span></div>
          <div class="sub muted">Atualizada <?= e(date('d/m/Y H:i', strtotime($s['atualizado_em']))) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
    <a href="<?= e(APP_BASE_URL) ?>/simulador_preco.php" class="btn btn-ghost block mt-2">+ Nova simulação (em branco)</a>
  </div>
</details>
<?php endif; ?>

<form method="post" id="form_sim">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="op" value="salvar_sim">
  <input type="hidden" name="sim_id" value="<?= (int)$sim['id'] ?>">
  <input type="hidden" name="custos_json" id="custos_json_input">

<div class="card">
  <div class="field">
    <label>Nome do serviço (rascunho) *</label>
    <input type="text" id="sim_nome" name="nome" value="<?= e($sim['nome']) ?>" placeholder="ex: META ADS Pro, LANDING PAGE Premium" required>
  </div>
  <div class="field">
    <label>📝 Descrição do serviço</label>
    <textarea id="sim_descricao" name="descricao" rows="8" placeholder="Conta em poucas palavras o que será feito. A IA vai expandir em descrição detalhada com escopo, entregas, ferramentas, responsabilidades. Ex: 'Anúncios Google Ads + Meta Ads, até 5 campanhas/plataforma'"><?= e($sim['descricao']) ?></textarea>
    <div class="hint">A IA vai DETALHAR completamente — você só dá o ponto de partida.</div>
  </div>
  <button type="button" class="btn btn-brand block" id="btn_ia" onclick="sugerirComIA()">✨ Preencher com IA</button>
  <div id="ia_msg" class="hint mt-2" style="text-align:center;"></div>

  <div class="grid-2 mt-3">
    <div class="field">
      <label>Tipo de cobrança</label>
      <select id="sim_tipo" name="tipo" onchange="toggleSimPeriodo()">
        <option value="unico" <?= $sim['tipo']==='unico'?'selected':'' ?>>Único (one-shot)</option>
        <option value="mensal" <?= $sim['tipo']==='mensal'?'selected':'' ?>>Mensal (recorrente)</option>
        <option value="por_unidade" <?= $sim['tipo']==='por_unidade'?'selected':'' ?>>Por unidade</option>
      </select>
    </div>
    <div class="field" id="sim_periodo_box">
      <label>Período mínimo (meses)</label>
      <input type="number" id="sim_periodo" name="periodo_minimo_meses" min="0" max="60" value="<?= (int)$sim['periodo_minimo_meses'] ?>">
      <div class="hint">Tempo mínimo que o cliente deve manter o contrato.</div>
    </div>
  </div>
  <label class="check"><input type="checkbox" id="sim_ia" name="tem_variante_ia" <?= $sim['tem_variante_ia']?'checked':'' ?>> Tem variante "com IA" (preços alternativos)</label>
</div>

<script>
function toggleSimPeriodo() {
  document.getElementById('sim_periodo_box').style.display = document.getElementById('sim_tipo').value === 'mensal' ? 'block' : 'none';
}
</script>

<h2 class="mt-5">💸 Custos (USD)</h2>
<div class="card">
  <p class="muted" style="font-size:13px;">Cada linha tem <strong>Valor total ÷ Dividir por = por unidade</strong>. Use o divisor pra rateio: ex. "Opus Clips $174 dividido por 36 vídeos" → $4.83 por vídeo. Pra custo direto, deixe divisor em 1.</p>
  <p class="hint">💡 Use o botão <strong>🔍</strong> em cada linha pra pesquisar o preço atual da ferramenta no mercado.</p>

  <details class="mt-2">
    <summary class="muted" style="cursor:pointer; padding:8px 0; font-size:13px;">💼 Adicionar software popular (preços referência)</summary>
    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Canva Pro (mensal)', 13)">Canva Pro $13</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Adobe Creative Cloud', 55)">Adobe CC $55</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Figma Pro', 15)">Figma $15</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('ChatGPT Plus', 20)">ChatGPT $20</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Midjourney', 30)">Midjourney $30</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Capcut Pro', 10)">Capcut Pro $10</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Opus Clips Pro', 29)">Opus Clips $29</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Google Drive 2TB', 10)">Google Drive 2TB $10</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Dropbox Pro', 12)">Dropbox $12</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Notion Plus', 10)">Notion $10</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Buffer (postagem)', 6)">Buffer $6</button>
      <button type="button" class="btn btn-ghost small" onclick="adicionarLinha('Meta Business Suite', 0)">Meta Suite (grátis)</button>
    </div>
  </details>

  <div id="lista_custos"></div>
  <button type="button" class="btn btn-secondary block mt-3" onclick="adicionarLinha()">+ Adicionar custo</button>

  <div class="info-pair mt-3" style="border-top:1px solid var(--border); padding-top:var(--s-3);">
    <strong>Custo total</strong>
    <strong class="money md" style="color:var(--c-danger);">$ <span id="total_custo">0.00</span></strong>
  </div>
</div>

<h2 class="mt-5">📈 Margem e preço final</h2>
<div class="card">
  <div class="grid-2">
    <div class="field">
      <label>Margem desejada (%)</label>
      <input type="number" id="margem" name="margem_pct" value="<?= (float)$sim['margem_pct'] ?>" min="0" step="1" oninput="recalcular()">
      <div class="hint">% sobre o custo. Ex: custo $40 + 50% margem = preço $60.</div>
    </div>
    <div class="field">
      <label>Preço calculado (USD)</label>
      <input type="text" id="preco_calc" disabled>
    </div>
  </div>

  <div class="info-pair mt-3">
    <span class="l">💵 Preço final (USD)</span>
    <strong class="money lg" style="color:var(--c-success);">$ <span id="preco_final">0</span></strong>
  </div>
  <div class="info-pair muted" style="font-size:13px;">
    <span class="l">↳ Lucro USD</span>
    <span>$ <span id="lucro_usd">0.00</span></span>
  </div>
  <div class="info-pair muted" style="font-size:13px;">
    <span class="l">↳ Em BRL (cot. <?= number_format($cot['BRL'], 4) ?>)</span>
    <span>R$ <span id="preco_brl">0</span></span>
  </div>
  <div class="info-pair muted" style="font-size:13px;">
    <span class="l">↳ Em EUR (cot. <?= number_format($cot['EUR'], 4) ?>)</span>
    <span>€ <span id="preco_eur">0</span></span>
  </div>
</div>

<div class="btn-pair mt-5">
  <button type="submit" class="btn btn-secondary block" onclick="prepararSalvar()">💾 <?= $sim['id'] ? 'Salvar alterações' : 'Salvar simulação' ?></button>
</div>

</form>

<?php if ((int)$sim['id'] > 0): ?>
<form method="post" class="mt-3" onsubmit="return confirm('Apagar esta simulação?');">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="op" value="apagar_sim">
  <input type="hidden" name="sim_id" value="<?= (int)$sim['id'] ?>">
  <button type="submit" class="btn btn-ghost small block" style="color:var(--c-danger);">🗑 Apagar simulação</button>
</form>
<?php endif; ?>

<form method="get" action="<?= e(APP_BASE_URL) ?>/catalogo.php" class="mt-5">
  <input type="hidden" name="acao" value="novo">
  <input type="hidden" name="nome" id="frm_nome">
  <input type="hidden" name="descricao" id="frm_descricao">
  <input type="hidden" name="preco_usd" id="frm_preco">
  <input type="hidden" name="preco_ia_usd" id="frm_preco_ia">
  <input type="hidden" name="tem_variante_ia" id="frm_ia">
  <input type="hidden" name="tipo" id="frm_tipo">
  <input type="hidden" name="periodo_minimo_meses" id="frm_periodo">
  <button type="submit" class="btn btn-brand block" onclick="preparaCriar()">✓ Criar item no catálogo com este preço →</button>
</form>

<details class="card mt-5">
  <summary class="muted" style="cursor:pointer; padding:var(--s-3);">💡 Dicas pra definir custos</summary>
  <div class="desc">
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Pagamento ao funcionário</strong>: o que vocês acordaram em USD por execução</li>
      <li><strong>Software/licenças</strong>: rateio do custo mensal das ferramentas usadas</li>
      <li><strong>Anúncios/orçamento de mídia</strong>: se faz parte do escopo</li>
      <li><strong>Horas próprias</strong>: tempo gerencial</li>
      <li><strong>Margem realista</strong>: 40–80% pra marketing digital</li>
    </ul>
  </div>
</details>

<script>
const COT_BRL = <?= json_encode($cot['BRL']) ?>;
const COT_EUR = <?= json_encode($cot['EUR']) ?>;
const CUSTOS_INICIAIS = <?= json_encode(json_decode($sim['custos_json'], true) ?: []) ?>;

let counter = 0;

function adicionarLinha(desc = '', val = '', divisor = '') {
  counter++;
  const id = 'custo_' + counter;
  const html = `
    <div id="${id}" style="border:1px solid var(--border); border-radius:8px; padding:10px; margin-bottom:8px;">
      <div style="display:flex; gap:6px; align-items:center; margin-bottom:6px;">
        <input type="text" class="custo-desc" placeholder="ex: Pagto funcionário, Canva Pro" value="${desc}" style="flex:1;">
        <button type="button" class="btn btn-ghost small" onclick="pesquisarPreco('${id}')" title="Pesquisar preço no Google" style="padding:6px 12px;">🔍</button>
        <button type="button" class="btn btn-ghost small" onclick="removerLinha('${id}')" title="Remover" style="padding:6px 12px;">🗑</button>
      </div>
      <div style="display:grid; grid-template-columns:1fr auto 1fr auto; gap:6px; align-items:center;">
        <div>
          <div class="hint" style="margin-bottom:2px;">Valor total (USD)</div>
          <input type="number" class="custo-val" placeholder="0.00" step="0.01" min="0" value="${val}" oninput="recalcular()">
        </div>
        <div style="font-size:18px; padding-top:18px; color:var(--txt-2);">÷</div>
        <div>
          <div class="hint" style="margin-bottom:2px;">Dividir por (rateio)</div>
          <input type="number" class="custo-div" placeholder="1" step="1" min="1" value="${divisor}" oninput="recalcular()">
        </div>
        <div style="text-align:right; padding-top:18px;">
          <div class="hint" style="margin-bottom:2px;">= por unidade</div>
          <strong class="custo-rateado" style="font-size:14px;">$ 0.00</strong>
        </div>
      </div>
    </div>
  `;
  document.getElementById('lista_custos').insertAdjacentHTML('beforeend', html);
  recalcular();
}

function removerLinha(id) {
  document.getElementById(id).remove();
  recalcular();
}

function pesquisarPreco(id) {
  const desc = document.getElementById(id).querySelector('.custo-desc').value.trim();
  if (!desc) { alert('Preencha a descrição primeiro.'); return; }
  const q = encodeURIComponent(desc + ' price monthly subscription 2026');
  window.open('https://www.google.com/search?q=' + q, '_blank', 'noopener');
}

function recalcular() {
  let total = 0;
  document.querySelectorAll('#lista_custos > div').forEach(linha => {
    const v = parseFloat(linha.querySelector('.custo-val')?.value) || 0;
    const d = Math.max(1, parseFloat(linha.querySelector('.custo-div')?.value) || 1);
    const r = v / d;
    const rateadoEl = linha.querySelector('.custo-rateado');
    if (rateadoEl) rateadoEl.textContent = '$ ' + r.toFixed(2);
    total += r;
  });
  document.getElementById('total_custo').textContent = total.toFixed(2);

  const margem = parseFloat(document.getElementById('margem').value) || 0;
  const preco = total * (1 + margem / 100);
  const preco_int = Math.ceil(preco);

  document.getElementById('preco_calc').value = '$ ' + preco.toFixed(2);
  document.getElementById('preco_final').textContent = preco_int;
  document.getElementById('lucro_usd').textContent = (preco_int - total).toFixed(2);
  document.getElementById('preco_brl').textContent = Math.ceil(preco_int * COT_BRL);
  document.getElementById('preco_eur').textContent = Math.ceil(preco_int * COT_EUR);
}

function coletarCustos() {
  const arr = [];
  document.querySelectorAll('#lista_custos > div').forEach(linha => {
    const desc = linha.querySelector('.custo-desc')?.value.trim() || '';
    const val = parseFloat(linha.querySelector('.custo-val')?.value) || 0;
    const div = parseFloat(linha.querySelector('.custo-div')?.value) || 1;
    if (desc || val > 0) arr.push({descricao: desc, valor: val, dividir_por: div});
  });
  return arr;
}

function prepararSalvar() {
  document.getElementById('custos_json_input').value = JSON.stringify(coletarCustos());
}

function preparaCriar() {
  const nome = document.getElementById('sim_nome').value.trim();
  const descricao = document.getElementById('sim_descricao').value.trim();
  const preco = document.getElementById('preco_final').textContent;
  const ia = document.getElementById('sim_ia').checked;
  const tipo = document.getElementById('sim_tipo').value;
  const periodo = document.getElementById('sim_periodo').value;
  document.getElementById('frm_nome').value = nome;
  document.getElementById('frm_descricao').value = descricao;
  document.getElementById('frm_preco').value = preco;
  document.getElementById('frm_ia').value = ia ? '1' : '';
  document.getElementById('frm_tipo').value = tipo;
  document.getElementById('frm_periodo').value = tipo === 'mensal' ? periodo : 0;
  if (ia) {
    document.getElementById('frm_preco_ia').value = Math.ceil(parseFloat(preco) * 1.20);
  }
}

async function sugerirComIA() {
  const btn = document.getElementById('btn_ia');
  const msg = document.getElementById('ia_msg');
  const nome = document.getElementById('sim_nome').value.trim();
  const descricao = document.getElementById('sim_descricao').value.trim();
  if (!nome && !descricao) { msg.innerHTML = '<span style="color:var(--c-danger);">Preencha o nome ou a descrição primeiro.</span>'; return; }
  btn.disabled = true;
  btn.innerHTML = '⏳ Analisando...';
  msg.innerHTML = 'Consultando IA (~10s)...';
  const fd = new FormData();
  fd.append('csrf', '<?= e(csrf_token()) ?>');
  fd.append('nome', nome);
  fd.append('descricao', descricao);
  try {
    const resp = await fetch('<?= e(APP_BASE_URL) ?>/simulador_ia.php', { method: 'POST', body: fd });
    const data = await resp.json();
    if (!resp.ok || !data.ok) { msg.innerHTML = '<span style="color:var(--c-danger);">' + (data.error || 'Erro') + '</span>'; return; }
    const s = data.sugestao;
    if (s.nome)                 document.getElementById('sim_nome').value = s.nome;
    if (s.descricao)            document.getElementById('sim_descricao').value = s.descricao;
    if (s.tipo)                 document.getElementById('sim_tipo').value = s.tipo;
    if (s.periodo_minimo_meses != null) document.getElementById('sim_periodo').value = s.periodo_minimo_meses;
    if (s.margem_pct != null)   document.getElementById('margem').value = s.margem_pct;
    toggleSimPeriodo();
    document.getElementById('lista_custos').innerHTML = '';
    counter = 0;
    if (Array.isArray(s.custos)) s.custos.forEach(c => adicionarLinha(c.descricao || '', c.valor || '', c.dividir_por || 1));
    recalcular();
    msg.innerHTML = '<span style="color:var(--c-success);">✓ Sugestão aplicada! Revise a descrição e os custos antes de salvar.</span>';
  } catch (e) {
    msg.innerHTML = '<span style="color:var(--c-danger);">Erro: ' + e.message + '</span>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '✨ Preencher com IA';
  }
}

// Inicializa
toggleSimPeriodo();
if (CUSTOS_INICIAIS.length > 0) {
  CUSTOS_INICIAIS.forEach(c => adicionarLinha(c.descricao || '', c.valor || '', c.dividir_por || 1));
} else {
  adicionarLinha('Pagamento ao funcionário (USD)', '');
  adicionarLinha('Software/licenças (rateio)', '');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
