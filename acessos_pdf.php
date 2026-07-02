<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_sadmin();
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title><?= e(t('Matriz de Acesso — Dite Ads')) ?></title>
<style>
  @media print {
    @page { size: A4 landscape; margin: 1cm; }
    .no-print { display: none !important; }
    body { font-size: 10px; }
    .matriz td, .matriz th { padding: 4px 6px; }
    h2 { page-break-after: avoid; }
    table { page-break-inside: avoid; }
  }
  body {
    font-family: -apple-system, "Inter", "Segoe UI", Roboto, Arial, sans-serif;
    max-width: 1200px;
    margin: 24px auto;
    padding: 0 20px;
    color: #1a1a1a;
    background: #fff;
  }
  .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #2563EB; padding-bottom: 16px; }
  .header h1 { margin: 0; font-size: 24px; color: #2563EB; }
  .header .sub { color: #666; font-size: 12px; margin-top: 4px; }

  h2 {
    margin-top: 24px;
    padding: 8px 12px;
    background: linear-gradient(135deg, #EC4899, #9333EA, #2563EB);
    color: #fff;
    border-radius: 6px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    page-break-after: avoid;
  }

  .matriz {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-bottom: 16px;
  }
  .matriz th, .matriz td {
    border: 1px solid #ddd;
    padding: 6px 8px;
    vertical-align: top;
  }
  .matriz thead th {
    background: #f5f5f5;
    text-align: center;
    font-weight: 700;
  }
  .matriz td:first-child { font-weight: 500; text-align: left; }
  .matriz td:not(:first-child) { text-align: center; }
  .yes { color: #10B981; font-weight: 700; font-size: 14px; }
  .no { color: #ccc; font-size: 14px; }
  .obs { font-size: 10px; color: #666; font-style: italic; }

  .legenda { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; font-size: 11px; margin-top: 16px; }
  .legenda strong { color: #2563EB; }

  .actions {
    position: sticky;
    top: 0;
    background: #fff;
    padding: 12px 0;
    text-align: center;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 16px;
    z-index: 100;
  }
  .actions button {
    background: #2563EB;
    color: #fff;
    border: 0;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    margin: 0 4px;
  }
  .actions button.secondary { background: #6b7280; }
  .footer { text-align: center; font-size: 10px; color: #999; margin-top: 32px; }

  /* Compatibilidade impressão */
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
</style>
</head>
<body>

<div class="actions no-print">
  <button onclick="window.print()">🖨 <?= e(t('Imprimir / Salvar como PDF')) ?></button>
  <button class="secondary" onclick="history.back()">← <?= e(t('Voltar')) ?></button>
  <p style="font-size:12px; color:#666; margin-top:8px;"><?= e(t('Clique em "Imprimir" → no diálogo do navegador, escolha')) ?> <strong>"<?= e(t('Salvar como PDF')) ?>"</strong> <?= e(t('como destino.')) ?></p>
</div>

<div class="header">
  <h1>🔐 <?= e(t('Matriz de Acesso — Sistema Dite Ads')) ?></h1>
  <div class="sub"><?= e(t('Controle e Gestão')) ?> · <?= e(t('gerado em')) ?> <?= e(date('d/m/Y H:i')) ?> · cont.diteads.com</div>
</div>

<h2>🏠 <?= e(t('Navegação base')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Dashboard (KPIs + Alertas + Previsão)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('Menu inferior (atalhos rápidos)')) ?></td><td><?= e(t('Início · Clientes · Catálogo · Perfil')) ?></td><td><?= e(t('Início · Clientes · Painel · Perfil')) ?></td><td><?= e(t('Início · Agenda · Pagto · Perfil')) ?></td><td><?= e(t('Início · Cobranças · Entregas · Perfil')) ?></td></tr>
    <tr><td><?= e(t('Página de Ajuda contextual')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('Busca global (Ctrl+K)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('Notificações in-app (🔔 sino)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('PWA instalável (mobile/desktop)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
  </tbody>
</table>

<h2>👤 <?= e(t('Perfil próprio (Minha conta)')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Editar nome + senha')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('Editar WiseTag/CPF/País')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Editar capacidade mensal declarada')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Toggle 🟢/🔴 "Aceitando novos clientes"')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Ativar/desativar 2FA (recuperação)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('Regerar 8 backup codes 2FA')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
  </tbody>
</table>

<h2>👥 <?= e(t('Pessoas (Clientes & Equipe)')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Listar/criar/editar clientes')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="obs"><?= e(t('só os dele (leitura)')) ?></td><td class="no">—</td></tr>
    <tr><td><?= e(t('Apagar cliente em cascata')) ?> <span class="obs"><?= e(t('(bloqueado se há pagamento confirmado)')) ?></span></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Funcionários (Equipe → Lista)')) ?></td><td class="yes">✓ <?= e(t('CRUD completo')) ?></td><td class="obs"><?= e(t('só funcionário comum')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Promover usuário a admin/sadmin')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Apagar usuário em cascata')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Configurar duplas (trabalha_com)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Convites (cadastro por link)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Capacidade da equipe (overview)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Editar capacidade de outros funcionários')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>📦 <?= e(t('Catálogo & Simulador')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Catálogo (ver/criar/editar itens)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Apagar item (se sem assinaturas vinculadas)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Recalcular preços (cotação)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Variante IA exige preço IA (validação)')) ?></td><td class="obs"><?= e(t('bloqueia save')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Simulador de preço')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Usar IA (Anthropic Claude) p/ sugestão')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Configurar API key Anthropic')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>📝 <?= e(t('Assinaturas')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Atribuir itens aos clientes')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Editar/pausar/cancelar assinatura')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Atribuir a funcionário marcado 🔴')) ?></td><td class="obs"><?= e(t('aviso + checkbox forçar')) ?></td><td class="obs"><?= e(t('aviso + checkbox forçar')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Apagar assinatura definitivamente')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Aviso de período mínimo de contrato')) ?></td><td class="yes">✓ <?= e(t('vê')) ?></td><td class="yes">✓ <?= e(t('vê')) ?></td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>💳 <?= e(t('Cobranças')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Ver lista de cobranças')) ?></td><td><?= e(t('todas')) ?></td><td><?= e(t('todas')) ?></td><td class="no">—</td><td><?= e(t('só dele')) ?></td></tr>
    <tr><td><?= e(t('Gerar cobrança (cron diário 5h)')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Gerar manual / avulsa')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Cobrança avulsa com vencimento passado')) ?></td><td class="obs"><?= e(t('bloqueado')) ?></td><td class="obs"><?= e(t('bloqueado')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Marcar como paga')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Cancelar cobrança paga')) ?></td><td class="obs"><?= e(t('bloqueado (estornar antes)')) ?></td><td class="obs"><?= e(t('bloqueado')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Cobrança zerada → cancela automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Aceitar/rejeitar comprovante')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Enviar comprovante (foto/PDF)')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('Baixar recibo PDF (só pagamentos confirmados)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td><?= e(t('se for dele')) ?></td></tr>
    <tr><td><?= e(t('Régua respeita saldo restante')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>🪝 <?= e(t('Wise — Webhook & Reconciliação')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Endpoint webhook (wise_webhook.php)')) ?></td><td class="obs"><?= e(t('público (validação RSA)')) ?></td><td class="obs"><?= e(t('público')) ?></td><td class="obs"><?= e(t('público')) ?></td><td class="obs"><?= e(t('público')) ?></td></tr>
    <tr><td><?= e(t('Painel de eventos + reconciliação (wise_eventos.php)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Confirmar/Rejeitar pagamento Wise pendente')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Configurar chave pública RSA da Wise')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Toggle validação assinatura (não funciona em produção)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Upload CSV manual da Wise (wise_sync.php)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Card laranja no dashboard com pendências')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>📅 <?= e(t('Agenda & Entregas')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Minha agenda (executar entregas)')) ?></td><td><?= e(t('se é executor')) ?></td><td><?= e(t('se é executor')) ?></td><td class="yes">✓</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Toggle "Minha / Agenda do parceiro" (em dupla)')) ?></td><td class="no">—</td><td class="no">—</td><td class="yes">✓</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Ver agenda de outro funcionário')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="obs"><?= e(t('só da dupla')) ?></td><td class="no">—</td></tr>
    <tr><td><?= e(t('Acompanhamento geral (todos)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Vista calendário consolidada')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Ver entregas próprias contratadas')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td><td class="yes">✓</td></tr>
  </tbody>
</table>

<h2>💵 <?= e(t('Pagamentos a Funcionários')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Fila de pagamentos (USD)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Aprovar/efetuar pagamento')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Apagar pagamento lançado')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Comprovante PDF (comprovante_funcionario.php)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td><?= e(t('próprios')) ?></td><td class="no">—</td></tr>
    <tr><td><?= e(t('Meus pagamentos (histórico)')) ?></td><td><?= e(t('se é executor')) ?></td><td><?= e(t('se é executor')) ?></td><td class="yes">✓</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>💰 <?= e(t('Finanças (Painel + Despesas + Distribuição + Pagto)')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Painel financeiro (3 abas)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Gráfico saúde (1m/3m/6m/1a/Tudo)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Despesas (CRUD)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Distribuição de lucro — ver')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Distribuição — pagar sócios')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Distribuição — trava de quota disponível')) ?></td><td class="obs"><?= e(t('bloqueia exceder')) ?></td><td class="obs"><?= e(t('bloqueia exceder')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Distribuição — apagar lançamento')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Formas de pagto (Zelle/Wise/QR)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Cotação USD (atualizar)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Exportar CSV (export.php)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>💬 <?= e(t('Comunicação (Régua + Templates)')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Régua de cobrança — etapas (CRUD)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Reordenar etapas com ↑↓')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Régua cron diário 6h (com janela 14 dias)')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Tarefas WhatsApp pendentes')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Templates de mensagem (CRUD)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Validação de variáveis ao salvar template')) ?></td><td class="obs"><?= e(t('avisa typos')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Instalar templates padrão (✨)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Apagar template')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Disparar WhatsApp (wa.me)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>🔔 <?= e(t('Alertas Operacionais')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Alerta POSTAGEM sem marcação (cron qua/sex 9h)')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="no">—</td><td><?= e(t('recebe email se pendente')) ?></td><td class="no">—</td></tr>
    <tr><td><?= e(t('Tela de gestão de alertas (alertas.php)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Dry-run sem envio')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Disparar manual fora do cron')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>💾 <?= e(t('Manutenção do Sistema')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Backup do banco (backups.php)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Backup automático diário 4h (gzip, 14 dias)')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Baixar backup (.sql.gz)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Limpeza mensal (limpeza.php)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Limpeza automática dia 1 às 3h')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Ver tamanho atual do banco (linhas + KB)')) ?></td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Matriz de Acesso PDF (esta página)')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>🔐 <?= e(t('Segurança & Auditoria')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;"><?= e(t('Item')) ?></th><th>👑 <?= e(t('Sadmin')) ?></th><th>⚙ <?= e(t('Admin')) ?></th><th>💼 <?= e(t('Funcionário')) ?></th><th>🤝 <?= e(t('Cliente')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><?= e(t('Login (apenas email + senha)')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('2FA não é exigido no login normal')) ?></td><td class="obs"><?= e(t('somente recuperação')) ?></td><td class="obs"><?= e(t('somente recuperação')) ?></td><td class="obs"><?= e(t('somente recuperação')) ?></td><td class="obs"><?= e(t('somente recuperação')) ?></td></tr>
    <tr><td><?= e(t('Esqueci senha — via email')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td><?= e(t('Esqueci senha — via 2FA (atalho)')) ?></td><td><?= e(t('se tem 2FA')) ?></td><td><?= e(t('se tem 2FA')) ?></td><td><?= e(t('se tem 2FA')) ?></td><td><?= e(t('se tem 2FA')) ?></td></tr>
    <tr><td><?= e(t('Reset de senha invalida sessões antigas')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td><td class="obs"><?= e(t('automático')) ?></td></tr>
    <tr><td><?= e(t('Auditoria (log de tudo) — auditoria.php')) ?></td><td class="yes">✓ <?= e(t('exclusivo')) ?></td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td><?= e(t('Configurar próprio 2FA + 8 backup codes')) ?></td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
  </tbody>
</table>

<h2>⏰ <?= e(t('Crons em Produção')) ?></h2>
<table class="matriz">
  <thead>
    <tr><th><?= e(t('Cron')) ?></th><th><?= e(t('Horário')) ?></th><th><?= e(t('O que faz')) ?></th></tr>
  </thead>
  <tbody>
    <tr><td><code>cron/limpeza_mensal.php</code></td><td>03:00 — <?= e(t('dia 1 do mês')) ?></td><td><?= e(t('Apaga logs antigos + OPTIMIZE TABLE')) ?></td></tr>
    <tr><td><code>cron/backup_db.php</code></td><td>04:00 — <?= e(t('todo dia')) ?></td><td><?= e(t('Dump gzip do banco (retém 14 dias)')) ?></td></tr>
    <tr><td><code>cron/gerar_cobrancas.php</code></td><td>05:00 — <?= e(t('todo dia')) ?></td><td><?= e(t('Gera cobranças mensais conforme dia_cobranca')) ?></td></tr>
    <tr><td><code>cron/regua_executar.php</code></td><td>06:00 — <?= e(t('todo dia')) ?></td><td><?= e(t('Dispara régua de cobrança (lembretes)')) ?></td></tr>
    <tr><td><code>cron/alerta_postagens.php</code></td><td>09:00 — <?= e(t('qua e sex')) ?></td><td><?= e(t('Email pra funcionário sem POSTAGEM marcada')) ?></td></tr>
  </tbody>
</table>

<h2>⚠ <?= e(t('Travas Globais (válidas para qualquer perfil)')) ?></h2>
<table class="matriz">
  <tbody>
    <tr><td><?= e(t('Apagar a si mesmo')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span></td></tr>
    <tr><td><?= e(t('Apagar o único Super Admin ativo')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span></td></tr>
    <tr><td><?= e(t('Apagar item do catálogo com assinaturas vinculadas')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span> <?= e(t('(desative em vez disso)')) ?></td></tr>
    <tr><td><?= e(t('Apagar cliente com pagamentos confirmados')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span> <?= e(t('(estorne ou desative)')) ?></td></tr>
    <tr><td><?= e(t('Cancelar cobrança com pagamento confirmado')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span> <?= e(t('(estorne primeiro)')) ?></td></tr>
    <tr><td><?= e(t('Cobrança avulsa com vencimento no passado')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span></td></tr>
    <tr><td><?= e(t('Item catálogo com variante IA sem preço IA')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span></td></tr>
    <tr><td><?= e(t('Distribuição > quota disponível')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO')) ?></span></td></tr>
    <tr><td><?= e(t('Webhook Wise: assinatura RSA obrigatória em produção')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('OBRIGATÓRIO')) ?></span></td></tr>
    <tr><td><?= e(t('Cron concorrente (mesmo script rodando 2x)')) ?></td><td colspan="4" style="text-align:center;"><span class="no">— <?= e(t('BLOQUEADO via flock')) ?></span></td></tr>
  </tbody>
</table>

<h2>🤖 <?= e(t('Comportamentos Automáticos')) ?></h2>
<table class="matriz">
  <tbody>
    <tr><td><?= e(t('Cobrança zerada (todos itens removidos) vira "cancelada"')) ?></td><td colspan="4" style="text-align:center;"><?= e(t('automático ao remover item')) ?></td></tr>
    <tr><td><?= e(t('Régua não dispara se cobrança tem saldo ≤ 0')) ?></td><td colspan="4" style="text-align:center;"><?= e(t('filtro na execução da régua')) ?></td></tr>
    <tr><td><?= e(t('Recibo PDF só lista pagamentos com pendente=0')) ?></td><td colspan="4" style="text-align:center;"><?= e(t('automático')) ?></td></tr>
    <tr><td><?= e(t('Webhook Wise marca pagamento como pendente=1 (admin confirma)')) ?></td><td colspan="4" style="text-align:center;"><?= e(t('automático')) ?></td></tr>
    <tr><td><?= e(t('Pagamento confirmado em cobrança paga → status preservado')) ?></td><td colspan="4" style="text-align:center;"><?= e(t('automático')) ?></td></tr>
    <tr><td><?= e(t('Timezone PHP↔MySQL alinhados no conectar (SET time_zone)')) ?></td><td colspan="4" style="text-align:center;"><?= e(t('automático')) ?></td></tr>
  </tbody>
</table>

<div class="legenda">
  <strong><?= e(t('Legenda:')) ?></strong> <?= e(t('✓ = acesso total · — = sem acesso · "exclusivo" = só este perfil tem · "automático" = via cron ou trigger · "se é executor" = quando admin/sadmin também trabalha como funcionário · "se tem 2FA" = somente se o usuário tem 2FA ativo no perfil · "público" = endpoint sem autenticação (mas com outra proteção: assinatura RSA, lock, etc).')) ?>
</div>

<div class="footer">
  Dite Ads — <?= e(t('Controle e Gestão')) ?> · <?= e(t('gerado por')) ?> <?= e($u['nome']) ?> <?= e(t('em')) ?> <?= e(date('d/m/Y H:i')) ?>
</div>

<script>
  // Foco no botão imprimir
  document.querySelector('button')?.focus();
</script>

</body>
</html>
