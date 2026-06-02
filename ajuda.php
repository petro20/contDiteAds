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
    <p><strong>⚠ Apagar em cascata:</strong> deleta cliente + cobranças + assinaturas + entregas + login. <strong>BLOQUEADO se houver pagamentos confirmados</strong> em cima (protege histórico financeiro e distribuição a sócios). Use "Desativar" nesses casos.</p>
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
    <p><strong>⚠ Variante IA:</strong> se marcar "tem variante com IA", o preço IA em USD vira obrigatório. Sistema bloqueia o save sem ele pra evitar assinaturas com valor zero.</p>
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
    <p><strong>👥 Lista:</strong> cadastra usuários (funcionário/admin/sadmin). Define WiseTag, capacidade mensal, valor USD por item. <strong>Funcionário também edita o próprio WiseTag/CPF/País/capacidade/aceitando clientes</strong> no perfil dele — você só precisa criar a conta.</p>
    <p><strong>👥 Duplas:</strong> overview "Duplas configuradas" no topo + badge "👥 dupla com X" em cada card. Pra criar: edita um funcionário → "Trabalha em dupla com". Ambos veem a mesma agenda, mas pagamento vai todo pro principal.</p>
    <p><strong>📊 Capacidade:</strong> overview de quanto cada um absorve por mês vs ocupação atual. Funcionário declara no próprio perfil.</p>
    <p><strong>🔴 Atribuição com aviso:</strong> ao tentar atribuir nova assinatura a funcionário marcado como "não aceitando clientes", sistema bloqueia e mostra checkbox "Sim, atribuir mesmo assim" pra confirmar exceção.</p>
    <p><strong>💵 Pagamentos:</strong> fila de itens entregues prontos pra pagar (USD). Email automático com PDF do comprovante.</p>
    <p><strong>⚠ Apagar em cascata:</strong> deleta pagamentos recebidos, entregas, capacidades. Cobranças/despesas reatribuídas a você. Trava: não apaga a si mesmo nem o último sadmin.</p>
  </div>

  <h2>📋 Acompanhamento geral (todos os funcionários)</h2>
  <div class="card">
    <p>Dashboard → <strong>📋 Acompanhamento geral</strong>. Visão consolidada do que cada funcionário e admin executa no mês.</p>
    <p><strong>3 vistas (botões no topo):</strong></p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>📋 Lista:</strong> resumo por pessoa (contagem de assinaturas/entregas/clientes)</li>
      <li><strong>📅 Por pessoa:</strong> expande cada um mostrando suas assinaturas + status de progresso</li>
      <li><strong>🗓 Calendário:</strong> grid mensal com todas as entregas de todos coloridas por funcionário</li>
    </ul>
    <p>Clica em qualquer card pra ir pra agenda detalhada daquela pessoa.</p>
  </div>

  <h2>🔐 Matriz de acesso (PDF)</h2>
  <div class="card">
    <p>Dashboard → <strong>🔐 Matriz de acesso (PDF)</strong>. Documento completo com quem pode o quê no sistema (16 seções: navegação, perfil, pessoas, catálogo, cobranças, Wise, agenda, finanças, comunicação, alertas, manutenção, segurança, crons, travas globais e comportamentos automáticos). Botão "🖨 Imprimir / Salvar como PDF" salva como arquivo. Útil pra compliance/onboarding/contador.</p>
  </div>

  <h2>⏰ Crons em produção (6 agendados)</h2>
  <div class="card">
    <p>Sistema roda sozinho com 6 tarefas agendadas no Hostinger:</p>
    <table style="width:100%; font-size:13px; border-collapse:collapse;">
      <thead><tr style="border-bottom:1px solid var(--border);">
        <th style="text-align:left; padding:6px;">Quando</th>
        <th style="text-align:left; padding:6px;">Script</th>
        <th style="text-align:left; padding:6px;">O quê</th>
      </tr></thead>
      <tbody>
        <tr><td style="padding:6px;">Dia 1 · 03:00</td><td><code>limpeza_mensal.php</code></td><td>Apaga logs antigos + OPTIMIZE TABLE</td></tr>
        <tr><td style="padding:6px;">Todo dia · 04:00</td><td><code>backup_db.php</code></td><td>Backup gzip (retém 14 dias)</td></tr>
        <tr><td style="padding:6px;">Todo dia · 05:00</td><td><code>gerar_cobrancas.php</code></td><td>Gera cobranças mensais</td></tr>
        <tr><td style="padding:6px;">Todo dia · 06:00</td><td><code>regua_executar.php</code></td><td>Régua de cobrança</td></tr>
        <tr><td style="padding:6px;">Qua + Sex · 09:00</td><td><code>alerta_postagens.php</code></td><td>Lembrete POSTAGEM</td></tr>
      </tbody>
    </table>
    <p class="hint">Todos os crons têm <code>flock</code> pra evitar execuções concorrentes. Audit_log registra cada execução.</p>
  </div>

  <h2>💰 Finanças (3 abas)</h2>
  <div class="card">
    <p><strong>💸 Despesas:</strong> ferramentas, software, marketing — recorrência única/mensal/anual.</p>
    <p><strong>💎 Distribuição:</strong> cada sócio = 1 quota; empresa = 1 quota. Botão Pagar registra cada lançamento. Sadmin pode apagar lançamentos errados. <strong>Trava:</strong> valor pago não pode exceder a quota disponível na competência (sistema mostra quota total / já pago / disponível).</p>
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
    <p><strong>Manual:</strong> "Marcar como paga" ou "Nova cobrança avulsa" (não aceita vencimento no passado).</p>
    <p><strong>Detalhe (cliente vê):</strong> QR Zelle + email + link Wise Quick Pay com passo a passo, botão copiar.</p>
    <p><strong>Comprovante do cliente:</strong> vai pra "Em análise" → alerta no dashboard → aceita/rejeita.</p>
    <p><strong>🪝 Pagamento via Wise:</strong> webhook detecta automaticamente. Vai como "pendente" pra você confirmar em <em>wise_eventos.php</em> ou pelo card laranja no dashboard.</p>
    <p><strong>Travas:</strong>
      <ul style="padding-left:20px; color:var(--txt-2); margin-top:4px;">
        <li>Cobrança paga não pode ser cancelada (estorne primeiro)</li>
        <li>Cobrança zerada (sem itens) vira "cancelada" automático</li>
        <li>Régua respeita saldo: se pagou parcial, não recobra o total</li>
      </ul>
    </p>
  </div>

  <h2>🪝 Wise — Webhook + Reconciliação</h2>
  <div class="card">
    <p>Em <em>Finanças → Pagamentos → 🪝 Webhook</em> (ou direto em <code>/wise_eventos.php</code>):</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>URL do webhook</strong> pra colar no painel Wise</li>
      <li><strong>Validação RSA SHA256</strong> ligada em produção (sempre obrigatória)</li>
      <li><strong>Chave pública da Wise</strong> salva no sistema</li>
      <li><strong>Lista de pagamentos pendentes</strong> com botão Confirmar/Rejeitar</li>
      <li><strong>Histórico dos últimos 100 eventos</strong></li>
    </ul>
    <p>Pagamentos chegam como <strong>pendentes</strong> propositalmente — você revisa antes de marcar como paga e liberar comissão.</p>
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
    <p><strong>2FA (meio de recuperação):</strong> ativa em <em>Minha conta → 🔐 Segurança</em>. <strong>NÃO é exigido no login normal</strong> — serve apenas como alternativa ao email pra recuperar acesso quando você esquece a senha (em <em>Esqueci minha senha → 🔐 Via 2FA</em>, entra direto com código do app).</p>
    <p><strong>🔑 Backup codes:</strong> ao ativar 2FA, sistema gera 8 códigos one-time-use. <strong>Salve em local seguro</strong> (impressão + gerenciador de senhas). Cada código serve como alternativa ao TOTP se você perder o celular. Você pode regerar a qualquer momento.</p>
    <p><strong>Reset de senha:</strong> ao trocar senha (via email ou após entrar pelo 2FA), todas as sessões em outros dispositivos são invalidadas automaticamente.</p>
    <p><strong>🔍 Auditoria:</strong> histórico de tudo no sistema. Só sadmin acessa.</p>
  </div>

  <h2>💾 Backup do banco</h2>
  <div class="card">
    <p>Em <code>/backups.php</code>: dump comprimido (gzip) do MySQL inteiro.</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Cron diário às 04:00</strong> (antes de gerar cobranças e régua)</li>
      <li><strong>Retenção:</strong> últimos 14 backups; mais antigos são apagados</li>
      <li><strong>Pasta protegida:</strong> <code>uploads/.backups/</code> com <code>.htaccess Require all denied</code> — só baixa via tela do admin</li>
      <li><strong>Botão "Gerar backup agora"</strong> pra forçar fora do cron</li>
      <li><strong>Restauração:</strong> baixa, descompacta (<code>gunzip</code>), importa no phpMyAdmin</li>
    </ul>
    <p class="hint" style="color:var(--c-attention);">💡 Recomendado: baixe manualmente 1x por mês pra um lugar fora da Hostinger (Drive, Dropbox, HD).</p>
  </div>

  <h2>🔔 Alertas automáticos pra funcionários</h2>
  <div class="card">
    <p><strong>Postagens sem marcação:</strong> cron quarta e sexta às 09:00 verifica funcionários com assinaturas de POSTAGEM ativas que não marcaram nenhuma entrega nessa semana.</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li>Critério: item do catálogo com 'POSTAGEM' no nome + 0 entregas com data_marcada entre seg e hoje</li>
      <li>Email vai pro funcionário direto com lista dos clientes pendentes + botão pra agenda</li>
      <li>Audit log registra cada execução</li>
      <li><strong>Testar / disparar manual:</strong> tela <code>/alertas.php</code> com botões "Dry-run" (só lista) e "Rodar agora" (envia emails)</li>
    </ul>
  </div>

  <h2>🧹 Limpeza mensal automática</h2>
  <div class="card">
    <p>Em <code>/limpeza.php</code>: cron mensal apaga registros antigos pra manter banco ágil + recupera espaço com OPTIMIZE TABLE.</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li><strong>Cron mensal dia 1 às 03:00</strong> (antes do backup das 04:00)</li>
      <li><strong>audit_log</strong> > 18 meses · <strong>wise_eventos</strong> processados > 6 meses · <strong>regua_eventos</strong> > 1 ano</li>
      <li><strong>senha_resets</strong> usados > 30 dias · <strong>totp_backup_codes</strong> usados > 90 dias · <strong>convites</strong> aceitos > 30 dias</li>
      <li><strong>Tabela com tamanho atual</strong> de cada tabela (linhas + KB) pra você ver o que tá crescendo</li>
      <li><strong>Botão "Rodar limpeza agora"</strong> pra forçar fora do cron</li>
    </ul>
    <p class="hint" style="color:var(--c-success);">✓ Dados financeiros (cobranças, pagamentos, distribuição) NUNCA são apagados.</p>
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
    <p><strong>Apagar:</strong> bloqueado se há pagamentos confirmados. Use "Desativar" pra preservar histórico.</p>
  </div>

  <h2>📦 Catálogo</h2>
  <div class="card">
    <p>USD é moeda mestre — só preencha USD, BRL/EUR são calculados.</p>
    <p><strong>📊 Simulador de preço</strong> com IA pra te ajudar a precificar serviços novos.</p>
    <p><strong>⚠ Variante IA:</strong> se marcar "tem variante com IA", o preço IA em USD vira obrigatório (sistema bloqueia o save sem ele).</p>
  </div>

  <h2>📊 Painel financeiro</h2>
  <div class="card">
    <p>3 abas (Agenda · Por cliente · Por serviço), gráfico de saúde + cards por moeda com lucro líquido.</p>
  </div>

  <h2>💳 Cobranças</h2>
  <div class="card">
    <p>Geração automática 7 dias antes. Comprovante do cliente vai pra "Em análise" → aceita/rejeita. WhatsApp com template editável.</p>
    <p><strong>🪝 Pagamentos via Wise:</strong> webhook detecta automaticamente e marca como pendente. Reconcilia no card laranja do dashboard ou em <code>/wise_eventos.php</code> (Confirmar/Rejeitar).</p>
    <p><strong>Cobrança paga</strong> não pode ser cancelada (estorne primeiro). <strong>Cobrança avulsa</strong> não aceita vencimento no passado.</p>
  </div>

  <h2>🧑‍💼 Equipe</h2>
  <div class="card">
    <p><strong>💵 Pagamentos:</strong> fila pra liberar pagamento em USD aos funcionários. Email automático com PDF.</p>
    <p><strong>Funcionário se autorregula:</strong> ele edita capacidade mensal e flag "aceitando novos clientes" no próprio perfil. Ao tentar atribuir cliente a alguém marcado 🔴, sistema bloqueia e pede confirmação explícita.</p>
  </div>

  <h2>📋 Acompanhamento geral</h2>
  <div class="card">
    <p>Visão consolidada do que cada funcionário está executando. 3 vistas: Lista · Por pessoa · 🗓 Calendário (grid mensal colorido por funcionário).</p>
  </div>

  <h2>🔔 Alertas automáticos pra funcionários</h2>
  <div class="card">
    <p>Sistema envia <strong>email automático</strong> pros funcionários toda quarta e sexta às 09:00 quando eles têm assinaturas de POSTAGEM ativas sem nenhuma entrega marcada na semana. <em>Você não precisa fazer nada</em> — o cron cuida disso. Audit_log registra cada execução.</p>
    <p>Se quiser disparar fora dos horários (ex: terça-feira), pode pedir pro sadmin rodar pela tela <code>/alertas.php</code>.</p>
  </div>

  <h2>💎 Distribuição de lucro</h2>
  <div class="card">
    <p>Sua quota = lucro do mês ÷ (nº sócios + 1). Botão Pagar registra recebimento.</p>
    <p><strong>Trava:</strong> sistema bloqueia valor maior que a quota disponível na competência (mostra quota total / já pago / disponível no erro).</p>
  </div>

  <h2>🔐 Segurança</h2>
  <div class="card">
    <p><strong>2FA (recuperação):</strong> ative em <em>Minha conta → 🔐 Segurança</em>. NÃO é pedido no login normal — serve como alternativa ao email se você esquecer a senha (use "Esqueci minha senha → Via 2FA").</p>
    <p><strong>🔑 Backup codes:</strong> ao ativar 2FA o sistema gera 8 códigos one-time-use. Salve em local seguro pra usar caso perca o celular.</p>
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
    <p>Indicador 🟢/🔴 mostra se está dentro da capacidade declarada por você no perfil.</p>
    <p><strong>👥 Trabalha em dupla?</strong> Se você foi vinculado a alguém pelo admin, vê dois botões no topo:
      <ul style="padding-left:20px; color:var(--txt-2);">
        <li><strong>Minha agenda</strong> — clientes atribuídos diretamente a você</li>
        <li><strong>Agenda do [parceiro]</strong> — agenda da dupla (pagamento vai todo pro principal; combinem off-platform)</li>
      </ul>
    </p>
  </div>

  <h2>📊 Painel</h2>
  <div class="card">
    <p>Em <em>Painel</em> você vê suas entregas dos últimos meses, valor recebido e previsão.</p>
  </div>

  <h2>📧 Lembretes automáticos por email</h2>
  <div class="card">
    <p>Toda <strong>quarta e sexta às 09:00</strong>, o sistema verifica se você tem assinaturas de <strong>POSTAGEM ativas sem nenhuma entrega marcada nessa semana</strong>. Se houver, você recebe um email com:</p>
    <ul style="padding-left:20px; color:var(--txt-2);">
      <li>Lista dos clientes pendentes</li>
      <li>Botão direto pra abrir sua agenda</li>
    </ul>
    <p class="hint">Pra não receber o email: marque suas entregas na agenda durante a semana (mesmo que parcial). Se você já entregou tudo e marcou, o sistema sabe que tá em dia e não envia nada.</p>
  </div>

  <h2>🧭 Navegação</h2>
  <div class="card">
    <p><strong>← Voltar:</strong> em qualquer tela (exceto dashboard), o botão ← no canto superior esquerdo volta pra tela anterior (usa o histórico do navegador). Funciona como o "voltar" do navegador.</p>
    <p><strong>🏠 Logo:</strong> no dashboard, o logo aparece no canto. Em outras telas, clica em "Início" no menu inferior pra voltar pro dashboard.</p>
  </div>

  <h2>💵 Meus pagamentos</h2>
  <div class="card">
    <p>Histórico do que já recebeu + previsão das entregas. Cada pagamento tem PDF de comprovante.</p>
  </div>

  <h2>👤 Minha conta</h2>
  <div class="card">
    <p><strong>WiseTag:</strong> obrigatório pra receber pagamento. <strong>Você edita em <em>Perfil</em></strong> (campos WiseTag/CPF/País). Se estiver vazio, aparece alerta vermelho avisando.</p>
    <p><strong>📊 Capacidade mensal:</strong> também no <em>Perfil</em>, abaixo dos dados pessoais. Informe quantos Criativos, Pacotes POSTAGEM e Sites/Projetos você absorve por mês. Sistema usa pra mostrar 🟢/🔴 quando admin tentar te atribuir um cliente. <strong>Mantenha atualizado.</strong></p>
    <p><strong>🟢/🔴 Aceitando novos clientes:</strong> checkbox no <em>Perfil</em>. Marque quando estiver com capacidade, desmarque quando estiver no limite. Sistema avisa o admin com alerta antes de tentar te atribuir novo cliente — admin pode forçar a exceção se quiser.</p>
    <p><strong>🔐 2FA + backup codes (recuperação):</strong> ative em <em>Minha conta → Segurança</em>. Não é pedido no login — serve pra recuperar conta se esquecer a senha. Os 8 backup codes te salvam se perder o celular — salva eles em local seguro.</p>
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
      <li><strong>💜 Zelle:</strong> QR Code (escaneia no app do banco) + email pra copiar. <em>Envie comprovante depois pelo botão.</em></li>
      <li><strong>🌍 Wise:</strong> botão "Abrir Wise" — preenche o valor exato da cobrança e paga. <strong>Não precisa enviar comprovante</strong> — o sistema detecta automaticamente.</li>
    </ul>
    <p>Comprovante (Zelle) vai pra "Em análise" e a Dite Ads confirma em até 1 dia útil.</p>
  </div>

  <h2>✅ Entregas</h2>
  <div class="card">
    <p>O que está sendo produzido pra você no mês (criativos, postagens, sites). Cada item mostra se já foi entregue.</p>
  </div>

  <h2>👤 Minha conta</h2>
  <div class="card">
    <p>Atualize nome e senha. Email é fixo (avise a Dite Ads se precisar trocar).</p>
    <p><strong>🔐 2FA (opcional, recuperação):</strong> ative em <em>Minha conta → Segurança</em> pra ter um app autenticador como alternativa ao email caso você esqueça a senha. Sistema gera 8 backup codes pra guardar caso perca o celular.</p>
    <p><strong>Esqueceu a senha?</strong> Use "Esqueci a senha" no login — pode escolher: receber link por email OU entrar direto com código do app 2FA (se tiver ativo).</p>
  </div>

  <h2>🧭 Navegação</h2>
  <div class="card">
    <p><strong>← Voltar:</strong> em qualquer tela tem o botão ← no canto superior esquerdo que volta pra tela anterior.</p>
    <p><strong>Menu inferior:</strong> Início · Cobranças · Entregas · Perfil — atalho rápido pras 4 áreas principais.</p>
    <p><strong>🔍 Busca (Ctrl+K):</strong> achar rápido qualquer cobrança ou entrega digitando o nome ou número.</p>
  </div>

  <h2>💬 Precisa de ajuda?</h2>
  <div class="card">
    <p>Email <a href="mailto:contact@diteads.com">contact@diteads.com</a> ou WhatsApp da Dite Ads.</p>
    <p>Se algo no sistema não funcionar como esperado, manda print pra gente resolver rápido.</p>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
