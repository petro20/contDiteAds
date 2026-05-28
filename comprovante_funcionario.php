<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/pagamentos.php';
$u = require_login();
$db = db();

$pid = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT p.*, u.nome AS func_nome, u.email, u.wisetag, u.cpf, u.pais FROM pagamentos_funcionario p JOIN usuarios u ON u.id = p.funcionario_id WHERE p.id = ?');
$stmt->execute([$pid]);
$pag = $stmt->fetch();
if (!$pag) { http_response_code(404); exit('Pagamento não encontrado.'); }

// Auth: admin/sadmin ou o próprio funcionário
if (!is_admin() && (int)$u['id'] !== (int)$pag['funcionario_id']) {
    http_response_code(403); exit('Acesso negado.');
}

$detalhes = detalhes_pagamento_funcionario($db, $pid);

// Layout próprio (não usa header/footer porque é página de impressão)
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Comprovante de pagamento #<?= (int)$pid ?> — Dite Ads</title>
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
</style>
</head>
<body>
  <div class="no-print" style="text-align:right; margin-bottom:16px;">
    <a href="javascript:window.print()" class="print-btn">🖨️ Imprimir / Salvar PDF</a>
  </div>

  <div class="header">
    <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" onerror="this.style.display='none'">
    <h1>Comprovante de pagamento</h1>
    <div class="sub">Dite Ads · #<?= (int)$pid ?> · <?= e(date('d/m/Y', strtotime($pag['data_pagamento']))) ?></div>
  </div>

  <div class="box">
    <div class="grid">
      <div><div class="l">Funcionário</div><div class="v"><?= e($pag['func_nome']) ?></div></div>
      <div><div class="l">Email</div><div class="v"><?= e($pag['email']) ?></div></div>
      <?php if ($pag['wisetag']): ?><div><div class="l">WiseTag</div><div class="v"><?= e($pag['wisetag']) ?></div></div><?php endif; ?>
      <?php if ($pag['pais']): ?><div><div class="l">País</div><div class="v"><?= e($pag['pais']) ?></div></div><?php endif; ?>
      <?php if ($pag['cpf']): ?><div><div class="l">CPF</div><div class="v"><?= e($pag['cpf']) ?></div></div><?php endif; ?>
      <div><div class="l">Data do pagamento</div><div class="v"><?= e(date('d/m/Y', strtotime($pag['data_pagamento']))) ?></div></div>
    </div>
  </div>

  <table>
    <thead><tr><th>Cliente</th><th>Serviço</th><th>Comp.</th><th style="text-align:right;">Qtd</th><th style="text-align:right;">Valor un.</th><th style="text-align:right;">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($detalhes as $d): ?>
      <tr>
        <td><?= e($d['nome_empresa'] ?? '—') ?></td>
        <td><?= e($d['descricao']) ?></td>
        <td><?= e($d['competencia_mes'] ?? '—') ?></td>
        <td style="text-align:right;"><?= (int)$d['quantidade'] ?></td>
        <td style="text-align:right;">$<?= number_format((float)$d['valor_unitario_usd'], 2, '.', ',') ?></td>
        <td style="text-align:right; font-weight:600;">$<?= number_format((float)$d['subtotal_usd'], 2, '.', ',') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="total">Total: $<?= number_format((float)$pag['valor_usd'], 2, '.', ',') ?> USD</div>

  <div class="footer">
    Comprovante gerado automaticamente pelo sistema Dite Ads em <?= e(date('d/m/Y H:i')) ?>.<br>
    Pagamento via Wise para o WiseTag/conta do funcionário.
  </div>
</body>
</html>
