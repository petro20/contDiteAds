<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/configuracoes.php';
require_once __DIR__ . '/lib/cotacao.php';
require_sadmin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op_post = $_POST['op'] ?? '';

    if ($op_post === 'atualizar_cotacao') {
        $c = cotacao_atual($db, true); // forcar refresh
        audit_log('cotacao.atualizada', 'configuracoes', 0);
        if ($c['fonte'] === 'api') {
            $flash = ['ok', sprintf('Cotação atualizada. USD→BRL %.4f · USD→EUR %.4f (de %s)', $c['BRL'], $c['EUR'], $c['data'])];
        } else {
            $flash = ['err', 'API de cotação indisponível. Usando último valor: USD→BRL ' . number_format($c['BRL'],4) . ' · USD→EUR ' . number_format($c['EUR'],4) . ' (' . $c['data'] . ')'];
        }
        goto fim_post; // NÃO cai no save principal
    }
    if ($op_post === 'remover_qr') {
        $atual = config_get($db, 'pagamento_zelle_qr');
        if ($atual) {
            $f = UPLOAD_DIR . '/' . $atual;
            if (is_file($f)) @unlink($f);
        }
        config_set($db, 'pagamento_zelle_qr', '');
        audit_log('config_pagamento.qr_removido', 'configuracoes', 0);
        header('Location: ' . APP_BASE_URL . '/config_pagamento.php?ok=qr_removido'); exit;
    }

    // Save principal — só roda se o usuário clicou em "Salvar" (não em outros botões)
    $zelle = trim((string)($_POST['zelle_email'] ?? ''));
    $wise  = trim((string)($_POST['wise_link']   ?? ''));
    $instr = trim((string)($_POST['instrucoes']  ?? ''));
    $api_ia = trim((string)($_POST['anthropic_api_key'] ?? ''));

    if ($wise && !preg_match('~^https?://~i', $wise)) {
        $flash = ['err', 'O link do Wise precisa começar com http:// ou https://'];
    } else {
        config_set($db, 'pagamento_zelle_email', $zelle);
        config_set($db, 'pagamento_wise_link',   $wise);
        config_set($db, 'pagamento_instrucoes',  $instr);
        // Só atualiza se foi alterado (preserva valor se admin não preencher pra evitar limpar)
        if ($api_ia !== '' && $api_ia !== '••••••••') {
            config_set($db, 'anthropic_api_key', $api_ia);
        }

        // Upload do QR code do Zelle
        if (!empty($_FILES['zelle_qr']['tmp_name']) && $_FILES['zelle_qr']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['zelle_qr']['tmp_name'];
            $size = (int)$_FILES['zelle_qr']['size'];
            $orig = (string)$_FILES['zelle_qr']['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','webp'], true)) {
                $flash = ['err', 'Formato não suportado. Use PNG, JPG ou WEBP.'];
            } elseif ($size > 2 * 1024 * 1024) {
                $flash = ['err', 'Arquivo muito grande (máx 2MB).'];
            } else {
                $dir = UPLOAD_DIR . '/zelle';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                // Garante que o .htaccess de exceção exista (libera só imagens)
                $htaccess = $dir . '/.htaccess';
                if (!is_file($htaccess)) {
                    @file_put_contents($htaccess,
                        "Require all granted\n" .
                        "<FilesMatch \"\\.(png|jpg|jpeg|webp|gif)\$\">\n    Require all granted\n</FilesMatch>\n" .
                        "<FilesMatch \"\\.(php|phtml|phar|html|htm|js|json|xml|sql|ini|env|sh)\$\">\n    Require all denied\n</FilesMatch>\n"
                    );
                }
                $nome = 'qr_zelle_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $destino = $dir . '/' . $nome;
                if (move_uploaded_file($tmp, $destino)) {
                    // Apaga anterior
                    $ant = config_get($db, 'pagamento_zelle_qr');
                    if ($ant) {
                        $fa = UPLOAD_DIR . '/' . $ant;
                        if (is_file($fa)) @unlink($fa);
                    }
                    config_set($db, 'pagamento_zelle_qr', 'zelle/' . $nome);
                } else {
                    $flash = ['err', 'Falhou ao salvar o arquivo.'];
                }
            }
        }

        if (!$flash) {
            audit_log('config_pagamento.atualizada', 'configuracoes', 0);
            $flash = ['ok', 'Configurações de pagamento salvas.'];
        }
    }
    fim_post:;
}
if (isset($_GET['ok']) && $_GET['ok'] === 'qr_removido') $flash = ['ok', 'QR Code removido.'];

$cfg = config_pagamento($db);

$page = 'Formas de pagamento';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Finanças</h1>
<?php render_group_tabs('financas', 'pagamento_cfg'); ?>
<h2>Formas de pagamento</h2>
<p class="muted">Configure os métodos de pagamento que serão exibidos nas cobranças e disponibilizados nas mensagens (templates).</p>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php
  $cot = cotacao_atual($db);
  $hoje = date('Y-m-d');
  $atualizada_hoje = ($cot['data'] === $hoje);
?>
<div class="card brand">
  <div class="title" style="color:var(--c-primary-2);">💱 Cotação USD (moeda mestre)</div>
  <p class="muted" style="font-size:13px;">Dite Ads cobra todos os serviços em USD. Pra clientes em BRL/EUR, o sistema converte automaticamente usando a cotação do dia.</p>
  <div class="info-pair"><span class="l">USD → BRL</span><span class="v"><strong>R$ <?= e(number_format($cot['BRL'], 4)) ?></strong></span></div>
  <div class="info-pair"><span class="l">USD → EUR</span><span class="v"><strong>€ <?= e(number_format($cot['EUR'], 4)) ?></strong></span></div>
  <div class="info-pair muted" style="font-size:12px;">
    <span class="l">Atualizada em <?= e($cot['data']) ?></span>
    <span class="v">
      <?php if ($cot['fonte'] === 'api'): ?><span class="status status-paga">api</span>
      <?php elseif ($cot['fonte'] === 'cache' && $atualizada_hoje): ?><span class="status status-info">do dia</span>
      <?php else: ?><span class="status status-vencida">defasada</span><?php endif; ?>
    </span>
  </div>
  <form method="post" class="mt-3">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="atualizar_cotacao">
    <button type="submit" class="btn btn-secondary block">🔄 Atualizar cotação agora</button>
  </form>
</div>

<?php $qr_path = config_get($db, 'pagamento_zelle_qr'); ?>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="card">
    <div class="title">💜 Zelle</div>
    <div class="field">
      <label>Email cadastrado no Zelle</label>
      <input type="email" name="zelle_email" value="<?= e($cfg['zelle_email']) ?>" placeholder="ex: voce@gmail.com">
      <div class="hint">O cliente vai usar este email no app do banco dele.</div>
    </div>
    <div class="field">
      <label>QR Code do Zelle (opcional)</label>
      <?php if ($qr_path): ?>
        <div style="text-align:center; padding:var(--s-3); background:#fff; border-radius:8px; margin-bottom:var(--s-3);">
          <img src="<?= e(APP_BASE_URL) ?>/uploads/<?= e($qr_path) ?>" alt="QR Code Zelle" style="max-width:200px; height:auto;">
        </div>
      <?php endif; ?>
      <input type="file" name="zelle_qr" accept="image/png,image/jpeg,image/webp">
      <div class="hint">PNG/JPG/WEBP até 2MB. <?= $qr_path ? 'Enviar novo substitui o atual.' : 'Salve o QR do Zelle como imagem e suba aqui.' ?></div>
    </div>
    <?php if ($qr_path): ?>
      <button type="submit" name="op" value="remover_qr" formnovalidate class="btn btn-ghost small" onclick="return confirm('Remover o QR Code atual?');" style="color:var(--c-danger);">🗑 Remover QR atual</button>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="title">🌍 Wise</div>
    <div class="field">
      <label>Link público de pagamento</label>
      <input type="url" name="wise_link" value="<?= e($cfg['wise_link']) ?>" placeholder="https://wise.com/pay/me/...">
      <div class="hint">O link gerado em "Receber → Compartilhar página de pagamento" no Wise.</div>
    </div>
  </div>

  <div class="card">
    <div class="title">📝 Instruções adicionais</div>
    <div class="field">
      <label>Texto extra (opcional)</label>
      <textarea name="instrucoes" rows="3" placeholder="ex: Após o pagamento, envie o comprovante pelo botão abaixo."><?= e($cfg['instrucoes']) ?></textarea>
    </div>
  </div>

  <div class="card">
    <div class="title">🌍 Wise — Sincronização automática</div>
    <p class="muted" style="font-size:13px;">2 opções para receber pagamentos automaticamente no sistema:</p>
    <a href="<?= e(APP_BASE_URL) ?>/wise_eventos.php" class="btn btn-brand block">🪝 Webhook em tempo real (recomendado)</a>
    <div class="hint">Configure 1 vez no painel Wise e pagamentos casam automaticamente assim que chegam.</div>
    <a href="<?= e(APP_BASE_URL) ?>/wise_sync.php" class="btn btn-secondary block mt-2">📤 Upload de CSV (manual)</a>
    <div class="hint">Alternativa: baixa o extrato do Wise e sobe aqui.</div>
  </div>

  <div class="card">
    <div class="title">🤖 IA (Anthropic Claude)</div>
    <p class="muted" style="font-size:13px;">Usado pelo Simulador de preço pra sugerir custos automaticamente. Pegue uma chave em <a href="https://console.anthropic.com/" target="_blank" rel="noopener" style="color:var(--c-primary-2);">console.anthropic.com</a> (começa com <code>sk-ant-</code>).</p>
    <div class="field">
      <label>API key</label>
      <?php $tem_key = (bool)config_get($db, 'anthropic_api_key'); ?>
      <input type="password" name="anthropic_api_key" value="<?= $tem_key ? '••••••••' : '' ?>" placeholder="sk-ant-..." autocomplete="off">
      <div class="hint"><?= $tem_key ? '✓ Chave já configurada. Deixe em branco pra manter, ou cole uma nova pra trocar.' : 'Nenhuma chave configurada — o botão "✨ Preencher com IA" do simulador não funciona sem isso.' ?></div>
    </div>
  </div>

  <button class="btn block" type="submit">Salvar</button>
</form>

<div class="section-label mt-5">Variáveis nos templates</div>
<div class="card">
  <p>Você pode usar nos templates de email/WhatsApp:</p>
  <ul style="padding-left:20px; color:var(--txt-2);">
    <li><code>{zelle_email}</code> → email cadastrado no Zelle</li>
    <li><code>{zelle_qr_url}</code> → URL pública da imagem do QR Code</li>
    <li><code>{link_wise}</code> → link público do Wise</li>
    <li><code>{instrucoes_pagamento}</code> → texto extra</li>
  </ul>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
