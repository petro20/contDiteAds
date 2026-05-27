<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$db = db();

$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');
$ini_m = $competencia . '-01';
$fim_m = date('Y-m-t', strtotime($ini_m));

// Lista todos os funcionários ativos (incluindo admins/sadmins que executam)
$funcs = $db->query("
    SELECT u.id, u.nome, u.role, u.aceitando_clientes, u.wisetag,
           u.trabalha_com_id, p.nome AS dupla_nome
    FROM usuarios u
    LEFT JOIN usuarios p ON p.id = u.trabalha_com_id
    WHERE u.ativo = 1 AND u.role IN ('funcionario','admin','sadmin')
    ORDER BY FIELD(u.role, 'funcionario','admin','sadmin'), u.nome
")->fetchAll();

// Pra cada um, conta assinaturas ativas que ele é responsável + entregas do mês + previsão
$dados = [];
foreach ($funcs as $f) {
    $fid = (int)$f['id'];
    // Assinaturas ativas onde é responsável
    $stmt = $db->prepare("SELECT COUNT(*) FROM assinaturas WHERE funcionario_id = ? AND status='ativa'");
    $stmt->execute([$fid]);
    $n_assin = (int)$stmt->fetchColumn();

    // Entregas executadas no mês
    $stmt = $db->prepare("SELECT COUNT(*) FROM entregas WHERE funcionario_id = ? AND competencia_mes = ?");
    $stmt->execute([$fid, $competencia]);
    $n_entregas = (int)$stmt->fetchColumn();

    // Clientes únicos no mês
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.cliente_id) FROM entregas e
        JOIN assinaturas a ON a.id = e.assinatura_id
        WHERE e.funcionario_id = ? AND e.competencia_mes = ?
    ");
    $stmt->execute([$fid, $competencia]);
    $n_clientes = (int)$stmt->fetchColumn();

    // Última entrega
    $stmt = $db->prepare("SELECT MAX(criado_em) FROM entregas WHERE funcionario_id = ? AND competencia_mes = ?");
    $stmt->execute([$fid, $competencia]);
    $ultima = $stmt->fetchColumn();

    // Capacidade total declarada
    $stmt = $db->prepare("SELECT COALESCE(SUM(capacidade_mensal),0) FROM capacidade_funcionario WHERE funcionario_id = ?");
    $stmt->execute([$fid]);
    $capacidade = (int)$stmt->fetchColumn();

    $dados[] = array_merge($f, [
        'n_assin' => $n_assin,
        'n_entregas' => $n_entregas,
        'n_clientes' => $n_clientes,
        'ultima' => $ultima,
        'capacidade' => $capacidade,
    ]);
}

// Filtra quem não tem nada no mês (opcional)
$mostrar_inativos = !empty($_GET['inativos']);
if (!$mostrar_inativos) {
    $dados = array_filter($dados, fn($d) => $d['n_assin'] > 0 || $d['n_entregas'] > 0);
}

// Mês ant/prox
$dt = DateTime::createFromFormat('Y-m', $competencia);
$mes_ant = (clone $dt)->modify('-1 month')->format('Y-m');
$mes_prox = (clone $dt)->modify('+1 month')->format('Y-m');
$nome_mes = ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)$dt->format('n')] . ' de ' . $dt->format('Y');

$page = 'Acompanhamento geral';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">📋 Acompanhamento geral</h1>
<p class="muted">Visão consolidada do que cada funcionário e admin está executando. Somente acompanhamento — pra ações use a Agenda individual de cada um.</p>

<div class="spaced mb-3">
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_ant) ?>">← <?= e($mes_ant) ?></a>
  <strong><?= e($nome_mes) ?></strong>
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_prox) ?>"><?= e($mes_prox) ?> →</a>
</div>

<div class="btn-pair mb-3">
  <a class="btn small <?= !$mostrar_inativos?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?>">Só com atividade</a>
  <a class="btn small <?= $mostrar_inativos?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?>&inativos=1">Todos os funcionários</a>
</div>

<?php if (!$dados): ?>
  <div class="card">
    <div class="title muted">Sem atividade neste mês</div>
    <div class="desc">Ninguém tem assinaturas ativas ou entregas em <?= e($nome_mes) ?>.</div>
  </div>
<?php else: ?>
  <?php
    // KPIs gerais
    $tot_assin = array_sum(array_column($dados, 'n_assin'));
    $tot_entregas = array_sum(array_column($dados, 'n_entregas'));
    $tot_pessoas = count(array_filter($dados, fn($d) => $d['n_assin'] > 0 || $d['n_entregas'] > 0));
  ?>
  <div class="grid-2">
    <div class="kpi"><div class="v"><?= $tot_pessoas ?></div><div class="l">Pessoas executando</div></div>
    <div class="kpi"><div class="v"><?= $tot_assin ?></div><div class="l">Assinaturas ativas</div></div>
    <div class="kpi"><div class="v"><?= $tot_entregas ?></div><div class="l">Entregas no mês</div></div>
    <div class="kpi"><div class="v"><?= count($dados) ?></div><div class="l">Pessoas listadas</div></div>
  </div>

  <?php
    require_once __DIR__ . '/lib/entregas.php';
    $modo_view = $_GET['view'] ?? 'compact';
    if (!in_array($modo_view, ['compact','expand'], true)) $modo_view = 'compact';
  ?>
  <div class="btn-pair mb-3">
    <a class="btn small <?= $modo_view==='compact'?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?><?= $mostrar_inativos?'&inativos=1':'' ?>&view=compact">📋 Lista compacta</a>
    <a class="btn small <?= $modo_view==='expand'?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?><?= $mostrar_inativos?'&inativos=1':'' ?>&view=expand">📅 Expandida (assinaturas)</a>
  </div>

  <div class="section-label mt-5">Por pessoa</div>
  <?php foreach ($dados as $d):
    $role_badge = '';
    if ($d['role'] === 'sadmin') $role_badge = '<span class="status status-destaque">super admin</span>';
    elseif ($d['role'] === 'admin') $role_badge = '<span class="status status-ia">admin</span>';
    $sem_atividade = ($d['n_assin'] === 0 && $d['n_entregas'] === 0);
  ?>
    <?php if ($modo_view === 'compact'): ?>
    <a class="card<?= $sem_atividade?' muted':'' ?>" href="<?= e(APP_BASE_URL) ?>/agenda.php?mes=<?= e($competencia) ?>&funcionario_id=<?= (int)$d['id'] ?>" style="text-decoration:none;">
      <div class="spaced">
        <div style="flex:1;">
          <div class="title">
            <?= e($d['nome']) ?>
            <?= $role_badge ?>
            <?php if ($d['trabalha_com_id']): ?><span class="status status-info">👥 dupla com <?= e($d['dupla_nome']) ?></span><?php endif; ?>
            <?php if ($d['role'] === 'funcionario'): ?>
              <?= $d['aceitando_clientes'] ? '<span class="status status-paga">🟢 aceitando</span>' : '<span class="status status-vencida">🔴 cheio</span>' ?>
            <?php endif; ?>
          </div>
          <div class="sub muted">
            <?php if ($d['n_assin'] > 0): ?><strong><?= $d['n_assin'] ?></strong> assinatura<?= $d['n_assin']>1?'s':'' ?> · <?php endif; ?>
            <?php if ($d['n_entregas'] > 0): ?>
              <strong><?= $d['n_entregas'] ?></strong> entrega<?= $d['n_entregas']>1?'s':'' ?> no mês · <strong><?= $d['n_clientes'] ?></strong> cliente<?= $d['n_clientes']>1?'s':'' ?>
            <?php else: ?>sem entregas neste mês<?php endif; ?>
            <?php if ($d['capacidade'] > 0): ?> · capacidade <strong><?= $d['capacidade'] ?></strong><?php endif; ?>
            <?php if ($d['ultima']): ?> · última <?= e(date('d/m H:i', strtotime($d['ultima']))) ?><?php endif; ?>
          </div>
        </div>
        <div class="muted" style="font-size:24px;">→</div>
      </div>
    </a>
    <?php else: // expand: lista as assinaturas e entregas inline ?>
    <div class="card<?= $sem_atividade?' muted':'' ?>" style="margin-bottom:var(--s-4);">
      <div class="spaced">
        <div style="flex:1;">
          <div class="title">
            <?= e($d['nome']) ?>
            <?= $role_badge ?>
            <?php if ($d['trabalha_com_id']): ?><span class="status status-info">👥 dupla com <?= e($d['dupla_nome']) ?></span><?php endif; ?>
            <?php if ($d['role'] === 'funcionario'): ?>
              <?= $d['aceitando_clientes'] ? '<span class="status status-paga">🟢</span>' : '<span class="status status-vencida">🔴</span>' ?>
            <?php endif; ?>
          </div>
          <div class="sub muted">
            <strong><?= $d['n_entregas'] ?></strong> entrega<?= $d['n_entregas']==1?'':'s' ?> · <strong><?= $d['n_clientes'] ?></strong> cliente<?= $d['n_clientes']==1?'':'s' ?>
            <?php if ($d['capacidade'] > 0): ?> · capacidade <?= $d['capacidade'] ?><?php endif; ?>
          </div>
        </div>
        <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/agenda.php?mes=<?= e($competencia) ?>&funcionario_id=<?= (int)$d['id'] ?>" style="text-decoration:none;">Abrir agenda →</a>
      </div>

      <?php
        // Lista assinaturas + contagem de entregas inline
        $assinaturas_func = agenda_assinaturas($db, (int)$d['id'], $competencia);
        if (!$assinaturas_func): ?>
          <p class="muted" style="font-size:13px; margin-top:var(--s-3);">Sem assinaturas neste mês.</p>
        <?php else: ?>
          <div style="margin-top:var(--s-3);">
          <?php foreach ($assinaturas_func as $af):
            $modo = entregas_modo_ui(['e_pacote' => $af['e_pacote'], 'tipo' => $af['tipo']]);
            $entregas_af = entregas_do_mes($db, (int)$af['assinatura_id'], $competencia);
            $cnt_af = count($entregas_af);
            // Resumo do progresso
            $progresso = '';
            if ($modo === 'calendar') $progresso = $cnt_af . ' dia' . ($cnt_af==1?'':'s') . ' marcado' . ($cnt_af==1?'':'s');
            elseif ($modo === 'tally') $progresso = $cnt_af . ' unidade' . ($cnt_af==1?'':'s');
            elseif ($modo === 'single') $progresso = $cnt_af > 0 ? '✅ entregue' : '⬜ pendente';
            else $progresso = $cnt_af > 0 ? $cnt_af . ' marcação' . ($cnt_af==1?'':'es') : 'em andamento';
          ?>
            <div class="info-pair" style="padding:8px 0; border-bottom:1px solid var(--border); font-size:13px;">
              <span class="l" style="flex:1;">
                <strong><?= e($af['nome_empresa']) ?></strong>
                · <?= e($af['item_nome']) ?>
                <?php if ($af['e_pacote']): ?><span class="status status-ia" style="font-size:10px;">pacote</span><?php endif; ?>
              </span>
              <span class="v" style="color:<?= $cnt_af>0?'var(--c-success)':'var(--c-orange)' ?>;"><?= e($progresso) ?></span>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<p class="muted center mt-5" style="font-size:13px;">Clique num funcionário pra ver a agenda detalhada dele.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
