<?php
/**
 * Lista pública de planos pro Dite Gateway (campo "URL de planos" do painel Apps).
 * URL: https://cont.diteads.com/api/plans  (rota no .htaccess -> este arquivo)
 *
 * Retorna os itens MENSAIS do catálogo como planos, em USD (moeda mestre).
 * Formato: { "data": [ { id, name, amount, currency, interval } ] }
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $db = db();
    $stmt = $db->query(
        "SELECT id, nome, preco_usd
         FROM itens_catalogo
         WHERE ativo = 1 AND tipo = 'mensal' AND preco_usd IS NOT NULL AND preco_usd > 0
         ORDER BY nome"
    );
    $plans = [];
    foreach ($stmt->fetchAll() as $r) {
        $plans[] = [
            'id'       => (int)$r['id'],
            'name'     => $r['nome'],
            'amount'   => (float)$r['preco_usd'],
            'currency' => 'USD',
            'interval' => 'month',
        ];
    }
    echo json_encode(['data' => $plans], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'erro interno']);
}
