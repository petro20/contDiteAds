<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/cobrancas.php';
require_once __DIR__ . '/lib/pagamentos.php';
require_once __DIR__ . '/lib/whatsapp.php';
$me = require_login();
$db = db();

$acao = $_GET['acao'] ?? 'lista';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

function pode_ver_cobranca(array $c, array $me): bool {
    if ($me['role'] === 'admin') return true;
    if ($me['role'] === 'cliente') return (int)$c['cliente_id'] === (int)$me['cliente_id'];
    if ($me['role'] === 'funcionario') {
        $stmt = db()->prepare('SELECT 1 FROM cobranca_itens ci JOIN assinaturas a ON a.id = ci.assinatura_id WHERE ci.cobranca_id = ? AND a.funcionario_id = ? LIMIT 1');
        $stmt->execute([(int)$c['id'], (int)$me['id']]);
        return (bool)$stmt->fetchColumn();
    }
    return false;
}

/**
 * Salva o upload de comprovante. Retorna caminho relativo ou null se falhar.
 * Aceita PDF, JPG, PNG até 5MB.
 */
function salvar_comprovante_upload(array $file, int $cobranca_id): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Arquivo maior que 5MB.');

    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) throw new RuntimeException('Tipo não aceito (use PDF, JPG, PNG).');
    $ext = $allowed[$mime];

    $base = UPLOAD_DIR . '/comprovantes/' . date('Y/m');
    if (!is_dir($base) && !mkdir($base, 0775, true)) throw new RuntimeException('Erro ao criar diretório.');
    $name = 'cobr_' . $cobranca_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $base . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Erro ao salvar arquivo.');
    // Retorna caminho relativo ao public_html (uploads/comprovantes/AAAA/MM/...)
    return 'comprovantes/' . date('Y/m') . '/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'gerar_manual' && is_admin()) {
        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $competencia = trim((string)($_POST['competencia'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');
        $r = gerar_cobranca_mensal($db, $cliente_id, $competencia);
        if ($r['cobranca_id']) {
            audit_log('cobranca.gerada_manual', 'cobrancas', (int)$r['cobranca_id']);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . (int)$r['cobranca_id']); exit;
        }
        $map = ['empty'=>'Cliente não tem assinaturas elegíveis no mês.','exists'=>'Já existe cobrança nesse mês.','cliente_nao_encontrado'=>'Cliente não encontrado.'];
        $flash = ['err', $map[$r['status']] ?? 'Falhou.'];
    }

    if ($op === 'cancelar' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE cobrancas SET status='cancelada' WHERE id=?");
        $stmt->execute([$cid]);
        audit_log('cobranca.cancelada', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'reabrir' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE cobrancas SET status='aberta' WHERE id=?");
        $stmt->execute([$cid]);
        atualiza_status_cobranca($db, $cid);
        audit_log('cobranca.reaberta', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'enviar_comprovante') {
        // Cliente ou admin podem anexar
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM cobrancas WHERE id = ?');
        $stmt->execute([$cid]);
        $cob = $stmt->fetch();
        if (!$cob || !pode_ver_cobranca($cob, $me)) { http_response_code(403); exit('Acesso negado.'); }

        try {
            $path = salvar_comprovante_upload($_FILES['comprovante'] ?? [], $cid);
            if (!$path) throw new RuntimeException('Selecione um arquivo.');
            // Registra um pagamento "pendente" — admin confirma valor depois
            // Por enquanto: cliente upload cria um registro com valor = saldo restante
            $stmt = $db->prepare('SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos_cliente WHERE cobranca_id = ?');
            $stmt->execute([$cid]);
            $pago = (float)$stmt->fetchColumn();
            $saldo = max((float)$cob['valor_total'] - $pago, 0);

            $obs = trim((string)($_POST['observacao'] ?? '')) ?: 'Comprovante enviado pelo cliente';
            $valor = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0')) ?: $saldo;
            $data  = trim((string)($_POST['data'] ?? '')) ?: date('Y-m-d');
            $metodo = trim((string)($_POST['metodo'] ?? '')) ?: null;

            registrar_pagamento_cliente($db, $cid, $valor, $data, $metodo, $obs, $path, (int)$me['id']);
            audit_log('pagamento.comprovante_enviado', 'cobrancas', $cid);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid . '&ok=comp'); exit;
        } catch (Throwable $e) {
            $flash = ['err', $e->getMessage()];
        }
    }

    if ($op === 'registrar_pagamento_admin' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        $valor = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
        $data  = trim((string)($_POST['data'] ?? '')) ?: date('Y-m-d');
        $metodo = trim((string)($_POST['metodo'] ?? '')) ?: null;
        $obs    = trim((string)($_POST['observacao'] ?? '')) ?: null;
        if ($valor <= 0) {
            $flash = ['err', 'Valor inválido.'];
        } else {
            $path = null;
            if (!empty($_FILES['comprovante']['tmp_name'])) {
                try { $path = salvar_comprovante_upload($_FILES['comprovante'], $cid); }
                catch (Throwable $e) { $flash = ['err', $e->getMessage()]; }
            }
            if (!$flash) {
                registrar_pagamento_cliente($db, $cid, $valor, $data, $metodo, $obs, $path, (int)$me['id']);
                audit_log('pagamento.registrado', 'cobrancas', $cid);
                header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid . '&ok=pag'); exit;
            }
        }
    }

    if ($op === 'remover_pagamento' && is_admin()) {
        $pid = (int)($_POST['pagamento_id'] ?? 0);
        $stmt = $db->prepare('SELECT cobranca_id, comprovante_path FROM pagamentos_cliente WHERE id = ?');
        $stmt->execute([$pid]);
        $row = $stmt->fetch();
        if ($row) {
            $stmt = $db->prepare('DELETE FROM pagamentos_cliente WHERE id = ?');
            $stmt->execute([$pid]);
            atualiza_status_cobranca($db, (int)$row['cobranca_id']);
            // Apaga arquivo do disco (opcional — pode manter pra audit)
            if ($row['comprovante_path']) {
                $f = UPLOAD_DIR . '/' . $row['comprovante_path'];
                if (is_file($f)) @unlink($f);
            }
            audit_log('pagamento.removido', 'cobrancas', (int)$row['cobranca_id']);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . (int)$row['cobranca_id']); exit;
        }
    }
}

if (isset($_GET['ok'])) {
    $msgs = ['comp' => 'Comprovante enviado. Admin vai conferir e confirmar.',
             'pag'  => 'Pagamento registrado.'];
    $flash = ['ok', $msgs[$_GET['ok']] ?? 'OK.'];
}

// Download de comprovante (com auth check)
if (isset($_GET['baixar_comprovante'])) {
    $pid = (int)$_GET['baixar_comprovante'];
    $stmt = $db->prepare('SELECT p.comprovante_path, p.cobranca_id, c.cliente_id, c.id AS cid FROM pagamentos_cliente p JOIN cobrancas c ON c.id = p.cobranca_id WHERE p.id = ?');
    $stmt->execute([$pid]);
    $r = $stmt->fetch();
    if (!$r || !$r['comprovante_path']) { http_response_code(404); exit('Não encontrado.'); }
    $cob = ['id' => $r['cid'], 'cliente_id' => $r['cliente_id']];
    if (!pode_ver_cobranca($cob, $me)) { http_response_code(403); exit('Acesso negado.'); }
    $f = UPLOAD_DIR . '/' . $r['comprovante_path'];
    if (!is_file($f)) { http_response_code(404); exit('Arquivo perdido.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="comprovante_' . $pid . '"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

$page = 'Cobranças';
$nav_active = 'cobrancas';

if ($id) {
    $stmt = $db->prepare('SELECT c.*, cl.nome_empresa, cl.moeda AS cli_moeda FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
    $stmt->execute([$id]);
    $cob = $stmt->fetch();
    if (!$cob || !pode_ver_cobranca($cob, $me)) {
        http_response_code(404);
        $show_back = true; $back_to = APP_BASE_URL . '/cobrancas.php';
        require __DIR__ . '/includes/header.php';
        echo '<h1 class="page-title">Cobrança não encontrada</h1>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }
    $stmt = $db->prepare('SELECT ci.*, a.funcionario_id, u.nome AS func_nome FROM cobranca_itens ci LEFT JOIN assinaturas a ON a.id = ci.assinatura_id LEFT JOIN usuarios u ON u.id = a.funcionario_id WHERE ci.cobranca_id = ? ORDER BY ci.id');
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT p.*, u.nome AS registrado_por_nome FROM pagamentos_cliente p JOIN usuarios u ON u.id = p.registrado_por WHERE p.cobranca_id = ? ORDER BY p.data_pagamento DESC');
    $stmt->execute([$id]);
    $pagamentos = $stmt->fetchAll();
    $pago = (float)array_sum(array_column($pagamentos, 'valor_pago'));
    $saldo = max((float)$cob['valor_total'] - $pago, 0);
    $vencido = $cob['status'] === 'aberta' && strtotime($cob['vencimento']) < strtotime(date('Y-m-d'));

    $show_back = true; $back_to = APP_BASE_URL . '/cobrancas.php';
    $page = 'Cobrança #' . (int)$cob['id'];
    $page_sub = $cob['nome_empresa'] . ' · ' . $cob['competencia_mes'];
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

    <div class="card <?= $vencido ? 'danger' : ($cob['status']==='paga' ? 'success' : '') ?>">
      <div class="spaced">
        <div>
          <div class="muted" style="font-size:12px; text-transform:uppercase;">Total</div>
          <div class="money lg"><?= e(money_fmt((float)$cob['valor_total'], $cob['moeda'])) ?></div>
        </div>
        <span class="status status-<?= e($cob['status']) ?>"><?= e($cob['status']) ?><?= $vencido?' · vencida':'' ?></span>
      </div>
      <div class="grid-2 mt-3">
        <div><div class="muted" style="font-size:12px;">Vencimento</div><strong><?= e(date('d/m/Y', strtotime($cob['vencimento']))) ?></strong></div>
        <div><div class="muted" style="font-size:12px;">Pago</div><strong><?= e(money_fmt($pago, $cob['moeda'])) ?></strong></div>
        <div><div class="muted" style="font-size:12px;">Saldo</div><strong><?= e(money_fmt($saldo, $cob['moeda'])) ?></strong></div>
        <div><div class="muted" style="font-size:12px;">Competência</div><strong><?= e($cob['competencia_mes']) ?></strong></div>
      </div>
    </div>

    <h2>Itens</h2>
    <?php foreach ($itens as $it): ?>
      <div class="card">
        <div class="spaced">
          <div>
            <div class="title"><?= e($it['descricao']) ?></div>
            <div class="sub muted"><?= (int)$it['quantidade'] ?>× <?= e(money_fmt((float)$it['valor_unitario'], $cob['moeda'])) ?><?= $it['func_nome'] ? ' · ' . e($it['func_nome']) : '' ?></div>
          </div>
          <div class="money md"><?= e(money_fmt((float)$it['subtotal'], $cob['moeda'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>

    <h2>Pagamentos</h2>
    <?php if (!$pagamentos): ?>
      <p class="muted">Nenhum pagamento registrado.</p>
    <?php else: ?>
      <?php foreach ($pagamentos as $p): ?>
        <div class="card">
          <div class="spaced">
            <div>
              <div class="title"><?= e(date('d/m/Y', strtotime($p['data_pagamento']))) ?> · <?= e($p['metodo'] ?? '—') ?></div>
              <div class="sub muted">
                Por <?= e($p['registrado_por_nome']) ?>
                <?php if ($p['observacao']): ?> · <?= e($p['observacao']) ?><?php endif; ?>
              </div>
            </div>
            <div class="right">
              <div class="money md"><?= e(money_fmt((float)$p['valor_pago'], $cob['moeda'])) ?></div>
            </div>
          </div>
          <?php if ($p['comprovante_path']): ?>
          <div class="mt-3">
            <a class="btn small btn-ghost" href="?baixar_comprovante=<?= (int)$p['id'] ?>" target="_blank">📎 Ver comprovante</a>
          </div>
          <?php endif; ?>
          <?php if (is_admin()): ?>
          <form method="post" class="mt-3" onsubmit="return confirm('Remover este pagamento? O comprovante também será apagado.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="remover_pagamento">
            <input type="hidden" name="pagamento_id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-ghost small" type="submit">✕ Remover pagamento</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($cob['status'] === 'aberta'): ?>
      <?php if ($me['role'] === 'cliente'): ?>
        <h2>Enviar comprovante</h2>
        <div class="card">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="enviar_comprovante">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <div class="field">
              <label>Arquivo (PDF, JPG ou PNG até 5MB)</label>
              <input type="file" name="comprovante" required accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <div class="grid-2">
              <div class="field"><label>Data do pagamento</label><input type="date" name="data" required value="<?= e(date('Y-m-d')) ?>"></div>
              <div class="field"><label>Valor pago (<?= e($cob['moeda']) ?>)</label><input type="number" step="0.01" min="0.01" name="valor" required value="<?= e(number_format($saldo, 2, '.', '')) ?>"></div>
            </div>
            <div class="field"><label>Método</label>
              <select name="metodo"><option value="">—</option><option>Pix</option><option>Transferência</option><option>Boleto</option><option>Outro</option></select>
            </div>
            <div class="field"><label>Observação</label><input name="observacao" placeholder="opcional"></div>
            <button class="btn block" type="submit">Enviar comprovante</button>
          </form>
        </div>
      <?php elseif (is_admin()): ?>
        <h2>Registrar pagamento</h2>
        <div class="card">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="registrar_pagamento_admin">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <div class="grid-2">
              <div class="field"><label>Valor pago (<?= e($cob['moeda']) ?>)</label><input type="number" step="0.01" min="0.01" name="valor" required value="<?= e(number_format($saldo, 2, '.', '')) ?>"></div>
              <div class="field"><label>Data</label><input type="date" name="data" required value="<?= e(date('Y-m-d')) ?>"></div>
            </div>
            <div class="field"><label>Método</label>
              <select name="metodo"><option value="">—</option><option>Pix</option><option>Transferência</option><option>Boleto</option><option>Dinheiro</option><option>Cartão</option><option>Outro</option></select>
            </div>
            <div class="field"><label>Observação</label><input name="observacao"></div>
            <div class="field"><label>Comprovante (opcional)</label><input type="file" name="comprovante" accept=".pdf,.jpg,.jpeg,.png"></div>
            <button class="btn block" type="submit">Confirmar pagamento</button>
          </form>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <h2>Ações</h2>
    <a class="btn btn-secondary block" href="<?= e(APP_BASE_URL) ?>/recibo.php?cobranca=<?= (int)$cob['id'] ?>" target="_blank">📄 Ver recibo (imprimir/PDF)</a>

    <?php if (is_admin()):
      $vars = wa_vars_cobranca($db, (int)$cob['id']);
      $tem_tel = !empty($vars['_telefone']);
      // Determina template padrão por status
      if ($cob['status'] === 'paga')             $codigo_tpl = 'pagamento_confirmado';
      elseif (strtotime($cob['vencimento']) < strtotime(date('Y-m-d'))) $codigo_tpl = 'lembrete_vencida';
      else                                       $codigo_tpl = 'cobranca_nova';
      $tpl = wa_template($db, $codigo_tpl, 'whatsapp');
      if ($tem_tel && $tpl) {
        $msg = wa_render($tpl['corpo'], $vars);
        $link = wa_link($vars['_telefone'], $msg);
      }
    ?>
      <?php if ($tem_tel && $tpl): ?>
        <a class="btn btn-whatsapp block mt-3" href="<?= e($link) ?>" target="_blank">💬 Enviar pelo WhatsApp ({{<?= e($codigo_tpl) ?>}})</a>
      <?php else: ?>
        <div class="card attention mt-3">
          <div class="title">⚠ WhatsApp indisponível</div>
          <div class="desc">
            <?= !$tem_tel ? 'Cliente sem telefone cadastrado.' : 'Template ' . e($codigo_tpl) . ' não encontrado/ativo.' ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="btn-pair mt-5">
        <?php if ($cob['status'] !== 'cancelada'): ?>
          <form method="post" style="flex:1; margin:0;" onsubmit="return confirm('Cancelar esta cobrança?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="cancelar">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <button class="btn btn-danger block" type="submit">Cancelar cobrança</button>
          </form>
        <?php else: ?>
          <form method="post" style="flex:1; margin:0;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="reabrir">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <button class="btn block" type="submit">Reabrir</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Lista
require __DIR__ . '/includes/header.php';
$f_status = $_GET['status'] ?? '';
$f_cliente = (int)($_GET['cliente_id'] ?? 0);

$where = ['1=1']; $params = [];
if ($me['role'] === 'cliente') {
    $where[] = 'c.cliente_id = ?'; $params[] = (int)$me['cliente_id'];
} elseif ($me['role'] === 'funcionario') {
    $where[] = 'c.id IN (SELECT ci.cobranca_id FROM cobranca_itens ci JOIN assinaturas a ON a.id = ci.assinatura_id WHERE a.funcionario_id = ?)';
    $params[] = (int)$me['id'];
}
if (in_array($f_status, ['aberta','paga','cancelada'], true)) {
    $where[] = 'c.status = ?'; $params[] = $f_status;
}
if (is_admin() && $f_cliente) {
    $where[] = 'c.cliente_id = ?'; $params[] = $f_cliente;
}

$sql = 'SELECT c.id, c.competencia_mes, c.valor_total, c.moeda, c.vencimento, c.status, cl.nome_empresa
        FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY c.status = "paga", c.vencimento DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cobr = $stmt->fetchAll();
$cls = is_admin() ? $db->query('SELECT id, nome_empresa FROM clientes WHERE ativo=1 ORDER BY nome_empresa')->fetchAll() : [];
?>
<h1 class="page-title">Cobranças</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if (is_admin()): ?>
  <details class="card">
    <summary><strong>🔧 Gerar cobrança manualmente</strong> (teste)</summary>
    <form method="post" class="mt-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="gerar_manual">
      <div class="field"><label>Cliente</label>
        <select name="cliente_id" required>
          <option value="">— selecione —</option>
          <?php foreach ($cls as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['nome_empresa']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Competência (YYYY-MM)</label><input name="competencia" value="<?= e(date('Y-m')) ?>" pattern="\d{4}-\d{2}" required></div>
      <button class="btn block" type="submit">Gerar agora</button>
    </form>
  </details>

  <form method="get" class="card">
    <div class="grid-2">
      <div class="field"><label>Status</label>
        <select name="status" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="aberta"    <?= $f_status==='aberta'?'selected':'' ?>>Aberta</option>
          <option value="paga"      <?= $f_status==='paga'?'selected':'' ?>>Paga</option>
          <option value="cancelada" <?= $f_status==='cancelada'?'selected':'' ?>>Cancelada</option>
        </select>
      </div>
      <div class="field"><label>Cliente</label>
        <select name="cliente_id" onchange="this.form.submit()">
          <option value="0">Todos</option>
          <?php foreach ($cls as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $f_cliente==$c['id']?'selected':'' ?>><?= e($c['nome_empresa']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </form>
<?php endif; ?>

<div class="section-label mt-5">Cobranças (<?= count($cobr) ?>)</div>
<?php foreach ($cobr as $c):
    $vencido = $c['status'] === 'aberta' && strtotime($c['vencimento']) < strtotime(date('Y-m-d'));
?>
  <a class="list-card" href="?id=<?= (int)$c['id'] ?>">
    <div class="info">
      <div class="nome">
        #<?= (int)$c['id'] ?> · <?= e($c['nome_empresa']) ?>
        <span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span>
        <?php if ($vencido): ?><span class="status status-vencida">vencida</span><?php endif; ?>
      </div>
      <div class="sub"><?= e($c['competencia_mes']) ?> · venc <?= e(date('d/m/Y', strtotime($c['vencimento']))) ?></div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt((float)$c['valor_total'], $c['moeda'])) ?></div>
    </div>
  </a>
<?php endforeach; ?>
<?php if (!$cobr): ?>
  <p class="muted center mt-5">Nenhuma cobrança.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
