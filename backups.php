<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
$me = require_sadmin();
$db = db();
$flash = null;

$dir = __DIR__ . '/uploads/.backups';

// Download (servido via PHP pra contornar o .htaccess que bloqueia direto)
if (isset($_GET['baixar'])) {
    $nome = basename((string)$_GET['baixar']);
    if (!preg_match('/^db_\d{4}-\d{2}-\d{2}\.sql\.gz$/', $nome)) {
        http_response_code(400); exit('Nome inválido.');
    }
    $f = $dir . '/' . $nome;
    if (!is_file($f)) { http_response_code(404); exit('Backup não encontrado.'); }
    audit_log('backup.baixado', 'sistema', 0);
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

// Roda backup sob demanda — inclui o script diretamente (não depende de exec)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'rodar_agora') {
        $script = __DIR__ . '/cron/backup_db.php';
        if (is_file($script)) {
            // O script detecta PHP_SAPI e ajusta o output. Aqui captura tudo.
            ob_start();
            try {
                include $script;
            } catch (Throwable $e) {
                echo "Erro fatal: " . $e->getMessage();
            }
            $out = ob_get_clean();
            audit_log('backup.rodado_manual', 'sistema', 0);
            $sucesso = strpos($out, 'OK:') !== false;
            $flash = [$sucesso ? 'ok' : 'err', "Saída do script:\n" . $out];
        } else {
            $flash = ['err', 'Script cron/backup_db.php não encontrado.'];
        }
    }
}

// Lista backups existentes
$backups = [];
if (is_dir($dir)) {
    foreach (glob($dir . '/db_*.sql.gz') ?: [] as $f) {
        $backups[] = [
            'nome' => basename($f),
            'data' => date('d/m/Y H:i', filemtime($f)),
            'tamanho' => filesize($f),
        ];
    }
    usort($backups, fn($a, $b) => strcmp($b['nome'], $a['nome']));
}

$page = 'Backups do banco';
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">💾 Backups do banco</h1>
<p class="muted">Dumps comprimidos do MySQL. Rotação automática: últimos 14 dias. Gerados pelo cron diário (04:00) ou sob demanda abaixo.</p>

<?php if ($flash): ?>
  <div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
<?php endif; ?>

<div class="card brand">
  <div class="title">⚙ Configuração do cron</div>
  <p class="muted" style="font-size:13px;">Pra rodar automaticamente todo dia às 04:00, configure no painel Hostinger (Avançado → Cron Jobs):</p>
  <code style="display:block; padding:10px; background:var(--bg-input); border-radius:6px; font-size:12px; word-break:break-all;">0 4 * * * php /home/u788472657/domains/cont.diteads.com/public_html/cron/backup_db.php</code>
</div>

<div class="card">
  <form method="post" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='Rodando…';">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="rodar_agora">
    <button class="btn block" type="submit">🔄 Gerar backup agora</button>
    <div class="hint">Cria um dump do banco e adiciona à lista abaixo. Pode demorar uns segundos.</div>
  </form>
</div>

<h2 class="mt-5">Backups disponíveis (<?= count($backups) ?>)</h2>

<?php if (!$backups): ?>
  <div class="card">
    <div class="title muted">Nenhum backup ainda</div>
    <div class="desc">Configure o cron acima ou clique em "Gerar backup agora" pra criar o primeiro.</div>
  </div>
<?php else: ?>
  <?php foreach ($backups as $b): ?>
    <div class="card">
      <div class="spaced">
        <div>
          <div class="title"><?= e($b['nome']) ?></div>
          <div class="muted" style="font-size:13px;"><?= e($b['data']) ?> · <?= number_format($b['tamanho'] / 1024 / 1024, 2) ?> MB</div>
        </div>
        <a class="btn small btn-brand" href="?baixar=<?= e(urlencode($b['nome'])) ?>">📥 Baixar</a>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="card mt-5">
  <div class="title">🛟 Como restaurar um backup</div>
  <ol style="padding-left:20px; color:var(--txt-2); font-size:13px;">
    <li>Baixa o arquivo <code>.sql.gz</code></li>
    <li>Descompacta localmente: <code>gunzip db_2026-05-28.sql.gz</code></li>
    <li>Acessa phpMyAdmin (Hostinger → Bancos de Dados)</li>
    <li>Seleciona o banco <code><?= e(DB_NAME) ?></code></li>
    <li>Aba "Importar" → escolhe o arquivo <code>.sql</code> descompactado</li>
    <li>Clica Executar</li>
  </ol>
  <div class="hint" style="color:var(--c-danger);">⚠ Restaurar SOBRESCREVE os dados atuais. Faça um backup ANTES do backup, se possível.</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
