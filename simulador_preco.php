<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/cotacao.php';
require_sadmin();
$db = db();

$page = 'Simulador de preço';
$show_back = true;
$back_to = APP_BASE_URL . '/catalogo.php';
require __DIR__ . '/includes/header.php';

$cot = cotacao_atual($db);
?>
<h1 class="page-title">📊 Simulador de preço</h1>
<p class="muted">Liste os custos do serviço (em USD), defina a margem desejada, e o sistema calcula o preço final pra cadastrar no catálogo.</p>

<div class="card">
  <div class="field">
    <label>Nome do serviço (rascunho)</label>
    <input type="text" id="sim_nome" placeholder="ex: META ADS Pro, LANDING PAGE Premium">
  </div>
  <div class="grid-2">
    <div class="field">
      <label>Tipo de cobrança</label>
      <select id="sim_tipo" onchange="toggleSimPeriodo()">
        <option value="unico">Único (one-shot)</option>
        <option value="mensal" selected>Mensal (recorrente)</option>
        <option value="por_unidade">Por unidade</option>
      </select>
    </div>
    <div class="field" id="sim_periodo_box">
      <label>Período mínimo (meses)</label>
      <input type="number" id="sim_periodo" min="0" max="60" value="3" placeholder="0 = sem mínimo">
      <div class="hint">Tempo mínimo que o cliente deve manter o contrato.</div>
    </div>
  </div>
  <label class="check"><input type="checkbox" id="sim_ia"> Tem variante "com IA" (preços alternativos)</label>
</div>

<script>
function toggleSimPeriodo() {
  document.getElementById('sim_periodo_box').style.display = document.getElementById('sim_tipo').value === 'mensal' ? 'block' : 'none';
}
</script>

<h2 class="mt-5">💸 Custos (USD)</h2>
<div class="card">
  <p class="muted" style="font-size:13px;">Cada linha tem <strong>Valor total ÷ Dividir por = por unidade</strong>. Use o divisor pra rateio: ex. "Opus Clips $174 dividido por 36 vídeos" → $4.83 por vídeo. Pra custo direto, deixe divisor em 1.</p>
  <p class="hint">💡 Use o botão <strong>🔍</strong> em cada linha pra abrir o Google e pesquisar o preço atual da ferramenta no mercado.</p>

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
    <p class="hint" style="margin-top:8px;">Valores aproximados de referência. Clique no <strong>🔍</strong> em cada linha pra confirmar o preço atual no Google.</p>
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
      <input type="number" id="margem" value="50" min="0" step="1" oninput="recalcular()">
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
    <span><span id="lucro_usd">0.00</span></span>
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

<form method="get" action="<?= e(APP_BASE_URL) ?>/catalogo.php" class="mt-5">
  <input type="hidden" name="acao" value="novo">
  <input type="hidden" name="nome" id="frm_nome">
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
      <li><strong>Pagamento ao funcionário</strong>: o que vocês acordaram em USD por execução desse item</li>
      <li><strong>Software/licenças</strong>: rateio do custo mensal das ferramentas usadas (ex: Canva Pro $13/mês ÷ 10 clientes = $1.30/cliente)</li>
      <li><strong>Anúncios/orçamento de mídia</strong>: se faz parte do escopo</li>
      <li><strong>Horas próprias</strong>: tempo que você (admin) gasta gerenciando, multiplicado pelo seu valor/hora</li>
      <li><strong>Margem realista</strong>: 40–80% pra serviços de marketing digital costuma ser saudável</li>
    </ul>
  </div>
</details>

<script>
const COT_BRL = <?= json_encode($cot['BRL']) ?>;
const COT_EUR = <?= json_encode($cot['EUR']) ?>;

let counter = 0;

function adicionarLinha(desc = '', val = '', divisor = '') {
  counter++;
  const id = 'custo_' + counter;
  const html = `
    <div id="${id}" style="border:1px solid var(--border); border-radius:8px; padding:10px; margin-bottom:8px;">
      <div style="display:flex; gap:6px; align-items:center; margin-bottom:6px;">
        <input type="text" class="custo-desc" placeholder="ex: Pagto funcionário, Canva Pro, Opus Clips" value="${desc}" style="flex:1;">
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
  if (!desc) {
    alert('Preencha a descrição primeiro (nome do software, ferramenta, etc.) que eu pesquiso o preço pra você.');
    return;
  }
  // Abre Google buscando o preço da ferramenta
  const q = encodeURIComponent(desc + ' price monthly subscription 2026');
  window.open('https://www.google.com/search?q=' + q, '_blank', 'noopener');
}

function recalcular() {
  let total = 0;
  document.querySelectorAll('#lista_custos > div').forEach(linha => {
    const valEl = linha.querySelector('.custo-val');
    const divEl = linha.querySelector('.custo-div');
    const rateadoEl = linha.querySelector('.custo-rateado');
    const v = parseFloat(valEl?.value) || 0;
    const d = Math.max(1, parseFloat(divEl?.value) || 1);
    const r = v / d;
    if (rateadoEl) rateadoEl.textContent = '$ ' + r.toFixed(2);
    total += r;
  });
  document.getElementById('total_custo').textContent = total.toFixed(2);

  const margem = parseFloat(document.getElementById('margem').value) || 0;
  const preco = total * (1 + margem / 100);
  const preco_int = Math.ceil(preco); // arredonda pra cima

  document.getElementById('preco_calc').value = '$ ' + preco.toFixed(2);
  document.getElementById('preco_final').textContent = preco_int;
  document.getElementById('lucro_usd').textContent = '$ ' + (preco_int - total).toFixed(2);
  document.getElementById('preco_brl').textContent = Math.ceil(preco_int * COT_BRL);
  document.getElementById('preco_eur').textContent = Math.ceil(preco_int * COT_EUR);
}

function preparaCriar() {
  const nome = document.getElementById('sim_nome').value.trim();
  const preco = document.getElementById('preco_final').textContent;
  const ia = document.getElementById('sim_ia').checked;
  const tipo = document.getElementById('sim_tipo').value;
  const periodo = document.getElementById('sim_periodo').value;
  document.getElementById('frm_nome').value = nome;
  document.getElementById('frm_preco').value = preco;
  document.getElementById('frm_ia').value = ia ? '1' : '';
  document.getElementById('frm_tipo').value = tipo;
  document.getElementById('frm_periodo').value = tipo === 'mensal' ? periodo : 0;
  // Pré-popula preço da IA com 20% a mais
  if (ia) {
    document.getElementById('frm_preco_ia').value = Math.ceil(parseFloat(preco) * 1.20);
  }
}

// Adiciona 2 linhas iniciais
adicionarLinha('Pagamento ao funcionário (USD)', '');
adicionarLinha('Software/licenças (rateio)', '');
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
