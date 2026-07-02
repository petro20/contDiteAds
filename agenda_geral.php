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

$page = t('Acompanhamento geral');
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">📋 <?= e(t('Acompanhamento geral')) ?></h1>
<p class="muted"><?= e(t('Visão consolidada do que cada funcionário e admin está executando. Somente acompanhamento — pra ações use a Agenda individual de cada um.')) ?></p>

<div class="spaced mb-3">
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_ant) ?>">← <?= e($mes_ant) ?></a>
  <strong><?= e($nome_mes) ?></strong>
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_prox) ?>"><?= e($mes_prox) ?> →</a>
</div>

<div class="btn-pair mb-3">
  <a class="btn small <?= !$mostrar_inativos?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?>"><?= e(t('Só com atividade')) ?></a>
  <a class="btn small <?= $mostrar_inativos?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?>&inativos=1"><?= e(t('Todos os funcionários')) ?></a>
</div>

<?php if (!$dados): ?>
  <div class="card">
    <div class="title muted"><?= e(t('Sem atividade neste mês')) ?></div>
    <div class="desc"><?= e(t('Ninguém tem assinaturas ativas ou entregas em')) ?> <?= e($nome_mes) ?>.</div>
  </div>
<?php else: ?>
  <?php
    // KPIs gerais
    $tot_assin = array_sum(array_column($dados, 'n_assin'));
    $tot_entregas = array_sum(array_column($dados, 'n_entregas'));
    $tot_pessoas = count(array_filter($dados, fn($d) => $d['n_assin'] > 0 || $d['n_entregas'] > 0));
  ?>
  <div class="grid-2">
    <div class="kpi"><div class="v"><?= $tot_pessoas ?></div><div class="l"><?= e(t('Pessoas executando')) ?></div></div>
    <div class="kpi"><div class="v"><?= $tot_assin ?></div><div class="l"><?= e(t('Assinaturas ativas')) ?></div></div>
    <div class="kpi"><div class="v"><?= $tot_entregas ?></div><div class="l"><?= e(t('Entregas no mês')) ?></div></div>
    <div class="kpi"><div class="v"><?= count($dados) ?></div><div class="l"><?= e(t('Pessoas listadas')) ?></div></div>
  </div>

  <?php
    require_once __DIR__ . '/lib/entregas.php';
    $modo_view = $_GET['view'] ?? 'compact';
    if (!in_array($modo_view, ['compact','expand','calendar'], true)) $modo_view = 'compact';
  ?>
  <div class="btn-pair mb-3" style="flex-wrap:wrap;">
    <a class="btn small <?= $modo_view==='compact'?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?><?= $mostrar_inativos?'&inativos=1':'' ?>&view=compact">📋 <?= e(t('Lista')) ?></a>
    <a class="btn small <?= $modo_view==='expand'?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?><?= $mostrar_inativos?'&inativos=1':'' ?>&view=expand">📅 <?= e(t('Por pessoa')) ?></a>
    <a class="btn small <?= $modo_view==='calendar'?'btn-brand':'btn-secondary' ?>" href="?mes=<?= e($competencia) ?><?= $mostrar_inativos?'&inativos=1':'' ?>&view=calendar">🗓 <?= e(t('Calendário')) ?></a>
  </div>

  <?php if ($modo_view === 'calendar'):
    // Busca TODAS as entregas do mês com info dos funcionários, clientes, itens
    $stmt = $db->prepare("
        SELECT e.id, e.funcionario_id, e.data_marcada, e.criado_em, e.indice,
               u.nome AS func_nome, u.role,
               cl.nome_empresa, i.nome AS item_nome, i.tipo, i.e_pacote,
               a.id AS assin_id
        FROM entregas e
        JOIN usuarios u    ON u.id = e.funcionario_id
        JOIN assinaturas a ON a.id = e.assinatura_id
        JOIN clientes cl   ON cl.id = a.cliente_id
        JOIN itens_catalogo i ON i.id = a.item_id
        WHERE e.competencia_mes = ?
        ORDER BY u.nome
    ");
    $stmt->execute([$competencia]);
    $todas_entregas = $stmt->fetchAll();

    // Atribui cor consistente pra cada funcionário (hash do ID)
    $cores = ['#3B82F6','#10B981','#A855F7','#F59E0B','#EC4899','#06B6D4','#84CC16','#F97316','#8B5CF6','#14B8A6'];
    $func_cor = [];
    $func_nome = [];
    foreach ($todas_entregas as $en) {
        $fid = (int)$en['funcionario_id'];
        if (!isset($func_cor[$fid])) {
            $func_cor[$fid] = $cores[count($func_cor) % count($cores)];
            $func_nome[$fid] = $en['func_nome'];
        }
    }

    // Agrupa por data (usa data_marcada se houver, senão criado_em)
    $por_dia = [];
    foreach ($todas_entregas as $en) {
        $data = $en['data_marcada'] ?: substr($en['criado_em'], 0, 10);
        if (!isset($por_dia[$data])) $por_dia[$data] = [];
        $por_dia[$data][] = $en;
    }

    // Gera calendário do mês
    $primeiro_dt = new DateTimeImmutable($ini_m);
    $dias_no_mes = (int)$primeiro_dt->format('t');
    $dia_semana_ini = (int)$primeiro_dt->format('w'); // 0=Dom, 6=Sáb
  ?>

  <h2 class="mt-5">🗓 <?= e(t('Calendário do mês — todos os funcionários')) ?></h2>

  <?php if ($func_cor): ?>
    <div class="card">
      <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <?php foreach ($func_cor as $fid => $cor): ?>
          <span style="display:inline-flex; align-items:center; gap:6px; font-size:13px;">
            <span style="width:14px; height:14px; border-radius:3px; background:<?= e($cor) ?>;"></span>
            <?= e($func_nome[$fid]) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card" style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:560px;">
      <thead>
        <tr>
          <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $w): ?>
            <th style="padding:6px; color:var(--txt-3); font-size:12px; text-align:center; border-bottom:1px solid var(--border);"><?= e(t($w)) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $celulas = [];
        for ($i = 0; $i < $dia_semana_ini; $i++) $celulas[] = null;
        for ($d = 1; $d <= $dias_no_mes; $d++) $celulas[] = $d;
        while (count($celulas) % 7 !== 0) $celulas[] = null;

        for ($r = 0; $r < count($celulas); $r += 7): ?>
          <tr>
          <?php for ($c = 0; $c < 7; $c++):
              $dia = $celulas[$r + $c];
              if ($dia === null): ?>
                <td style="padding:6px; border:1px solid var(--border); background:transparent; height:90px;"></td>
              <?php else:
                $iso = $competencia . '-' . str_pad((string)$dia, 2, '0', STR_PAD_LEFT);
                $entregas_dia = $por_dia[$iso] ?? [];
                $hoje = date('Y-m-d') === $iso;
              ?>
                <td style="padding:4px; border:1px solid var(--border); vertical-align:top; height:90px; background:<?= $hoje?'rgba(59,130,246,0.08)':'transparent' ?>;">
                  <div style="font-size:13px; font-weight:<?= $hoje?'700':'600' ?>; color:<?= $hoje?'var(--c-primary-2)':'var(--txt-2)' ?>; margin-bottom:3px;"><?= $dia ?></div>
                  <?php foreach ($entregas_dia as $en):
                    $fid = (int)$en['funcionario_id'];
                    $cor = $func_cor[$fid] ?? '#666';
                  ?>
                    <div title="<?= e($en['func_nome']) . ' — ' . e($en['nome_empresa']) . ' / ' . e($en['item_nome']) ?>"
                         style="background:<?= e($cor) ?>; color:#fff; font-size:10px; padding:2px 4px; border-radius:3px; margin-bottom:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                      <?= e($en['nome_empresa']) ?>
                    </div>
                  <?php endforeach; ?>
                </td>
              <?php endif;
          endfor; ?>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <p class="muted center mt-3" style="font-size:13px;"><?= e(t('Passe o mouse sobre cada chip pra ver detalhes (funcionário, cliente, item).')) ?></p>

  <?php else: ?>

  <div class="section-label mt-5"><?= e(t('Por pessoa')) ?></div>
  <?php foreach ($dados as $d):
    $role_badge = '';
    if ($d['role'] === 'sadmin') $role_badge = '<span class="status status-destaque">' . e(t('super admin')) . '</span>';
    elseif ($d['role'] === 'admin') $role_badge = '<span class="status status-ia">' . e(t('admin')) . '</span>';
    $sem_atividade = ($d['n_assin'] === 0 && $d['n_entregas'] === 0);
  ?>
    <?php if ($modo_view === 'compact'): ?>
    <a class="card<?= $sem_atividade?' muted':'' ?>" href="<?= e(APP_BASE_URL) ?>/agenda.php?mes=<?= e($competencia) ?>&funcionario_id=<?= (int)$d['id'] ?>" style="text-decoration:none;">
      <div class="spaced">
        <div style="flex:1;">
          <div class="title">
            <?= e($d['nome']) ?>
            <?= $role_badge ?>
            <?php if ($d['trabalha_com_id']): ?><span class="status status-info">👥 <?= e(t('dupla com')) ?> <?= e($d['dupla_nome']) ?></span><?php endif; ?>
            <?php if ($d['role'] === 'funcionario'): ?>
              <?= $d['aceitando_clientes'] ? '<span class="status status-paga">🟢 ' . e(t('aceitando')) . '</span>' : '<span class="status status-vencida">🔴 ' . e(t('cheio')) . '</span>' ?>
            <?php endif; ?>
          </div>
          <div class="sub muted">
            <?php if ($d['n_assin'] > 0): ?><strong><?= $d['n_assin'] ?></strong> <?= e($d['n_assin']>1 ? t('assinaturas') : t('assinatura')) ?> · <?php endif; ?>
            <?php if ($d['n_entregas'] > 0): ?>
              <strong><?= $d['n_entregas'] ?></strong> <?= e($d['n_entregas']>1 ? t('entregas') : t('entrega')) ?> <?= e(t('no mês')) ?> · <strong><?= $d['n_clientes'] ?></strong> <?= e($d['n_clientes']>1 ? t('clientes') : t('cliente')) ?>
            <?php else: ?><?= e(t('sem entregas neste mês')) ?><?php endif; ?>
            <?php if ($d['capacidade'] > 0): ?> · <?= e(t('capacidade')) ?> <strong><?= $d['capacidade'] ?></strong><?php endif; ?>
            <?php if ($d['ultima']): ?> · <?= e(t('última')) ?> <?= e(date('d/m H:i', strtotime($d['ultima']))) ?><?php endif; ?>
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
            <?php if ($d['trabalha_com_id']): ?><span class="status status-info">👥 <?= e(t('dupla com')) ?> <?= e($d['dupla_nome']) ?></span><?php endif; ?>
            <?php if ($d['role'] === 'funcionario'): ?>
              <?= $d['aceitando_clientes'] ? '<span class="status status-paga">🟢</span>' : '<span class="status status-vencida">🔴</span>' ?>
            <?php endif; ?>
          </div>
          <div class="sub muted">
            <strong><?= $d['n_entregas'] ?></strong> <?= e($d['n_entregas']==1 ? t('entrega') : t('entregas')) ?> · <strong><?= $d['n_clientes'] ?></strong> <?= e($d['n_clientes']==1 ? t('cliente') : t('clientes')) ?>
            <?php if ($d['capacidade'] > 0): ?> · <?= e(t('capacidade')) ?> <?= $d['capacidade'] ?><?php endif; ?>
          </div>
        </div>
        <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/agenda.php?mes=<?= e($competencia) ?>&funcionario_id=<?= (int)$d['id'] ?>" style="text-decoration:none;"><?= e(t('Abrir agenda')) ?> →</a>
      </div>

      <?php
        // Lista assinaturas + contagem de entregas inline
        $assinaturas_func = agenda_assinaturas($db, (int)$d['id'], $competencia);
        if (!$assinaturas_func): ?>
          <p class="muted" style="font-size:13px; margin-top:var(--s-3);"><?= e(t('Sem assinaturas neste mês.')) ?></p>
        <?php else: ?>
          <div style="margin-top:var(--s-3);">
          <?php foreach ($assinaturas_func as $af):
            $modo = entregas_modo_ui(['e_pacote' => $af['e_pacote'], 'tipo' => $af['tipo']]);
            $entregas_af = entregas_do_mes($db, (int)$af['assinatura_id'], $competencia);
            $cnt_af = count($entregas_af);
            // Resumo do progresso
            $progresso = '';
            if ($modo === 'calendar') $progresso = $cnt_af . ' ' . ($cnt_af==1 ? t('dia marcado') : t('dias marcados'));
            elseif ($modo === 'tally') $progresso = $cnt_af . ' ' . ($cnt_af==1 ? t('unidade') : t('unidades'));
            elseif ($modo === 'single') $progresso = $cnt_af > 0 ? '✅ ' . t('entregue') : '⬜ ' . t('pendente');
            else $progresso = $cnt_af > 0 ? $cnt_af . ' ' . ($cnt_af==1 ? t('marcação') : t('marcações')) : t('em andamento');
          ?>
            <div class="info-pair" style="padding:8px 0; border-bottom:1px solid var(--border); font-size:13px;">
              <span class="l" style="flex:1;">
                <strong><?= e($af['nome_empresa']) ?></strong>
                · <?= e($af['item_nome']) ?>
                <?php if ($af['e_pacote']): ?><span class="status status-ia" style="font-size:10px;"><?= e(t('pacote')) ?></span><?php endif; ?>
              </span>
              <span class="v" style="color:<?= $cnt_af>0?'var(--c-success)':'var(--c-orange)' ?>;"><?= e($progresso) ?></span>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php endif; // fim do else do view=calendar ?>
<?php endif; ?>

<p class="muted center mt-5" style="font-size:13px;"><?= e(t('Clique num funcionário pra ver a agenda detalhada dele.')) ?></p>

<?php require __DIR__ . '/includes/footer.php'; ?>
