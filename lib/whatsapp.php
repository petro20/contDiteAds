<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/money.php';

/**
 * Helpers de WhatsApp (Sprint 5 — N11).
 * Sistema só monta link wa.me com mensagem pronta. Admin clica e envia.
 */

/**
 * Limpa telefone: remove tudo que não é dígito.
 * Hostinger users do Brasil escrevem "+55 (11) 99999-9999" → "5511999999999".
 */
function wa_telefone(string $tel): string {
    return preg_replace('/\D+/', '', $tel) ?? '';
}

/**
 * Monta o link wa.me. Retorna URL pronta pra abrir.
 */
function wa_link(string $telefone, string $mensagem): string {
    $tel = wa_telefone($telefone);
    if ($tel === '') return '';
    return 'https://wa.me/' . $tel . '?text=' . rawurlencode($mensagem);
}

/**
 * Renderiza um template substituindo as variáveis.
 */
function wa_render(string $corpo, array $vars): string {
    foreach ($vars as $k => $v) {
        $corpo = str_replace('{' . $k . '}', (string)$v, $corpo);
    }
    // Detecta variáveis não substituídas — typo do admin no template,
    // ex: {nome_do_cliente} (correto é {nome_cliente}).
    // Loga warning e substitui por string vazia pra não constranger o cliente.
    if (preg_match_all('/\{[a-z_]+\}/', $corpo, $sobras)) {
        $unicas = array_unique($sobras[0]);
        error_log('wa_render: variáveis não substituídas — ' . implode(', ', $unicas));
        foreach ($unicas as $var_orfa) {
            $corpo = str_replace($var_orfa, '', $corpo);
        }
    }
    return $corpo;
}

/**
 * Valida um template — retorna lista de variáveis usadas que NÃO estão na lista
 * de suportadas. Usado no save de templates pra avisar admin antes de salvar.
 *
 * @param string $corpo Texto com {variaveis}
 * @param array $suportadas Lista de chaves válidas (sem chaves)
 * @return string[] Variáveis órfãs encontradas no corpo
 */
function wa_validar_variaveis(string $corpo, array $suportadas): array {
    if (!preg_match_all('/\{([a-z_]+)\}/', $corpo, $m)) return [];
    $usadas = array_unique($m[1]);
    return array_values(array_diff($usadas, $suportadas));
}

/**
 * Carrega um template por código + canal. Retorna assunto+corpo ou null.
 */
function wa_template(PDO $db, string $codigo, string $canal): ?array {
    $stmt = $db->prepare('SELECT id, assunto, corpo FROM templates_mensagem WHERE codigo = ? AND canal = ? AND ativo = 1 LIMIT 1');
    $stmt->execute([$codigo, $canal]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Monta as variáveis padrão de uma cobrança.
 */
function wa_vars_cobranca(PDO $db, int $cobranca_id): array {
    $stmt = $db->prepare('SELECT c.*, cl.nome_empresa, cl.nome_contato, cl.telefone FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
    $stmt->execute([$cobranca_id]);
    $cob = $stmt->fetch();
    if (!$cob) return [];

    $stmt = $db->prepare('SELECT descricao, quantidade FROM cobranca_itens WHERE cobranca_id = ?');
    $stmt->execute([$cobranca_id]);
    $itens = $stmt->fetchAll();
    $itens_txt = '';
    foreach ($itens as $it) {
        $itens_txt .= "- " . $it['descricao'] . ($it['quantidade'] > 1 ? " (×{$it['quantidade']})" : '') . "\n";
    }

    return [
        'nome_cliente'    => $cob['nome_contato'] ?: $cob['nome_empresa'],
        'nome_empresa'    => $cob['nome_empresa'],
        'mes_referencia'  => $cob['competencia_mes'],
        'valor'           => number_format((float)$cob['valor_total'], 2, ',', '.'),
        'moeda'           => $cob['moeda'],
        'vencimento'      => date('d/m/Y', strtotime($cob['vencimento'])),
        'itens'           => rtrim($itens_txt),
        'link_recibo'     => APP_BASE_URL . '/recibo.php?cobranca=' . (int)$cobranca_id,
        'link_comprovante'=> APP_BASE_URL . '/cobrancas.php?id=' . (int)$cobranca_id,
        'link_sistema'    => APP_BASE_URL . '/',
        'zelle_email'         => (function() use ($db) {
            require_once __DIR__ . '/configuracoes.php';
            $email = config_get($db, 'pagamento_zelle_email');
            // Insere ZERO-WIDTH SPACE antes do @ pra evitar que WhatsApp/email
            // converta em link mailto: clicável (que abriria o app de email
            // em vez de mostrar o email pro cliente copiar pro app do banco).
            // O cliente seleciona/copia normalmente — o ZWSP some na maioria
            // dos campos de input.
            return $email ? str_replace('@', "\u{200B}@", $email) : '';
        })(),
        'zelle_qr_url'        => (function() use ($db) { require_once __DIR__ . '/configuracoes.php'; $q = config_get($db, 'pagamento_zelle_qr'); return $q ? (APP_BASE_URL . '/uploads/' . $q) : ''; })(),
        'link_wise'           => (function() use ($db) { require_once __DIR__ . '/configuracoes.php'; return config_get($db, 'pagamento_wise_link'); })(),
        'instrucoes_pagamento'=> (function() use ($db) { require_once __DIR__ . '/configuracoes.php'; return config_get($db, 'pagamento_instrucoes'); })(),
        '_telefone'       => $cob['telefone'] ?? '',
    ];
}
