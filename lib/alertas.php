<?php
declare(strict_types=1);

/**
 * Detecção e envio de alertas operacionais pros funcionários.
 *
 * Foco atual: postagens da semana sem marcação na agenda.
 */

require_once __DIR__ . '/../includes/db.php';

/**
 * Retorna a segunda-feira (00:00) da semana de uma data de referência.
 */
function alertas_inicio_semana(DateTimeImmutable $ref): DateTimeImmutable {
    $dow = (int)$ref->format('N'); // 1=seg, 7=dom
    return $ref->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
}

/**
 * Lista funcionários com assinaturas de POSTAGEM ativas que ainda NÃO marcaram
 * nenhuma entrega esta semana (segunda → hoje).
 *
 * Critério "POSTAGEM": item do catálogo com nome contendo a string 'POSTAGEM'.
 * Match case-insensitive pra cobrir variantes (postagem, Postagens etc).
 *
 * @return array Lista de ['funcionario_id','nome','email','assinaturas' => [...]]
 */
function alertas_postagens_pendentes(PDO $db, DateTimeImmutable $hoje): array {
    $inicio = alertas_inicio_semana($hoje)->format('Y-m-d');
    $fim    = $hoje->format('Y-m-d');

    // Funcionários com pelo menos 1 assinatura POSTAGEM ativa
    $sql = "
        SELECT u.id, u.nome, u.email,
               a.id   AS assin_id,
               cl.nome_empresa,
               it.nome AS item_nome
        FROM assinaturas a
        JOIN usuarios       u  ON u.id  = a.funcionario_id
        JOIN clientes       cl ON cl.id = a.cliente_id
        JOIN itens_catalogo it ON it.id = a.item_id
        WHERE a.status = 'ativa'
          AND u.ativo = 1
          AND u.role IN ('funcionario','admin')
          AND u.email IS NOT NULL AND u.email != ''
          AND UPPER(it.nome) LIKE '%POSTAGEM%'
        ORDER BY u.id, a.id
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (!$rows) return [];

    // Pra cada assinatura, verifica se TEM entrega marcada nesta semana
    $stmt_count = $db->prepare(
        'SELECT COUNT(*) FROM entregas
         WHERE assinatura_id = ?
           AND data_marcada BETWEEN ? AND ?'
    );

    // Agrupa por funcionário, só inclui assinaturas SEM entrega na semana
    $agrupado = [];
    foreach ($rows as $r) {
        $stmt_count->execute([(int)$r['assin_id'], $inicio, $fim]);
        $tem_entregas = (int)$stmt_count->fetchColumn() > 0;
        if ($tem_entregas) continue; // tudo OK, não alerta

        $uid = (int)$r['id'];
        if (!isset($agrupado[$uid])) {
            $agrupado[$uid] = [
                'funcionario_id' => $uid,
                'nome'           => $r['nome'],
                'email'          => $r['email'],
                'assinaturas'    => [],
            ];
        }
        $agrupado[$uid]['assinaturas'][] = [
            'cliente_nome' => $r['nome_empresa'],
            'item_nome'    => $r['item_nome'],
            'assinatura_id'=> (int)$r['assin_id'],
        ];
    }

    return array_values($agrupado);
}

/**
 * Monta o corpo do email de alerta pro funcionário.
 */
function alertas_email_corpo(array $pendente, DateTimeImmutable $hoje): string {
    $hoje_str = $hoje->format('d/m/Y');
    $dia_semana = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'][(int)$hoje->format('w')];

    $lista_html = '';
    foreach ($pendente['assinaturas'] as $a) {
        $lista_html .= '<li><strong>' . htmlspecialchars($a['cliente_nome']) . '</strong> — ' . htmlspecialchars($a['item_nome']) . '</li>';
    }

    $link_agenda = APP_BASE_URL . '/agenda.php';

    return '<p>Olá, <strong>' . htmlspecialchars($pendente['nome']) . '</strong>!</p>'
         . '<p>É <strong>' . htmlspecialchars($dia_semana) . ' (' . $hoje_str . ')</strong> e você ainda não marcou nenhuma entrega de POSTAGEM esta semana nas assinaturas abaixo:</p>'
         . '<ul>' . $lista_html . '</ul>'
         . '<p>Entra no sistema e marca o que já entregou:</p>'
         . '<p><a href="' . $link_agenda . '" style="display:inline-block; padding:10px 18px; background:#9333EA; color:#fff; text-decoration:none; border-radius:6px;">📅 Abrir minha agenda</a></p>'
         . '<p style="color:#666; font-size:13px;">Se a entrega ainda não aconteceu, beleza — só não esquece de marcar quando rolar. Esse aviso é automático às quartas e sextas pra manter o ritmo das postagens em dia.</p>'
         . '<p>— Sistema Dite Ads</p>';
}
