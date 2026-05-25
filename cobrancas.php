<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_login();
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

function carrega_cobranca(PDO $db, int $id): ?array {
    $stmt = $db->prepare('SELECT * FROM cobrancas WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function total_pago(PDO $db, int $cobranca_id): float {
    $stmt = $db->prepare('SELECT COALESCE(SUM(valor_pago), 0) FROM pagamentos WHERE cobranca_id = ?');
    $stmt->execute([$cobranca_id]);
    return (float)$stmt->fetchColumn();
}

function pode_ver_cobranca(array $c, array $me): bool {
    if ($me['role'] === 'admin') return true;
    if ($me['role'] === 'funcionario') return (int)$c['funcionario_id'] === (int)$me['id'];
    if ($me['role'] === 'cliente')     return (int)$c['cliente_id']     === (int)$me['cliente_id'];
    return false;
}

function atualiza_status_cobranca(PDO $db, int $cobranca_id): void {
    $stmt = $db->prepare('SELECT valor, status FROM cobrancas WHERE id=?');
    $stmt->execute([$cobranca_id]);
    $c = $stmt->fetch();
    if (!$c || $c['status'] === 'cancelada') return;
    $pago = total_pago($db, $cobranca_id);
    $novo = $pago >= (float)$c['valor'] ? 'paga' : 'aberta';
    if ($novo !== $c['status']) {
        $stmt = $db->prepare('UPDATE cobrancas SET status=? WHERE id=?');
        $stmt->execute([$novo, $cobranca_id]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'salvar_cobranca') {
        if (!is_admin()) { http_response_code(403); exit('Apenas admin.'); }
        $pid       = (int)($_POST['id'] ?? 0);
        $cliente   = (int)($_POST['cliente_id'] ?? 0);
        $servico   = (int)($_POST['servico_id'] ?? 0) ?: null;
        $funci     = (int)($_POST['funcionario_id'] ?? 0) ?: null;
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $valor     = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
        $vencimento= trim((string)($_POST['vencimento'] ?? '')) ?: null;

        if ($descricao === '' || !$cliente || $valor <= 0) {
            $flash = ['err', 'Descrição, cliente e valor (>0) são obrigatórios.'];
            $acao = $pid ? 'editar' : 'novo';
            $id = $pid;
        } elseif ($pid) {
            $stmt = $db->prepare('UPDATE cobrancas SET cliente_id=?, servico_id=?, funcionario_id=?, descricao=?, valor=?, vencimento=? WHERE id=?');
            $stmt->execute([$cliente, $servico, $funci, $descricao, $valor, $vencimento, $pid]);
            atualiza_status_cobranca($db, $pid);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $pid); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO cobrancas (cliente_id, servico_id, funcionario_id, descricao, valor, vencimento, criado_por) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$cliente, $servico, $funci, $descricao, $valor, $vencimento, $me['id']]);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $db->lastInsertId()); exit;
        }
    }

    if ($op === 'cancelar') {
        if (!is_admin()) { http_response_code(403); exit('Apenas admin.'); }
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE cobrancas SET status='cancelada' WHERE id=?");
        $stmt->execute([$cid]);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'reabrir') {
        if (!is_admin()) { http_response_code(403); exit('Apenas admin.'); }
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE cobrancas SET status='aberta' WHERE id=?");
        $stmt->execute([$cid]);
        atualiza_status_cobranca($db, $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'add_pagamento') {
        if (!is_admin()) { http_response_code(403); exit('Apenas admin pode registrar pagamentos.'); }
        $cid = (int)($_POST['cobranca_id'] ?? 0);
        $c   = carrega_cobranca($db, $cid);
        if (!$c) { http_response_code(404); exit('Cobrança não encontrada.'); }

        $valor   = (float)str_replace(',', '.', (string)($_POST['valor_pago'] ?? '0'));
        $data    = trim((string)($_POST['data_pagamento'] ?? '')) ?: date('Y-m-d');
        $metodo  = trim((string)($_POST['metodo'] ?? '')) ?: null;
        $obs     = trim((string)($_POST['observacao'] ?? '')) ?: null;

        if ($valor <= 0) {
            $flash = ['err', 'Valor do pagamento deve ser maior que zero.'];
            $acao = 'ver'; $id = $cid;
        } else {
            $stmt = $db->prepare('INSERT INTO pagamentos (cobranca_id, valor_pago, data_pagamento, metodo, observacao, registrado_por) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$cid, $valor, $data, $metodo, $obs, $me['id']]);
            atualiza_status_cobranca($db, $cid);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
        }
    }

    if ($op === 'remove_pagamento') {
        if (!is_admin()) { http_response_code(403); exit('Apenas admin.'); }
        $pid = (int)($_POST['pagamento_id'] ?? 0);
        $stmt = $db->prepare('SELECT cobranca_id FROM pagamentos WHERE id=?');
        $stmt->execute([$pid]);
        $cid = (int)$stmt->fetchColumn();
        $stmt = $db->prepare('DELETE FROM pagamentos WHERE id=?');
        $stmt->execute([$pid]);
        if ($cid) atualiza_status_cobranca($db, $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }
}

if ($id && $acao === 'lista') $acao = 'ver';

$page = 'Cobranças';
require __DIR__ . '/includes/header.php';

if ($acao === 'novo' || $acao === 'editar') {
    if (!is_admin()) { http_response_code(403); exit('Apenas admin.'); }
    $c = ['id'=>0, 'cliente_id'=>0, 'servico_id'=>0, 'funcionario_id'=>0, 'descricao'=>'', 'valor'=>'', 'vencimento'=>''];
    if ($acao === 'editar' && $id) $c = carrega_cobranca($db, $id) ?: $c;
    $clientes = $db->query('SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome')->fetchAll();
    $servicos = $db->query('SELECT id, nome, valor_padrao FROM servicos WHERE ativo=1 ORDER BY nome')->fetchAll();
    $funcs    = $db->query("SELECT id, nome FROM usuarios WHERE ativo=1 AND role IN ('admin','funcionario') ORDER BY nome")->fetchAll();
    ?>
    <h1><?= $c['id'] ? 'Editar cobrança' : 'Nova cobrança' ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="salvar_cobranca">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="grid-2">
          <div class="field"><label>Cliente *</label><select name="cliente_id" required>
            <option value="">— selecione —</option>
            <?php foreach ($clientes as $cli): ?>
              <option value="<?= (int)$cli['id'] ?>" <?= $c['cliente_id']==$cli['id']?'selected':'' ?>><?= e($cli['nome']) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="field"><label>Serviço</label><select name="servico_id" id="servico-select">
            <option value="">—</option>
            <?php foreach ($servicos as $s): ?>
              <option value="<?= (int)$s['id'] ?>" data-valor="<?= $s['valor_padrao'] !== null ? e(number_format((float)$s['valor_padrao'], 2, '.', '')) : '' ?>" <?= $c['servico_id']==$s['id']?'selected':'' ?>><?= e($s['nome']) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="field"><label>Funcionário responsável (comissão)</label><select name="funcionario_id">
            <option value="">—</option>
            <?php foreach ($funcs as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= $c['funcionario_id']==$f['id']?'selected':'' ?>><?= e($f['nome']) ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="field"><label>Vencimento</label><input type="date" name="vencimento" value="<?= e($c['vencimento']) ?>"></div>
        </div>
        <div class="field"><label>Descrição *</label><input name="descricao" required value="<?= e($c['descricao']) ?>" placeholder="Ex: Gestão de tráfego — Maio/2026"></div>
        <div class="field"><label>Valor (R$) *</label><input type="number" step="0.01" min="0.01" name="valor" id="valor-input" required value="<?= $c['valor'] !== '' ? e(number_format((float)$c['valor'], 2, '.', '')) : '' ?>"></div>
        <div class="actions">
          <button class="btn" type="submit">Salvar</button>
          <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">Cancelar</a>
        </div>
      </form>
    </div>
    <script>
    document.getElementById('servico-select')?.addEventListener('change', function(){
      var v = this.selectedOptions[0]?.dataset.valor;
      var input = document.getElementById('valor-input');
      if (v && !input.value) input.value = v;
    });
    </script>
    <?php
}
elseif ($acao === 'ver' && $id) {
    $c = carrega_cobranca($db, $id);
    if (!$c || !pode_ver_cobranca($c, $me)) { http_response_code(404); echo '<h1>Cobrança não encontrada.</h1>'; require __DIR__ . '/includes/footer.php'; exit; }

    $stmt = $db->prepare('SELECT cl.nome AS cliente, s.nome AS servico, u.nome AS funcionario FROM cobrancas c LEFT JOIN clientes cl ON cl.id=c.cliente_id LEFT JOIN servicos s ON s.id=c.servico_id LEFT JOIN usuarios u ON u.id=c.funcionario_id WHERE c.id=?');
    $stmt->execute([$id]);
    $info = $stmt->fetch();

    $stmt = $db->prepare('SELECT p.*, u.nome AS registrado_por_nome FROM pagamentos p JOIN usuarios u ON u.id=p.registrado_por WHERE p.cobranca_id=? ORDER BY p.data_pagamento DESC, p.id DESC');
    $stmt->execute([$id]);
    $pagamentos = $stmt->fetchAll();

    $pago    = total_pago($db, $id);
    $saldo   = (float)$c['valor'] - $pago;
    $vencido = $c['status'] === 'aberta' && $c['vencimento'] && strtotime($c['vencimento']) < strtotime(date('Y-m-d'));
    ?>
    <h1>Cobrança #<?= (int)$c['id'] ?></h1>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <div class="card">
      <div class="grid-2">
        <div><strong>Cliente:</strong> <?= e($info['cliente']) ?></div>
        <div><strong>Serviço:</strong> <?= e($info['servico'] ?? '—') ?></div>
        <div><strong>Funcionário:</strong> <?= e($info['funcionario'] ?? '—') ?></div>
        <div><strong>Vencimento:</strong> <?= $c['vencimento'] ? e(date('d/m/Y', strtotime($c['vencimento']))) : '—' ?> <?= $vencido ? '<span class="status status-aberta">vencido</span>' : '' ?></div>
        <div><strong>Valor:</strong> R$ <?= number_format((float)$c['valor'], 2, ',', '.') ?></div>
        <div><strong>Status:</strong> <span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></div>
        <div><strong>Pago:</strong> R$ <?= number_format($pago, 2, ',', '.') ?></div>
        <div><strong>Saldo:</strong> R$ <?= number_format(max($saldo, 0), 2, ',', '.') ?></div>
      </div>
      <p style="margin-top:1rem;"><strong>Descrição:</strong> <?= e($c['descricao']) ?></p>

      <?php if (is_admin()): ?>
      <div class="actions" style="margin-top:1rem;">
        <a class="btn secondary" href="?acao=editar&id=<?= (int)$c['id'] ?>">Editar</a>
        <?php if ($c['status'] !== 'cancelada'): ?>
        <form method="post" style="display:inline;" onsubmit="return confirm('Cancelar esta cobrança?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="cancelar">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn danger" type="submit">Cancelar cobrança</button>
        </form>
        <?php else: ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="reabrir">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn" type="submit">Reabrir</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <h2>Pagamentos</h2>
    <?php if (is_admin() && $c['status'] !== 'cancelada'): ?>
    <div class="card">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="add_pagamento">
        <input type="hidden" name="cobranca_id" value="<?= (int)$c['id'] ?>">
        <div class="grid-2">
          <div class="field"><label>Valor (R$)</label><input type="number" step="0.01" min="0.01" name="valor_pago" required value="<?= $saldo > 0 ? e(number_format($saldo, 2, '.', '')) : '' ?>"></div>
          <div class="field"><label>Data</label><input type="date" name="data_pagamento" value="<?= e(date('Y-m-d')) ?>" required></div>
          <div class="field"><label>Método</label><select name="metodo">
            <option value="">—</option>
            <option>Pix</option>
            <option>Transferência</option>
            <option>Boleto</option>
            <option>Dinheiro</option>
            <option>Cartão</option>
            <option>Outro</option>
          </select></div>
        </div>
        <div class="field"><label>Observação</label><input name="observacao" placeholder="opcional"></div>
        <button class="btn" type="submit">Registrar pagamento</button>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($pagamentos): ?>
    <table>
      <thead><tr><th>Data</th><th>Valor</th><th>Método</th><th>Observação</th><th>Registrado por</th><?php if (is_admin()): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($pagamentos as $p): ?>
        <tr>
          <td><?= e(date('d/m/Y', strtotime($p['data_pagamento']))) ?></td>
          <td>R$ <?= number_format((float)$p['valor_pago'], 2, ',', '.') ?></td>
          <td><?= e($p['metodo'] ?? '—') ?></td>
          <td><?= e($p['observacao'] ?? '') ?></td>
          <td><?= e($p['registrado_por_nome']) ?></td>
          <?php if (is_admin()): ?>
          <td>
            <form method="post" style="display:inline;" onsubmit="return confirm('Remover este pagamento?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="op" value="remove_pagamento">
              <input type="hidden" name="pagamento_id" value="<?= (int)$p['id'] ?>">
              <button class="btn danger small" type="submit">Remover</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="muted">Nenhum pagamento registrado.</p>
    <?php endif; ?>
    <?php
}
else {
    $where  = ['1=1'];
    $params = [];
    if ($me['role'] === 'funcionario') {
        $where[] = 'c.funcionario_id = ?';
        $params[] = $me['id'];
    } elseif ($me['role'] === 'cliente') {
        $where[] = 'c.cliente_id = ?';
        $params[] = $me['cliente_id'];
    }
    $fStatus  = $_GET['status'] ?? '';
    $fCliente = (int)($_GET['cliente_id'] ?? 0);
    if (in_array($fStatus, ['aberta','paga','cancelada'], true)) {
        $where[] = 'c.status = ?'; $params[] = $fStatus;
    }
    if (is_admin() && $fCliente) { $where[] = 'c.cliente_id = ?'; $params[] = $fCliente; }

    $sql = 'SELECT c.id, c.descricao, c.valor, c.vencimento, c.status,
                   cl.nome AS cliente,
                   u.nome AS funcionario,
                   (SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos WHERE cobranca_id=c.id) AS pago
            FROM cobrancas c
            JOIN clientes cl ON cl.id = c.cliente_id
            LEFT JOIN usuarios u ON u.id = c.funcionario_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY (c.status="paga") ASC, c.vencimento IS NULL, c.vencimento ASC, c.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cobrancas = $stmt->fetchAll();
    $clientes = is_admin() ? $db->query('SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome')->fetchAll() : [];
    ?>
    <h1>Cobranças</h1>
    <?php if (is_admin()): ?><p><a class="btn" href="?acao=novo">+ Nova cobrança</a></p><?php endif; ?>

    <form method="get" class="card" style="display:flex; gap:.7rem; align-items:end; flex-wrap:wrap;">
      <div class="field" style="margin:0;"><label>Status</label>
        <select name="status">
          <option value="">— todas —</option>
          <?php foreach (['aberta','paga','cancelada'] as $s): ?>
            <option value="<?= $s ?>" <?= $fStatus===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (is_admin()): ?>
      <div class="field" style="margin:0;"><label>Cliente</label>
        <select name="cliente_id">
          <option value="0">— todos —</option>
          <?php foreach ($clientes as $cli): ?>
            <option value="<?= (int)$cli['id'] ?>" <?= $fCliente==$cli['id']?'selected':'' ?>><?= e($cli['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <button class="btn" type="submit">Filtrar</button>
      <a class="btn secondary" href="<?= e(APP_BASE_URL) ?>/cobrancas.php">Limpar</a>
    </form>

    <table>
      <thead><tr>
        <th>#</th><th>Descrição</th><?php if ($me['role'] !== 'cliente'): ?><th>Cliente</th><?php endif; ?>
        <?php if (is_admin()): ?><th>Funcionário</th><?php endif; ?>
        <th>Valor</th><th>Pago</th><th>Vencimento</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($cobrancas as $c):
        $vencido = $c['status'] === 'aberta' && $c['vencimento'] && strtotime($c['vencimento']) < strtotime(date('Y-m-d'));
      ?>
        <tr>
          <td>#<?= (int)$c['id'] ?></td>
          <td><?= e($c['descricao']) ?></td>
          <?php if ($me['role'] !== 'cliente'): ?><td><?= e($c['cliente']) ?></td><?php endif; ?>
          <?php if (is_admin()): ?><td><?= e($c['funcionario'] ?? '—') ?></td><?php endif; ?>
          <td>R$ <?= number_format((float)$c['valor'], 2, ',', '.') ?></td>
          <td>R$ <?= number_format((float)$c['pago'], 2, ',', '.') ?></td>
          <td><?= $c['vencimento'] ? e(date('d/m/Y', strtotime($c['vencimento']))) : '—' ?><?= $vencido ? ' ⚠️' : '' ?></td>
          <td><span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
          <td><a class="btn small" href="?id=<?= (int)$c['id'] ?>">Abrir</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cobrancas): ?>
        <tr><td colspan="9" class="muted">Nenhuma cobrança.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <?php
}
require __DIR__ . '/includes/footer.php';
