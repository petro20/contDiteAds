<?php
require_once __DIR__ . '/includes/auth.php';
$u = require_sadmin();
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Matriz de Acesso — Dite Ads</title>
<style>
  @media print {
    @page { size: A4 landscape; margin: 1cm; }
    .no-print { display: none !important; }
    body { font-size: 10px; }
    .matriz td, .matriz th { padding: 4px 6px; }
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
  <button onclick="window.print()">🖨 Imprimir / Salvar como PDF</button>
  <button class="secondary" onclick="history.back()">← Voltar</button>
  <p style="font-size:12px; color:#666; margin-top:8px;">Clique em "Imprimir" → no diálogo do navegador, escolha <strong>"Salvar como PDF"</strong> como destino.</p>
</div>

<div class="header">
  <h1>🔐 Matriz de Acesso — Sistema Dite Ads</h1>
  <div class="sub">Controle e Gestão · gerado em <?= e(date('d/m/Y H:i')) ?> · cont.diteads.com</div>
</div>

<h2>🏠 Navegação base</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Dashboard (KPIs + Alertas + Previsão)</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td>Menu inferior (atalhos rápidos)</td><td>Início · Clientes · Catálogo · Perfil</td><td>Início · Clientes · Painel · Perfil</td><td>Início · Agenda · Pagto · Perfil</td><td>Início · Cobranças · Entregas · Perfil</td></tr>
    <tr><td>Página de Ajuda contextual</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
    <tr><td>Perfil próprio + 2FA</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
  </tbody>
</table>

<h2>👥 Pessoas (Clientes & Equipe)</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Listar/criar/editar clientes</td><td class="yes">✓</td><td class="yes">✓</td><td class="obs">só os dele (leitura)</td><td class="no">—</td></tr>
    <tr><td>Apagar cliente em cascata</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Funcionários (Equipe → Lista)</td><td class="yes">✓ CRUD completo</td><td class="obs">só funcionário comum</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Promover usuário a admin/sadmin</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Apagar usuário em cascata</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Configurar duplas (trabalha_com)</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Convites (cadastro por link)</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Capacidade da equipe</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>📦 Catálogo & Simulador</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Catálogo (ver/criar/editar itens)</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Apagar item (se sem assinaturas vinculadas)</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Recalcular preços (cotação)</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Simulador de preço</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Usar IA (Anthropic Claude) p/ sugestão</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Configurar API key Anthropic</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>📝 Assinaturas</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Atribuir itens aos clientes</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Editar/pausar/cancelar assinatura</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Apagar assinatura definitivamente</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Aviso de período mínimo de contrato</td><td class="yes">✓ vê</td><td class="yes">✓ vê</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>💳 Cobranças</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Ver lista de cobranças</td><td>todas</td><td>todas</td><td class="no">—</td><td>só dele</td></tr>
    <tr><td>Gerar cobrança (cron diário 5h)</td><td class="obs">automático</td><td class="obs">automático</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Gerar manual / avulsa</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Marcar como paga</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Aceitar/rejeitar comprovante</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Enviar comprovante (foto/PDF)</td><td class="no">—</td><td class="no">—</td><td class="no">—</td><td class="yes">✓</td></tr>
    <tr><td>Baixar recibo PDF</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td>se for dele</td></tr>
  </tbody>
</table>

<h2>📅 Agenda & Entregas</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Minha agenda (executar entregas)</td><td>se é executor</td><td>se é executor</td><td class="yes">✓</td><td class="no">—</td></tr>
    <tr><td>Ver agenda de outro funcionário</td><td class="yes">✓</td><td class="yes">✓</td><td class="obs">só da dupla</td><td class="no">—</td></tr>
    <tr><td>Acompanhamento geral (todos)</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Vista calendário consolidada</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Ver entregas próprias contratadas</td><td class="no">—</td><td class="no">—</td><td class="no">—</td><td class="yes">✓</td></tr>
  </tbody>
</table>

<h2>💵 Pagamentos a Funcionários</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Fila de pagamentos (USD)</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Aprovar/efetuar pagamento</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Apagar pagamento lançado</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Comprovante PDF</td><td class="yes">✓</td><td class="yes">✓</td><td>próprios</td><td class="no">—</td></tr>
    <tr><td>Meus pagamentos (histórico)</td><td>se é executor</td><td>se é executor</td><td class="yes">✓</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>💰 Finanças (Painel + Despesas + Distribuição + Pagto)</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Painel financeiro (3 abas)</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Gráfico saúde (1m/3m/6m/1a/Tudo)</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Despesas (CRUD)</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Distribuição de lucro — ver</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Distribuição — pagar sócios</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Distribuição — apagar lançamento</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Formas de pagto (Zelle/Wise/QR)</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Cotação USD (atualizar)</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>💬 Comunicação (Régua + Templates)</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Régua de cobrança — etapas (CRUD)</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Reordenar etapas com ↑↓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Tarefas WhatsApp pendentes</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Templates de mensagem (CRUD)</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Instalar templates padrão (✨)</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Apagar template</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>Disparar WhatsApp (wa.me)</td><td class="yes">✓</td><td class="yes">✓</td><td class="no">—</td><td class="no">—</td></tr>
  </tbody>
</table>

<h2>🔐 Admin / Segurança</h2>
<table class="matriz">
  <thead>
    <tr><th style="width:30%;">Item</th><th>👑 Sadmin</th><th>⚙ Admin</th><th>💼 Funcionário</th><th>🤝 Cliente</th></tr>
  </thead>
  <tbody>
    <tr><td>Auditoria (log de tudo)</td><td class="yes">✓ exclusivo</td><td class="no">—</td><td class="no">—</td><td class="no">—</td></tr>
    <tr><td>2FA — configurar próprio</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
  </tbody>
</table>

<h2>⚠ Travas Globais (válidas para qualquer perfil)</h2>
<table class="matriz">
  <tbody>
    <tr><td>Apagar a si mesmo</td><td colspan="4" style="text-align:center;"><span class="no">— BLOQUEADO</span></td></tr>
    <tr><td>Apagar o único Super Admin ativo</td><td colspan="4" style="text-align:center;"><span class="no">— BLOQUEADO</span></td></tr>
    <tr><td>Apagar item do catálogo com assinaturas vinculadas</td><td colspan="4" style="text-align:center;"><span class="no">— BLOQUEADO</span> (desative em vez disso)</td></tr>
  </tbody>
</table>

<div class="legenda">
  <strong>Legenda:</strong> ✓ = acesso total · — = sem acesso · "exclusivo" = só este perfil tem ·
  "automático" = via cron · "se é executor" = quando admin/sadmin também trabalha como funcionário.
</div>

<div class="footer">
  Dite Ads — Controle e Gestão · gerado por <?= e($u['nome']) ?> em <?= e(date('d/m/Y H:i')) ?>
</div>

<script>
  // Foco no botão imprimir
  document.querySelector('button')?.focus();
</script>

</body>
</html>
