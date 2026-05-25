<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
$u = require_login();
$db = db();

$cid = (int)($_GET['cobranca'] ?? 0);
$stmt = $db->prepare('SELECT c.*, cl.nome_empresa, cl.nome_contato, cl.documento, cl.email AS cli_email, cl.endereco, cl.telefone FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
$stmt->execute([$cid]);
$cob = $stmt->fetch();
if (!$cob) { http_response_code(404); exit('Cobrança não encontrada.'); }

// Auth: admin OU cliente da própria cobrança
$autorizado = is_admin() || ($u['role'] === 'cliente' && (int)$u['cliente_id'] === (int)$cob['cliente_id']);
if (!$autorizado) { http_response_code(403); exit('Acesso negado.'); }

$stmt = $db->prepare('SELECT * FROM cobranca_itens WHERE cobranca_id = ? ORDER BY id');
$stmt->execute([$cid]);
$itens = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM pagamentos_cliente WHERE cobranca_id = ? ORDER BY data_pagamento');
$stmt->execute([$cid]);
$pagamentos = $stmt->fetchAll();
$total_pago = (float)array_sum(array_column($pagamentos, 'valor_pago'));
$saldo = max((float)$cob['valor_total'] - $total_pago, 0);
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Recibo #<?= (int)$cid ?> — Dite Ads</title>
<style>
  @media print { @page { margin: 1.5cm; } .no-print { display: none !important; } }
  body { font-family: -apple-system, "Inter", "Segoe UI", Roboto, Arial, sans-serif; max-width: 720px; margin: 32px auto; padding: 0 16px; color: #1a1a1a; }
  .header { text-align: center; margin-bottom: 32px; }
  .header img { max-width: 140px; }
  .header h1 { font-size: 22px; margin: 16px 0 4px; }
  .header .sub { color: #666; font-size: 13px; }
  .box { border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .grid .l { color: #666; font-size: 11px; text-transform: uppercase; }
  .grid .v { font-weight: 600; font-size: 14px; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
  th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
  th { background: #f7f7f8; font-size: 11px; text-transform: uppercase; color: #666; }
  .total { font-size: 20px; font-weight: 700; text-align: right; padding: 16px; background: #f1f5f9; border-radius: 8px; margin-top: 16px; }
  .footer { margin-top: 48px; font-size: 11px; color: #999; text-align: center; }
  .print-btn { display: inline-block; padding: 12px 24px; background: #2563EB; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; }
  .status-pill { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
  .status-paga { background: #d1fae5; color: #065f46; }
  .status-aberta { background: #fef3c7; color: #92400e; }
  .status-cancelada { background: #e5e7eb; color: #4b5563; }
</style>
</head>
<body>
  <div class="no-print" style="text-align:right; margin-bottom:16px;">
    <a href="javascript:window.print()" class="print-btn">🖨️ Imprimir / Salvar PDF</a>
  </div>

  <div class="header">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
    <h1>Recibo de cobrança</h1>
    <div class="sub">Dite Ads · Cobrança #<?= (int)$cob['id'] ?> · Competência <?= e($cob['competencia_mes']) ?></div>
    <div class="sub" style="margin-top:8px;"><span class="status-pill status-<?= e($cob['status']) ?>"><?= e($cob['status']) ?></span></div>
  </div>

  <div class="box">
    <div class="grid">
      <div><div class="l">Cliente</div><div class="v"><?= e($cob['nome_empresa']) ?></div></div>
      <?php if ($cob['nome_contato']): ?><div><div class="l">Contato</div><div class="v"><?= e($cob['nome_contato']) ?></div></div><?php endif; ?>
      <?php if ($cob['documento']): ?><div><div class="l">Documento</div><div class="v"><?= e($cob['documento']) ?></div></div><?php endif; ?>
      <?php if ($cob['cli_email']): ?><div><div class="l">Email</div><div class="v"><?= e($cob['cli_email']) ?></div></div><?php endif; ?>
      <?php if ($cob['telefone']): ?><div><div class="l">Telefone</div><div class="v"><?= e($cob['telefone']) ?></div></div><?php endif; ?>
      <?php if ($cob['endereco']): ?><div><div class="l">Endereço</div><div class="v"><?= e($cob['endereco']) ?></div></div><?php endif; ?>
      <div><div class="l">Vencimento</div><div class="v"><?= e(date('d/m/Y', strtotime($cob['vencimento']))) ?></div></div>
      <div><div class="l">Moeda</div><div class="v"><?= e($cob['moeda']) ?></div></div>
    </div>
  </div>

  <table>
    <thead><tr><th>Descrição</th><th style="text-align:right;">Qtd</th><th style="text-align:right;">Valor un.</th><th style="text-align:right;">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($itens as $it): ?>
      <tr>
        <td><?= e($it['descricao']) ?></td>
        <td style="text-align:right;"><?= (int)$it['quantidade'] ?></td>
        <td style="text-align:right;"><?= e(money_fmt((float)$it['valor_unitario'], $cob['moeda'])) ?></td>
        <td style="text-align:right; font-weight:600;"><?= e(money_fmt((float)$it['subtotal'], $cob['moeda'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="total">Total: <?= e(money_fmt((float)$cob['valor_total'], $cob['moeda'])) ?></div>

  <?php if ($pagamentos): ?>
  <h3 style="margin-top:32px; font-size:14px; text-transform:uppercase; color:#666;">Pagamentos recebidos</h3>
  <table>
    <thead><tr><th>Data</th><th>Método</th><th>Observação</th><th style="text-align:right;">Valor</th></tr></thead>
    <tbody>
    <?php foreach ($pagamentos as $p): ?>
      <tr>
        <td><?= e(date('d/m/Y', strtotime($p['data_pagamento']))) ?></td>
        <td><?= e($p['metodo'] ?? '—') ?></td>
        <td><?= e($p['observacao'] ?? '') ?></td>
        <td style="text-align:right; font-weight:600;"><?= e(money_fmt((float)$p['valor_pago'], $cob['moeda'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="text-align:right; padding:12px; margin-top:8px; font-size:13px;">
    Total pago: <strong><?= e(money_fmt($total_pago, $cob['moeda'])) ?></strong>
    <?php if ($saldo > 0): ?><br>Saldo: <strong style="color:#dc2626;"><?= e(money_fmt($saldo, $cob['moeda'])) ?></strong><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="footer">
    Recibo gerado pelo sistema Dite Ads em <?= e(date('d/m/Y H:i')) ?>.
  </div>
</body>
</html>
