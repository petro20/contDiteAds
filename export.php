<?php
/**
 * Exporta CSV (importável em Excel/Google Sheets) das principais visões.
 * Tipos suportados via ?tipo=:
 *   - cobrancas (sadmin/admin)
 *   - despesas (sadmin)
 *   - distribuicao (sadmin/admin)
 *   - clientes (sadmin/admin)
 *   - funcionarios (sadmin/admin)
 *   - entregas (sadmin/admin)
 *
 * Opcional: &mes=YYYY-MM pra filtrar período (cobrancas/despesas/distribuicao/entregas).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
$me = require_admin();
$db = db();

$tipo = $_GET['tipo'] ?? '';
$mes  = $_GET['mes']  ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

$nome_arq = 'export_' . $tipo . '_' . $mes . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nome_arq . '"');

// BOM UTF-8 pro Excel reconhecer acentos
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

switch ($tipo) {
    case 'cobrancas':
        fputcsv($out, [t('ID'),t('Cliente'),t('Competência'),t('Valor'),t('Moeda'),t('Status'),t('Vencimento'),t('Criada em')]);
        $stmt = $db->prepare("SELECT c.id, cl.nome_empresa, c.competencia_mes, c.valor_total, c.moeda, c.status, c.vencimento, c.criado_em
                              FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
                              WHERE c.competencia_mes = ? ORDER BY c.vencimento DESC");
        $stmt->execute([$mes]);
        foreach ($stmt->fetchAll() as $r) fputcsv($out, $r);
        break;

    case 'despesas':
        if (!is_sadmin()) { http_response_code(403); exit(t('Só sadmin.')); }
        fputcsv($out, [t('ID'),t('Nome'),t('Categoria'),t('Valor'),t('Moeda'),t('Recorrência'),t('Data início'),t('Data fim'),t('Ativo')]);
        foreach ($db->query("SELECT id,nome,categoria,valor,moeda,recorrencia,data_inicio,data_fim,ativo FROM despesas ORDER BY categoria,nome")->fetchAll() as $r) {
            fputcsv($out, $r);
        }
        break;

    case 'distribuicao':
        fputcsv($out, [t('ID'),t('Sócio'),t('Competência'),t('Moeda'),t('Valor'),t('Data pagamento'),t('Observação')]);
        $stmt = $db->prepare("SELECT ps.id, COALESCE(u.nome,'Empresa') AS socio, ps.competencia_mes, ps.moeda, ps.valor, ps.data_pagamento, ps.observacao
                              FROM pagamentos_socio ps LEFT JOIN usuarios u ON u.id = ps.socio_id
                              WHERE ps.competencia_mes = ? ORDER BY ps.data_pagamento DESC");
        $stmt->execute([$mes]);
        foreach ($stmt->fetchAll() as $r) fputcsv($out, $r);
        break;

    case 'clientes':
        fputcsv($out, [t('ID'),t('Empresa'),t('Contato'),t('Email'),t('Telefone'),t('Moeda'),t('Documento'),t('Ativo'),t('Criado em')]);
        foreach ($db->query("SELECT id,nome_empresa,nome_contato,email,telefone,moeda,documento,ativo,criado_em FROM clientes ORDER BY nome_empresa")->fetchAll() as $r) {
            fputcsv($out, $r);
        }
        break;

    case 'funcionarios':
        fputcsv($out, [t('ID'),t('Nome'),t('Email'),t('Role'),t('WiseTag'),t('País'),t('Aceitando clientes'),t('Trabalha com'),t('Ativo')]);
        $stmt = $db->query("SELECT u.id, u.nome, u.email, u.role, u.wisetag, u.pais, u.aceitando_clientes, COALESCE(p.nome,'') AS dupla, u.ativo
                            FROM usuarios u LEFT JOIN usuarios p ON p.id = u.trabalha_com_id
                            WHERE u.role IN ('sadmin','admin','funcionario') ORDER BY u.nome");
        foreach ($stmt->fetchAll() as $r) fputcsv($out, $r);
        break;

    case 'entregas':
        fputcsv($out, [t('ID'),t('Funcionário'),t('Cliente'),t('Item'),t('Tipo'),t('Competência'),t('Data'),t('Índice'),t('Criada em')]);
        $stmt = $db->prepare("SELECT e.id, u.nome, cl.nome_empresa, i.nome, i.tipo, e.competencia_mes, e.data_marcada, e.indice, e.criado_em
                              FROM entregas e
                              JOIN usuarios u   ON u.id = e.funcionario_id
                              JOIN assinaturas a ON a.id = e.assinatura_id
                              JOIN clientes cl  ON cl.id = a.cliente_id
                              JOIN itens_catalogo i ON i.id = a.item_id
                              WHERE e.competencia_mes = ? ORDER BY u.nome, e.criado_em");
        $stmt->execute([$mes]);
        foreach ($stmt->fetchAll() as $r) fputcsv($out, $r);
        break;

    default:
        fputcsv($out, [t('Erro'),t('Tipo não reconhecido. Use: cobrancas, despesas, distribuicao, clientes, funcionarios, entregas')]);
}

fclose($out);
