<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/hard_delete.php';
$me = require_login();
if ($me['role'] === 'cliente') { header('Location: ' . APP_BASE_URL . '/dashboard.php'); exit; }
$func_view_only = $me['role'] === 'funcionario'; // funcionário vê só seus clientes, read-only
if (!is_admin() && !$func_view_only) { http_response_code(403); exit('Acesso negado.'); }
$db = db();

$acao  = $_GET['acao'] ?? 'lista';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin()) { http_response_code(403); exit('Apenas admin pode modificar.'); }
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'salvar') {
        $pid       = (int)($_POST['id'] ?? 0);
        $nome_emp  = trim((string)($_POST['nome_empresa'] ?? ''));
        $nome_cnt  = trim((string)($_POST['nome_contato'] ?? '')) ?: null;
        $doc       = trim((string)($_POST['documento'] ?? '')) ?: null;
        $email     = trim((string)($_POST['email'] ?? '')) ?: null;
        $moeda     = in_array($_POST['moeda'] ?? '', ['USD','BRL','EUR'], true) ? $_POST['moeda'] : 'BRL';
        $tel       = trim((string)($_POST['telefone'] ?? '')) ?: null;
        $end       = trim((string)($_POST['endereco'] ?? '')) ?: null;
        $link_grp  = trim((string)($_POST['link_grupo'] ?? '')) ?: null;
        $obs       = trim((string)($_POST['observacoes'] ?? '')) ?: null;
        $ativo     = isset($_POST['ativo']) ? 1 : 0;
        // dia_cobranca: dia do mês (1-31) em que o cliente paga. Cron gera a
        // cobrança 7 dias antes desse dia. Se vazio → NULL (cron pula esse cliente).
        $dia_cob_raw = trim((string)($_POST['dia_cobranca'] ?? ''));
        $dia_cob   = ($dia_cob_raw === '') ? null : max(1, min(31, (int)$dia_cob_raw));
        if ($nome_emp === '') {
            $flash = ['err','Nome da empresa é obrigatório.'];
            $acao = $pid ? 'editar' : 'novo'; $id = $pid;
        } elseif ($pid) {
            // Detecta mudança de moeda — vai recalcular valor_cobrado das assinaturas ativas
            $stmt = $db->prepare('SELECT moeda FROM clientes WHERE id = ?');
            $stmt->execute([$pid]);
            $moeda_antiga = $stmt->fetchColumn();
            $stmt = $db->prepare('UPDATE clientes SET nome=?, nome_empresa=?, nome_contato=?, documento=?, email=?, moeda=?, telefone=?, endereco=?, link_grupo=?, observacoes=?, dia_cobranca=?, ativo=? WHERE id=?');
            $stmt->execute([$nome_emp, $nome_emp, $nome_cnt, $doc, $email, $moeda, $tel, $end, $link_grp, $obs, $dia_cob, $ativo, $pid]);
            audit_log('cliente.editado', 'clientes', $pid);

            $extra_query = '';
            if ($moeda_antiga && $moeda_antiga !== $moeda) {
                // USD é a moeda mestre do Dite Ads. Pra outras moedas, converte
                // preco_usd do catálogo usando a cotação do dia.
                require_once __DIR__ . '/lib/cotacao.php';
                $cot = cotacao_atual($db);
                $stmt = $db->prepare("
                    SELECT a.id, a.variante, i.nome AS item_nome, i.preco_usd, i.preco_ia_usd
                    FROM assinaturas a
                    JOIN itens_catalogo i ON i.id = a.item_id
                    WHERE a.cliente_id = ? AND a.status = 'ativa'
                ");
                $stmt->execute([$pid]);
                $atualizadas = 0; $sem_preco = [];
                $upd = $db->prepare('UPDATE assinaturas SET valor_cobrado = ? WHERE id = ?');
                foreach ($stmt->fetchAll() as $a) {
                    $preco_usd = $a['variante'] === 'ia'
                        ? ($a['preco_ia_usd'] !== null ? (float)$a['preco_ia_usd'] : null)
                        : ($a['preco_usd']    !== null ? (float)$a['preco_usd']    : null);
                    if ($preco_usd === null || $preco_usd <= 0) {
                        $sem_preco[] = $a['item_nome'];
                        continue;
                    }
                    $novo = $moeda === 'USD' ? $preco_usd : usd_para($db, $preco_usd, $moeda);
                    $upd->execute([$novo, (int)$a['id']]);
                    $atualizadas++;
                }
                $msg_parts = [];
                if ($atualizadas) {
                    $info_cot = $moeda === 'USD' ? '' : sprintf(' (cotação USD→%s: %.4f de %s)', $moeda, $cot[$moeda] ?? 0, $cot['data'] ?? '?');
                    $msg_parts[] = $atualizadas . ' assinatura(s) com valor atualizado' . $info_cot;
                }
                if ($sem_preco)   $msg_parts[] = count($sem_preco) . ' sem preço USD no catálogo: ' . implode(', ', $sem_preco);
                if ($msg_parts)   $extra_query = '&moeda_msg=' . urlencode(implode(' · ', $msg_parts));
                audit_log('cliente.moeda_alterada', 'clientes', $pid);
            }

            header('Location: ' . APP_BASE_URL . '/clientes.php?acao=editar&id=' . $pid . '&ok=upd' . $extra_query); exit;
        } else {
            $stmt = $db->prepare('INSERT INTO clientes (nome, nome_empresa, nome_contato, documento, email, moeda, telefone, endereco, link_grupo, observacoes, dia_cobranca, ativo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$nome_emp, $nome_emp, $nome_cnt, $doc, $email, $moeda, $tel, $end, $link_grp, $obs, $dia_cob, $ativo]);
            $newId = (int)$db->lastInsertId();
            audit_log('cliente.criado', 'clientes', $newId);
            header('Location: ' . APP_BASE_URL . '/clientes.php?acao=editar&id=' . $newId . '&ok=add'); exit;
        }
    }

    if ($op === 'apagar') {
        if (!is_sadmin()) { http_response_code(403); exit('Apenas Super Admin pode apagar clientes em cascata.'); }
        $pid = (int)($_POST['id'] ?? 0);
        try {
            hard_delete_cliente($db, $pid);
            audit_log('cliente.apagado_cascade', 'clientes', $pid);
            header('Location: ' . APP_BASE_URL . '/clientes.php?ok=del'); exit;
        } catch (Throwable $e) {
            $flash = ['err', 'Erro ao apagar: ' . $e->getMessage()];
            $acao = 'editar'; $id = $pid;
        }
    }

    if ($op === 'criar_login') {
        $cid    = (int)($_POST['cliente_id'] ?? 0);
        $email  = trim((string)($_POST['login_email'] ?? ''));
        $senha  = (string)($_POST['login_senha'] ?? '');
        if ($cid && $email && $senha !== '') {
            try {
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha_hash, role, cliente_id, ativo) SELECT COALESCE(nome_contato, nome_empresa), ?, ?, 'cliente', id, 1 FROM clientes WHERE id = ?");
                $stmt->execute([$email, password_hash($senha, PASSWORD_DEFAULT), $cid]);
                audit_log('cliente.login_criado', 'clientes', $cid);
            } catch (PDOException $e) {
                $flash = ['err', (int)$e->errorInfo[1] === 1062 ? 'Já existe usuário com este email.' : 'Erro: ' . $e->getMessage()];
                $acao = 'editar'; $id = $cid;
            }
        }
        if (!$flash) { header('Location: ' . APP_BASE_URL . '/clientes.php?acao=editar&id=' . $cid . '&ok=login'); exit; }
    }
}

if (isset($_GET['ok'])) {
    $msgs = ['add'=>'Cliente criado.','upd'=>'Cliente atualizado.','login'=>'Login do cliente criado.'];
    $msg = $msgs[$_GET['ok']] ?? 'OK.';
    if (!empty($_GET['moeda_msg'])) $msg .= ' Moeda alterada: ' . $_GET['moeda_msg'];
    $flash = ['ok', $msg];
}

$page = 'Clientes';
$nav_active = 'clientes';

if (($acao === 'novo' || $acao === 'editar') && !is_admin()) {
    http_response_code(403); exit('Apenas admin pode criar/editar clientes.');
}

if ($acao === 'novo' || $acao === 'editar') {
    $show_back = true;
    $back_to = APP_BASE_URL . '/clientes.php';
    $c = ['id'=>0,'nome_empresa'=>'','nome_contato'=>'','documento'=>'','email'=>'','moeda'=>'BRL','telefone'=>'','endereco'=>'','link_grupo'=>'','observacoes'=>'','dia_cobranca'=>null,'ativo'=>1];
    if ($acao === 'editar' && $id) {
        $stmt = $db->prepare('SELECT * FROM clientes WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $c = array_merge($c, $row);
    }
    $userCliente = null;
    if ($c['id']) {
        $stmt = $db->prepare("SELECT id, email FROM usuarios WHERE cliente_id=? AND role='cliente' LIMIT 1");
        $stmt->execute([$c['id']]);
        $userCliente = $stmt->fetch() ?: null;
    }
    $page = $c['id'] ? 'Editar cliente' : 'Novo cliente';
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <div class="card">
        <div class="field"><label>Nome da empresa *</label><input name="nome_empresa" required value="<?= e($c['nome_empresa']) ?>"></div>
        <div class="field"><label>Nome do contato</label><input name="nome_contato" value="<?= e($c['nome_contato']) ?>"></div>
        <div class="field"><label>Documento (CNPJ/CPF)</label><input name="documento" value="<?= e($c['documento']) ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($c['email']) ?>"></div>
        <div class="field"><label>Moeda do cliente *</label>
          <select name="moeda" required>
            <?php foreach (['BRL'=>'Real (R$)','USD'=>'Dólar ($)','EUR'=>'Euro (€)'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $c['moeda']===$k?'selected':'' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Dia de vencimento (1 a 31)</label>
          <input type="number" name="dia_cobranca" min="1" max="31" value="<?= $c['dia_cobranca'] !== null ? (int)$c['dia_cobranca'] : '' ?>" placeholder="ex: 10">
          <div class="hint">Dia do mês em que este cliente paga. O sistema gera a cobrança automaticamente <strong>7 dias antes</strong> desse dia. Deixe em branco pra não gerar automático.</div>
        </div>
        <div class="field"><label>Telefone (com DDI)</label><input name="telefone" value="<?= e($c['telefone']) ?>" placeholder="+55 11 99999-9999"></div>
        <div class="field"><label>Endereço</label><input name="endereco" value="<?= e($c['endereco']) ?>"></div>
        <div class="field"><label>Link do grupo (WhatsApp/Telegram)</label><input name="link_grupo" value="<?= e($c['link_grupo']) ?>"></div>
        <div class="field"><label>Observações</label><textarea name="observacoes"><?= e($c['observacoes']) ?></textarea></div>
        <label class="check"><input type="checkbox" name="ativo" <?= $c['ativo']?'checked':'' ?>> Cliente ativo</label>
      </div>
      <button class="btn block" type="submit">Salvar cliente</button>
    </form>

    <?php if ($c['id']): ?>
      <h2>Assinaturas</h2>
      <a class="btn btn-secondary block" href="<?= e(APP_BASE_URL) ?>/assinaturas.php?cliente_id=<?= (int)$c['id'] ?>">Ver e atribuir itens →</a>

      <?php if (is_sadmin()): ?>
      <details class="mt-5">
        <summary class="muted" style="cursor:pointer; padding:var(--s-3);">⚠ Zona de perigo</summary>
        <form method="post" class="mt-3" onsubmit="return confirm('APAGAR DEFINITIVAMENTE este cliente?\n\n⚠ Vai apagar EM CASCATA tudo ligado a ele:\n  • todas as cobranças (e pagamentos recebidos)\n  • todas as assinaturas (e entregas)\n  • o login de cliente, se houver\n\nIsto NÃO pode ser desfeito. Para preservar histórico, desative em vez de apagar.\n\nConfirmar?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="op" value="apagar">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-danger block" type="submit">🗑 Apagar em cascata</button>
        </form>
      </details>
      <?php endif; ?>

      <h2>Acesso ao sistema</h2>
      <div class="card">
        <?php if ($userCliente): ?>
          <div class="title">✅ Login ativo</div>
          <div class="desc"><?= e($userCliente['email']) ?></div>
          <p class="muted mt-3">Para resetar senha do cliente, peça pra ele usar "Esqueci minha senha" no login.</p>
        <?php else: ?>
          <p class="muted">Crie um login para o cliente acessar o sistema e ver as cobranças.</p>
          <form method="post" class="mt-3">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="criar_login">
            <input type="hidden" name="cliente_id" value="<?= (int)$c['id'] ?>">
            <div class="field"><label>Email de login</label><input type="email" name="login_email" required value="<?= e($c['email']) ?>"></div>
            <div class="field">
              <label>Senha inicial</label>
              <div class="field-password">
                <input type="password" name="login_senha" required>
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Mostrar senha">👁</button>
              </div>
            </div>
            <button class="btn block" type="submit">Criar login</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

require __DIR__ . '/includes/header.php';
if ($func_view_only) {
    // Funcionário vê só clientes que atende via assinaturas ativas
    $stmt = $db->prepare('
        SELECT DISTINCT cl.id, cl.nome_empresa, cl.nome_contato, cl.moeda, cl.ativo,
               (SELECT COUNT(*) FROM assinaturas a2 WHERE a2.cliente_id=cl.id AND a2.funcionario_id=? AND a2.status="ativa") AS qtd_assin,
               0 AS tem_login
        FROM clientes cl
        JOIN assinaturas a ON a.cliente_id = cl.id
        WHERE a.funcionario_id = ? AND cl.ativo = 1
        ORDER BY cl.nome_empresa');
    $stmt->execute([(int)$me['id'], (int)$me['id']]);
    $clientes = $stmt->fetchAll();
} else {
    $clientes = $db->query('
        SELECT cl.id, cl.nome_empresa, cl.nome_contato, cl.moeda, cl.ativo,
               (SELECT COUNT(*) FROM usuarios u WHERE u.cliente_id=cl.id AND u.role="cliente") AS tem_login
        FROM clientes cl ORDER BY cl.nome_empresa, cl.nome
    ')->fetchAll();
}
?>
<h1 class="page-title"><?= $func_view_only ? 'Meus clientes' : 'Clientes' ?></h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php if (is_admin()): ?>
<div class="btn-pair">
  <a href="?acao=novo" class="btn btn-brand">+ Novo</a>
  <a href="<?= e(APP_BASE_URL) ?>/convites.php" class="btn btn-secondary">✉️ Convidar</a>
</div>
<?php endif; ?>
<div class="section-label mt-5"><?= $func_view_only ? 'Que você atende' : 'Cadastrados' ?> (<?= count($clientes) ?>)</div>
<?php foreach ($clientes as $cl): ?>
  <?php if ($func_view_only): ?>
    <a class="list-card" href="<?= e(APP_BASE_URL) ?>/agenda.php?cliente_id=<?= (int)$cl['id'] ?>">
      <div class="info">
        <div class="nome"><?= e($cl['nome_empresa'] ?: '(sem nome)') ?></div>
        <div class="sub"><?= e($cl['nome_contato'] ?? '—') ?> · <?= e($cl['moeda']) ?> · <?= (int)$cl['qtd_assin'] ?> serviço<?= $cl['qtd_assin']==1?'':'s' ?></div>
      </div>
    </a>
  <?php else: ?>
    <a class="list-card" href="?acao=editar&id=<?= (int)$cl['id'] ?>">
      <div class="info">
        <div class="nome">
          <?= e($cl['nome_empresa'] ?: '(sem nome)') ?>
          <?php if (!$cl['ativo']): ?><span class="status status-info">inativo</span><?php endif; ?>
        </div>
        <div class="sub">
          <?= e($cl['nome_contato'] ?? '—') ?> · <?= e($cl['moeda']) ?>
          <?php if ($cl['tem_login']): ?> · <span class="status status-paga">login</span><?php endif; ?>
        </div>
      </div>
    </a>
  <?php endif; ?>
<?php endforeach; ?>
<?php if (!$clientes): ?>
  <p class="muted center mt-5"><?= $func_view_only ? 'Você ainda não tem clientes atribuídos.' : 'Nenhum cliente. Use "+ Novo" ou "Convidar".' ?></p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
