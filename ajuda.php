<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
$u = require_login();

$page = 'Ajuda';
$nav_active = '';
$show_back = true;
$back_to = APP_BASE_URL . '/perfil.php';
require __DIR__ . '/includes/header.php';

$role = $u['role'];
$role_label = [
    'sadmin'      => 'Super Admin',
    'admin'       => 'Admin',
    'funcionario' => 'Funcionário',
    'cliente'     => 'Cliente',
][$role] ?? $role;
?>
<h1 class="page-title">Minha conta</h1>
<?php render_group_tabs('conta', 'ajuda'); ?>
<h2>Como usar o sistema</h2>
<p class="muted">Guia para o perfil <strong><?= e($role_label) ?></strong>. Olá, <?= e($u['nome']) ?>!</p>

<?php if ($role === 'sadmin'): ?>

  <div class="card brand">
    <div class="title">👑 Você é Super Admin</div>
    <div class="desc">Acesso total: equipe, catálogo, finanças, distribuição, comunicação, IA e segurança. Único que pode apagar dados em cascata, promover usuários e configurar APIs.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão do mês</strong> — recebimento + pagamento + lucro previstos, atualizam conforme assinaturas e despesas.</p>
    <p><strong>Alertas automáticos</strong>: comprovantes em análise · cobranças vencidas · funcionários sem WiseTag · sobrecarga · distribuição pendente.</p>
    <p><strong>KPIs clicáveis:</strong> Recebido · A receber · A pagar funcionários · Lucro do mês.</p>
    <p><strong>📋 Link do sistema:</strong> botão Copiar pra usar em mensagens (variável <code>{link_sistema}</code> nos templates).</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>+ Novo</strong> direto ou <strong>✉️ Convidar</strong> (link por email).</p>
    <p><strong>Mudou moeda do cliente?</strong> Sistema recalcula automaticamente o valor de todas as assinaturas ativas dele usando a cotação USD do dia.</p>
    <p><strong>⚠ Apagar em cascata:</strong> deleta cliente + cobranças + assinaturas + entregas + login. Sem volta — prefira desativar pra preservar histórico.</p>
  </div>

  <h2>📦 Catálogo (form em 5 passos)</h2>
  <div class="card">
    <p><strong>USD = moeda mestre.</strong> Você só preenche USD; BRL/EUR são calculados via cotação do dia (arredondado pra cima, sem centavos).</p>
    <p><strong>Form organizado em 5 passos:</strong></p>
    <ol style="padding-left:20px; color:var(--txt-2);">
      <li><strong>1️⃣ Identificação</strong> — nome + descrição + botão <strong>✨ Preencher/Refinar com IA</strong> (gera tudo a partir de poucas palavras)</li>
      <li><strong>2️⃣ Tipo de cobrança</strong> — único/mensal/por_unidade + período mínimo (se mensal)</li>
      <li><strong>3️⃣ Preço</strong> — USD com preview BRL/EUR ao vivo, opção "preço a negociar" e "variante com IA"</li>
      <li><strong>4️⃣ Responsabilidades</strong> — o que a agência entrega, o funcionário faz, o cliente fornece (a IA preenche)</li>
      <li><strong>5️⃣ Opções</strong> — item ativo, é pacote</li>
    </ol>
    <p><strong>📊 Simulador de preço:</strong> botão no topo do catálogo — descreva o serviço, IA sugere custos + margem + responsabilidades. Salve simulações pra editar depois.</p>
    <p><strong>🔄 Recalcular preços:</strong> atualiza BRL/EUR de TODOS os itens com cotação atual.</p>
    <p><strong>⚠ Apagar item:</strong> só funciona se NÃO houver assinaturas vinculadas. Caso contrário, desative o item.</p>
  </div>

  <h2>📊 Simulador de preço</h2>
  <div class="card">
    <p>Acessível em <strong>📦 Catálogo → 📊 Simulador de preço</strong>.</p>
    <p><strong>Funcionalidades:</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>✨ IA</strong> — descreve serviço em 1 linha, IA preenche tudo (custos, margem, responsabilidades, período)</li>
      <li><strong>🔍 Pesquisa preço</strong> — botão em cada linha de custo abre Google buscando o preço atual</li>
      <li><strong>💼 Atalhos</strong> — botões pra adicionar softwares populares (Canva, Adobe, ChatGPT, etc.)</li>
      <li><strong>Rateio</strong> — cada custo tem "valor ÷ dividir = por unidade" (ex: \$174 ÷ 36 vídeos)</li>
      <li><strong>💾 Salvar simulação</strong> — fica salva pra editar depois (lista com todas no topo)</li>
      <li><strong>✓ Criar item no catálogo</strong> — leva tudo preenchido</li>
    </ul>
    <p><strong>Pré-requisito IA:</strong> chave Anthropic em <em>Finanças → Pagamentos → 🤖 IA</em>.</p>
  </div>

  <h2>🧑‍💼 Equipe (3 abas)</h2>
  <div class="card">
    <p><strong>👥 Lista:</strong> cadastra usuários (funcionário/admin/sadmin). Define WiseTag, capacidade mensal, valor USD por item.</p>
    <p><strong>👥 Duplas:</strong> overview "Duplas configuradas" no topo + badge "👥 dupla com X" em cada card. Pra criar: edita um funcionário → "Trabalha em dupla com". Ambos veem a mesma agenda, mas pagamento vai todo pro principal.</p>
    <p><strong>📊 Capacidade:</strong> overview de quanto cada um absorve por mês vs ocupação atual.</p>
    <p><strong>💵 Pagamentos:</strong> fila de itens entregues prontos pra pagar (USD). Email automático com PDF do comprovante.</p>
    <p><strong>⚠ Apagar em cascata:</strong> deleta pagamentos recebidos, entregas, capacidades. Cobranças/despesas reatribuídas a você. Trava: não apaga a si mesmo nem o último sadmin.</p>
  </div>

  <h2>💰 Finanças (3 abas)</h2>
  <div class="card">
    <p><strong>💸 Despesas:</strong> ferramentas, software, marketing — recorrência única/mensal/anual.</p>
    <p><strong>💎 Distribuição:</strong> cada sócio = 1 quota; empresa = 1 quota. Botão Pagar registra cada lançamento. Sadmin pode apagar lançamentos errados.</p>
    <p><strong>💳 Pagamentos:</strong> esta aba concentra:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>💱 Cotação USD:</strong> USD→BRL e USD→EUR atual + botão "Atualizar agora" (AwesomeAPI, cache diário)</li>
      <li><strong>💜 Zelle:</strong> email + QR Code (upload)</li>
      <li><strong>🌍 Wise:</strong> link público</li>
      <li><strong>📝 Instruções extras</strong></li>
      <li><strong>🤖 API key da Anthropic</strong> (pra IA do simulador e do catálogo funcionar)</li>
    </ul>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p><strong>3 abas:</strong> Agenda · Por cliente · Por serviço.</p>
    <p><strong>Gráfico:</strong> 1m/3m/6m/1a/Tudo, 4 séries (Receita/Despesa/Pago sócios/Lucro).</p>
    <p><strong>Cards por moeda:</strong> Entradas − Saídas = Lucro líquido. Distribuição já paga + saldo final.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Geração automática:</strong> cron diário às 5h, 7 dias antes do vencimento.</p>
    <p><strong>Manual:</strong> "Marcar como paga" ou "Nova cobrança avulsa".</p>
    <p><strong>Detalhe (cliente vê):</strong> QR Zelle + email + link Wise com passo a passo, botão copiar.</p>
    <p><strong>Comprovante do cliente:</strong> vai pra "Em análise" → alerta no dashboard → aceita/rejeita.</p>
  </div>

  <h2>💬 Comunicação (3 abas)</h2>
  <div class="card">
    <p><strong>⏰ Etapas:</strong> régua automática com dias relativos ao vencimento:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>negativo</strong> = antes (ex: −3 = 3 dias antes)</li>
      <li><strong>zero</strong> = no dia</li>
      <li><strong>positivo</strong> = após vencer</li>
    </ul>
    <p>Botões ↑↓ pra reordenar etapas.</p>
    <p><strong>📤 Tarefas:</strong> fila WhatsApp pendente, botão "Abrir WhatsApp" com mensagem renderizada.</p>
    <p><strong>📝 Templates:</strong> CRUD de templates. <strong>✨ Instalar templates de pagamento</strong> cria templates padrão completos com Zelle + Wise + QR.</p>
  </div>

  <h2>🔐 Segurança e auditoria</h2>
  <div class="card">
    <p><strong>2FA:</strong> ativa em <em>Minha conta → 🔐 Segurança</em>.</p>
    <p><strong>🔍 Auditoria:</strong> histórico de tudo no sistema. Só sadmin acessa.</p>
  </div>

<?php elseif ($role === 'admin'): ?>

  <div class="card brand">
    <div class="title">⚙ Você é Admin</div>
    <div class="desc">Gerencia clientes, cobranças, equipe e painel financeiro. Recebe lucro como sócio. Configurações sensíveis (templates, formas de pagto, IA) são feitas pelo sadmin.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão do mês</strong> + alertas + KPIs financeiros.</p>
    <p><strong>📋 Link do sistema</strong> copiável pra enviar a clientes.</p>
  </div>

  <h2>👥 Clientes</h2>
  <div class="card">
    <p><strong>+ Novo</strong> ou <strong>Convidar</strong>. Detalhe do cliente tem assinaturas, cobranças, dados.</p>
    <p>Ao trocar moeda do cliente, valores das assinaturas se convertem automaticamente.</p>
  </div>

  <h2>📦 Catálogo</h2>
  <div class="card">
    <p>USD é moeda mestre — só preencha USD, BRL/EUR são calculados.</p>
    <p><strong>📊 Simulador de preço</strong> com IA pra te ajudar a precificar serviços novos.</p>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p>3 abas (Agenda · Por cliente · Por serviço), gráfico de saúde + cards por moeda com lucro líquido.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p>Geração automática 7 dias antes. Comprovante do cliente vai pra "Em análise" → aceita/rejeita. WhatsApp com template editável.</p>
  </div>

  <h2>🧑‍💼 Equipe</h2>
  <div class="card">
    <p><strong>💵 Pagamentos:</strong> fila pra liberar pagamento em USD aos funcionários. Email automático com PDF.</p>
  </div>

  <h2>💎 Distribuição de lucro</h2>
  <div class="card">
    <p>Sua quota = lucro do mês ÷ (nº sócios + 1). Botão Pagar registra recebimento.</p>
  </div>

  <h2>🔐 Segurança</h2>
  <div class="card">
    <p>Ative 2FA em <em>Minha conta → 🔐 Segurança</em>.</p>
  </div>

<?php elseif ($role === 'funcionario'): ?>

  <div class="card brand">
    <div class="title">💼 Você é Funcionário</div>
    <div class="desc">Executa entregas dos serviços na sua agenda. Recebe em USD via WiseTag.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão de recebimento:</strong> USD que vai receber este mês pelas assinaturas ativas.</p>
    <p><strong>KPIs:</strong> clientes que atende · serviços ativos · entregas do mês · a receber.</p>
  </div>

  <h2>📅 Agenda</h2>
  <div class="card">
    <p>Itens pra entregar no mês, agrupados por cliente. Checkbox em cada um — marque ao concluir.</p>
    <p>Indicador 🟢/🔴 mostra se está dentro da capacidade.</p>
    <p><strong>👥 Trabalha em dupla?</strong> Se você foi vinculado a alguém pelo admin, vai ver a agenda do principal aqui — pode marcar entregas, mas o pagamento vai todo pra ele (combinem como dividir off-platform).</p>
  </div>

  <h2>💵 Meus pagamentos</h2>
  <div class="card">
    <p>Histórico do que já recebeu + previsão das entregas. Cada pagamento tem PDF de comprovante.</p>
  </div>

  <h2>👤 Minha conta</h2>
  <div class="card">
    <p><strong>WiseTag:</strong> obrigatório pra receber.</p>
    <p><strong>Aceitando clientes:</strong> ative/desative quando estiver cheio.</p>
    <p><strong>🔐 2FA</strong> e <strong>❓ Ajuda</strong> nas abas Minha conta.</p>
  </div>

<?php else: // cliente ?>

  <div class="card brand">
    <div class="title">🤝 Bem-vindo cliente!</div>
    <div class="desc">Acompanha cobranças, envia comprovantes, vê entregas e o que contratou.</div>
  </div>

  <h2>🏠 Início (dashboard)</h2>
  <div class="card">
    <p><strong>🔮 Previsão de gastos:</strong> total deste mês (pago + aberto + análise + assinaturas).</p>
    <p><strong>Meus serviços contratados</strong> com quem executa cada um.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p><strong>Como pagar:</strong> abra a cobrança e veja:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>💜 Zelle:</strong> QR Code (escaneia no app do banco) + email pra copiar</li>
      <li><strong>🌍 Wise:</strong> botão "Abrir Wise" com valor</li>
    </ul>
    <p>Depois envie o <strong>comprovante</strong> pelo botão. Status vai pra "Em análise" e admin confirma.</p>
  </div>

  <h2>✅ Entregas</h2>
  <div class="card">
    <p>O que está sendo produzido pra você no mês (criativos, postagens, sites). Cada item mostra se já foi entregue.</p>
  </div>

  <h2>👤 Minha conta</h2>
  <div class="card">
    <p>Atualize nome e senha. Email é fixo.</p>
  </div>

  <h2>💬 Precisa de ajuda?</h2>
  <div class="card">
    <p>Email <a href="mailto:contact@diteads.com">contact@diteads.com</a> ou WhatsApp da Dite Ads.</p>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
