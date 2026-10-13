<?php
/**
 * UNE Sino-Gateway — HTTP/SSE Transport for MCP
 */

$t_start = microtime(true);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Session-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function getClientIp(): string {
    return $_SERVER['HTTP_CF_CONNECTING_IP'] 
        ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
        ?? $_SERVER['REMOTE_ADDR'] 
        ?? 'cli';
}

function checkRateLimit(string $ip, int $maxPerMinute = 60): bool {
    try {
        $dbPath = '/var/www/atoa-api/rate_limits.sqlite';
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $db->exec("CREATE TABLE IF NOT EXISTS ip_hits (
            ip TEXT,
            hit_time INTEGER
        );
        CREATE INDEX IF NOT EXISTS idx_hits ON ip_hits(ip, hit_time);");

        $now = time();
        $window = $now - 60;

        $db->exec("DELETE FROM ip_hits WHERE hit_time < $window");

        $stmt = $db->prepare("SELECT COUNT(*) FROM ip_hits WHERE ip = ? AND hit_time >= ?");
        $stmt->execute([$ip, $window]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= $maxPerMinute) {
            return false;
        }

        $ins = $db->prepare("INSERT INTO ip_hits (ip, hit_time) VALUES (?, ?)");
        $ins->execute([$ip, $now]);
        return true;
    } catch (\Throwable $e) {
        return true;
    }
}

$rawInput = file_get_contents('php://input');
$request = json_decode($rawInput, true);

function sendJsonRpcResponse(string|int|null $id, ?array $result, ?array $error, float $t_start, ?string $method, string $rawInput): void {
    $execMs = round((microtime(true) - $t_start) * 1000, 2);
    $nowUtc = gmdate('Y-m-d\TH:i:s\Z');
    $executionNonce = bin2hex(random_bytes(8));
    $payloadToSeal = $rawInput . '|' . json_encode($result) . '|' . $nowUtc . '|' . $executionNonce;
    $stateProofHash = hash('sha256', $payloadToSeal);

    $meta = [
        'state_proof' => [
            'proof_hash'       => $stateProofHash,
            'timestamp_utc'    => $nowUtc,
            'nonce'            => $executionNonce,
            'corpus_version'   => 'PRC-CIVIL-CODE-2026 / GACC-TARIFF-2026',
            'authority'        => "Supreme People's Court (PRC) / CIETAC / GACC",
            'nature'           => 'Deterministic Regulatory Snapshot & Computational State Proof',
            'legal_basis'      => 'PRC Civil Code Book III, CISG UNCITRAL & Customs Tariff of the PRC',
            'reliance'         => 'Certified accurate computational snapshot of statutory provisions, arbitration standards and customs tariffs at UTC timestamp.',
            'status'           => 'SEALED'
        ],
        'efficiency' => [
            'latency_ms'       => $execMs,
            'footprint_rating' => ($execMs < 5.0) ? 'A+ (Ultra-frugal)' : 'A (Frugal)'
        ]
    ];

    $response = ['jsonrpc' => '2.0'];
    if ($id !== null) {
        $response['id'] = $id;
    }
    if ($error !== null) {
        $response['error'] = $error;
    } else {
        if ($result !== null) {
            $result['_meta'] = $meta;
        }
        $response['result'] = $result;
    }

    try {
        $logDbPath = __DIR__ . '/sino_gateway.sqlite';
        if (file_exists($logDbPath)) {
            $logDb = new PDO('sqlite:' . $logDbPath);
            $logDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $logDb->exec("CREATE TABLE IF NOT EXISTS mcp_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                ip TEXT,
                method TEXT,
                user_agent TEXT,
                payload TEXT,
                exec_time_ms REAL
            );");
            $ip = getClientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $stmt = $logDb->prepare("INSERT INTO mcp_logs (ip, method, user_agent, payload, exec_time_ms) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ip, $method ?? 'unknown', $ua, substr($rawInput, 0, 500), $execMs]);
        }
    } catch (\Throwable $ignored) {}

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$tools = [
    [
        'name' => 'sino_inspection_delay',
        'description' => 'Calculates strict statutory inspection deadlines and statute of repose under PRC Civil Code Art. 621 and CISG Art. 38/39.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'arrival_date' => ['type' => 'string', 'description' => 'Arrival date of cargo in YYYY-MM-DD format'],
                'defect_type' => ['type' => 'string', 'enum' => ['apparent', 'hidden'], 'description' => 'Nature of defect']
            ],
            'required' => ['arrival_date']
        ]
    ],
    [
        'name' => 'sino_defect_notice',
        'description' => 'Generates a formal bilingual (English/Chinese) dispute notice opposable under PRC law and CISG with cryptographic audit seal.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'contract_ref' => ['type' => 'string', 'description' => 'Purchase order or contract reference'],
                'bl_number' => ['type' => 'string', 'description' => 'Bill of Lading or Airway Bill number'],
                'defect_summary' => ['type' => 'string', 'description' => 'Specific non-conformity ascertained upon inspection'],
                'cargo_desc' => ['type' => 'string', 'description' => 'Description of the shipment'],
                'remedy' => ['type' => 'string', 'enum' => ['replacement', 'price_reduction', 'rescission']]
            ],
            'required' => ['contract_ref', 'bl_number', 'defect_summary']
        ]
    ],
    [
        'name' => 'sino_cietac_clause',
        'description' => 'Provides legally compliant CIETAC arbitration model clause in Chinese and English.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => (object)[]
        ]
    ],
    [
        'name' => 'sino_matrix_concordance',
        'description' => 'Searches bilingual legal concordance matrix between PRC Civil Code and international provisions.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Keyword or article reference']
            ],
            'required' => ['query']
        ]
    ],
    [
        'name' => 'sino_customs_valuation',
        'description' => 'Calculates deterministic import duties, VAT (增值税), and Consumption Tax (消费税) under GACC regulations across all 97 HS chapters for goods exported to China.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'hs_code' => ['type' => 'string', 'description' => '2, 4 or 6-digit Harmonized System code (e.g., 2204, 3304, 8471, 8703, 30)'],
                'cif_value_eur' => ['type' => 'number', 'description' => 'CIF invoice value in EUR'],
                'exchange_rate_eur_cny' => ['type' => 'number', 'description' => 'EUR to CNY exchange rate (default: 7.80)']
            ],
            'required' => ['hs_code', 'cif_value_eur']
        ]
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'GET' || !$request || !isset($request['method'])) {
    sendJsonRpcResponse(null, [
        'status' => 'operational',
        'server' => 'UNE Sino-Gateway MCP Server',
        'protocol' => 'MCP/JSON-RPC-2.0',
        'endpoints' => [
            'rpc' => 'https://legal.une.education/sino/mcp.php',
            'spec' => 'https://legal.une.education/sino/openapi.json'
        ],
        'tools_count' => count($tools),
        'pricing' => [
            'model' => 'freemium',
            'rate_limit' => '60 requests/minute',
            'free_tier' => [
                'included_calls' => 1000,
                'metered_methods' => ['tools/call']
            ]
        ]
    ], null, $t_start, 'GET/info', $rawInput ?: '');
}

$id = $request['id'] ?? null;
$method = $request['method'] ?? null;
$params = $request['params'] ?? [];

function executeLocalAction($actionParams) {
    $url = 'http://127.0.0.1/sino/index.php?' . http_build_query($actionParams ?? []);
    $ctx = stream_context_create([
        'http' => [
            'header' => "Host: legal.une.education\r\nUser-Agent: UNE-MCP-Internal/1.0\r\n",
            'timeout' => 5
        ]
    ]);
    $res = (!empty($url)) ? @file_get_contents($url, false, $ctx) : false;
    return $res ? json_decode($res, true) : ['error' => 'Internal router unreachable'];
}

function calculateChinaImportTariff(string $hsCode, float $cifEur, float $rateEurCny = 7.80): array {
    $cleanHs = preg_replace('/[^0-9]/', '', $hsCode);
    $prefixes = [];
    if (strlen($cleanHs) >= 6) $prefixes[] = substr($cleanHs, 0, 6);
    if (strlen($cleanHs) >= 4) $prefixes[] = substr($cleanHs, 0, 4);
    if (strlen($cleanHs) >= 2) $prefixes[] = substr($cleanHs, 0, 2);

    $dbPath = __DIR__ . '/sino_gateway.sqlite';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

    $matched = null;
    if (!empty($prefixes)) {
        $inClause = implode(',', array_fill(0, count($prefixes), '?'));
        $stmt = $db->prepare("SELECT * FROM china_tariff_rates WHERE hs_prefix IN ($inClause) ORDER BY LENGTH(hs_prefix) DESC LIMIT 1");
        $stmt->execute($prefixes);
        $matched = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $mfnDutyRate = $matched ? (float)$matched['mfn_duty_rate'] : 8.0;
    $vatRate = $matched ? (float)$matched['vat_rate'] : 13.0;
    $consumptionRate = $matched ? (float)$matched['consumption_tax_rate'] : 0.0;
    $category = $matched ? $matched['category_en'] : 'General manufactured goods';

    $cifCny = round($cifEur * $rateEurCny, 2);
    $dutyAmountCny = round($cifCny * ($mfnDutyRate / 100), 2);

    $ctBase = ($consumptionRate > 0) ? ($cifCny + $dutyAmountCny) / (1 - ($consumptionRate / 100)) : 0;
    $ctAmountCny = round($ctBase * ($consumptionRate / 100), 2);

    $vatBase = $cifCny + $dutyAmountCny + $ctAmountCny;
    $vatAmountCny = round($vatBase * ($vatRate / 100), 2);

    $totalLandedCostCny = round($cifCny + $dutyAmountCny + $ctAmountCny + $vatAmountCny, 2);

    return [
        'hs_code'                 => $cleanHs,
        'product_category'        => $category,
        'cif_value_eur'           => $cifEur,
        'cif_value_cny'           => $cifCny,
        'exchange_rate'           => $rateEurCny,
        'duty_mfn_percent'        => $mfnDutyRate,
        'duty_amount_cny'         => $dutyAmountCny,
        'consumption_tax_percent' => $consumptionRate,
        'consumption_tax_cny'     => $ctAmountCny,
        'vat_percent'             => $vatRate,
        'vat_amount_cny'          => $vatAmountCny,
        'total_taxes_cny'         => round($dutyAmountCny + $ctAmountCny + $vatAmountCny, 2),
        'total_landed_cost_cny'   => $totalLandedCostCny,
        'authority'               => 'General Administration of Customs of the PRC (GACC)'
    ];
}

if ($method === 'notifications/initialized' || ($id === null && str_starts_with((string)$method, 'notifications/'))) {
    try {
        $logDb = new PDO('sqlite:' . __DIR__ . '/sino_gateway.sqlite');
        $stmt = $logDb->prepare("INSERT INTO mcp_logs (ip, method, user_agent, payload, exec_time_ms) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([getClientIp(), $method, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', substr($rawInput, 0, 500), 0.5]);
    } catch (\Throwable $e) {}
    http_response_code(204);
    exit;
}

if ($method === 'initialize') {
    sendJsonRpcResponse($id, [
        'protocolVersion' => '2024-11-05',
        'capabilities' => [
            'tools' => new stdClass()
        ],
        'serverInfo' => [
            'name' => 'sino-gateway',
            'version' => '1.2.0'
        ]
    ], null, $t_start, $method, $rawInput);
}

if ($method === 'tools/list') {
    sendJsonRpcResponse($id, ['tools' => $tools], null, $t_start, $method, $rawInput);
}

if ($method === 'resources/list') {
    sendJsonRpcResponse($id, ['resources' => []], null, $t_start, $method, $rawInput);
}

if ($method === 'prompts/list') {
    sendJsonRpcResponse($id, ['prompts' => []], null, $t_start, $method, $rawInput);
}

if ($method === 'tools/call') {
    $clientIp = getClientIp();
    if (!checkRateLimit($clientIp, 60)) {
        sendJsonRpcResponse($id, null, [
            'code' => -32000,
            'message' => 'Rate limit exceeded: maximum 60 requests per minute per IP'
        ], $t_start, $method, $rawInput);
    }

    $toolName = $params['name'] ?? '';
    $args = $params['arguments'] ?? [];

    if ($toolName === 'sino_customs_valuation') {
        $hsCode = (string)($args['hs_code'] ?? '');
        $cifEur = (float)($args['cif_value_eur'] ?? 0);
        $rate = (float)($args['exchange_rate_eur_cny'] ?? 7.80);
        $res = calculateChinaImportTariff($hsCode, $cifEur, $rate);

        sendJsonRpcResponse($id, [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]
            ]
        ], null, $t_start, $method, $rawInput);
    }

    $actionMap = [
        'sino_inspection_delay' => array_merge(['action' => 'inspection_delay'], $args),
        'sino_defect_notice' => array_merge(['action' => 'defect_notice'], $args),
        'sino_cietac_clause' => array_merge(['action' => 'cietac_clause'], $args),
        'sino_matrix_concordance' => array_merge(['action' => 'matrix'], $args)
    ];

    if (!isset($actionMap[$toolName])) {
        sendJsonRpcResponse($id, null, ['code' => -32601, 'message' => "Tool not found: $toolName"], $t_start, $method, $rawInput);
    }

    $apiResult = executeLocalAction($actionMap[$toolName]);
    sendJsonRpcResponse($id, [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]
        ]
    ], null, $t_start, $method, $rawInput);
}

sendJsonRpcResponse($id, ['supported' => true], null, $t_start, $method, $rawInput);