<?php
/**
 * Endpoint AJAX de busca global.
 * Procura em clientes, cobrancas, funcionarios e assinaturas.
 * Retorna JSON com até 20 resultados, agrupados por tipo.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
$me = require_login();
$db = db();

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'q' => $q, 'resultados' => []]); exit;
}

$qlike = '%' . $q . '%';
$resultados = [];
$base = APP_BASE_URL;

// CLIENTES — sadmin/admin/funcionário veem
if (is_admin() || $me['role'] === 'funcionario') {
    try {
        $sql = "SELECT id, nome_empresa, nome_contato, email, telefone FROM clientes
                WHERE nome_empresa LIKE ? OR nome_contato LIKE ? OR email LIKE ? OR telefone LIKE ?
                LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute([$qlike, $qlike, $qlike, $qlike]);
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = [
                'tipo'    => 'cliente',
                'icone'   => '👥',
                'titulo'  => $r['nome_empresa'] ?: $r['nome_contato'],
                'sub'     => trim(($r['nome_contato'] ? $r['nome_contato'] : '') . ($r['email'] ? ' · ' . $r['email'] : '')),
                'href'    => $base . '/clientes.php?acao=editar&id=' . (int)$r['id'],
            ];
        }
    } catch (Throwable $e) {}
}

// COBRANÇAS — sadmin/admin vê todas, cliente só as dele
if (is_admin()) {
    try {
        $sql = "SELECT c.id, c.competencia_mes, c.valor_total, c.moeda, c.status, c.vencimento, cl.nome_empresa
                FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
                WHERE cl.nome_empresa LIKE ? OR c.competencia_mes LIKE ?
                ORDER BY c.vencimento DESC LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute([$qlike, $qlike]);
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = [
                'tipo'   => 'cobranca',
                'icone'  => '💳',
                'titulo' => $r['nome_empresa'] . ' · ' . $r['competencia_mes'],
                'sub'    => $r['moeda'] . ' ' . number_format((float)$r['valor_total'], 2, ',', '.') . ' · ' . $r['status'] . ' · vence ' . date('d/m/Y', strtotime($r['vencimento'])),
                'href'   => $base . '/cobrancas.php?id=' . (int)$r['id'],
            ];
        }
    } catch (Throwable $e) {}
} elseif ($me['role'] === 'cliente' && !empty($me['cliente_id'])) {
    try {
        $stmt = $db->prepare("SELECT id, competencia_mes, valor_total, moeda, status, vencimento
                              FROM cobrancas WHERE cliente_id = ? AND competencia_mes LIKE ?
                              ORDER BY vencimento DESC LIMIT 5");
        $stmt->execute([(int)$me['cliente_id'], $qlike]);
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = [
                'tipo'   => 'cobranca',
                'icone'  => '💳',
                'titulo' => 'Cobrança ' . $r['competencia_mes'],
                'sub'    => $r['moeda'] . ' ' . number_format((float)$r['valor_total'], 2, ',', '.') . ' · ' . $r['status'],
                'href'   => $base . '/cobrancas.php?id=' . (int)$r['id'],
            ];
        }
    } catch (Throwable $e) {}
}

// FUNCIONÁRIOS — só admin/sadmin
if (is_admin()) {
    try {
        $sql = "SELECT id, nome, email, role, wisetag FROM usuarios
                WHERE role IN ('sadmin','admin','funcionario') AND (nome LIKE ? OR email LIKE ? OR wisetag LIKE ?)
                LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute([$qlike, $qlike, $qlike]);
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = [
                'tipo'   => 'funcionario',
                'icone'  => '🧑‍💼',
                'titulo' => $r['nome'] . ' (' . $r['role'] . ')',
                'sub'    => $r['email'] . ($r['wisetag'] ? ' · ' . $r['wisetag'] : ''),
                'href'   => $base . '/funcionarios.php?acao=editar&id=' . (int)$r['id'],
            ];
        }
    } catch (Throwable $e) {}
}

// ITENS DO CATÁLOGO — só sadmin
if (is_sadmin()) {
    try {
        $sql = "SELECT id, nome, tipo, preco_usd FROM itens_catalogo WHERE nome LIKE ? LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute([$qlike]);
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = [
                'tipo'   => 'item',
                'icone'  => '📦',
                'titulo' => $r['nome'],
                'sub'    => $r['tipo'] . ($r['preco_usd'] ? ' · $ ' . number_format((float)$r['preco_usd'], 2, '.', ',') : ''),
                'href'   => $base . '/catalogo.php?acao=editar&id=' . (int)$r['id'],
            ];
        }
    } catch (Throwable $e) {}
}

echo json_encode(['ok' => true, 'q' => $q, 'resultados' => $resultados]);
