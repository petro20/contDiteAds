<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
$u  = require_login();
$db = db();

$page = 'Início';
$nav_active = is_admin() ? 'painel' : ($u['role'] === 'funcionario' ? 'agenda' : 'cobrancas');
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Olá, <?= e($u['nome']) ?></h1>

<?php if (is_admin()): ?>
  <?php
  require_once __DIR__ . '/lib/despesas.php';
  $competencia_now = date('Y-m');
  $ini_mes = $competencia_now . '-01';
  $fim_mes = date('Y-m-t', strtotime($ini_mes));

  // Comprovantes em análise (cliente enviou, aguardando admin aceitar/rejeitar)
  $tot_em_analise = 0;
  try { $tot_em_analise = (int)$db->query("SELECT COUNT(*) FROM cobrancas WHERE status='em_analise'")->fetchColumn(); } catch (Throwable $e) {}

  // Cobranças abertas (a receber) — neste mês
  $tot_abertas = 0; $val_abertas_brl = 0.0; $val_abertas_usd = 0.0; $val_abertas_eur = 0.0;
  try {
      $stmt = $db->prepare("SELECT moeda, COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total
                            FROM cobrancas WHERE status='aberta' AND competencia_mes = ? GROUP BY moeda");
      $stmt->execute([$competencia_now]);
      foreach ($stmt->fetchAll() as $r) {
          $tot_abertas += (int)$r['qtd'];
          if ($r['moeda'] === 'BRL') $val_abertas_brl = (float)$r['total'];
          elseif ($r['moeda'] === 'USD') $val_abertas_usd = (float)$r['total'];
          elseif ($r['moeda'] === 'EUR') $val_abertas_eur = (float)$r['total'];
      }
  } catch (Throwable $e) {}

  // Recebido este mês (pagamentos confirmados)
  $rec_brl = 0.0; $rec_usd = 0.0; $rec_eur = 0.0;
  try {
      $stmt = $db->prepare("SELECT c.moeda, COALESCE(SUM(p.valor_pago),0) AS total
                            FROM pagamentos_cliente p JOIN cobrancas c ON c.id = p.cobranca_id
                            WHERE p.data_pagamento BETWEEN ? AND ? AND COALESCE(p.pendente,0)=0
                            GROUP BY c.moeda");
      $stmt->execute([$ini_mes, $fim_mes]);
      foreach ($stmt->fetchAll() as $r) {
          if ($r['moeda'] === 'BRL') $rec_brl = (float)$r['total'];
          elseif ($r['moeda'] === 'USD') $rec_usd = (float)$r['total'];
          elseif ($r['moeda'] === 'EUR') $rec_eur = (float)$r['total'];
      }
  } catch (Throwable $e) {}

  // A pagar funcionários (fila USD)
  $a_pagar_func = 0.0;
  try {
      require_once __DIR__ . '/lib/pagamentos.php';
      $fila = fila_pagamentos_funcionarios($db);
      $a_pagar_func = (float)array_sum(array_column($fila, 'total_usd'));
  } catch (Throwable $e) {}

  // Lucro líquido do mês (receita - despesas - pag func USD)
  $desp_mes_data = despesas_do_mes($db, $competencia_now);
  $pag_func_mes_usd = 0.0;
  try {
      $stmt = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE data_pagamento BETWEEN ? AND ?");
      $stmt->execute([$ini_mes, $fim_mes]);
      $pag_func_mes_usd = (float)$stmt->fetchColumn();
  } catch (Throwable $e) {}
  $lucro_brl = $rec_brl - ($desp_mes_data['totais']['BRL'] ?? 0);
  $lucro_usd = $rec_usd - ($desp_mes_data['totais']['USD'] ?? 0) - $pag_func_mes_usd;
  $lucro_eur = $rec_eur - ($desp_mes_data['totais']['EUR'] ?? 0);
  ?>

  <?php
  // ============ ALERTAS ============
  // 1. Cobranças vencidas
  $tot_vencidas = 0; $val_vencidas = [];
  try {
      $stmt = $db->query("SELECT moeda, COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total
                          FROM cobrancas WHERE status='aberta' AND vencimento < CURDATE() GROUP BY moeda");
      foreach ($stmt->fetchAll() as $r) { $tot_vencidas += (int)$r['qtd']; $val_vencidas[$r['moeda']] = (float)$r['total']; }
  } catch (Throwable $e) {}

  // 2. Funcionários sobrecarregados (entregas do mês > capacidade declarada)
  $sobrecarga = 0;
  try {
      $stmt = $db->query("
          SELECT COUNT(DISTINCT u.id) FROM usuarios u
          JOIN capacidade_funcionario c ON c.funcionario_id = u.id
          LEFT JOIN entregas e ON e.funcionario_id = u.id AND e.competencia_mes = DATE_FORMAT(CURDATE(),'%Y-%m')
          WHERE u.role IN ('funcionario','admin') AND u.ativo=1
          GROUP BY u.id, c.categoria, c.capacidade_mensal
          HAVING COUNT(e.id) > c.capacidade_mensal
      ");
      $sobrecarga = (int)$stmt->rowCount();
  } catch (Throwable $e) {}

  // 3. Funcionários sem WiseTag mas com USD pendente (impede pagar)
  $sem_wisetag_com_grana = 0;
  try {
      $stmt = $db->query("
          SELECT COUNT(DISTINCT ci.funcionario_id) FROM cobranca_itens ci
          JOIN cobrancas c ON c.id = ci.cobranca_id
          JOIN usuarios u ON u.id = ci.funcionario_id
          LEFT JOIN pagamento_funcionario_itens pfi ON pfi.cobranca_item_id = ci.id
          WHERE c.status='paga' AND pfi.id IS NULL AND (u.wisetag IS NULL OR u.wisetag='')
      ");
      $sem_wisetag_com_grana = (int)$stmt->fetchColumn();
  } catch (Throwable $e) {}

  // Previsão de faturamento do mês = recebido + a receber (aberta) + em análise — por moeda
  $prev_faturamento = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
  $prev_faturamento['BRL'] = $rec_brl + $val_abertas_brl;
  $prev_faturamento['USD'] = $rec_usd + $val_abertas_usd;
  $prev_faturamento['EUR'] = $rec_eur + $val_abertas_eur;
  try {
      $stmt = $db->prepare("SELECT moeda, COALESCE(SUM(valor_total),0) AS total
                            FROM cobrancas WHERE status='em_analise' AND competencia_mes = ? GROUP BY moeda");
      $stmt->execute([$competencia_now]);
      foreach ($stmt->fetchAll() as $r) { $prev_faturamento[$r['moeda']] += (float)$r['total']; }
  } catch (Throwable $e) {}
  $tem_prev = ($prev_faturamento['BRL'] + $prev_faturamento['USD'] + $prev_faturamento['EUR']) > 0;

  // 4. Lucro do mês pendente de distribuir (se já passou dia 5 e tem sócio pendente)
  $dist_pendente = false;
  if ((int)date('j') >= 5 && ($lucro_brl > 0 || $lucro_usd > 0 || $lucro_eur > 0)) {
      try {
          $stmt = $db->prepare("SELECT COALESCE(SUM(valor),0) FROM pagamentos_socio WHERE competencia_mes = ?");
          $stmt->execute([$competencia_now]);
          $ja_distribuido = (float)$stmt->fetchColumn();
          if ($ja_distribuido < ($lucro_brl + $lucro_usd + $lucro_eur) * 0.5) {
              $dist_pendente = true;
          }
      } catch (Throwable $e) {}
  }
  ?>

  <a class="card brand" href="<?= e(APP_BASE_URL) ?>/painel.php" style="text-decoration:none;">
    <div class="title" style="color:var(--c-primary-2);">🔮 Previsão de faturamento <span class="muted" style="font-weight:normal; font-size:12px;">(<?= e(date('M/y')) ?>)</span></div>
    <div class="desc" style="margin-top:6px;">
      <?php
        $partes_prev = [];
        foreach ($prev_faturamento as $m => $v) {
            if ($v > 0) $partes_prev[] = '<strong style="color:var(--txt-1);">' . e(money_fmt($v, $m)) . '</strong>';
        }
        if ($partes_prev) {
            echo implode(' · ', $partes_prev);
        } else {
            echo '<strong class="muted">Nada previsto pra este mês ainda</strong>';
        }
      ?>
    </div>
    <div class="desc muted" style="font-size:12px; margin-top:4px;">recebido + a receber + em análise · este mês</div>
  </a>

  <?php if ($tot_em_analise > 0): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?status=em_analise">
      <div class="title" style="color:var(--c-orange);">🔔 <?= $tot_em_analise ?> comprovante<?= $tot_em_analise>1?'s':'' ?> aguardando verificação</div>
      <div class="desc">Cliente enviou comprovante de pagamento. Aceite ou rejeite para concluir o ciclo.</div>
    </a>
  <?php endif; ?>

  <?php if ($tot_vencidas > 0): ?>
    <a class="card danger" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?status=aberta">
      <div class="title" style="color:var(--c-danger);">💢 <?= $tot_vencidas ?> cobrança<?= $tot_vencidas>1?'s':'' ?> vencida<?= $tot_vencidas>1?'s':'' ?></div>
      <div class="desc">
        <?php $partes = []; foreach ($val_vencidas as $m => $v) $partes[] = money_fmt($v, $m); echo e(implode(' · ', $partes)); ?>
         · cobrar urgência via WhatsApp ou régua
      </div>
    </a>
  <?php endif; ?>

  <?php if ($sem_wisetag_com_grana > 0): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/funcionarios.php">
      <div class="title" style="color:var(--c-orange);">💳 <?= $sem_wisetag_com_grana ?> funcionário<?= $sem_wisetag_com_grana>1?'s':'' ?> sem WiseTag com USD a receber</div>
      <div class="desc">Cadastre o @wisetag no perfil deles pra liberar o pagamento.</div>
    </a>
  <?php endif; ?>

  <?php if ($sobrecarga > 0): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/capacidade.php">
      <div class="title" style="color:var(--c-orange);">📊 <?= $sobrecarga ?> funcionário<?= $sobrecarga>1?'s':'' ?> acima da capacidade</div>
      <div class="desc">A agenda do mês passou do que ele declarou conseguir entregar.</div>
    </a>
  <?php endif; ?>

  <?php if ($dist_pendente): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/distribuicao.php">
      <div class="title" style="color:var(--c-orange);">💎 Lucro do mês pendente de distribuir</div>
      <div class="desc">Já passou do dia 5 e o lucro ainda não foi distribuído aos sócios.</div>
    </a>
  <?php endif; ?>
  <div class="grid-2">
    <a class="kpi" href="<?= e(APP_BASE_URL) ?>/painel.php" style="text-decoration:none;">
      <div class="v" style="font-size:18px; color:var(--c-success);">
        <?php if ($rec_brl > 0): ?><?= e(money_fmt($rec_brl, 'BRL')) ?><br><?php endif; ?>
        <?php if ($rec_usd > 0): ?><?= e(money_fmt($rec_usd, 'USD')) ?><br><?php endif; ?>
        <?php if ($rec_eur > 0): ?><?= e(money_fmt($rec_eur, 'EUR')) ?><?php endif; ?>
        <?php if ($rec_brl == 0 && $rec_usd == 0 && $rec_eur == 0): ?>—<?php endif; ?>
      </div>
      <div class="l">✅ Recebido este mês</div>
    </a>
    <a class="kpi" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?status=aberta" style="text-decoration:none;">
      <div class="v" style="font-size:18px;">
        <?php if ($val_abertas_brl > 0): ?><?= e(money_fmt($val_abertas_brl, 'BRL')) ?><br><?php endif; ?>
        <?php if ($val_abertas_usd > 0): ?><?= e(money_fmt($val_abertas_usd, 'USD')) ?><br><?php endif; ?>
        <?php if ($val_abertas_eur > 0): ?><?= e(money_fmt($val_abertas_eur, 'EUR')) ?><?php endif; ?>
        <?php if ($tot_abertas == 0): ?>0<?php endif; ?>
      </div>
      <div class="l">⏳ A receber (<?= $tot_abertas ?> cobr.)</div>
    </a>
    <a class="kpi" href="<?= e(APP_BASE_URL) ?>/pagamentos_funcionarios.php" style="text-decoration:none;" <?= $a_pagar_func>0?'style="border-color:var(--c-orange); text-decoration:none;"':'' ?>>
      <div class="v" style="font-size:18px; color:<?= $a_pagar_func>0?'var(--c-orange)':'var(--txt-1)' ?>;">$<?= e(number_format($a_pagar_func, 2, '.', ',')) ?></div>
      <div class="l">💵 A pagar funcionários (USD)</div>
    </a>
    <a class="kpi" href="<?= e(APP_BASE_URL) ?>/painel.php" style="text-decoration:none;">
      <div class="v" style="font-size:18px;">
        <?php if ($lucro_brl != 0): ?><span style="color:<?= $lucro_brl>=0?'var(--c-success)':'var(--c-danger)' ?>;"><?= e(money_fmt($lucro_brl, 'BRL')) ?></span><br><?php endif; ?>
        <?php if ($lucro_usd != 0): ?><span style="color:<?= $lucro_usd>=0?'var(--c-success)':'var(--c-danger)' ?>;"><?= e(money_fmt($lucro_usd, 'USD')) ?></span><br><?php endif; ?>
        <?php if ($lucro_eur != 0): ?><span style="color:<?= $lucro_eur>=0?'var(--c-success)':'var(--c-danger)' ?>;"><?= e(money_fmt($lucro_eur, 'EUR')) ?></span><?php endif; ?>
        <?php if ($lucro_brl == 0 && $lucro_usd == 0 && $lucro_eur == 0): ?>—<?php endif; ?>
      </div>
      <div class="l">💎 Lucro do mês</div>
    </a>
  </div>

  <div class="section-label">Ações rápidas</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/painel.php">
    <div class="title">📊 Painel financeiro</div>
    <div class="desc">Agenda · Por cliente · Por serviço (vencidos, recebido, em aberto)</div>
  </a>
  <?php if (is_sadmin()): ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/catalogo.php">
    <div class="title">📦 Catálogo</div>
    <div class="desc">Cadastrar e editar itens <span class="status status-destaque">sadmin</span></div>
  </a>
  <?php endif; ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/clientes.php">
    <div class="title">👥 Clientes</div>
    <div class="desc">Gerenciar clientes e gerar convites</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/funcionarios.php">
    <div class="title">🧑‍💼 Funcionários</div>
    <div class="desc">Gerenciar equipe e capacidades</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/assinaturas.php">
    <div class="title">📝 Assinaturas</div>
    <div class="desc">Atribuir itens do catálogo a clientes</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">
    <div class="title">💳 Cobranças</div>
    <div class="desc">Ver cobranças geradas e gerar manualmente para teste</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/pagamentos_funcionarios.php">
    <div class="title">💵 Pagamentos a funcionários</div>
    <div class="desc">Fila de valores liberados em USD para pagar via Wise</div>
  </a>
  <?php if (is_sadmin()): ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/regua.php">
    <div class="title">⏰ Régua de cobrança</div>
    <div class="desc">Etapas automáticas + tarefas WhatsApp pendentes <span class="status status-destaque">sadmin</span></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/templates.php">
    <div class="title">📝 Templates de mensagem</div>
    <div class="desc">Editar textos de email e WhatsApp <span class="status status-destaque">sadmin</span></div>
  </a>
  <?php endif; ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/capacidade.php">
    <div class="title">📊 Capacidade da equipe</div>
    <div class="desc">Quanto cada funcionário absorve por mês</div>
  </a>
  <?php if (is_sadmin()): ?>
  <div class="section-label">Super Admin</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/auditoria.php">
    <div class="title">🔍 Auditoria</div>
    <div class="desc">Histórico de tudo no sistema (só sadmin)</div>
  </a>
  <?php endif; ?>

  <div class="section-label">Sociedade</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/distribuicao.php">
    <div class="title">💰 Distribuição de lucro</div>
    <div class="desc">Receita − despesas dividida em quotas (sócios + empresa)</div>
  </a>
  <?php if (is_sadmin()): ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/despesas.php">
    <div class="title">💸 Despesas da empresa</div>
    <div class="desc">Cadastrar gastos (ferramentas, software, etc.) <span class="status status-destaque">sadmin</span></div>
  </a>
  <?php endif; ?>

  <div class="section-label">Minha área de execução (também trabalho nos serviços)</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/agenda.php">
    <div class="title">📅 Minha agenda</div>
    <div class="desc">Marcar entregas dos clientes que eu atendo (como funcionário)</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/meus_pagamentos.php">
    <div class="title">💵 Meus pagamentos</div>
    <div class="desc">O que tenho a receber em USD (quando o cliente paga)</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/convites.php">
    <div class="title">✉️ Gerar convite</div>
    <div class="desc">Link para cliente ou funcionário se cadastrar</div>
  </a>

<?php elseif ($u['role'] === 'funcionario'): ?>
  <?php
    require_once __DIR__ . '/lib/pagamentos.php';
    $competencia_now = date('Y-m');

    $stmt = $db->prepare("SELECT COUNT(DISTINCT cliente_id) FROM assinaturas WHERE funcionario_id=? AND status='ativa'");
    $stmt->execute([(int)$u['id']]);
    $tot_clientes = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM assinaturas WHERE funcionario_id=? AND status='ativa'");
    $stmt->execute([(int)$u['id']]);
    $tot_assin = (int)$stmt->fetchColumn();

    $pendentes = itens_pendentes_funcionario($db, (int)$u['id']);
    $a_receber = (float)array_sum(array_column($pendentes, 'subtotal_usd'));

    $stmt = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE funcionario_id=? AND YEAR(data_pagamento)=YEAR(CURDATE()) AND MONTH(data_pagamento)=MONTH(CURDATE())");
    $stmt->execute([(int)$u['id']]);
    $recebido_mes = (float)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM entregas WHERE funcionario_id=? AND competencia_mes=?");
    $stmt->execute([(int)$u['id'], $competencia_now]);
    $tot_entregas = (int)$stmt->fetchColumn();
  ?>
  <div class="grid-2">
    <div class="kpi"><div class="v"><?= $tot_clientes ?></div><div class="l">Clientes que atendo</div></div>
    <div class="kpi"><div class="v"><?= $tot_assin ?></div><div class="l">Serviços ativos</div></div>
    <div class="kpi"><div class="v"><?= $tot_entregas ?></div><div class="l">Entregas este mês</div></div>
    <div class="kpi" <?= $a_receber>0?'style="border-color:var(--c-success);"':'' ?>>
      <div class="v">$<?= e(number_format($a_receber, 2, '.', ',')) ?></div>
      <div class="l">A receber (USD)</div>
    </div>
  </div>

  <?php if ($recebido_mes > 0): ?>
    <div class="card success">
      <div class="title">💵 $<?= e(number_format($recebido_mes, 2, '.', ',')) ?> USD recebidos este mês</div>
    </div>
  <?php endif; ?>

  <div class="section-label">Acesso rápido</div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/agenda.php">
    <div class="title">📅 Minha agenda</div>
    <div class="desc">Marcar entregas dos meus clientes</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/clientes.php">
    <div class="title">👥 Meus clientes</div>
    <div class="desc">Lista de quem eu atendo</div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/meus_pagamentos.php">
    <div class="title">💵 Meus pagamentos</div>
    <div class="desc">A receber + histórico em USD</div>
  </a>

<?php else: /* cliente */ ?>
  <?php
    $cid = (int)($u['cliente_id'] ?? 0);
    if ($cid) {
      $stmt = $db->prepare("SELECT moeda, nome_empresa FROM clientes WHERE id = ?");
      $stmt->execute([$cid]);
      $cli = $stmt->fetch();
      $stmt = $db->prepare("SELECT COALESCE(SUM(valor_total),0) FROM cobrancas WHERE cliente_id = ? AND status='aberta'");
      $stmt->execute([$cid]);
      $em_aberto = (float)$stmt->fetchColumn();
      $stmt = $db->prepare("SELECT COUNT(*) FROM cobrancas WHERE cliente_id = ? AND status='aberta' AND vencimento < CURDATE()");
      $stmt->execute([$cid]);
      $vencidas = (int)$stmt->fetchColumn();
    } else { $cli = null; $em_aberto = 0; $vencidas = 0; }
  ?>

  <?php if (!$cli): ?>
    <div class="card attention"><div class="title">⚠ Conta sem empresa vinculada</div><div class="desc">Avise o admin pra ligar sua conta a um cliente cadastrado.</div></div>
  <?php else: ?>
    <div class="grid-2">
      <div class="kpi"><div class="v"><?= e(money_fmt($em_aberto, $cli['moeda'])) ?></div><div class="l">Em aberto</div></div>
      <div class="kpi <?= $vencidas?'':'' ?>" <?= $vencidas?'style="border-color:var(--c-danger);"':'' ?>><div class="v"><?= $vencidas ?></div><div class="l"><?= $vencidas?'<span style="color:var(--c-danger);">Vencidas</span>':'Vencidas' ?></div></div>
    </div>

    <?php
      $stmt = $db->prepare("
        SELECT a.id, a.variante, a.valor_cobrado, a.status, a.iniciada_em,
               i.nome AS item_nome, i.tipo, i.e_pacote,
               u.nome AS func_nome
        FROM assinaturas a
        JOIN itens_catalogo i ON i.id = a.item_id
        LEFT JOIN usuarios u ON u.id = a.funcionario_id
        WHERE a.cliente_id = ? AND a.status = 'ativa'
        ORDER BY i.nome
      ");
      $stmt->execute([$cid]);
      $minhas_assin = $stmt->fetchAll();
    ?>
    <div class="section-label">Meus serviços contratados</div>
    <?php if (!$minhas_assin): ?>
      <div class="card"><div class="desc muted">Nenhum serviço ativo. Fale com a Dite Ads pra contratar.</div></div>
    <?php else: foreach ($minhas_assin as $a): ?>
      <div class="card">
        <div class="spaced">
          <div>
            <div class="title" style="color:var(--txt-1);">
              <?= e($a['item_nome']) ?>
              <?php if ($a['e_pacote']): ?><span class="status status-ia">pacote</span><?php endif; ?>
              <?php if ($a['variante']==='ia'): ?><span class="status status-destaque">com IA</span><?php endif; ?>
            </div>
            <div class="sub muted">
              <?= e(['unico'=>'único','mensal'=>'mensal','por_unidade'=>'por unidade'][$a['tipo']] ?? $a['tipo']) ?>
              · ativo desde <?= e(date('d/m/Y', strtotime($a['iniciada_em']))) ?>
              <?php if ($a['func_nome']): ?> · com <?= e($a['func_nome']) ?><?php endif; ?>
            </div>
          </div>
          <div class="money md"><?= e(money_fmt((float)$a['valor_cobrado'], $cli['moeda'])) ?></div>
        </div>
      </div>
    <?php endforeach; endif; ?>

    <div class="section-label">Acesso rápido</div>
    <a class="card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">
      <div class="title">💳 Minhas cobranças</div>
      <div class="desc">Ver, anexar comprovante de pagamento</div>
    </a>
    <a class="card" href="<?= e(APP_BASE_URL) ?>/entregas.php">
      <div class="title">✅ Minhas entregas</div>
      <div class="desc">Acompanhar o que foi entregue no mês</div>
    </a>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
