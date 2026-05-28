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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'upload_csv') {
    csrf_check();
    if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $erro_csv = 'Selecione um arquivo CSV.';
    } else {
        $h = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$h) {
            $erro_csv = 'Não consegui abrir o arquivo.';
        } else {
            // Detecta separador (Wise usa ; em alguns países, , em outros)
            $primeira = fgets($h);
            $sep = (substr_count($primeira, ';') > substr_count($primeira, ',')) ? ';' : ',';
            rewind($h);
            $header = fgetcsv($h, 0, $sep);
            // Mapeia colunas comuns do CSV Wise (em PT e EN)
            $col = [];
            foreach ($header as $i => $h_nome) {
                $hn = strtolower(trim((string)$h_nome));
                if (preg_match('/(date|data)/', $hn))         $col['data'] = $i;
                elseif (preg_match('/(amount|valor)/', $hn) && !isset($col['valor']))  $col['valor'] = $i;
                elseif (preg_match('/(currency|moeda)/', $hn) && !isset($col['moeda']))  $col['moeda'] = $i;
                elseif (preg_match('/(direction|tipo|type)/', $hn))    $col['direction'] = $i;
                elseif (preg_match('/(reference|referência|referencia)/', $hn)) $col['ref'] = $i;
                elseif (preg_match('/(source name|payer|remetente|sender|from name)/', $hn)) $col['payer'] = $i;
                elseif (preg_match('/(description|descricao|descrição)/', $hn) && !isset($col['desc'])) $col['desc'] = $i;
            }
            while (($row = fgetcsv($h, 0, $sep)) !== false) {
                $valor_raw = $row[$col['valor']] ?? '0';
                $valor = (float)str_replace([',', '.'], ['.', ''], $valor_raw); // best-effort número
                // Aceita +1234.56 (Wise às vezes prefixa)
                $valor = abs($valor);
                // Direção: se tem coluna 'direction' e for OUT/saída, ignora
                if (isset($col['direction'])) {
                    $dir = strtoupper(trim((string)($row[$col['direction']] ?? '')));
                    if (in_array($dir, ['OUT','OUTGOING','SAIDA','DEBIT'], true)) continue;
                } else {
                    // Sem coluna de direção: pega só valores positivos (entradas)
                    $v_raw = trim((string)($row[$col['valor']] ?? ''));
                    if (strpos($v_raw, '-') === 0) continue;
                }
                if ($valor <= 0) continue;
                $transacoes[] = [
                    'data'   => substr((string)($row[$col['data']] ?? ''), 0, 10),
                    'valor'  => $valor,
                    'moeda'  => strtoupper(trim((string)($row[$col['moeda']] ?? ''))) ?: 'USD',
                    'ref'    => trim((string)($row[$col['ref']]   ?? '')),
                    'payer'  => trim((string)($row[$col['payer']] ?? '')),
                    'desc'   => trim((string)($row[$col['desc']]  ?? '')),
                ];
            }
            fclose($h);
            if (!$transacoes) $erro_csv = 'Nenhum crédito encontrado no CSV. Verifique se exportou o extrato correto.';
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
