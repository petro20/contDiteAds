<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/pagamentos.php';
$me = require_sadmin();
$db = db();
$flash = null;

// --- Confirmar registro de pagamentos casados ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'confirmar_match') {
    csrf_check();
    $items = $_POST['match'] ?? [];
    $n = 0;
    foreach ($items as $key => $cobranca_id) {
        $cid = (int)$cobranca_id;
        if (!$cid) continue;
        $valor = (float)str_replace(',', '.', (string)($_POST['valor'][$key] ?? '0'));
        $data  = (string)($_POST['data'][$key] ?? date('Y-m-d'));
        $ref   = trim((string)($_POST['ref'][$key] ?? ''));
        $obs   = 'Wise CSV · ref ' . $ref;
        try {
            registrar_pagamento_cliente($db, $cid, $valor, $data, 'Wise', $obs, null, (int)$me['id'], false);
            $n++;
        } catch (Throwable $e) {
            error_log('Wise CSV registro erro: ' . $e->getMessage());
        }
    }
    audit_log('wise.csv_import', 'cobrancas', $n);
    $flash = ['ok', $n . ' pagamento(s) registrado(s) com sucesso!'];
}

// --- Processar upload de CSV ---
$transacoes = [];
$erro_csv = null;
$debug_csv = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'upload_csv') {
    csrf_check();
    if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $erro_csv = 'Selecione um arquivo CSV.';
    } else {
        $h = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$h) {
            $erro_csv = 'Não consegui abrir o arquivo.';
        } else {
            $primeira = fgets($h);
            // Remove BOM se houver
            $primeira = preg_replace('/^\xEF\xBB\xBF/', '', $primeira);
            $sep = (substr_count($primeira, ';') > substr_count($primeira, ',')) ? ';' : ',';
            rewind($h);
            // Lê e remove BOM da primeira célula
            $header = fgetcsv($h, 0, $sep);
            if ($header && isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

            // Mapeia colunas (mais agressivo, várias variações)
            $col = [];
            foreach ($header as $i => $h_nome) {
                $hn = strtolower(trim((string)$h_nome));
                // Valor: amount, target amount, source amount, valor
                if (preg_match('/^(target |source )?(amount|valor)/', $hn) && !isset($col['valor']))  $col['valor'] = $i;
                // Moeda
                elseif (preg_match('/^(target |source )?(currency|moeda)/', $hn) && !isset($col['moeda']))  $col['moeda'] = $i;
                // Data: date, created on, completed on, data
                elseif (preg_match('/(^date|created|completed|finished|data|paid)/', $hn) && !isset($col['data'])) $col['data'] = $i;
                elseif (preg_match('/(direction|tipo|type)/', $hn) && !isset($col['direction'])) $col['direction'] = $i;
                elseif (preg_match('/(reference|referência|referencia|payment ref)/', $hn) && !isset($col['ref'])) $col['ref'] = $i;
                elseif (preg_match('/(source name|payer|remetente|sender|from name|de\b)/', $hn) && !isset($col['payer'])) $col['payer'] = $i;
                elseif (preg_match('/(description|descrição|descricao|details)/', $hn) && !isset($col['desc'])) $col['desc'] = $i;
                elseif (preg_match('/^id$|transferwise id|transaction id/', $hn) && !isset($col['id'])) $col['id'] = $i;
            }

            $total_linhas = 0;
            $rejeitadas_dir = 0;
            $rejeitadas_valor = 0;
            $primeiras_linhas = [];
            while (($row = fgetcsv($h, 0, $sep)) !== false) {
                $total_linhas++;
                if ($total_linhas <= 3) $primeiras_linhas[] = $row;

                if (!isset($col['valor'])) continue;
                $valor_raw = $row[$col['valor']] ?? '';
                // Normaliza número: "1,234.56" → "1234.56" ou "1.234,56" → "1234.56"
                $v_clean = (string)$valor_raw;
                $tem_virgula = strpos($v_clean, ',');
                $tem_ponto = strpos($v_clean, '.');
                if ($tem_virgula !== false && $tem_ponto !== false) {
                    // Decide qual é decimal pela posição (último é decimal)
                    if ($tem_virgula > $tem_ponto) { $v_clean = str_replace('.', '', $v_clean); $v_clean = str_replace(',', '.', $v_clean); }
                    else                            { $v_clean = str_replace(',', '', $v_clean); }
                } elseif ($tem_virgula !== false) {
                    $v_clean = str_replace(',', '.', $v_clean);
                }
                $valor_signed = (float)$v_clean;
                $valor = abs($valor_signed);

                // Direção: usa coluna se houver
                $is_credito = true;
                if (isset($col['direction'])) {
                    $dir = strtoupper(trim((string)($row[$col['direction']] ?? '')));
                    if (in_array($dir, ['OUT','OUTGOING','SAIDA','DEBIT','-'], true)) $is_credito = false;
                    elseif (in_array($dir, ['IN','INCOMING','ENTRADA','CREDIT','+'], true)) $is_credito = true;
                } else {
                    // Pega sinal do valor: positivo = crédito
                    $is_credito = $valor_signed > 0 || strpos(trim((string)$valor_raw), '-') !== 0;
                    if (strpos(trim((string)$valor_raw), '-') === 0) $is_credito = false;
                }
                if (!$is_credito) { $rejeitadas_dir++; continue; }
                if ($valor <= 0) { $rejeitadas_valor++; continue; }

                $transacoes[] = [
                    'data'   => substr((string)($row[$col['data']] ?? ''), 0, 10),
                    'valor'  => $valor,
                    'moeda'  => strtoupper(trim((string)($row[$col['moeda']] ?? ''))) ?: 'USD',
                    'ref'    => trim((string)($row[$col['ref']]   ?? '')) ?: trim((string)($row[$col['id']]  ?? '')),
                    'payer'  => trim((string)($row[$col['payer']] ?? '')),
                    'desc'   => trim((string)($row[$col['desc']]  ?? '')),
                ];
            }
            fclose($h);

            // Sempre passa info de debug
            $debug_csv = [
                'separador' => $sep,
                'header'    => $header,
                'colunas_mapeadas' => $col,
                'total_linhas' => $total_linhas,
                'rejeitadas_direcao' => $rejeitadas_dir,
                'rejeitadas_valor'   => $rejeitadas_valor,
                'aceitas' => count($transacoes),
                'primeiras_linhas' => $primeiras_linhas,
            ];

            if (!$transacoes && !$erro_csv) $erro_csv = 'Nenhum crédito encontrado. Veja o debug abaixo pra entender o que aconteceu com seu CSV.';
        }
    }
}

// Busca cobranças abertas pra casar
$cobrancas_abertas = [];
if ($transacoes) {
    $moedas_csv = array_unique(array_column($transacoes, 'moeda'));
    if ($moedas_csv) {
        $in = str_repeat('?,', count($moedas_csv) - 1) . '?';
        $stmt = $db->prepare("
            SELECT c.id, c.cliente_id, c.valor_total, c.moeda, c.status, c.vencimento, cl.nome_empresa
            FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
            WHERE c.status IN ('aberta','em_analise') AND c.moeda IN ($in)
            ORDER BY c.vencimento
        ");
        $stmt->execute($moedas_csv);
        $cobrancas_abertas = $stmt->fetchAll();
        foreach ($cobrancas_abertas as &$c) {
            $stmt2 = $db->prepare('SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos_cliente WHERE cobranca_id = ?');
            $stmt2->execute([(int)$c['id']]);
            $c['pago'] = (float)$stmt2->fetchColumn();
            $c['saldo'] = (float)$c['valor_total'] - $c['pago'];
        }
        unset($c);
    }
}

function casar_csv(array $tx, array $cobrancas): ?array {
    $val = (float)$tx['valor'];
    foreach ($cobrancas as $cob) {
        if ($cob['moeda'] === $tx['moeda'] && abs($cob['saldo'] - $val) < 0.01 && $cob['saldo'] > 0) {
            return $cob;
        }
    }
    return null;
}

$matched = []; $unmatched = [];
foreach ($transacoes as $tx) {
    $cob = casar_csv($tx, $cobrancas_abertas);
    if ($cob) $matched[]   = ['tx' => $tx, 'cob' => $cob];
    else      $unmatched[] = $tx;
}

$page = 'Wise CSV — Sincronizar pagamentos';
$show_back = true;
$back_to = APP_BASE_URL . '/config_pagamento.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">🌍 Wise — Importar CSV de pagamentos</h1>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php if ($erro_csv): ?><div class="flash err"><?= e($erro_csv) ?></div><?php endif; ?>

<?php if ($debug_csv): ?>
  <details class="card" style="border-color:var(--c-orange);">
    <summary style="cursor:pointer; padding:8px 0; color:var(--c-orange);"><strong>🔍 Debug do CSV</strong> (colunas detectadas, linhas processadas)</summary>
    <div style="font-size:12px; margin-top:8px;">
      <p><strong>Separador detectado:</strong> <code>"<?= e($debug_csv['separador']) ?>"</code></p>
      <p><strong>Total de linhas lidas:</strong> <?= (int)$debug_csv['total_linhas'] ?> ·
         <strong>Aceitas:</strong> <?= (int)$debug_csv['aceitas'] ?> ·
         <strong>Rejeitadas (saída):</strong> <?= (int)$debug_csv['rejeitadas_direcao'] ?> ·
         <strong>Rejeitadas (valor zero):</strong> <?= (int)$debug_csv['rejeitadas_valor'] ?></p>
      <p><strong>Cabeçalhos do CSV:</strong></p>
      <code style="display:block; background:var(--bg-input); padding:8px; border-radius:4px; word-break:break-all; font-size:11px;">
        <?= e(implode(' | ', $debug_csv['header'])) ?>
      </code>
      <p style="margin-top:8px;"><strong>Colunas mapeadas:</strong></p>
      <pre style="background:var(--bg-input); padding:8px; border-radius:4px; font-size:11px; overflow-x:auto;"><?= e(print_r($debug_csv['colunas_mapeadas'], true)) ?></pre>
      <?php if ($debug_csv['primeiras_linhas']): ?>
        <p style="margin-top:8px;"><strong>Primeiras linhas do CSV (pra você verificar):</strong></p>
        <?php foreach ($debug_csv['primeiras_linhas'] as $idx => $linha): ?>
          <code style="display:block; background:var(--bg-input); padding:8px; border-radius:4px; word-break:break-all; font-size:11px; margin-bottom:4px;">
            #<?= $idx + 1 ?>: <?= e(implode(' | ', array_slice($linha, 0, 12))) ?>
          </code>
        <?php endforeach; ?>
      <?php endif; ?>
      <p class="hint" style="margin-top:8px;">Se as colunas não foram identificadas corretamente, me envie esse debug pra eu ajustar o parser.</p>
    </div>
  </details>
<?php endif; ?>

<details class="card">
  <summary class="muted" style="cursor:pointer; padding:8px 0;"><strong>📋 Como exportar o CSV do Wise</strong></summary>
  <ol style="padding-left:20px; margin-top:8px; color:var(--txt-2); font-size:13px;">
    <li>Entre em <a href="https://wise.com/all-transactions" target="_blank" rel="noopener" style="color:var(--c-primary-2);">wise.com/all-transactions</a></li>
    <li>Aplique filtros (período, conta da Dite Ads Teams)</li>
    <li>Clica em <strong>"Baixar"</strong> ou <strong>"Exportar"</strong> → escolhe <strong>CSV</strong></li>
    <li>Salva o arquivo no computador/celular</li>
    <li>Volta aqui e faz upload abaixo</li>
  </ol>
  <p class="hint">O sistema lê automaticamente as colunas. Aceita CSV em PT-BR ou inglês, separado por vírgula ou ponto-e-vírgula.</p>
</details>

<form method="post" enctype="multipart/form-data" class="mt-3">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="op" value="upload_csv">
  <div class="card">
    <div class="field">
      <label>Arquivo CSV do extrato</label>
      <input type="file" name="csv" accept=".csv,text/csv" required>
      <div class="hint">Aceita o CSV de "Todas as transações" exportado da Wise.</div>
    </div>
    <button class="btn btn-brand block" type="submit">📤 Enviar e analisar CSV</button>
  </div>
</form>

<?php if ($matched): ?>
  <h2 class="mt-5">✅ Pagamentos casados (<?= count($matched) ?>)</h2>
  <p class="muted" style="font-size:13px;">Esses créditos batem exatamente com cobranças abertas (mesma moeda e valor exato). Revise e confirme.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="confirmar_match">
    <?php foreach ($matched as $idx => $m):
      $tx  = $m['tx'];
      $cob = $m['cob'];
    ?>
      <div class="card success" style="margin-bottom:var(--s-3);">
        <label class="check" style="font-weight:600;">
          <input type="checkbox" name="match[<?= (int)$idx ?>]" value="<?= (int)$cob['id'] ?>" checked>
          Registrar este pagamento
        </label>
        <div class="info-pair muted" style="font-size:12px; margin-top:8px;">
          <span class="l">🌍 Wise: <?= e($tx['payer'] ?: '—') ?></span>
          <span class="v"><?= e($tx['moeda']) ?> <?= number_format($tx['valor'], 2, ',', '.') ?> · <?= e($tx['data']) ?></span>
        </div>
        <div class="info-pair" style="font-size:13px;">
          <span class="l">💳 Cobrança</span>
          <span class="v"><strong><?= e($cob['nome_empresa']) ?></strong> · saldo <?= e($cob['moeda']) ?> <?= number_format($cob['saldo'], 2, ',', '.') ?></span>
        </div>
        <input type="hidden" name="valor[<?= (int)$idx ?>]" value="<?= e(number_format($tx['valor'], 2, '.', '')) ?>">
        <input type="hidden" name="data[<?= (int)$idx ?>]" value="<?= e($tx['data']) ?>">
        <input type="hidden" name="ref[<?= (int)$idx ?>]" value="<?= e($tx['ref']) ?>">
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-success block mt-3">✓ Confirmar e registrar (<?= count($matched) ?>) pagamento(s)</button>
  </form>
<?php endif; ?>

<?php if ($unmatched): ?>
  <h2 class="mt-5">❓ Créditos sem casamento (<?= count($unmatched) ?>)</h2>
  <p class="muted" style="font-size:13px;">Esses créditos não batem com nenhuma cobrança em aberto (moeda diferente, valor diferente, ou cobrança não existe).</p>
  <?php foreach ($unmatched as $tx): ?>
    <div class="card attention">
      <div class="info-pair">
        <span class="l">🌍 <?= e($tx['payer'] ?: ($tx['desc'] ?: '—')) ?></span>
        <span class="v"><strong><?= e($tx['moeda']) ?> <?= number_format($tx['valor'], 2, ',', '.') ?></strong></span>
      </div>
      <div class="info-pair muted" style="font-size:12px;">
        <span class="l"><?= e($tx['data']) ?></span>
        <span class="v">ref: <?= e($tx['ref'] ?: '—') ?></span>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
