<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/**
 * Registra uma entrada no audit_log. Falha silenciosamente
 * (auditoria não deve quebrar a operação principal).
 *
 * @param string $acao  ex: 'cliente.criado', 'cobranca.paga'
 * @param string|null $entidade  ex: 'clientes', 'cobrancas'
 * @param int|null $entidade_id
 * @param array|null $antes  snapshot antes da mudança
 * @param array|null $depois snapshot depois
 */
function audit_log(string $acao, ?string $entidade = null, ?int $entidade_id = null, ?array $antes = null, ?array $depois = null): void {
    try {
        $uid = $_SESSION['user_id'] ?? null;
        $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = db()->prepare('INSERT INTO audit_log (usuario_id, acao, entidade, entidade_id, payload_antes, payload_depois, ip) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $uid,
            $acao,
            $entidade,
            $entidade_id,
            $antes  !== null ? json_encode($antes,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $depois !== null ? json_encode($depois, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $ip,
        ]);
    } catch (Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}
