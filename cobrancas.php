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
    if (in_array($me['role'], ['admin','sadmin'], true)) return true;
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

    if ($op === 'nova_avulsa' && is_admin()) {
        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $vencimento = $_POST['vencimento'] ?? date('Y-m-d', strtotime('+5 days'));
        $competencia = $_POST['competencia'] ?? date('Y-m');
        $descricoes = $_POST['descricao'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $valores = $_POST['valor'] ?? [];

        // Valida vencimento: precisa ser data válida e não pode ser no passado
        // (evita admin digitar ano errado e disparar régua em cascata).
        $venc_ts = strtotime((string)$vencimento);
        $hoje_ts = strtotime(date('Y-m-d'));

        if (!$cliente_id || !is_array($descricoes) || count($descricoes) === 0) {
            $flash = ['err', 'Cliente e ao menos 1 item são obrigatórios.'];
        } elseif ($venc_ts === false) {
            $flash = ['err', 'Data de vencimento inválida.'];
        } elseif ($venc_ts < $hoje_ts) {
            $flash = ['err', 'Vencimento não pode ser no passado. Use uma data futura ou hoje.'];
        } else {
            $stmt = $db->prepare('SELECT moeda FROM clientes WHERE id = ?');
            $stmt->execute([$cliente_id]);
            $moeda = (string)$stmt->fetchColumn() ?: 'BRL';

            $linhas = [];
            $total = 0.0;
            foreach ($descricoes as $i => $desc) {
                $desc = trim((string)$desc);
                $qtd = max(1, (int)($quantidades[$i] ?? 1));
                $val = (float)str_replace(',', '.', (string)($valores[$i] ?? '0'));
                if ($desc === '' || $val <= 0) continue;
                $linhas[] = ['descricao'=>$desc, 'quantidade'=>$qtd, 'valor_unitario'=>$val, 'subtotal'=>$qtd * $val];
                $total += $qtd * $val;
            }
            if (!$linhas) {
                $flash = ['err', 'Adicione ao menos 1 item válido.'];
            } else {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare('INSERT INTO cobrancas (cliente_id, competencia_mes, valor_total, moeda, vencimento, status) VALUES (?,?,?,?,?,"aberta")');
                    $stmt->execute([$cliente_id, $competencia, $total, $moeda, $vencimento]);
                    $newId = (int)$db->lastInsertId();
                    $ins = $db->prepare('INSERT INTO cobranca_itens (cobranca_id, assinatura_id, descricao, quantidade, valor_unitario, subtotal) VALUES (?, NULL, ?, ?, ?, ?)');
                    foreach ($linhas as $l) {
                        $ins->execute([$newId, $l['descricao'], $l['quantidade'], $l['valor_unitario'], $l['subtotal']]);
                    }
                    $db->commit();
                    audit_log('cobranca.avulsa_criada', 'cobrancas', $newId);
                    header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $newId . '&ok=add'); exit;
                } catch (Throwable $e) {
                    $db->rollBack();
                    $flash = ['err', 'Erro: ' . $e->getMessage()];
                }
            }
        }
    }

    if ($op === 'adicionar_item' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        $desc = trim((string)($_POST['descricao'] ?? ''));
        $qtd  = max(1, (int)($_POST['quantidade'] ?? 1));
        $val  = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
        if ($desc && $val > 0) {
            $sub = $qtd * $val;
            $stmt = $db->prepare('INSERT INTO cobranca_itens (cobranca_id, assinatura_id, descricao, quantidade, valor_unitario, subtotal) VALUES (?, NULL, ?, ?, ?, ?)');
            $stmt->execute([$cid, $desc, $qtd, $val, $sub]);
            $stmt = $db->prepare('UPDATE cobrancas SET valor_total = valor_total + ? WHERE id = ?');
            $stmt->execute([$sub, $cid]);
            audit_log('cobranca.item_adicionado', 'cobranca_itens', (int)$db->lastInsertId());
        }
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'cancelar' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        // Proteção: não permite cancelar cobrança paga com dinheiro confirmado.
        // Admin teria que estornar os pagamentos primeiro pra evitar perder histórico.
        try {
            $stmt = $db->prepare('SELECT COALESCE(SUM(valor_pago), 0) FROM pagamentos_cliente WHERE cobranca_id = ? AND pendente = 0');
            $stmt->execute([$cid]);
            $pago_confirmado = (float)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $pago_confirmado = 0.0;
        }
        if ($pago_confirmado > 0) {
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid . '&err=cancelar_paga'); exit;
        }
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

    if ($op === 'marcar_paga' && is_admin()) {
        // Ação rápida: registra pagamento com saldo completo, hoje, sem método/obs
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT valor_total FROM cobrancas WHERE id = ?');
        $stmt->execute([$cid]);
        $valor_total = (float)$stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos_cliente WHERE cobranca_id = ?');
        $stmt->execute([$cid]);
        $pago = (float)$stmt->fetchColumn();
        $saldo = $valor_total - $pago;
        if ($saldo > 0) {
            registrar_pagamento_cliente($db, $cid, $saldo, date('Y-m-d'), null, 'Marcado pago pelo admin', null, (int)$me['id']);
            audit_log('cobranca.marcada_paga', 'cobrancas', $cid);
        }
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid . '&ok=pag'); exit;
    }

    if ($op === 'silenciar_toggle' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('UPDATE cobrancas SET silenciada = 1 - silenciada WHERE id = ?');
        $stmt->execute([$cid]);
        audit_log('cobranca.silenciada_toggle', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'remover_item' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        $iid = (int)($_POST['item_id'] ?? 0);
        $stmt = $db->prepare('SELECT subtotal FROM cobranca_itens WHERE id = ? AND cobranca_id = ?');
        $stmt->execute([$iid, $cid]);
        $sub = (float)$stmt->fetchColumn();
        if ($sub > 0) {
            $stmt = $db->prepare('DELETE FROM cobranca_itens WHERE id = ?');
            $stmt->execute([$iid]);
            $stmt = $db->prepare('UPDATE cobrancas SET valor_total = GREATEST(0, valor_total - ?) WHERE id = ?');
            $stmt->execute([$sub, $cid]);
            audit_log('cobranca.item_removido', 'cobranca_itens', $iid);
            // Reavalia status: se valor_total zerou, vira 'cancelada'.
            atualiza_status_cobranca($db, $cid);
        }
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'apagar' && is_admin()) {
        $cid = (int)($_POST['id'] ?? 0);
        // Apaga arquivos de comprovante do disco antes
        $stmt = $db->prepare('SELECT comprovante_path FROM pagamentos_cliente WHERE cobranca_id = ?');
        $stmt->execute([$cid]);
        foreach ($stmt->fetchAll() as $p) {
            if ($p['comprovante_path']) {
                $f = UPLOAD_DIR . '/' . $p['comprovante_path'];
                if (is_file($f)) @unlink($f);
            }
        }
        $stmt = $db->prepare('DELETE FROM cobrancas WHERE id = ?');
        $stmt->execute([$cid]);
        audit_log('cobranca.apagada', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?ok=del'); exit;
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

            // pendente=true: aguarda confirmação do admin
            registrar_pagamento_cliente($db, $cid, $valor, $data, $metodo, $obs, $path, (int)$me['id'], true);
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

    if ($op === 'aceitar_comprovante' && is_admin()) {
        $pid = (int)($_POST['pagamento_id'] ?? 0);
        $stmt = $db->prepare('SELECT cobranca_id FROM pagamentos_cliente WHERE id = ?');
        $stmt->execute([$pid]);
        $cid = (int)$stmt->fetchColumn();
        if ($cid) {
            try {
                $stmt = $db->prepare('UPDATE pagamentos_cliente SET pendente = 0 WHERE id = ?');
                $stmt->execute([$pid]);
            } catch (PDOException $e) { /* schema antigo, ignora */ }
            atualiza_status_cobranca($db, $cid);
            audit_log('pagamento.comprovante_aceito', 'pagamentos_cliente', $pid);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
        }
    }

    if ($op === 'rejeitar_comprovante' && is_admin()) {
        $pid = (int)($_POST['pagamento_id'] ?? 0);
        $stmt = $db->prepare('SELECT cobranca_id, comprovante_path FROM pagamentos_cliente WHERE id = ?');
        $stmt->execute([$pid]);
        $row = $stmt->fetch();
        if ($row) {
            $stmt = $db->prepare('DELETE FROM pagamentos_cliente WHERE id = ?');
            $stmt->execute([$pid]);
            if ($row['comprovante_path']) {
                $f = UPLOAD_DIR . '/' . $row['comprovante_path'];
                if (is_file($f)) @unlink($f);
            }
            atualiza_status_cobranca($db, (int)$row['cobranca_id']);
            audit_log('pagamento.comprovante_rejeitado', 'pagamentos_cliente', $pid);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . (int)$row['cobranca_id']); exit;
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
if (isset($_GET['err'])) {
    $errs = ['cancelar_paga' => 'Não dá pra cancelar esta cobrança: ela já tem pagamentos confirmados. Estorne os pagamentos primeiro (em "Pagamento detalhado") e tente de novo.'];
    $flash = ['err', $errs[$_GET['err']] ?? 'Erro.'];
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
    $stmt = $db->prepare('SELECT ci.*, a.funcionario_id, a.variante, u.nome AS func_nome, i.tipo AS item_tipo, i.e_pacote
                          FROM cobranca_itens ci
                          LEFT JOIN assinaturas a ON a.id = ci.assinatura_id
                          LEFT JOIN itens_catalogo i ON i.id = a.item_id
                          LEFT JOIN usuarios u ON u.id = a.funcionario_id
                          WHERE ci.cobranca_id = ? ORDER BY ci.id');
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll();

    // Pra cada item, conta entregas no mês (se aplicável)
    foreach ($itens as &$it) {
        $it['progresso'] = '';
        if ($it['assinatura_id']) {
            if ($it['item_tipo'] === 'por_unidade' && (int)$it['quantidade'] > 0) {
                $it['progresso'] = (int)$it['quantidade'] . ' entregas';
            } elseif ($it['e_pacote']) {
                $stmt2 = $db->prepare('SELECT COUNT(*) FROM entregas WHERE assinatura_id = ? AND competencia_mes = ?');
                $stmt2->execute([(int)$it['assinatura_id'], $cob['competencia_mes']]);
                $marcadas = (int)$stmt2->fetchColumn();
                $it['progresso'] = $marcadas . '/' . (int)$it['quantidade'] . ' entregas';
            } else {
                $it['progresso'] = 'ativo';
            }
        }
    }
    unset($it);

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

    <?php
      // KPI total
      if ($cob['status'] === 'paga')          { $status_label = 'PAGA';        $status_class = 'success'; }
      elseif ($cob['status'] === 'em_analise'){ $status_label = 'EM ANÁLISE';  $status_class = 'destaque'; }
      elseif ($cob['status'] === 'cancelada') { $status_label = 'CANCELADA';   $status_class = 'info'; }
      elseif ($vencido)                       { $status_label = 'VENCIDA · ' . date('d/m', strtotime($cob['vencimento'])); $status_class = 'vencida'; }
      else                                    { $status_label = 'PENDENTE · vence ' . date('d/m', strtotime($cob['vencimento'])); $status_class = 'aberta'; }
    ?>
    <div class="card hero">
      <div class="label">Valor total</div>
      <div class="value"><?= e(money_fmt((float)$cob['valor_total'], $cob['moeda'])) ?></div>
      <span class="status status-<?= e($status_class) ?>"><?= e($status_label) ?></span>
      <?php if ($pago > 0 && $cob['status'] !== 'paga'): ?>
        <div class="sub">Pago: <?= e(money_fmt($pago, $cob['moeda'])) ?> · Saldo: <?= e(money_fmt($saldo, $cob['moeda'])) ?></div>
      <?php endif; ?>
    </div>

    <div class="section-label">Itens cobrados</div>
    <?php if (is_admin()): ?>
      <details class="card">
        <summary class="muted" style="cursor:pointer; padding:6px;">+ Adicionar item avulso</summary>
        <form method="post" class="mt-3">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="adicionar_item">
          <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
          <div class="field"><label>Descrição</label><input name="descricao" required placeholder="Ex: Hora extra de design"></div>
          <div class="grid-2">
            <div class="field"><label>Quantidade</label><input type="number" min="1" name="quantidade" value="1" required></div>
            <div class="field"><label>Valor unitário (<?= e($cob['moeda']) ?>)</label><input type="number" step="0.01" min="0.01" name="valor" required></div>
          </div>
          <button class="btn block" type="submit">Adicionar à cobrança</button>
        </form>
      </details>
    <?php endif; ?>
    <?php foreach ($itens as $it): ?>
      <div class="card" style="position:relative;">
        <?php if (is_admin()): ?>
          <form method="post" style="position:absolute; top:8px; right:8px; margin:0;" onsubmit="return confirm('Remover este item da cobrança?\n\nO valor total da cobrança vai ser ajustado automaticamente.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="remover_item">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
            <button type="submit" title="Remover item" style="background:transparent; border:0; color:var(--c-danger); cursor:pointer; font-size:18px; line-height:1; padding:4px 8px; border-radius:6px;">✕</button>
          </form>
        <?php endif; ?>
        <div class="spaced" style="<?= is_admin() ? 'padding-right:32px;' : '' ?>">
          <div class="info" style="flex:1; min-width:0;">
            <div class="title" style="color:var(--txt-1);"><?= e($it['descricao']) ?></div>
            <div class="sub muted">
              <?php if ($it['func_nome']): ?><?= e($it['func_nome']) ?><?php endif; ?>
              <?php if ($it['progresso']): ?><?= $it['func_nome'] ? ' · ' : '' ?><?= e($it['progresso']) ?><?php endif; ?>
              <?php if (!$it['func_nome'] && !$it['progresso']): ?><?= (int)$it['quantidade'] ?>× <?= e(money_fmt((float)$it['valor_unitario'], $cob['moeda'])) ?><?php endif; ?>
            </div>
          </div>
          <div class="money md"><?= e(money_fmt((float)$it['subtotal'], $cob['moeda'])) ?></div>
        </div>
        <?php if (is_admin() && !empty($it['assinatura_id'])): ?>
          <a class="btn btn-ghost small mt-3" href="<?= e(APP_BASE_URL) ?>/assinaturas.php?acao=editar&id=<?= (int)$it['assinatura_id'] ?>" style="display:inline-block;">✏ Editar assinatura</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if (is_admin() && $cob['status'] === 'aberta'):
      $vars = wa_vars_cobranca($db, (int)$cob['id']);
      $tem_tel = !empty($vars['_telefone']);
      $codigo_tpl = $vencido ? 'lembrete_vencida' : 'cobranca_nova';
      $tpl = wa_template($db, $codigo_tpl, 'whatsapp');
      $wa_link = '';
      if ($tem_tel && $tpl) $wa_link = wa_link($vars['_telefone'], wa_render($tpl['corpo'], $vars));

      $stmt_em = $db->prepare('SELECT email FROM clientes WHERE id = ?');
      $stmt_em->execute([(int)$cob['cliente_id']]);
      $cli_email = (string)$stmt_em->fetchColumn();
      $tpl_em = wa_template($db, $codigo_tpl, 'email');
      $mailto = '';
      if ($cli_email && $tpl_em) {
          $assunto = rawurlencode(wa_render($tpl_em['assunto'] ?: 'Cobrança', $vars));
          $corpo   = rawurlencode(wa_render($tpl_em['corpo'], $vars));
          $mailto  = 'mailto:' . $cli_email . '?subject=' . $assunto . '&body=' . $corpo;
      }
    ?>
      <div class="btn-pair mt-3">
        <?php if ($wa_link): ?>
          <a class="btn btn-whatsapp" href="<?= e($wa_link) ?>" target="_blank">💬 WhatsApp</a>
        <?php else: ?>
          <button class="btn btn-ghost" disabled title="<?= $tem_tel ? 'Template não encontrado' : 'Cliente sem telefone' ?>">💬 WhatsApp</button>
        <?php endif; ?>
        <?php if ($mailto): ?>
          <a class="btn btn-secondary" href="<?= e($mailto) ?>">✉ Email</a>
        <?php else: ?>
          <button class="btn btn-ghost" disabled title="Cliente sem email ou template ausente">✉ Email</button>
        <?php endif; ?>
      </div>

      <form method="post" class="mt-3" onsubmit="return confirm('Marcar esta cobrança como totalmente paga?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="marcar_paga">
        <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
        <button class="btn block" type="submit">✓ Marcar como paga</button>
      </form>

      <form method="post" class="mt-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="silenciar_toggle">
        <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
        <button class="btn btn-ghost block" type="submit">🔕 <?= $cob['silenciada'] ? 'Reativar lembretes' : 'Silenciar lembretes' ?></button>
      </form>

      <details class="mt-5">
        <summary class="muted" style="cursor:pointer; padding:var(--s-3);">Pagamento detalhado (parcial, com comprovante, etc.)</summary>
        <div class="card mt-3">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="registrar_pagamento_admin">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <div class="grid-2">
              <div class="field"><label>Valor (<?= e($cob['moeda']) ?>)</label><input type="number" step="0.01" min="0.01" name="valor" required value="<?= e(number_format($saldo, 2, '.', '')) ?>"></div>
              <div class="field"><label>Data</label><input type="date" name="data" required value="<?= e(date('Y-m-d')) ?>"></div>
            </div>
            <div class="field"><label>Método</label>
              <select name="metodo"><option value="">—</option><option>Pix</option><option>Transferência</option><option>Boleto</option><option>Dinheiro</option><option>Cartão</option><option>Outro</option></select>
            </div>
            <div class="field"><label>Observação</label><input name="observacao"></div>
            <div class="field"><label>Comprovante (opcional)</label><input type="file" name="comprovante" accept=".pdf,.jpg,.jpeg,.png"></div>
            <button class="btn block" type="submit">Registrar pagamento</button>
          </form>
        </div>
      </details>
    <?php endif; ?>

    <?php
    // Mostra formas de pagamento configuradas (cliente E admin veem) — só quando ainda não foi paga
    if (in_array($cob['status'], ['aberta','em_analise'], true)):
        require_once __DIR__ . '/lib/configuracoes.php';
        $cfg_pag = config_pagamento($db);
        $tem_metodo = $cfg_pag['zelle_email'] || $cfg_pag['wise_link'];
        if ($tem_metodo):
    ?>
      <h2 class="mt-5">💳 Formas de pagamento</h2>
      <p class="muted" style="font-size:13px;">Escolha uma das opções abaixo. Após pagar, envie o comprovante pelo botão no fim da página.</p>

      <?php if ($cfg_pag['zelle_email'] || $cfg_pag['zelle_qr']): ?>
        <div class="card">
          <div class="title">💜 Pagar via Zelle</div>
          <div class="desc" style="margin-bottom:var(--s-3);">Use o app do <strong>seu banco</strong> (Bank of America, Chase, Wells Fargo, etc.) e procure pela opção <em>Zelle</em>.</div>

          <?php if ($cfg_pag['zelle_qr']): ?>
            <div style="text-align:center; padding:var(--s-3); background:#fff; border-radius:8px; margin:var(--s-3) 0;">
              <img src="<?= e($cfg_pag['zelle_qr_url']) ?>" alt="QR Code Zelle" style="max-width:240px; width:100%; height:auto;">
            </div>
            <div class="desc muted" style="font-size:13px; text-align:center; margin-bottom:var(--s-3);">📱 <strong>Opção 1:</strong> escaneie o QR Code com o app do banco</div>
          <?php endif; ?>

          <?php if ($cfg_pag['zelle_email']): ?>
            <div class="desc" style="font-size:13px; margin-bottom:var(--s-2);"><strong>Opção 2:</strong> envie pra este email no Zelle:</div>
            <div class="spaced" style="gap:8px; background:var(--bg-input); border-radius:6px; padding:10px;">
              <code id="zelle_email_txt" style="flex:1; font-size:14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($cfg_pag['zelle_email']) ?></code>
              <button type="button" class="btn small btn-brand" onclick="copiarTxt('zelle_email_txt', this)">📋 Copiar</button>
            </div>
            <div class="desc muted" style="font-size:11px; margin-top:var(--s-2);">Valor: <strong><?= e(money_fmt((float)$saldo, $cob['moeda'])) ?></strong></div>
          <?php endif; ?>

          <details style="margin-top:var(--s-3);">
            <summary style="cursor:pointer; color:var(--c-primary-2); font-size:13px;">Como pagar com Zelle passo a passo</summary>
            <ol style="padding-left:20px; color:var(--txt-2); font-size:13px; margin-top:var(--s-2);">
              <li>Abra o app do seu banco</li>
              <li>Procure a opção <strong>Zelle</strong> ou <em>Send Money with Zelle</em></li>
              <li>Adicione o destinatário usando o QR Code ou o email acima</li>
              <li>Digite o valor: <strong><?= e(money_fmt((float)$saldo, $cob['moeda'])) ?></strong></li>
              <li>Confirme e envie</li>
              <li>Envie o comprovante pelo botão no fim desta página</li>
            </ol>
          </details>
        </div>
      <?php endif; ?>

      <?php if ($cfg_pag['wise_link']): ?>
        <div class="card">
          <div class="title">🌍 Pagar via Wise</div>
          <div class="desc" style="margin-bottom:var(--s-3);">Internacional, em várias moedas, com taxa baixa. Clique no botão pra abrir a página de pagamento da Dite Ads no Wise — <strong>preencha o valor <?= e(money_fmt((float)$saldo, $cob['moeda'])) ?></strong> e siga as instruções.</div>
          <a class="btn btn-brand block" href="<?= e($cfg_pag['wise_link']) ?>" target="_blank" rel="noopener">🌍 Abrir Wise ↗</a>
          <details style="margin-top:var(--s-3);">
            <summary style="cursor:pointer; color:var(--c-primary-2); font-size:13px;">Como pagar com Wise passo a passo</summary>
            <ol style="padding-left:20px; color:var(--txt-2); font-size:13px; margin-top:var(--s-2);">
              <li>Clique no botão "Abrir Wise" acima</li>
              <li>Faça login (ou crie conta gratuita)</li>
              <li>Digite o valor: <strong><?= e(money_fmt((float)$saldo, $cob['moeda'])) ?></strong> (importante: valor exato pra cobrança casar automaticamente)</li>
              <li>Escolha o método (cartão, débito, transferência)</li>
              <li>Confirme — o pagamento cai direto na conta da Dite Ads</li>
              <li>O sistema detecta automaticamente; não precisa enviar comprovante</li>
            </ol>
          </details>
        </div>
      <?php endif; ?>

      <?php if ($cfg_pag['instrucoes']): ?>
        <div class="card">
          <div class="title">📝 Observações</div>
          <div class="desc"><?= nl2br(e($cfg_pag['instrucoes'])) ?></div>
        </div>
      <?php endif; ?>

      <script>
      function copiarTxt(elementId, btn) {
        const txt = document.getElementById(elementId).textContent.trim();
        navigator.clipboard.writeText(txt).then(() => {
          const orig = btn.innerHTML;
          btn.innerHTML = '✅ Copiado!';
          setTimeout(() => btn.innerHTML = orig, 2000);
        });
      }
      </script>
    <?php endif; endif; ?>

    <?php if ($me['role'] === 'cliente' && $cob['status'] === 'aberta'): ?>
      <h2 class="mt-5">Enviar comprovante</h2>
      <div class="card">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="enviar_comprovante">
          <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
          <div class="field"><label>Arquivo (PDF/JPG/PNG ≤5MB)</label><input type="file" name="comprovante" required accept=".pdf,.jpg,.jpeg,.png"></div>
          <div class="grid-2">
            <div class="field"><label>Data</label><input type="date" name="data" required value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="field"><label>Valor (<?= e($cob['moeda']) ?>)</label><input type="number" step="0.01" min="0.01" name="valor" required value="<?= e(number_format($saldo, 2, '.', '')) ?>"></div>
          </div>
          <div class="field"><label>Método</label>
            <select name="metodo"><option value="">—</option><option>Pix</option><option>Transferência</option><option>Boleto</option><option>Outro</option></select>
          </div>
          <button class="btn block" type="submit">Enviar comprovante</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($pagamentos): ?>
    <details class="mt-5">
      <summary class="muted" style="cursor:pointer; padding:var(--s-3);">Pagamentos registrados (<?= count($pagamentos) ?>)</summary>
      <?php foreach ($pagamentos as $p):
        $is_pendente = isset($p['pendente']) && (int)$p['pendente'] === 1;
      ?>
        <div class="card mt-3<?= $is_pendente ? ' attention' : '' ?>">
          <div class="spaced">
            <div>
              <div class="title">
                <?= e(date('d/m/Y', strtotime($p['data_pagamento']))) ?> · <?= e($p['metodo'] ?? '—') ?>
                <?php if ($is_pendente): ?><span class="status status-destaque">aguardando confirmação</span><?php endif; ?>
              </div>
              <div class="sub muted">Por <?= e($p['registrado_por_nome']) ?><?= $p['observacao'] ? ' · ' . e($p['observacao']) : '' ?></div>
            </div>
            <div class="money md"><?= e(money_fmt((float)$p['valor_pago'], $cob['moeda'])) ?></div>
          </div>
          <div class="btn-pair mt-3">
            <?php if ($p['comprovante_path']): ?>
              <a class="btn btn-ghost small" href="?baixar_comprovante=<?= (int)$p['id'] ?>" target="_blank">📎 Ver comprovante</a>
            <?php endif; ?>
          </div>
          <?php if (is_admin() && $is_pendente): ?>
            <div class="btn-pair mt-3">
              <form method="post" style="flex:1;" onsubmit="return confirm('Aceitar este comprovante e marcar a cobrança como paga?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="op" value="aceitar_comprovante">
                <input type="hidden" name="pagamento_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-success block" type="submit">✓ Aceitar comprovante</button>
              </form>
              <form method="post" style="flex:1;" onsubmit="return confirm('Rejeitar este comprovante? O arquivo será apagado.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="op" value="rejeitar_comprovante">
                <input type="hidden" name="pagamento_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-danger block" type="submit">✕ Rejeitar</button>
              </form>
            </div>
          <?php elseif (is_admin() && !$is_pendente): ?>
            <form method="post" class="mt-3" onsubmit="return confirm('Remover este pagamento?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="op" value="remover_pagamento">
              <input type="hidden" name="pagamento_id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-ghost small" type="submit">✕ Remover pagamento</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </details>
    <?php endif; ?>

    <a class="btn btn-ghost block mt-5" href="<?= e(APP_BASE_URL) ?>/recibo.php?cobranca=<?= (int)$cob['id'] ?>" target="_blank">📄 Ver recibo (PDF)</a>

    <?php if (is_admin()): ?>
      <details class="mt-5">
        <summary class="muted" style="cursor:pointer; padding:var(--s-3);">⚠ Zona de perigo</summary>
        <div class="mt-3">
          <?php if ($cob['status'] !== 'cancelada'): ?>
            <form method="post" class="mb-3" onsubmit="return confirm('Cancelar esta cobrança? (Pode ser reaberta depois)');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="op" value="cancelar">
              <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
              <button class="btn btn-ghost block" type="submit">Cancelar cobrança</button>
            </form>
          <?php else: ?>
            <form method="post" class="mb-3">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="op" value="reabrir">
              <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
              <button class="btn block" type="submit">Reabrir cobrança</button>
            </form>
          <?php endif; ?>

          <form method="post" onsubmit="return confirm('APAGAR DEFINITIVAMENTE esta cobrança?\n\nTodos os itens, pagamentos e comprovantes serão removidos. Não dá pra desfazer.\n\nConfirmar?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="apagar">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <button class="btn btn-danger block" type="submit">🗑 Apagar definitivamente</button>
          </form>
        </div>
      </details>
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
    // EXISTS é mais barato que IN (SELECT) — para de buscar no primeiro match.
    // Com os índices da migration 015, a checagem fica em O(log n) por cobrança.
    $where[] = 'EXISTS (
        SELECT 1 FROM cobranca_itens ci
        JOIN assinaturas a ON a.id = ci.assinatura_id
        WHERE ci.cobranca_id = c.id AND a.funcionario_id = ?
    )';
    $params[] = (int)$me['id'];
}
if (in_array($f_status, ['aberta','em_analise','paga','cancelada'], true)) {
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
    <summary><strong>➕ Nova cobrança avulsa</strong> (itens livres)</summary>
    <form method="post" class="mt-3" id="form_avulsa">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="nova_avulsa">
      <div class="grid-2">
        <div class="field"><label>Cliente</label>
          <select name="cliente_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($cls as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['nome_empresa']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Vencimento</label><input type="date" name="vencimento" value="<?= e(date('Y-m-d', strtotime('+5 days'))) ?>" required></div>
      </div>
      <div class="field"><label>Competência (YYYY-MM)</label><input name="competencia" value="<?= e(date('Y-m')) ?>" pattern="\d{4}-\d{2}" required></div>

      <div class="section-label">Itens</div>
      <div id="lista_itens"></div>
      <button type="button" class="btn btn-ghost block" onclick="adicionarLinhaItem()">+ Adicionar item</button>

      <button class="btn block mt-3" type="submit">Criar cobrança</button>
    </form>
    <script>
    function adicionarLinhaItem() {
      const lista = document.getElementById('lista_itens');
      const linha = document.createElement('div');
      linha.className = 'card';
      linha.style.position = 'relative';
      linha.innerHTML = `
        <button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:8px;right:8px;background:transparent;border:0;color:var(--c-danger);cursor:pointer;font-size:16px;">✕</button>
        <div class="field"><label>Descrição</label><input name="descricao[]" required placeholder="Ex: Criação extra de criativo"></div>
        <div class="grid-2">
          <div class="field"><label>Quantidade</label><input type="number" min="1" name="quantidade[]" value="1" required></div>
          <div class="field"><label>Valor unitário</label><input type="number" step="0.01" min="0.01" name="valor[]" required></div>
        </div>`;
      lista.appendChild(linha);
    }
    adicionarLinhaItem();
    </script>
  </details>

  <details class="card mt-3">
    <summary><strong>🔧 Gerar cobrança mensal automática</strong> (testar cron)</summary>
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
          <option value="aberta"     <?= $f_status==='aberta'?'selected':'' ?>>Aberta</option>
          <option value="em_analise" <?= $f_status==='em_analise'?'selected':'' ?>>Em análise</option>
          <option value="paga"       <?= $f_status==='paga'?'selected':'' ?>>Paga</option>
          <option value="cancelada"  <?= $f_status==='cancelada'?'selected':'' ?>>Cancelada</option>
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
