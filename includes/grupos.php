<?php
/**
 * Helpers de navegação por grupo. Cada "grupo" agrega várias páginas
 * relacionadas (Equipe, Finanças, Minha conta, Comunicação). A função
 * render_group_tabs() emite uma tabs-bar com links pra cada página do
 * grupo, destacando a ativa.
 *
 * Uso na página:
 *   require_once __DIR__ . '/includes/grupos.php';
 *   // ... depois do header.php ...
 *   render_group_tabs('equipe', 'funcionarios');
 */

function grupos_definidos(): array {
    $base = APP_BASE_URL;
    return [
        'equipe' => [
            'titulo' => 'Equipe',
            'abas'   => [
                ['key'=>'funcionarios',  'label'=>'👥 Lista',       'href'=>"$base/funcionarios.php"],
                ['key'=>'capacidade',    'label'=>'📊 Capacidade',  'href'=>"$base/capacidade.php"],
                ['key'=>'pagamentos',    'label'=>'💵 Pagamentos',  'href'=>"$base/pagamentos_funcionarios.php"],
            ],
        ],
        'financas' => [
            'titulo' => 'Finanças',
            'abas'   => [
                ['key'=>'despesas',      'label'=>'💸 Despesas',     'href'=>"$base/despesas.php"],
                ['key'=>'distribuicao',  'label'=>'💎 Distribuição', 'href'=>"$base/distribuicao.php"],
                ['key'=>'pagamento_cfg', 'label'=>'💳 Pagamentos',   'href'=>"$base/config_pagamento.php"],
            ],
        ],
        'conta' => [
            'titulo' => 'Minha conta',
            'abas'   => [
                ['key'=>'perfil',    'label'=>'👤 Perfil',     'href'=>"$base/perfil.php"],
                ['key'=>'seguranca', 'label'=>'🔐 Segurança',  'href'=>"$base/seguranca.php"],
                ['key'=>'ajuda',     'label'=>'❓ Ajuda',      'href'=>"$base/ajuda.php"],
            ],
        ],
    ];
}

function render_group_tabs(string $grupo, string $aba_ativa): void {
    $grupos = grupos_definidos();
    if (!isset($grupos[$grupo])) return;
    $g = $grupos[$grupo];
    echo '<nav class="tabs-bar">';
    foreach ($g['abas'] as $aba) {
        $cls = $aba['key'] === $aba_ativa ? 'active' : '';
        echo '<a class="' . htmlspecialchars($cls) . '" href="' . htmlspecialchars($aba['href']) . '">' . $aba['label'] . '</a>';
    }
    echo '</nav>';
}
