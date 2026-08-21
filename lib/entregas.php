<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/**
 * Helpers de entregas (Sprint 3).
 *
 * Regras adotadas:
 *  - Funcionário pode marcar/desmarcar a qualquer momento.
 *  - Admin NÃO precisa aprovar (marcação do func = válida).
 *  - Cliente vê em tempo real.
 *
 * Tipos de UI:
 *  - e_pacote=1 (ex: POSTAGEM 7D/5D/2D)   → checkbox por dia no calendário
 *  - tipo=por_unidade (CTF, CTV, CTI)     → tally (lista de marcações, sem limite)
 *  - tipo=unico                            → 1 checkbox "entregue"
 *  - tipo=mensal sem pacote (Meta/Google) → sem checkbox (read-only "ativo")
 */

function entregas_modo_ui(array $item): string {
    if ((int)$item['e_pacote'] === 1) return 'calendar';
    if ($item['tipo'] === 'por_unidade') return 'tally';
    if ($item['tipo'] === 'unico')       return 'single';
    return 'info'; // mensal sem pacote
}

/**
 * Redes sociais suportadas na Agenda (modo calendário).
 * Ordem = ordem canônica de exibição. Cada slug tem nome (marca, não traduz),
 * cor da marca e um SVG inline (viewBox 0 0 24 24; tamanho vem do CSS) para
 * identificação visual imediata. SVG inline = funciona offline (PWA), sem rede.
 */
function entregas_redes_defs(): array {
    return [
        'ig' => ['nome' => 'Instagram', 'cor' => '#D62976',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><defs><linearGradient id="ig-grad" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".35" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset="1" stop-color="#962FBF"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="6" fill="url(#ig-grad)"/><rect x="6.2" y="6.2" width="11.6" height="11.6" rx="3.4" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="12" cy="12" r="3.1" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="16.6" cy="7.4" r="1.2" fill="#fff"/></svg>'],
        'fb' => ['nome' => 'Facebook', 'cor' => '#1877F2',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="6" fill="#1877F2"/><path d="M13.5 21v-7h2.3l.4-2.9h-2.7V9.2c0-.8.3-1.4 1.5-1.4h1.3V5.2c-.6-.1-1.4-.2-2.2-.2-2.2 0-3.6 1.3-3.6 3.7v2.2H8v2.9h2.2V21z" fill="#fff"/></svg>'],
        'tiktok' => ['nome' => 'TikTok', 'cor' => '#000000',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="6" fill="#010101"/><path d="M16.7 7.1c-.9-.6-1.5-1.5-1.7-2.6h-2.2v9.3c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2c.2 0 .4 0 .6.1V9.7c-.2 0-.4 0-.6 0-2.3 0-4.2 1.9-4.2 4.2S8.5 18 10.8 18s4.2-1.9 4.2-4.2V9.5c.9.6 1.9 1 3 1V8.3c-.5 0-1-.4-1.3-.8z" fill="#25F4EE"/><path d="M17.4 7.6c-.9-.6-1.5-1.5-1.7-2.6h-2.2v9.3c0 1.1-.9 2-2 2-.6 0-1.2-.3-1.5-.7.4.2.8.3 1.2.3 1.1 0 2-.9 2-2V4.5h2.2c.2 1.1.8 2 1.7 2.6.3.4.8.7 1.3.8v.4c-.4-.1-.7-.4-1-.7z" fill="#FE2C55"/><path d="M16.7 7.1c-.9-.6-1.5-1.5-1.7-2.6h-1.5v9.3c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2c.2 0 .4 0 .6.1V9.7c-.2 0-.4 0-.6 0-2.3 0-4.2 1.9-4.2 4.2S9.5 18 11.8 18s4.2-1.9 4.2-4.2V9.5c.9.6 1.9 1 3 1V8.3c-.9 0-1.7-.4-2.3-.8z" fill="#fff"/></svg>'],
        'youtube' => ['nome' => 'YouTube', 'cor' => '#FF0000',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5.5" width="20" height="13" rx="4" fill="#FF0000"/><path d="M10.2 8.9v6.2l5.4-3.1z" fill="#fff"/></svg>'],
        'linkedin' => ['nome' => 'LinkedIn', 'cor' => '#0A66C2',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="4" fill="#0A66C2"/><path d="M7.1 9.6H4.7V19h2.4zM5.9 8.4c.8 0 1.3-.6 1.3-1.3 0-.7-.5-1.3-1.3-1.3s-1.3.6-1.3 1.3c0 .7.5 1.3 1.3 1.3zM19.3 19v-5.3c0-2.6-1.4-3.8-3.2-3.8-1.5 0-2.1.8-2.5 1.4V9.6h-2.3c0 .7 0 9.4 0 9.4h2.4v-5.2c0-.3 0-.6.1-.8.3-.5.7-1.1 1.6-1.1 1.1 0 1.6.9 1.6 2.1V19z" fill="#fff"/></svg>'],
        'x' => ['nome' => 'X (Twitter)', 'cor' => '#000000',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="6" fill="#010101"/><path d="M13.9 10.7 18 6h-1.4l-3.3 3.9L10.6 6H7l4.4 6.3L7 18h1.4l3.6-4.2 2.9 4.2H18zm-1.4 1.6-.5-.6L9 7.1h1.4l2.6 3.8.5.6 3.6 5.1h-1.4z" fill="#fff"/></svg>'],
        'meta' => ['nome' => 'Meta Business Suite', 'cor' => '#0064E0',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><defs><linearGradient id="meta-grad" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#0064E0"/><stop offset="1" stop-color="#0082FB"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="6" fill="url(#meta-grad)"/><path d="M6.4 15.3c-.7 0-1.3-.7-1.3-2.2 0-2.4 1.2-4.6 2.6-4.6.8 0 1.4.5 2.3 1.9.5.8.9 1.5 1.3 2.2.5-.8 1-1.6 1.5-2.3.9-1.3 1.6-1.8 2.5-1.8 1.5 0 2.7 2.1 2.7 4.6 0 1.4-.5 2.2-1.4 2.2-.8 0-1.3-.5-1.9-1.6-.4-.7-.8-1.5-1.2-2.2l-.9 1.5c-.6 1-1 1.5-1.4 1.9-.5.4-1 .6-1.6.6-.9 0-1.4-.5-2-1.5l-.9-1.6c-.4.8-.6 1.5-.6 2 0 .6.2.9.5.9.3 0 .5-.2.9-.7l.6.9c-.5.9-1.1 1.4-1.9 1.4zm1-5.5c-.6 0-1.2 1.1-1.2 2.6 0 .3 0 .5.1.8l.7-1.3c.5-.9.8-1.4 1.2-1.9-.3-.5-.6-.8-.9-.8zm8.1 0c-.3 0-.6.3-1 .8.4.5.8 1.1 1.2 1.9l.7 1.2c0-.2.1-.5.1-.8 0-1.4-.6-2.5-1.2-2.5z" fill="#fff"/></svg>'],
        'googleads' => ['nome' => 'Google Ads', 'cor' => '#4285F4',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="6" fill="#fff"/><rect x="9.1" y="4.2" width="4.6" height="12.6" rx="2.3" transform="rotate(-30 11.4 10.5)" fill="#FBBC04"/><rect x="10.3" y="4.2" width="4.6" height="12.6" rx="2.3" transform="rotate(30 12.6 10.5)" fill="#4285F4"/><circle cx="8.4" cy="17" r="2.4" fill="#34A853"/></svg>'],
    ];
}

/**
 * Normaliza um CSV de slugs vindo do POST: mantém só slugs válidos, sem duplicar,
 * na ordem canônica. Devolve CSV limpo (ex.: "ig,fb") — defesa contra entrada arbitrária.
 */
function entregas_redes_normaliza(string $csv): string {
    $validos = array_keys(entregas_redes_defs());
    $pedidos = array_filter(array_map('trim', explode(',', $csv)), 'strlen');
    $pedidos = array_intersect($pedidos, $validos);      // só slugs que existem
    $ordenado = array_values(array_intersect($validos, $pedidos)); // ordem canônica, sem dup
    return implode(',', $ordenado);
}

/**
 * Devolve o SVG inline de uma rede (string vazia se slug inválido).
 */
function entregas_rede_svg(string $slug): string {
    $defs = entregas_redes_defs();
    return $defs[$slug]['svg'] ?? '';
}

/**
 * Renderiza a fileira de ícones de um dia a partir do CSV salvo em `redes`.
 * String vazia se não houver redes.
 */
function entregas_redes_html(?string $csv): string {
    $csv = trim((string)$csv);
    if ($csv === '') return '';
    $defs = entregas_redes_defs();
    $out = '';
    foreach (explode(',', $csv) as $slug) {
        if (isset($defs[$slug])) {
            $out .= '<span class="rede-ico" title="' . htmlspecialchars($defs[$slug]['nome'], ENT_QUOTES) . '">' . $defs[$slug]['svg'] . '</span>';
        }
    }
    return $out === '' ? '' : '<span class="dia-redes">' . $out . '</span>';
}

/**
 * Checa se uma coluna existe (migration aditiva pode não ter rodado ainda).
 * Local pra não acoplar lib/entregas a lib/cobrancas.
 */
function entregas_coluna_existe(PDO $db, string $tabela, string $coluna): bool {
    static $cache = [];
    $key = $tabela . '.' . $coluna;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$tabela, $coluna]);
        return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

/**
 * Lista as entregas de uma assinatura no mês.
 */
function entregas_do_mes(PDO $db, int $assinatura_id, string $competencia): array {
    $col_redes = entregas_coluna_existe($db, 'entregas', 'redes') ? ', redes' : '';
    $stmt = $db->prepare("SELECT id, data_marcada, indice, criado_em$col_redes FROM entregas WHERE assinatura_id = ? AND competencia_mes = ? ORDER BY data_marcada, indice, id");
    $stmt->execute([$assinatura_id, $competencia]);
    return $stmt->fetchAll();
}

/**
 * Conta marcações de uma assinatura no mês.
 */
function entregas_count(PDO $db, int $assinatura_id, string $competencia): int {
    $stmt = $db->prepare('SELECT COUNT(*) FROM entregas WHERE assinatura_id = ? AND competencia_mes = ?');
    $stmt->execute([$assinatura_id, $competencia]);
    return (int)$stmt->fetchColumn();
}

/**
 * Marca um dia (calendar mode). Idempotente.
 */
function entregas_toggle_dia(PDO $db, int $assinatura_id, string $competencia, string $data, int $funcionario_id, string $redes = ''): array {
    $stmt = $db->prepare('SELECT id FROM entregas WHERE assinatura_id = ? AND competencia_mes = ? AND data_marcada = ? LIMIT 1');
    $stmt->execute([$assinatura_id, $competencia, $data]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $stmt = $db->prepare('DELETE FROM entregas WHERE id = ?');
        $stmt->execute([$existing]);
        return ['action' => 'removed', 'id' => (int)$existing];
    }
    $redes = entregas_redes_normaliza($redes);
    if (entregas_coluna_existe($db, 'entregas', 'redes')) {
        $stmt = $db->prepare('INSERT INTO entregas (assinatura_id, competencia_mes, data_marcada, funcionario_id, redes) VALUES (?,?,?,?,?)');
        $stmt->execute([$assinatura_id, $competencia, $data, $funcionario_id, $redes]);
    } else {
        $stmt = $db->prepare('INSERT INTO entregas (assinatura_id, competencia_mes, data_marcada, funcionario_id) VALUES (?,?,?,?)');
        $stmt->execute([$assinatura_id, $competencia, $data, $funcionario_id]);
    }
    return ['action' => 'added', 'id' => (int)$db->lastInsertId(), 'redes' => $redes];
}

/**
 * Adiciona 1 unidade (tally mode). Sem dedup — cada chamada = +1.
 */
function entregas_add_unidade(PDO $db, int $assinatura_id, string $competencia, int $funcionario_id): int {
    $stmt = $db->prepare('SELECT COALESCE(MAX(indice),0) FROM entregas WHERE assinatura_id = ? AND competencia_mes = ?');
    $stmt->execute([$assinatura_id, $competencia]);
    $idx = (int)$stmt->fetchColumn() + 1;
    $stmt = $db->prepare('INSERT INTO entregas (assinatura_id, competencia_mes, indice, funcionario_id) VALUES (?,?,?,?)');
    $stmt->execute([$assinatura_id, $competencia, $idx, $funcionario_id]);
    return (int)$db->lastInsertId();
}

/**
 * Marca/desmarca o "entregue" de item único.
 */
function entregas_toggle_unico(PDO $db, int $assinatura_id, string $competencia, int $funcionario_id): array {
    $stmt = $db->prepare('SELECT id FROM entregas WHERE assinatura_id = ? AND competencia_mes = ? LIMIT 1');
    $stmt->execute([$assinatura_id, $competencia]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $stmt = $db->prepare('DELETE FROM entregas WHERE id = ?');
        $stmt->execute([$existing]);
        return ['action' => 'removed'];
    }
    $stmt = $db->prepare('INSERT INTO entregas (assinatura_id, competencia_mes, funcionario_id) VALUES (?,?,?)');
    $stmt->execute([$assinatura_id, $competencia, $funcionario_id]);
    return ['action' => 'added'];
}

/**
 * Remove uma entrega específica por id (qualquer modo).
 */
function entregas_remover(PDO $db, int $entrega_id): void {
    $stmt = $db->prepare('DELETE FROM entregas WHERE id = ?');
    $stmt->execute([$entrega_id]);
}

/**
 * Lista assinaturas que aparecem na agenda do funcionário no mês.
 * Filtra assinaturas ativas onde ele é responsável.
 */
function agenda_assinaturas(PDO $db, int $funcionario_id, string $competencia): array {
    $sql = 'SELECT a.id AS assinatura_id, a.variante, a.valor_cobrado,
                   cl.id AS cliente_id, cl.nome_empresa, cl.moeda,
                   i.id AS item_id, i.nome AS item_nome, i.tipo, i.e_pacote
            FROM assinaturas a
            JOIN clientes cl ON cl.id = a.cliente_id
            JOIN itens_catalogo i ON i.id = a.item_id
            WHERE a.funcionario_id = ? AND a.status = "ativa"
            ORDER BY cl.nome_empresa, i.nome';
    $stmt = $db->prepare($sql);
    $stmt->execute([$funcionario_id]);
    return $stmt->fetchAll();
}

/**
 * Lista assinaturas de um cliente (para o cliente ver entregas).
 */
function agenda_assinaturas_cliente(PDO $db, int $cliente_id, string $competencia): array {
    $sql = 'SELECT a.id AS assinatura_id, a.variante, a.valor_cobrado,
                   i.id AS item_id, i.nome AS item_nome, i.tipo, i.e_pacote,
                   u.nome AS funcionario_nome
            FROM assinaturas a
            JOIN itens_catalogo i ON i.id = a.item_id
            LEFT JOIN usuarios u ON u.id = a.funcionario_id
            WHERE a.cliente_id = ? AND a.status = "ativa"
            ORDER BY i.nome';
    $stmt = $db->prepare($sql);
    $stmt->execute([$cliente_id]);
    return $stmt->fetchAll();
}

/**
 * Constrói uma matriz semana×dia para renderizar calendário do mês.
 */
function calendario_do_mes(string $competencia): array {
    $first = DateTime::createFromFormat('Y-m-d', $competencia . '-01');
    $days = (int)$first->format('t');
    $startDow = (int)$first->format('w'); // 0=dom .. 6=sáb
    $matrix = [];
    $week = array_fill(0, 7, null);
    for ($d = 1; $d <= $days; $d++) {
        $col = ($startDow + $d - 1) % 7;
        $week[$col] = sprintf('%s-%02d', $competencia, $d);
        if ($col === 6 || $d === $days) {
            $matrix[] = $week;
            $week = array_fill(0, 7, null);
        }
    }
    return $matrix;
}
