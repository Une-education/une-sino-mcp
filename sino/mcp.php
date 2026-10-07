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

$rawInput = file_get_contents('php://input');
$request = json_decode($rawInput, true);

function sendJsonRpcResponse(string|int|null $id, ?array $result, ?array $error, float $t_start, ?string $method, string $rawInput): void {
    $response = ['jsonrpc' => '2.0'];
    if ($id !== null) {
        $response['id'] = $id;
    }
    if ($error !== null) {
        $response['error'] = $error;
    } else {
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
            $execMs = round((microtime(true) - $t_start) * 1000, 2);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
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
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'operational',
        'server' => 'UNE Sino-Gateway MCP Server',
        'protocol' => 'MCP/JSON-RPC-2.0',
        'endpoints' => [
            'rpc' => 'https://legal.une.education/sino/mcp.php',
            'spec' => 'https://legal.une.education/sino/openapi.json'
        ],
        'tools_count' => count($tools)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
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

if ($method === 'notifications/initialized' || ($id === null && str_starts_with((string)$method, 'notifications/'))) {
    try {
        $logDb = new PDO('sqlite:' . __DIR__ . '/sino_gateway.sqlite');
        $stmt = $logDb->prepare("INSERT INTO mcp_logs (ip, method, user_agent, payload, exec_time_ms) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'cli', $method, $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', substr($rawInput, 0, 500), 0.5]);
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
            'version' => '1.1.0'
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
    $toolName = $params['name'] ?? '';
    $args = $params['arguments'] ?? [];

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
