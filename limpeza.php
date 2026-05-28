<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/limpeza.php';
$me = require_sadmin();
$db = db();
$flash = null;
$saida = null;

// Rodar limpeza sob demanda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'rodar_agora') {
        ob_start();
        try {
            include __DIR__ . '/cron/limpeza_mensal.php';
        } catch (Throwable $e) {
            echo "Erro fatal: " . $e->getMessage();
        }
        $saida = ob_get_clean();
        $flash = ['ok', 'Limpeza concluída.'];
    }
}

// Pega as estatísticas atuais de tamanho do banco
$tabelas_info = [];
try {
    $stmt = $db->query("
        SELECT table_name AS tabela,
               table_rows AS linhas,
               ROUND(data_length / 1024, 1) AS data_kb,
               ROUND(index_length / 1024, 1) AS index_kb
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE'
        ORDER BY (data_length + index_length) DESC
    ");
    $tabelas_info = $stmt->fetchAll();
} catch (Throwable $e) {}

// Calcula totais
$total_kb = 0;
$total_linhas = 0;
foreach ($tabelas_info as $t) {
    $total_kb += (float)$t['data_kb'] + (float)$t['index_kb'];
    $total_linhas += (int)$t['linhas'];
}

// Última execução (do audit_log)
$ultima = null;
try {
    $stmt = $db->prepare("SELECT criado_em, alvo_id AS apagadas FROM audit_log WHERE acao = 'limpeza.mensal' ORDER BY criado_em DESC LIMIT 1");
    $stmt->execute();
    $ultima = $stmt->fetch() ?: null;
} catch (Throwable $e) {}

$page = 'Limpeza do banco';
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">🧹 Limpeza automática do banco</h1>
<p class="muted">Apaga registros antigos pra manter o banco ágil e o disco livre. Roda automaticamente todo dia 1 do mês às 03:00.</p>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if ($saida): ?>
  <pre style="background:var(--bg-input); padding:14px; border-radius:8px; font-size:12px; max-height:400px; overflow:auto;"><?= e($saida) ?></pre>
<?php endif; ?>

<div class="card brand">
  <div class="title">⚙ Configuração do cron</div>
  <p class="muted" style="font-size:13px;">Configure no hPanel (Avançado → Cron Jobs):</p>
  <code style="display:block; padding:10px; background:var(--bg-input); border-radius:6px; font-size:12px; word-break:break-all;">0 3 1 * * php /home/u788472657/domains/cont.diteads.com/public_html/cron/limpeza_mensal.php</code>
  <p class="hint">Dia 1 de cada mês às 03:00 — antes do backup das 04:00.</p>
</div>

<?php if ($ultima): ?>
  <div class="card">
    <div class="title">📅 Última execução</div>
    <p><?= e(date('d/m/Y H:i', strtotime($ultima['criado_em']))) ?> — <strong><?= (int)$ultima['apagadas'] ?> linha(s) apagada(s)</strong></p>
  </div>
<?php endif; ?>

<div class="card">
  <form method="post" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='Limpando…';">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="rodar_agora">
    <button class="btn block" type="submit">🧹 Rodar limpeza agora</button>
    <div class="hint">Pode demorar alguns segundos (DELETE + OPTIMIZE TABLE).</div>
  </form>
</div>

<h2 class="mt-5">📊 Tamanho atual do banco</h2>
<p class="muted">Total: <strong><?= number_format($total_kb / 1024, 2, ',', '.') ?> MB</strong> · <?= number_format($total_linhas, 0, ',', '.') ?> linhas</p>

<div class="card">
  <table style="width:100%; font-size:13px; border-collapse:collapse;">
    <thead>
      <tr style="border-bottom:1px solid var(--border);">
        <th style="text-align:left; padding:8px;">Tabela</th>
        <th style="text-align:right; padding:8px;">Linhas</th>
        <th style="text-align:right; padding:8px;">Dados (KB)</th>
        <th style="text-align:right; padding:8px;">Índice (KB)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tabelas_info as $t):
          $total_t = (float)$t['data_kb'] + (float)$t['index_kb'];
          $destaque = $total_t > 500 ? 'color:var(--c-attention);' : ($total_t > 100 ? 'color:var(--c-primary-2);' : '');
      ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:8px; <?= $destaque ?>"><?= e($t['tabela']) ?></td>
          <td style="padding:8px; text-align:right;"><?= number_format((int)$t['linhas'], 0, ',', '.') ?></td>
          <td style="padding:8px; text-align:right;"><?= number_format((float)$t['data_kb'], 1, ',', '.') ?></td>
          <td style="padding:8px; text-align:right;"><?= number_format((float)$t['index_kb'], 1, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2 class="mt-5">📋 O que é apagado</h2>
<div class="card">
  <ul style="padding-left:20px; color:var(--txt-2); font-size:13px;">
    <li><strong>audit_log</strong> — registros > 18 meses (compliance)</li>
    <li><strong>wise_eventos</strong> — eventos casados/sem-cobrança > 6 meses</li>
    <li><strong>regua_eventos</strong> — disparos de régua > 1 ano</li>
    <li><strong>senha_resets</strong> — tokens já usados > 30 dias</li>
    <li><strong>totp_backup_codes</strong> — códigos já consumidos > 90 dias</li>
    <li><strong>convites</strong> — convites aceitos > 30 dias</li>
  </ul>
  <p class="hint">Depois roda <code>OPTIMIZE TABLE</code> pra recuperar espaço em disco do MySQL (InnoDB não libera automaticamente após DELETE).</p>
  <p class="hint" style="color:var(--c-success);">✓ Dados financeiros (cobranças, pagamentos, distribuição) NUNCA são apagados — só logs e tokens vencidos.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
