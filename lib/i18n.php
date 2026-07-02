<?php
declare(strict_types=1);

/**
 * i18n minimalista (PT/EN/ES) sem dependências.
 *
 * A CHAVE de tradução é o próprio texto em PORTUGUÊS. Assim dá pra
 * envolver os textos existentes com t('...') e, se ainda não houver
 * tradução, o português aparece como fallback (nada quebra).
 *
 * Idioma escolhido fica num cookie ('idioma'); PT é o padrão.
 * Dicionários: lang/en.php e lang/es.php (arrays 'texto PT' => 'tradução').
 */

const I18N_IDIOMAS = ['pt' => 'Português', 'en' => 'English', 'es' => 'Español'];

function idioma_atual(): string {
    $l = $_COOKIE['idioma'] ?? 'pt';
    return isset(I18N_IDIOMAS[$l]) ? $l : 'pt';
}

/** Define o idioma (cookie de 1 ano). Chamado pelo seletor. */
function set_idioma(string $l): void {
    if (!isset(I18N_IDIOMAS[$l])) return;
    setcookie('idioma', $l, [
        'expires'  => time() + 86400 * 365,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    $_COOKIE['idioma'] = $l;
}

/** Carrega o dicionário do idioma atual (cacheado). PT = base (sem dicionário). */
function _i18n_dic(): array {
    static $cache = [];
    $l = idioma_atual();
    if (isset($cache[$l])) return $cache[$l];
    if ($l === 'pt') return $cache[$l] = [];
    $f = __DIR__ . '/../lang/' . $l . '.php';
    $d = is_file($f) ? require $f : [];
    return $cache[$l] = (is_array($d) ? $d : []);
}

/**
 * Traduz um texto (chave = português). Sem tradução → devolve o português.
 * Suporta placeholders: t('Olá, {nome}', ['nome' => 'Ana']).
 */
function t(string $pt, array $vars = []): string {
    $dic = _i18n_dic();
    $s = $dic[$pt] ?? $pt;
    if ($vars) {
        foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    return $s;
}
