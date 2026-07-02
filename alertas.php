<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/alertas.php';
require_once __DIR__ . '/lib/email.php';
$me = require_sadmin();
$db = db();
$flash = null;
$saida = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    if ($op === 'rodar_dry_run' || $op === 'rodar_agora') {
        $dry_run = ($op === 'rodar_dry_run');
        ob_start();
        try {
            $hoje = new DateTimeImmutable('today');
            $dow = (int)$hoje->format('N');
            $dia_nome = ['','Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'][$dow];

            echo "[" . date('Y-m-d H:i:s') . "] Alerta de postagens · hoje=" . $hoje->format('Y-m-d') . " ($dia_nome)";
            echo $dry_run ? " [DRY-RUN — sem envio]\n" : "\n";

            $pendentes = alertas_postagens_pendentes($db, $hoje);
            $total = count($pendentes);

            if ($total === 0) {
                echo "\n✓ Nenhum funcionário com POSTAGEM pendente esta semana. Time tá em dia!\n";
            } else {
                echo "\nDetectados $total funcionário(s) com pendência:\n\n";
                $enviados = 0; $falhas = 0;
                foreach ($pendentes as $p) {
                    $n_assin = count($p['assinaturas']);
                    echo " · " . $p['nome'] . " <" . $p['email'] . "> — $n_assin assinatura(s):\n";
                    foreach ($p['assinaturas'] as $a) {
                        echo "     - " . $a['cliente_nome'] . " (" . $a['item_nome'] . ")\n";
                    }
                    if ($dry_run) continue;

                    $assunto = sprintf('🔔 Lembrete: %d assinatura(s) de POSTAGEM sem marcação esta semana', $n_assin);
                    $html = alertas_email_corpo($p, $hoje);
                    $r = email_enviar($p['email'], $assunto, $html);
                    if ($r === true) { $enviados++; echo "   ✓ Email enviado\n"; }
                    else { $falhas++; echo "   ✗ Falha: " . (string)$r . "\n"; }
                }
                if (!$dry_run) {
                    echo "\nTOTAL: $enviados enviado(s), $falhas falha(s)\n";
                    try { audit_log('alerta.postagens_pendentes', 'sistema', $enviados); } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {
            echo "\nERRO: " . $e->getMessage() . "\n";
        }
        $saida = ob_get_clean();
        $flash = ['ok', $dry_run ? t('Dry-run concluído (nenhum email enviado).') : t('Alerta enviado.')];
    }
}

// Detecta se há pendentes agora (sem enviar)
$pendentes_agora = [];
try {
    $pendentes_agora = alertas_postagens_pendentes($db, new DateTimeImmutable('today'));
} catch (Throwable $e) {}

$page = t('Alertas operacionais');
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">🔔 <?= e(t('Alertas automáticos')) ?></h1>
<p class="muted"><?= e(t('Cron envia lembretes pros funcionários sobre tarefas pendentes da semana.')) ?></p>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if ($saida): ?>
  <pre style="background:var(--bg-input); padding:14px; border-radius:8px; font-size:12px; max-height:400px; overflow:auto;"><?= e($saida) ?></pre>
<?php endif; ?>

<div class="card brand">
  <div class="title">⏰ <?= e(t('Postagens da semana')) ?></div>
  <p><?= e(t('Detecta funcionários com assinaturas de POSTAGEM ativas que')) ?> <strong><?= e(t('ainda não marcaram nenhuma entrega nesta semana')) ?></strong> <?= e(t('(segunda → hoje).')) ?></p>
  <p class="muted" style="font-size:13px;"><strong><?= e(t('Agendado:')) ?></strong> <?= e(t('quarta e sexta às 09:00 (crons')) ?> <code>0 9 * * 3</code> <?= e(t('e')) ?> <code>0 9 * * 5</code>).</p>
</div>

<?php if ($pendentes_agora): ?>
  <div class="card attention">
    <div class="title">⚠ <?= count($pendentes_agora) ?> <?= e(t('funcionário(s) com pendência AGORA')) ?></div>
    <ul style="padding-left:20px;">
      <?php foreach ($pendentes_agora as $p): ?>
        <li><strong><?= e($p['nome']) ?></strong> — <?= count($p['assinaturas']) ?> <?= e(t('assinatura(s) sem marcação')) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php else: ?>
  <div class="card success">
    <div class="title">✓ <?= e(t('Nenhuma pendência detectada agora')) ?></div>
    <div class="desc"><?= e(t('Todos os funcionários com POSTAGEM ativa já marcaram pelo menos 1 entrega esta semana.')) ?></div>
  </div>
<?php endif; ?>

<div class="card">
  <form method="post" style="display:flex; gap:8px; flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <button type="submit" name="op" value="rodar_dry_run" class="btn btn-ghost" style="flex:1;">👀 <?= e(t('Dry-run (só listar)')) ?></button>
    <button type="submit" name="op" value="rodar_agora" class="btn" style="flex:1;" onclick="return confirm('<?= e(t('Enviar emails de verdade pros funcionários pendentes?')) ?>');">📧 <?= e(t('Rodar e enviar emails')) ?></button>
  </form>
  <div class="hint"><?= e(t('Dry-run só lista quem seria notificado, sem enviar. "Rodar agora" envia os emails de verdade — útil pra forçar alerta fora dos horários agendados.')) ?></div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
