<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
$u  = require_login();
$db = db();

$page = t('Início');
$nav_active = is_admin() ? 'painel' : ($u['role'] === 'funcionario' ? 'agenda' : 'cobrancas');
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= e(t('Olá,')) ?> <?= e($u['nome']) ?></h1>

<?php if (is_admin()): ?>
  <?php
  require_once __DIR__ . '/lib/despesas.php';
  $competencia_now = date('Y-m');
  $ini_mes = $competencia_now . '-01';
  $fim_mes = date('Y-m-t', strtotime($ini_mes));

  // Comprovantes em análise (cliente enviou, aguardando admin aceitar/rejeitar)
  $tot_em_analise = 0;
  try { $tot_em_analise = (int)$db->query("SELECT COUNT(*) FROM cobrancas WHERE status='em_analise'")->fetchColumn(); } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

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
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

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
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

  // A pagar funcionários (fila USD)
  $a_pagar_func = 0.0;
  try {
      require_once __DIR__ . '/lib/pagamentos.php';
      $fila = fila_pagamentos_funcionarios($db);
      $a_pagar_func = (float)array_sum(array_column($fila, 'total_usd'));
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

  // Lucro líquido do mês (receita - despesas - pag func USD)
  $desp_mes_data = despesas_do_mes($db, $competencia_now);
  $pag_func_mes_usd = 0.0;
  try {
      $stmt = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE data_pagamento BETWEEN ? AND ?");
      $stmt->execute([$ini_mes, $fim_mes]);
      $pag_func_mes_usd = (float)$stmt->fetchColumn();
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
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
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

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
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

  // 3. Funcionários sem WiseTag mas com USD pendente (impede pagar)
  $sem_wisetag_com_grana = 0;
  try {
      // funcionario_id está em ASSINATURAS, não em cobranca_itens. Bug antigo.
      $stmt = $db->query("
          SELECT COUNT(DISTINCT a.funcionario_id) FROM cobranca_itens ci
          JOIN cobrancas c ON c.id = ci.cobranca_id
          JOIN assinaturas a ON a.id = ci.assinatura_id
          JOIN usuarios u ON u.id = a.funcionario_id
          LEFT JOIN pagamento_funcionario_itens pfi ON pfi.cobranca_item_id = ci.id
          WHERE c.status='paga' AND pfi.id IS NULL AND (u.wisetag IS NULL OR u.wisetag='')
      ");
      $sem_wisetag_com_grana = (int)$stmt->fetchColumn();
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

  // Previsão de faturamento do mês = recebido + a receber + em análise + assinaturas ainda sem cobrança
  $prev_faturamento = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
  $prev_faturamento['BRL'] = $rec_brl + $val_abertas_brl;
  $prev_faturamento['USD'] = $rec_usd + $val_abertas_usd;
  $prev_faturamento['EUR'] = $rec_eur + $val_abertas_eur;
  try {
      $stmt = $db->prepare("SELECT moeda, COALESCE(SUM(valor_total),0) AS total
                            FROM cobrancas WHERE status='em_analise' AND competencia_mes = ? GROUP BY moeda");
      $stmt->execute([$competencia_now]);
      foreach ($stmt->fetchAll() as $r) { $prev_faturamento[$r['moeda']] += (float)$r['total']; }
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
  // Assinaturas ativas mensais que ainda não geraram cobrança neste mês
  try {
      $stmt = $db->prepare("
          SELECT cl.moeda, COALESCE(SUM(a.valor_cobrado), 0) AS total
          FROM assinaturas a
          JOIN clientes cl       ON cl.id = a.cliente_id
          JOIN itens_catalogo i  ON i.id  = a.item_id
          WHERE a.status='ativa' AND i.tipo='mensal'
            AND a.iniciada_em <= ?
            AND (a.encerrada_em IS NULL OR a.encerrada_em >= ?)
            AND NOT EXISTS (
              SELECT 1 FROM cobranca_itens ci JOIN cobrancas cb ON cb.id = ci.cobranca_id
              WHERE ci.assinatura_id = a.id AND cb.competencia_mes = ?
            )
          GROUP BY cl.moeda");
      $stmt->execute([$fim_mes, $ini_mes, $competencia_now]);
      foreach ($stmt->fetchAll() as $r) { $prev_faturamento[$r['moeda']] += (float)$r['total']; }
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
  $tem_prev = ($prev_faturamento['BRL'] + $prev_faturamento['USD'] + $prev_faturamento['EUR']) > 0;

  // Previsão de pagamento a funcionários (USD): total do que a empresa vai
  // pagar este mês considerando TODAS as assinaturas ativas mensais com
  // funcionário designado — independente de já ter cobrança gerada/paga ou não.
  // Lógica do user: "se tem assinatura, já se sabe quanto vai pagar".
  $pag_func_previsto = 0.0;
  try {
      $stmt = $db->prepare("
          SELECT COALESCE(SUM(COALESCE(fsp.valor_usd, 0)), 0)
          FROM assinaturas a
          JOIN itens_catalogo i ON i.id = a.item_id
          LEFT JOIN func_servico_pagamento fsp
                 ON fsp.funcionario_id = a.funcionario_id AND fsp.item_id = a.item_id
          WHERE a.status='ativa' AND i.tipo='mensal'
            AND a.funcionario_id IS NOT NULL
            AND a.iniciada_em <= ?
            AND (a.encerrada_em IS NULL OR a.encerrada_em >= ?)");
      $stmt->execute([$fim_mes, $ini_mes]);
      $pag_func_previsto = (float)$stmt->fetchColumn();
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }

  // Contagens pra ajudar o usuário a entender as previsões
  $qtd_assin_func = 0;
  $qtd_assin_sem_valor = 0;
  try {
      $stmt = $db->prepare("
          SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN fsp.valor_usd IS NULL OR fsp.valor_usd = 0 THEN 1 ELSE 0 END) AS sem_valor
          FROM assinaturas a
          JOIN itens_catalogo i ON i.id = a.item_id
          LEFT JOIN func_servico_pagamento fsp
                 ON fsp.funcionario_id = a.funcionario_id AND fsp.item_id = a.item_id
          WHERE a.status='ativa' AND i.tipo='mensal' AND a.funcionario_id IS NOT NULL
            AND a.iniciada_em <= ? AND (a.encerrada_em IS NULL OR a.encerrada_em >= ?)");
      $stmt->execute([$fim_mes, $ini_mes]);
      $row = $stmt->fetch();
      $qtd_assin_func = (int)($row['total'] ?? 0);
      $qtd_assin_sem_valor = (int)($row['sem_valor'] ?? 0);
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
  $qtd_despesas = count($desp_mes_data['detalhes'] ?? []);

  // Breakdown das despesas por moeda
  $despesas_por_moeda = $desp_mes_data['totais'] ?? ['BRL'=>0,'USD'=>0,'EUR'=>0];

  // Previsão de pagamento = despesas + pag funcionários previsto (USD)
  $prev_pagamento = [
      'BRL' => ($despesas_por_moeda['BRL'] ?? 0),
      'USD' => ($despesas_por_moeda['USD'] ?? 0) + $pag_func_previsto,
      'EUR' => ($despesas_por_moeda['EUR'] ?? 0),
  ];
  // Previsão de lucro = recebimento − pagamento
  $prev_lucro = [
      'BRL' => $prev_faturamento['BRL'] - $prev_pagamento['BRL'],
      'USD' => $prev_faturamento['USD'] - $prev_pagamento['USD'],
      'EUR' => $prev_faturamento['EUR'] - $prev_pagamento['EUR'],
  ];

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
      } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
  }
  ?>

  <a class="card brand" href="<?= e(APP_BASE_URL) ?>/painel.php" style="text-decoration:none;">
    <div class="title" style="color:var(--c-primary-2);">🔮 <?= e(t('Previsão do mês')) ?> <span class="muted" style="font-weight:normal; font-size:12px;">(<?= e(date('M/y')) ?>)</span></div>

    <div class="info-pair" style="margin-top:var(--s-3);">
      <span class="l"><strong>📥 <?= e(t('Recebimento')) ?></strong></span>
      <span class="v" style="text-align:right;">
        <?php
          $partes_prev = [];
          foreach ($prev_faturamento as $m => $v) {
              if ($v > 0) $partes_prev[] = '<strong style="color:var(--c-success);">' . e(money_fmt($v, $m)) . '</strong>';
          }
          echo $partes_prev ? implode('<br>', $partes_prev) : '<span class="muted">—</span>';
        ?>
      </span>
    </div>
    <div class="info-pair muted" style="font-size:12px;">
      <span class="l">↳ <?= e(t('cobranças + assinaturas ativas')) ?></span>
    </div>

    <div class="info-pair" style="margin-top:var(--s-2);">
      <span class="l"><strong>📤 <?= e(t('Pagamento')) ?></strong></span>
      <span class="v" style="text-align:right;">
        <?php
          $partes_pag = [];
          foreach ($prev_pagamento as $m => $v) {
              if ($v > 0) $partes_pag[] = '<strong style="color:var(--c-danger);">− ' . e(money_fmt($v, $m)) . '</strong>';
          }
          echo $partes_pag ? implode('<br>', $partes_pag) : '<span class="muted">—</span>';
        ?>
      </span>
    </div>
    <div class="info-pair muted" style="font-size:12px;">
      <span class="l">↳ 💸 <?= e(t('Despesas')) ?> (<?= (int)$qtd_despesas ?>)</span>
      <span class="v">
        <?php
          $partes_d = [];
          foreach ($despesas_por_moeda as $m => $v) if ($v > 0) $partes_d[] = e(money_fmt($v, $m));
          echo $partes_d ? implode(' · ', $partes_d) : '—';
        ?>
      </span>
    </div>
    <div class="info-pair muted" style="font-size:12px;">
      <span class="l">↳ 💵 <?= e(t('Funcionários')) ?> (<?= (int)$qtd_assin_func ?> <?= e(t('assin.')) ?>)</span>
      <span class="v"><?= $pag_func_previsto > 0 ? e(money_fmt($pag_func_previsto, 'USD')) : '—' ?></span>
    </div>
    <?php if ($qtd_assin_sem_valor > 0): ?>
      <div class="info-pair" style="font-size:12px; color:var(--c-orange);">
        <span class="l">⚠ <?= (int)$qtd_assin_sem_valor ?> <?= $qtd_assin_sem_valor>1?e(t('assinaturas sem valor USD configurado')):e(t('assinatura sem valor USD configurado')) ?></span>
      </div>
    <?php endif; ?>

    <div class="info-pair" style="border-top:1px solid var(--border); padding-top:var(--s-3); margin-top:var(--s-3);">
      <strong style="font-size:15px;">💎 <?= e(t('Lucro previsto')) ?></strong>
      <span class="v" style="text-align:right;">
        <?php
          $partes_lucro = [];
          foreach ($prev_lucro as $m => $v) {
              if ($v != 0) {
                  $cor = $v >= 0 ? 'var(--c-success)' : 'var(--c-danger)';
                  $partes_lucro[] = '<strong style="font-size:15px; color:' . $cor . ';">' . e(money_fmt($v, $m)) . '</strong>';
              }
          }
          echo $partes_lucro ? implode('<br>', $partes_lucro) : '<span class="muted">—</span>';
        ?>
      </span>
    </div>
  </a>

  <?php if ($tot_em_analise > 0): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?status=em_analise">
      <div class="title" style="color:var(--c-orange);">🔔 <?= $tot_em_analise ?> <?= $tot_em_analise>1?e(t('comprovantes aguardando verificação')):e(t('comprovante aguardando verificação')) ?></div>
      <div class="desc"><?= e(t('Cliente enviou comprovante de pagamento. Aceite ou rejeite para concluir o ciclo.')) ?></div>
    </a>
  <?php endif; ?>

  <?php
  // Pagamentos Wise pendentes (vindo do webhook como pendente=1)
  $tot_wise_pend = 0;
  try {
    $tot_wise_pend = (int)$db->query("SELECT COUNT(*) FROM pagamentos_cliente WHERE pendente=1 AND metodo='Wise'")->fetchColumn();
  } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
  if ($tot_wise_pend > 0):
  ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/wise_eventos.php">
      <div class="title" style="color:var(--c-orange);">🪝 <?= $tot_wise_pend ?> <?= $tot_wise_pend>1?e(t('pagamentos Wise aguardando reconciliação')):e(t('pagamento Wise aguardando reconciliação')) ?></div>
      <div class="desc"><?= e(t('Webhook detectou pagamentos automaticamente. Revise e confirme/rejeite no painel Wise.')) ?></div>
    </a>
  <?php endif; ?>

  <?php if ($tot_vencidas > 0): ?>
    <a class="card danger" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?status=aberta">
      <div class="title" style="color:var(--c-danger);">💢 <?= $tot_vencidas ?> <?= $tot_vencidas>1?e(t('cobranças vencidas')):e(t('cobrança vencida')) ?></div>
      <div class="desc">
        <?php $partes = []; foreach ($val_vencidas as $m => $v) $partes[] = money_fmt($v, $m); echo e(implode(' · ', $partes)); ?>
         · <?= e(t('cobrar urgência via WhatsApp ou régua')) ?>
      </div>
    </a>
  <?php endif; ?>

  <?php if ($sem_wisetag_com_grana > 0): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/funcionarios.php">
      <div class="title" style="color:var(--c-orange);">💳 <?= $sem_wisetag_com_grana ?> <?= $sem_wisetag_com_grana>1?e(t('funcionários sem WiseTag com USD a receber')):e(t('funcionário sem WiseTag com USD a receber')) ?></div>
      <div class="desc"><?= e(t('Cadastre o @wisetag no perfil deles pra liberar o pagamento.')) ?></div>
    </a>
  <?php endif; ?>

  <?php if ($sobrecarga > 0): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/capacidade.php">
      <div class="title" style="color:var(--c-orange);">📊 <?= $sobrecarga ?> <?= $sobrecarga>1?e(t('funcionários acima da capacidade')):e(t('funcionário acima da capacidade')) ?></div>
      <div class="desc"><?= e(t('A agenda do mês passou do que ele declarou conseguir entregar.')) ?></div>
    </a>
  <?php endif; ?>

  <?php if ($dist_pendente): ?>
    <a class="card attention" href="<?= e(APP_BASE_URL) ?>/distribuicao.php">
      <div class="title" style="color:var(--c-orange);">💎 <?= e(t('Lucro do mês pendente de distribuir')) ?></div>
      <div class="desc"><?= e(t('Já passou do dia 5 e o lucro ainda não foi distribuído aos sócios.')) ?></div>
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
      <div class="l">✅ <?= e(t('Recebido este mês')) ?></div>
    </a>
    <a class="kpi" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?status=aberta" style="text-decoration:none;">
      <div class="v" style="font-size:18px;">
        <?php if ($val_abertas_brl > 0): ?><?= e(money_fmt($val_abertas_brl, 'BRL')) ?><br><?php endif; ?>
        <?php if ($val_abertas_usd > 0): ?><?= e(money_fmt($val_abertas_usd, 'USD')) ?><br><?php endif; ?>
        <?php if ($val_abertas_eur > 0): ?><?= e(money_fmt($val_abertas_eur, 'EUR')) ?><?php endif; ?>
        <?php if ($tot_abertas == 0): ?>0<?php endif; ?>
      </div>
      <div class="l">⏳ <?= e(t('A receber')) ?> (<?= $tot_abertas ?> <?= e(t('cobr.')) ?>)</div>
    </a>
    <a class="kpi" href="<?= e(APP_BASE_URL) ?>/pagamentos_funcionarios.php" style="text-decoration:none;" <?= $a_pagar_func>0?'style="border-color:var(--c-orange); text-decoration:none;"':'' ?>>
      <div class="v" style="font-size:18px; color:<?= $a_pagar_func>0?'var(--c-orange)':'var(--txt-1)' ?>;">$<?= e(number_format($a_pagar_func, 2, '.', ',')) ?></div>
      <div class="l">💵 <?= e(t('A pagar funcionários (USD)')) ?></div>
    </a>
    <a class="kpi" href="<?= e(APP_BASE_URL) ?>/painel.php" style="text-decoration:none;">
      <div class="v" style="font-size:18px;">
        <?php if ($lucro_brl != 0): ?><span style="color:<?= $lucro_brl>=0?'var(--c-success)':'var(--c-danger)' ?>;"><?= e(money_fmt($lucro_brl, 'BRL')) ?></span><br><?php endif; ?>
        <?php if ($lucro_usd != 0): ?><span style="color:<?= $lucro_usd>=0?'var(--c-success)':'var(--c-danger)' ?>;"><?= e(money_fmt($lucro_usd, 'USD')) ?></span><br><?php endif; ?>
        <?php if ($lucro_eur != 0): ?><span style="color:<?= $lucro_eur>=0?'var(--c-success)':'var(--c-danger)' ?>;"><?= e(money_fmt($lucro_eur, 'EUR')) ?></span><?php endif; ?>
        <?php if ($lucro_brl == 0 && $lucro_usd == 0 && $lucro_eur == 0): ?>—<?php endif; ?>
      </div>
      <div class="l">💎 <?= e(t('Lucro do mês')) ?></div>
    </a>
  </div>

  <div class="section-label"><?= e(t('Link do sistema (para enviar a clientes/funcionários)')) ?></div>
  <div class="card">
    <div class="spaced" style="gap:8px;">
      <code id="link_sistema_txt" style="flex:1; padding:8px 12px; background:var(--bg-input); border-radius:6px; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e(APP_BASE_URL) ?>/</code>
      <button type="button" class="btn small btn-brand" onclick="copiarLinkSistema(this)">📋 <?= e(t('Copiar')) ?></button>
    </div>
    <div class="hint"><?= e(t('Use nas mensagens (WhatsApp, email). Nos templates use a variável')) ?> <code>{link_sistema}</code>.</div>
  </div>
  <script>
  function copiarLinkSistema(btn) {
    const txt = document.getElementById('link_sistema_txt').textContent.trim();
    navigator.clipboard.writeText(txt).then(() => {
      const orig = btn.innerHTML;
      btn.innerHTML = '✅ <?= e(t('Copiado!')) ?>';
      setTimeout(() => btn.innerHTML = orig, 2000);
    });
  }
  </script>

  <div class="section-label"><?= e(t('Ações rápidas')) ?></div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/painel.php">
    <div class="title">📊 <?= e(t('Painel financeiro')) ?></div>
    <div class="desc"><?= e(t('Agenda · Por cliente · Por serviço')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/clientes.php">
    <div class="title">👥 <?= e(t('Clientes')) ?></div>
    <div class="desc"><?= e(t('Gerenciar clientes e gerar convites')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/funcionarios.php">
    <div class="title">🧑‍💼 <?= e(t('Equipe')) ?></div>
    <div class="desc"><?= e(t('Lista · Capacidade · Pagamentos a funcionários')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/assinaturas.php">
    <div class="title">📝 <?= e(t('Assinaturas')) ?></div>
    <div class="desc"><?= e(t('Atribuir itens do catálogo a clientes')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">
    <div class="title">💳 <?= e(t('Cobranças')) ?></div>
    <div class="desc"><?= e(t('Ver e gerar cobranças')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/agenda_geral.php">
    <div class="title">📋 <?= e(t('Acompanhamento geral')) ?></div>
    <div class="desc"><?= e(t('Visão consolidada do que cada funcionário está executando no mês')) ?></div>
  </a>
  <?php if (is_sadmin()): ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/catalogo.php">
    <div class="title">📦 <?= e(t('Catálogo')) ?></div>
    <div class="desc"><?= e(t('Cadastrar e editar itens')) ?> <span class="status status-destaque">sadmin</span></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/despesas.php">
    <div class="title">💰 <?= e(t('Finanças')) ?></div>
    <div class="desc"><?= e(t('Despesas · Distribuição de lucro · Formas de pagamento')) ?> <span class="status status-destaque">sadmin</span></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/regua.php">
    <div class="title">💬 <?= e(t('Comunicação')) ?></div>
    <div class="desc"><?= e(t('Régua automática · Tarefas WhatsApp · Templates')) ?> <span class="status status-destaque">sadmin</span></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/auditoria.php">
    <div class="title">🔍 <?= e(t('Auditoria')) ?></div>
    <div class="desc"><?= e(t('Histórico de tudo no sistema')) ?> <span class="status status-destaque">sadmin</span></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/acessos_pdf.php" target="_blank">
    <div class="title">🔐 <?= e(t('Matriz de acesso (PDF)')) ?></div>
    <div class="desc"><?= e(t('Documento com quem pode o quê — abre em nova aba')) ?> <span class="status status-destaque">sadmin</span></div>
  </a>
  <?php else: // admin não-sadmin tem acesso a distribuição como sócio mas não às configs ?>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/distribuicao.php">
    <div class="title">💎 <?= e(t('Distribuição de lucro')) ?></div>
    <div class="desc"><?= e(t('Sua quota como sócio')) ?></div>
  </a>
  <?php endif; ?>

  <div class="section-label"><?= e(t('Minha área de execução (também trabalho nos serviços)')) ?></div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/agenda.php">
    <div class="title">📅 <?= e(t('Minha agenda')) ?></div>
    <div class="desc"><?= e(t('Marcar entregas dos clientes que eu atendo (como funcionário)')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/meus_pagamentos.php">
    <div class="title">💵 <?= e(t('Meus pagamentos')) ?></div>
    <div class="desc"><?= e(t('O que tenho a receber em USD (quando o cliente paga)')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/convites.php">
    <div class="title">✉️ <?= e(t('Gerar convite')) ?></div>
    <div class="desc"><?= e(t('Link para cliente ou funcionário se cadastrar')) ?></div>
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

    // Previsão MENSAL do funcionário:
    //   Lógica simplificada — se a assinatura está ativa, esse é o valor que ele vai
    //   receber este mês. Total = SUM(valor_usd) de todas as assinaturas ativas dele.
    $prev_func = 0.0;
    $qtd_assin_minhas = 0;
    try {
        $ini_mes_f = $competencia_now . '-01';
        $fim_mes_f = date('Y-m-t', strtotime($ini_mes_f));
        $stmt = $db->prepare("
            SELECT
              COALESCE(SUM(COALESCE(fsp.valor_usd, 0)), 0) AS total,
              COUNT(*) AS qtd
            FROM assinaturas a
            JOIN itens_catalogo i ON i.id = a.item_id
            LEFT JOIN func_servico_pagamento fsp
                   ON fsp.funcionario_id = a.funcionario_id AND fsp.item_id = a.item_id
            WHERE a.funcionario_id = ? AND a.status = 'ativa' AND i.tipo = 'mensal'
              AND a.iniciada_em <= ?
              AND (a.encerrada_em IS NULL OR a.encerrada_em >= ?)");
        $stmt->execute([(int)$u['id'], $fim_mes_f, $ini_mes_f]);
        $row = $stmt->fetch();
        $prev_func = (float)($row['total'] ?? 0);
        $qtd_assin_minhas = (int)($row['qtd'] ?? 0);
    } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
  ?>

  <a class="card brand" href="<?= e(APP_BASE_URL) ?>/meus_pagamentos.php" style="text-decoration:none;">
    <div class="title" style="color:var(--c-primary-2);">🔮 <?= e(t('Previsão de recebimento')) ?> <span class="muted" style="font-weight:normal; font-size:12px;">(<?= e(date('M/y')) ?>)</span></div>
    <div class="desc" style="margin-top:6px;">
      <?php if ($prev_func > 0): ?>
        <strong style="color:var(--c-success);">$<?= e(number_format($prev_func, 2, '.', ',')) ?> USD</strong>
      <?php else: ?>
        <strong class="muted"><?= e(t('Sem assinaturas ativas pra este mês')) ?></strong>
      <?php endif; ?>
    </div>
    <div class="desc muted" style="font-size:12px; margin-top:4px;"><?= $qtd_assin_minhas ?> <?= $qtd_assin_minhas==1?e(t('assinatura ativa mensal')):e(t('assinaturas ativas mensais')) ?> · <?= e(t('valor fixo por assinatura')) ?></div>
  </a>

  <div class="grid-2">
    <div class="kpi"><div class="v"><?= $tot_clientes ?></div><div class="l"><?= e(t('Clientes que atendo')) ?></div></div>
    <div class="kpi"><div class="v"><?= $tot_assin ?></div><div class="l"><?= e(t('Serviços ativos')) ?></div></div>
    <div class="kpi"><div class="v"><?= $tot_entregas ?></div><div class="l"><?= e(t('Entregas este mês')) ?></div></div>
    <div class="kpi" <?= $a_receber>0?'style="border-color:var(--c-success);"':'' ?>>
      <div class="v">$<?= e(number_format($a_receber, 2, '.', ',')) ?></div>
      <div class="l"><?= e(t('A receber (USD)')) ?></div>
    </div>
  </div>

  <?php if ($recebido_mes > 0): ?>
    <div class="card success">
      <div class="title">💵 $<?= e(number_format($recebido_mes, 2, '.', ',')) ?> <?= e(t('USD recebidos este mês')) ?></div>
    </div>
  <?php endif; ?>

  <div class="section-label"><?= e(t('Acesso rápido')) ?></div>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/agenda.php">
    <div class="title">📅 <?= e(t('Minha agenda')) ?></div>
    <div class="desc"><?= e(t('Marcar entregas dos meus clientes')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/clientes.php">
    <div class="title">👥 <?= e(t('Meus clientes')) ?></div>
    <div class="desc"><?= e(t('Lista de quem eu atendo')) ?></div>
  </a>
  <a class="card" href="<?= e(APP_BASE_URL) ?>/meus_pagamentos.php">
    <div class="title">💵 <?= e(t('Meus pagamentos')) ?></div>
    <div class="desc"><?= e(t('A receber + histórico em USD')) ?></div>
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

  <?php if (!$cli):
    // Busca contato de um admin pra cliente conseguir pedir ajuda
    $admin_email = '';
    try {
        $admin_email = (string)$db->query("SELECT email FROM usuarios WHERE role IN ('sadmin','admin') AND ativo=1 AND email IS NOT NULL AND email != '' ORDER BY role DESC LIMIT 1")->fetchColumn();
    } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
  ?>
    <div class="card attention">
      <div class="title">⚠ <?= e(t('Conta sem empresa vinculada')) ?></div>
      <div class="desc"><?= e(t('Sua conta de cliente ainda não foi associada a uma empresa cadastrada — então não conseguimos exibir suas cobranças e entregas. O administrador precisa fazer essa ligação.')) ?></div>
      <?php if ($admin_email): ?>
        <a class="btn block mt-3" href="mailto:<?= e($admin_email) ?>?subject=<?= e(rawurlencode(t('Vincular minha conta a uma empresa'))) ?>&body=<?= e(rawurlencode(t('Olá, sou') . " " . $u['nome'] . " (" . t('usuário') . " #" . $u['id'] . " · email " . ($u['email'] ?? '') . ").\n\n" . t('Minha conta ainda não está vinculada a uma empresa no painel. Pode fazer essa associação?') . "\n\n" . t('Obrigado!'))) ?>">✉ <?= e(t('Pedir vínculo ao admin')) ?></a>
      <?php endif; ?>
      <a class="btn btn-ghost block mt-2" href="<?= e(APP_BASE_URL) ?>/logout.php"><?= e(t('Sair')) ?></a>
    </div>
  <?php else:
    // Previsão MENSAL do cliente: já pago + em aberto + em análise — só do mês corrente
    $competencia_cli = date('Y-m');
    $ja_pago_cli = 0.0;
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(p.valor_pago),0) FROM pagamentos_cliente p
                              JOIN cobrancas c ON c.id = p.cobranca_id
                              WHERE c.cliente_id = ? AND c.competencia_mes = ? AND COALESCE(p.pendente,0)=0");
        $stmt->execute([$cid, $competencia_cli]);
        $ja_pago_cli = (float)$stmt->fetchColumn();
    } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
    $em_aberto_mes = 0.0;
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(valor_total),0) FROM cobrancas
                              WHERE cliente_id = ? AND status='aberta' AND competencia_mes = ?");
        $stmt->execute([$cid, $competencia_cli]);
        $em_aberto_mes = (float)$stmt->fetchColumn();
    } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
    $em_analise_cli = 0.0;
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(valor_total),0) FROM cobrancas
                              WHERE cliente_id = ? AND status='em_analise' AND competencia_mes = ?");
        $stmt->execute([$cid, $competencia_cli]);
        $em_analise_cli = (float)$stmt->fetchColumn();
    } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
    // Assinaturas ativas mensais dele sem cobrança gerada ainda neste mês
    $prev_assin_cli = 0.0;
    try {
        $ini_mes_c = $competencia_cli . '-01';
        $fim_mes_c = date('Y-m-t', strtotime($ini_mes_c));
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(a.valor_cobrado), 0)
            FROM assinaturas a
            JOIN itens_catalogo i ON i.id = a.item_id
            WHERE a.cliente_id = ? AND a.status='ativa' AND i.tipo='mensal'
              AND a.iniciada_em <= ?
              AND (a.encerrada_em IS NULL OR a.encerrada_em >= ?)
              AND NOT EXISTS (
                SELECT 1 FROM cobranca_itens ci JOIN cobrancas cb ON cb.id = ci.cobranca_id
                WHERE ci.assinatura_id = a.id AND cb.competencia_mes = ?
              )");
        $stmt->execute([$cid, $fim_mes_c, $ini_mes_c, $competencia_cli]);
        $prev_assin_cli = (float)$stmt->fetchColumn();
    } catch (Throwable $e) { error_log('dashboard: ' . $e->getMessage()); }
    $prev_cli = $ja_pago_cli + $em_aberto_mes + $em_analise_cli + $prev_assin_cli;
  ?>
    <a class="card brand" href="<?= e(APP_BASE_URL) ?>/cobrancas.php" style="text-decoration:none;">
      <div class="title" style="color:var(--c-primary-2);">🔮 <?= e(t('Previsão de gastos')) ?> <span class="muted" style="font-weight:normal; font-size:12px;">(<?= e(date('M/y')) ?>)</span></div>
      <div class="desc" style="margin-top:6px;">
        <?php if ($prev_cli > 0): ?>
          <strong style="color:var(--txt-1);"><?= e(money_fmt($prev_cli, $cli['moeda'])) ?></strong>
        <?php else: ?>
          <strong class="muted"><?= e(t('Sem cobranças neste mês ainda')) ?></strong>
        <?php endif; ?>
      </div>
      <div class="desc muted" style="font-size:12px; margin-top:4px;"><?= e(t('já pago + em aberto + em análise · este mês')) ?></div>
    </a>

    <div class="grid-2">
      <div class="kpi"><div class="v"><?= e(money_fmt($em_aberto, $cli['moeda'])) ?></div><div class="l"><?= e(t('Em aberto')) ?></div></div>
      <div class="kpi <?= $vencidas?'':'' ?>" <?= $vencidas?'style="border-color:var(--c-danger);"':'' ?>><div class="v"><?= $vencidas ?></div><div class="l"><?= $vencidas?'<span style="color:var(--c-danger);">'.e(t('Vencidas')).'</span>':e(t('Vencidas')) ?></div></div>
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
    <div class="section-label"><?= e(t('Meus serviços contratados')) ?></div>
    <?php if (!$minhas_assin): ?>
      <div class="card"><div class="desc muted"><?= e(t('Nenhum serviço ativo. Fale com a Dite Ads pra contratar.')) ?></div></div>
    <?php else: foreach ($minhas_assin as $a): ?>
      <div class="card">
        <div class="spaced">
          <div>
            <div class="title" style="color:var(--txt-1);">
              <?= e($a['item_nome']) ?>
              <?php if ($a['e_pacote']): ?><span class="status status-ia"><?= e(t('pacote')) ?></span><?php endif; ?>
              <?php if ($a['variante']==='ia'): ?><span class="status status-destaque"><?= e(t('com IA')) ?></span><?php endif; ?>
            </div>
            <div class="sub muted">
              <?= e(['unico'=>t('único'),'mensal'=>t('mensal'),'por_unidade'=>t('por unidade')][$a['tipo']] ?? $a['tipo']) ?>
              · <?= e(t('ativo desde')) ?> <?= e(date('d/m/Y', strtotime($a['iniciada_em']))) ?>
              <?php if ($a['func_nome']): ?> · <?= e(t('com')) ?> <?= e($a['func_nome']) ?><?php endif; ?>
            </div>
          </div>
          <div class="money md"><?= e(money_fmt((float)$a['valor_cobrado'], $cli['moeda'])) ?></div>
        </div>
      </div>
    <?php endforeach; endif; ?>

    <div class="section-label"><?= e(t('Acesso rápido')) ?></div>
    <a class="card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">
      <div class="title">💳 <?= e(t('Minhas cobranças')) ?></div>
      <div class="desc"><?= e(t('Ver, anexar comprovante de pagamento')) ?></div>
    </a>
    <a class="card" href="<?= e(APP_BASE_URL) ?>/entregas.php">
      <div class="title">✅ <?= e(t('Minhas entregas')) ?></div>
      <div class="desc"><?= e(t('Acompanhar o que foi entregue no mês')) ?></div>
    </a>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
