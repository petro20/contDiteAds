<?php
/**
 * Endpoint AJAX que pede pra Claude (Anthropic API) sugerir custos
 * e parâmetros pra um serviço descrito em linguagem natural.
 *
 * Espera POST com 'descricao' (textarea do simulador) e 'nome' (rascunho).
 * Retorna JSON com:
 *   { nome, tipo, periodo_minimo_meses, margem_pct, custos: [{descricao, valor, dividir_por}] }
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/configuracoes.php';
header('Content-Type: application/json; charset=utf-8');
require_sadmin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Use POST']); exit;
}

csrf_check();

$descricao = trim((string)($_POST['descricao'] ?? ''));
$nome      = trim((string)($_POST['nome'] ?? ''));
if ($descricao === '' && $nome === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Preencha a descrição ou o nome do serviço.']); exit;
}

$api_key = config_get($db, 'anthropic_api_key');
if (!$api_key) {
    // tenta env var como fallback
    $api_key = (string)getenv('ANTHROPIC_API_KEY');
}
if (!$api_key) {
    http_response_code(503);
    echo json_encode(['error' => 'API da IA não configurada. Vá em Finanças → Formas de pagamento e cadastre a chave da Anthropic.']); exit;
}

$prompt = <<<TXT
Você é um especialista em precificação de serviços de marketing digital pra uma agência (Dite Ads).

DESCRIÇÃO DO SERVIÇO:
Nome: {$nome}
{$descricao}

Sua tarefa: analisar a descrição e sugerir:
1. Nome ajustado/limpo do serviço (curto, claro, max 50 chars)
2. Tipo de cobrança: "unico" (one-shot), "mensal" (recorrente) ou "por_unidade"
3. Período mínimo de contrato em meses (0 a 12)
4. Margem desejada em % (geralmente 40-80% pra marketing digital)
5. Lista de custos REAIS típicos pra esse serviço, em USD. Cada custo:
   - descricao: nome do componente
   - valor: valor total em USD (preço de mercado realista da ferramenta)
   - dividir_por: pra rateio quando aplicável (ex: software \$50/mês ÷ 10 clientes = 10)

Exemplos de custos comuns pra marketing digital:
- Pagamento ao funcionário (USD por execução)
- Software/licenças (Canva Pro \$13, Adobe CC \$55, Figma \$15, ChatGPT \$20, Midjourney \$30, Capcut \$10, Opus Clips \$29, Google Drive 2TB \$10)
- Anúncios/orçamento de mídia (se aplicável)
- Horas próprias de gestão

Responda APENAS um objeto JSON válido (sem markdown, sem explicação extra), no formato:
{
  "nome": "string",
  "tipo": "mensal|unico|por_unidade",
  "periodo_minimo_meses": número,
  "margem_pct": número,
  "custos": [
    {"descricao": "string", "valor": número, "dividir_por": número}
  ]
}
TXT;

$payload = json_encode([
    'model'      => 'claude-sonnet-4-5',
    'max_tokens' => 1024,
    'messages'   => [['role' => 'user', 'content' => $prompt]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
    ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Falha ao consultar IA (HTTP ' . $code . ') ' . ($err ?: ''), 'resp' => $resp]); exit;
}

$data = json_decode($resp, true);
$texto = $data['content'][0]['text'] ?? '';
// Extrai JSON do texto (caso venha cercado por texto)
if (preg_match('/\{[\s\S]*\}/', $texto, $m)) {
    $sugestao = json_decode($m[0], true);
}
if (!isset($sugestao) || !is_array($sugestao)) {
    http_response_code(502);
    echo json_encode(['error' => 'Resposta da IA não pôde ser interpretada.', 'raw' => $texto]); exit;
}

echo json_encode(['ok' => true, 'sugestao' => $sugestao]);
