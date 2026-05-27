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
Você é um especialista em precificação E em escopo de serviços de marketing digital pra uma agência (Dite Ads).

ENTRADA DO ADMIN:
Nome rascunho: {$nome}
Descrição rascunho: {$descricao}

Sua tarefa: gerar um pacote COMPLETO pra esse serviço.

1. **nome**: ajustado/limpo, claro e curto (max 60 chars)
2. **descricao**: descrição DETALHADA do que será entregue. ENGLOBE:
   - O que o serviço inclui (cada entrega tangível)
   - Frequência/volume (ex: "até 5 campanhas", "2 vídeos/semana")
   - O que a Dite Ads (agência) entrega
   - O que o funcionário responsável executa
   - O que o cliente precisa fornecer (acesso, materiais, briefing)
   - Ferramentas/canais usados
   - Limites/escopo (o que NÃO está incluso)
   Use texto corrido, parágrafos curtos, com bullets quando fizer sentido.
   Mínimo 4–6 linhas. Tom profissional mas direto.
3. **tipo**: "unico" (one-shot), "mensal" (recorrente) ou "por_unidade"
4. **periodo_minimo_meses**: 0 a 12 (recomende fidelidade pra serviços contínuos)
5. **margem_pct**: 40–80% pra marketing digital
6. **custos**: lista REAL de custos em USD com rateio quando aplicável.
   Exemplos típicos:
   - Pagamento ao funcionário (USD por execução)
   - Software/licenças (Canva Pro \$13, Adobe CC \$55, Figma \$15, ChatGPT \$20,
     Midjourney \$30, Capcut Pro \$10, Opus Clips \$29, Google Drive 2TB \$10,
     Buffer \$6, Notion \$10)
   - Horas próprias de gestão (ex: 2h × \$30/h)
   - Tools de gestão de anúncios se aplicável
   Cada custo: {"descricao": "string", "valor": número_total_USD, "dividir_por": número}

7. **resp_agencia**: o que a Dite Ads (agência) entrega no escopo. Lista de bullets em texto, sem markdown. Ex: "• Setup das campanhas\\n• Otimização semanal\\n• Relatórios mensais"
8. **resp_funcionario**: o que o funcionário responsável executa operacionalmente. Bullets em texto.
9. **resp_cliente**: o que o cliente precisa fornecer (acessos, materiais, briefing). Bullets em texto.

Responda APENAS um JSON válido (sem markdown, sem texto extra antes ou depois):
{
  "nome": "string",
  "descricao": "string com quebras de linha \\n permitidas",
  "tipo": "mensal|unico|por_unidade",
  "periodo_minimo_meses": número,
  "margem_pct": número,
  "resp_agencia": "string",
  "resp_funcionario": "string",
  "resp_cliente": "string",
  "custos": [
    {"descricao": "string", "valor": número, "dividir_por": número}
  ]
}
TXT;

$payload = json_encode([
    'model'      => 'claude-sonnet-4-5',
    'max_tokens' => 2048,
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
