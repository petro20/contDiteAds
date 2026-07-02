<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
require_admin();
$db = db();
$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');

// Para cada funcionário ativo, lista capacidade declarada e ocupação calculada
$funcs = $db->query("SELECT id, nome, aceitando_clientes, wisetag FROM usuarios WHERE role='funcionario' AND ativo=1 ORDER BY nome")->fetchAll();

// Carrega capacidades e ocupação
foreach ($funcs as &$f) {
    $stmt = $db->prepare('SELECT categoria, capacidade_mensal FROM capacidade_funcionario WHERE funcionario_id = ?');
    $stmt->execute([(int)$f['id']]);
    $cap = [];
    foreach ($stmt->fetchAll() as $r) $cap[$r['categoria']] = (int)$r['capacidade_mensal'];

    // Ocupação: assinaturas ativas + entregas do mês
    // criativos = entregas em assinaturas tipo por_unidade do mês
    $stmt = $db->prepare("SELECT COUNT(*) FROM entregas en JOIN assinaturas a ON a.id = en.assinatura_id JOIN itens_catalogo i ON i.id = a.item_id WHERE a.funcionario_id = ? AND en.competencia_mes = ? AND i.tipo = 'por_unidade'");
    $stmt->execute([(int)$f['id'], $competencia]);
    $ocp_criativos = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM assinaturas a JOIN itens_catalogo i ON i.id = a.item_id WHERE a.funcionario_id = ? AND a.status='ativa' AND i.e_pacote = 1 AND i.nome LIKE 'POSTAGEM%'");
    $stmt->execute([(int)$f['id']]);
    $ocp_postagens = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM assinaturas a JOIN itens_catalogo i ON i.id = a.item_id WHERE a.funcionario_id = ? AND i.tipo = 'unico' AND a.iniciada_em LIKE ?");
    $stmt->execute([(int)$f['id'], $competencia . '%']);
    $ocp_sites = (int)$stmt->fetchColumn();

    $f['cap'] = $cap;
    $f['ocp'] = ['criativos' => $ocp_criativos, 'postagens' => $ocp_postagens, 'sites_projetos' => $ocp_sites];
}
unset($f);

$page = t('Capacidade da equipe');
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= e(t('Equipe')) ?></h1>
<?php render_group_tabs('equipe', 'capacidade'); ?>
<h2><?= e(t('Capacidade da equipe')) ?></h2>
<p class="muted"><?= e(t('Mês de referência:')) ?> <strong><?= e($competencia) ?></strong></p>

<?php if (!$funcs): ?>
  <p class="muted"><?= e(t('Nenhum funcionário ativo.')) ?></p>
<?php endif; ?>

<?php foreach ($funcs as $f): ?>
  <div class="card">
    <div class="spaced mb-3">
      <div>
        <div class="title"><?= e($f['nome']) ?> <?= $f['aceitando_clientes'] ? '🟢' : '🔴' ?></div>
        <div class="sub muted"><?= $f['aceitando_clientes'] ? e(t('Aceitando novos clientes')) : e(t('Lotado (não aceitando)')) ?></div>
      </div>
    </div>
    <?php foreach ([['criativos',t('Criativos')],['postagens',t('Pacotes POSTAGEM')],['sites_projetos',t('Sites/projetos')]] as [$cat,$lbl]):
        $declarado = $f['cap'][$cat] ?? null;
        $ocupado = $f['ocp'][$cat];
    ?>
      <div class="spaced" style="padding:6px 0; border-bottom:1px solid var(--border);">
        <div><?= e($lbl) ?></div>
        <div style="text-align:right;">
          <?php if ($declarado): $pct = round($ocupado / $declarado * 100); ?>
            <strong><?= $ocupado ?>/<?= $declarado ?></strong>
            <span class="muted" style="font-size:12px;">(<?= $pct ?>%)</span>
            <?php if ($pct >= 90): ?><span class="status status-vencida"><?= e(t('cheio')) ?></span><?php elseif ($pct >= 70): ?><span class="status status-warning"><?= e(t('próximo do limite')) ?></span><?php else: ?><span class="status status-paga"><?= e(t('tem espaço')) ?></span><?php endif; ?>
          <?php else: ?>
            <strong><?= $ocupado ?></strong>
            <span class="muted" style="font-size:12px;"><?= e(t('/ não declarado')) ?></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="mt-3"><a class="btn small btn-ghost" href="<?= e(APP_BASE_URL) ?>/funcionarios.php?acao=editar&id=<?= (int)$f['id'] ?>"><?= e(t('Editar funcionário →')) ?></a></div>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
