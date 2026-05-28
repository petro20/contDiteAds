<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    // Tenta selecionar com session_nonce; fallback pro schema antigo
    try {
        $stmt = db()->prepare('SELECT id, nome, email, role, ativo, cliente_id, cpf, wisetag, pais, aceitando_clientes, percentual_comissao, session_nonce FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        $stmt = db()->prepare('SELECT id, nome, email, role, ativo, cliente_id, cpf, wisetag, pais, aceitando_clientes, percentual_comissao FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch() ?: null;
    }
    if ($u && !$u['ativo']) {
        logout();
        return null;
    }
    // Validação de nonce: se a senha foi trocada (nonce no banco mudou),
    // forçar logout de sessões antigas.
    if ($u && isset($u['session_nonce']) && $u['session_nonce']) {
        $sess_nonce = $_SESSION['session_nonce'] ?? null;
        if ($sess_nonce === null) {
            // Primeira request após login — adota o nonce atual
            $_SESSION['session_nonce'] = $u['session_nonce'];
        } elseif ($sess_nonce !== $u['session_nonce']) {
            // Nonce mudou no banco (senha trocada em outro dispositivo) — derruba
            logout();
            return null;
        }
    }
    $cache = $u;
    return $u;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: ' . APP_BASE_URL . '/login.php');
        exit;
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if (!in_array($u['role'], ['admin','sadmin'], true)) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    return $u;
}

function is_admin(): bool {
    $u = current_user();
    return $u && in_array($u['role'], ['admin','sadmin'], true);
}

function require_sadmin(): array {
    $u = require_login();
    if ($u['role'] !== 'sadmin') {
        http_response_code(403);
        exit('Acesso negado. Apenas Super Admin.');
    }
    return $u;
}

function is_sadmin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'sadmin';
}

function login(string $email, string $senha): bool {
    $stmt = db()->prepare('SELECT id, senha_hash, ativo FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u || !$u['ativo'] || !password_verify($senha, $u['senha_hash'])) {
        return false;
    }
    // Checa 2FA tolerando ausência das colunas (antes da migration_003)
    $totp_enabled = 0;
    try {
        $st2 = db()->prepare('SELECT totp_enabled FROM usuarios WHERE id = ?');
        $st2->execute([(int)$u['id']]);
        $totp_enabled = (int)$st2->fetchColumn();
    } catch (Throwable $e) { /* migration 003 ainda não rodou */ }
    if ($totp_enabled === 1) {
        // Senha OK, mas precisa confirmar TOTP — guarda em pending
        session_regenerate_id(true);
        $_SESSION['pending_2fa_user_id'] = (int)$u['id'];
        return true;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $u['id'];
    return true;
}

function login_pendente_2fa(): bool {
    return !empty($_SESSION['pending_2fa_user_id']) && empty($_SESSION['user_id']);
}

function verificar_2fa_e_logar(string $codigo): bool {
    require_once __DIR__ . '/../lib/totp.php';
    $uid = $_SESSION['pending_2fa_user_id'] ?? null;
    if (!$uid) return false;
    $stmt = db()->prepare('SELECT totp_secret FROM usuarios WHERE id = ?');
    $stmt->execute([(int)$uid]);
    $sec = (string)$stmt->fetchColumn();

    // Primeiro tenta TOTP (6 dígitos numéricos)
    $ok = totp_verificar($sec, $codigo);
    // Se falhou, tenta como backup code (8 alfanuméricos, com ou sem hífen)
    if (!$ok) {
        $ok = totp_consumir_backup_code(db(), (int)$uid, $codigo);
    }
    if (!$ok) return false;

    unset($_SESSION['pending_2fa_user_id']);
    $_SESSION['user_id'] = (int)$uid;
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(400);
        exit('CSRF inválido.');
    }
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
