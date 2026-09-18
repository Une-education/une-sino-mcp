<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$start_time = microtime(true);
$base_dir = __DIR__;

try {
    $gateway_db = new PDO("sqlite:{$base_dir}/gateway.sqlite", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $uri_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    // Si passage direct via api.php?endpoint=...
    $endpoint = $_GET['endpoint'] ?? $uri_path;
    // Supprimer le préfixe api.php/ éventuel
    $endpoint = preg_replace('#^api\.php/?#', '', $endpoint);
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cost = 1;

    // --- 1. Endpoint PING ---
    if ($endpoint === 'ping') {
        $opt = [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$legi_db = new PDO("sqlite:{$base_dir}/legifrance.sqlite", null, null, $opt);
$juri_db = new PDO("sqlite:{$base_dir}/jurisprudence.sqlite", null, null, $opt);
        $total_juri = (int)$juri_db->query('SELECT COUNT(*) FROM decisions')->fetchColumn();
        $total_legi = (int)$legi_db->query('SELECT COUNT(*) FROM articles')->fetchColumn();
        $total_cross = (int)$juri_db->query('SELECT COUNT(*) FROM decision_articles')->fetchColumn();

        echo json_encode([
            'status' => 'healthy',
            'timestamp' => time(),
            'indexed_decisions' => $total_juri,
            'indexed_articles' => $total_legi,
            'indexed_citations_cross' => $total_cross,
            'tier_discovery_credits' => 10000
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // --- 2. Endpoint REGISTER ---
    if ($endpoint === 'register') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode([
                'status' => 'error',
                'code' => 405,
                'message' => 'Méthode non autorisée. Utilisez le verbe POST pour générer une clé API.',
                'usage' => [
                    'method' => 'POST',
                    'endpoint' => '/api.php?endpoint=register',
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => ['name' => 'NomDeVotreAgentOuProjet']
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $label = trim((string)($input['name'] ?? $_POST['name'] ?? 'Autonomous Agent'));

        $plain_token = "sk_live_" . bin2hex(random_bytes(16));
        $key_hash = hash('sha256', $plain_token);
        $initial_credits = 10000;

        $ins = $gateway_db->prepare('INSERT INTO api_keys (key_hash, label, tier, balance_credits) VALUES (?, ?, "free_trial", ?)');
        $ins->execute([$key_hash, $label, $initial_credits]);
        $new_key_id = (int)$gateway_db->lastInsertId();

        $latency = round((microtime(true) - $start_time) * 1000, 2);
        $log = $gateway_db->prepare('INSERT INTO api_logs (ip_address, key_id, endpoint, http_status, latency_ms) VALUES (?, ?, "register", 201, ?)');
        $log->execute([$client_ip, $new_key_id, $latency]);

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'api_key' => $plain_token,
            'balance_credits' => $initial_credits,
            'message' => 'Clé générée avec succès.'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // --- 3. Authentification & Décompte ---
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $key_data = null;
    $remaining_credits = null;

    if (preg_match('/Bearer\s+(sk_[a-zA-Z0-9_-]+)/', $auth_header, $matches)) {
        $key_hash = hash('sha256', $matches[1]);
        $stmt = $gateway_db->prepare('SELECT * FROM api_keys WHERE key_hash = ? AND is_active = 1');
        $stmt->execute([$key_hash]);
        $key_data = $stmt->fetch();

        if ($key_data) {
            $remaining_credits = (int)$key_data['balance_credits'];
            if ($remaining_credits <= 0) {
                http_response_code(402);
                header('X-Credits-Remaining: 0');
                echo json_encode(['error' => 'Payment Required', 'message' => 'Solde épuisé.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    // Quota IP requêtes anonymes
    if (!$key_data) {
        $stmt = $gateway_db->prepare("SELECT COUNT(*) FROM api_logs WHERE ip_address = ? AND key_id IS NULL AND created_at >= datetime('now', '-1 minute')");
        $stmt->execute([$client_ip]);
        $hits = (int)$stmt->fetchColumn();
        $free_remaining = max(0, 60 - $hits);

        header("X-RateLimit-Limit: 60");
        header("X-RateLimit-Remaining: {$free_remaining}");

        if ($free_remaining <= 0) {
            http_response_code(429);
            echo json_encode(['error' => 'Too Many Requests', 'message' => 'Quota IP atteint (60 req/min).'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // --- 4. Exécution des requêtes métiers ---
    $response = [];

    // 1. ARTICLE LÉGIFRANCE
    if ($endpoint === 'article') {
        $cost = 1;
        $code = trim($_GET['code'] ?? '');
        $num = trim($_GET['num'] ?? '');

        if (!$code || !$num) {
            http_response_code(422);
            $response = [
                'status' => 'error',
                'message' => 'Paramètres requis manquants : code et num.',
                'usage' => [
                    'endpoint' => '/api.php?endpoint=article',
                    'params' => ['code' => 'Nom du code (ex: civil, travail, commerce)', 'num' => 'Numéro d\'article (ex: 1240, L1232-1)'],
                    'example' => '/api.php?endpoint=article&code=civil&num=1240'
                ]
            ];
        } else {
            $legi_db = new PDO("sqlite:{$base_dir}/legifrance.sqlite");
            $stmt = $legi_db->prepare('SELECT id, cid, code, num, etat, date_debut, date_fin, titre, texte FROM articles WHERE code LIKE ? AND num = ? LIMIT 1');
            $stmt->execute(['%' . $code . '%', $num]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($article) {
                $response = ['status' => 'success', 'data' => $article];
            } else {
                http_response_code(404);
                $response = ['status' => 'not_found', 'message' => 'Article introuvable.'];
            }
        }

    // 2. DÉCISION JUDILIBRE
    } elseif ($endpoint === 'decision') {
        $cost = 1;
        $id = trim($_GET['id'] ?? '');
        $ecli = trim($_GET['ecli'] ?? '');

        if (!$id && !$ecli) {
            http_response_code(422);
            $response = [
                'status' => 'error',
                'message' => 'Paramètre requis manquant : id ou ecli.',
                'usage' => [
                    'endpoint' => '/api.php?endpoint=decision',
                    'params' => ['id' => 'Identifiant Judilibre (ex: 6079be87...)', 'ecli' => 'Numéro ECLI complet'],
                    'example' => '/api.php?endpoint=decision&ecli=ECLI:FR:CCASS:2020:CR00589'
                ]
            ];
        } else {
            $juri_db = new PDO("sqlite:{$base_dir}/jurisprudence.sqlite");
            $stmt = $ecli
                ? $juri_db->prepare('SELECT id, ecli, date_decision, chambre, solution, publication, titres, sommaire, dispositif FROM decisions WHERE ecli = ? LIMIT 1')
                : $juri_db->prepare('SELECT id, ecli, date_decision, chambre, solution, publication, titres, sommaire, dispositif FROM decisions WHERE id = ? LIMIT 1');
            $stmt->execute([$ecli ?: $id]);
            $decision = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($decision) {
                $response = ['status' => 'success', 'data' => $decision];
            } else {
                http_response_code(404);
                $response = ['status' => 'not_found', 'message' => 'Décision introuvable.'];
            }
        }

    // 3. JURISPRUDENCE CROISÉE
    } elseif ($endpoint === 'cross') {
        $cost = 2;
        $code = trim($_GET['code'] ?? 'civil');
        $num = trim($_GET['num'] ?? '');
        $clean_num = str_replace([' ', '.'], '', strtoupper($num));

        if (!$num) {
            http_response_code(422);
            $response = [
                'status' => 'error',
                'message' => 'Paramètre requis manquant : num.',
                'usage' => [
                    'endpoint' => '/api.php?endpoint=cross',
                    'params' => ['num' => 'Numéro d\'article (obligatoire)', 'code' => 'Nom du code (optionnel, défaut: civil)'],
                    'example' => '/api.php?endpoint=cross&code=travail&num=L1232-1'
                ]
            ];
        } else {
            $legi_db = new PDO("sqlite:{$base_dir}/legifrance.sqlite");
            $juri_db = new PDO("sqlite:{$base_dir}/jurisprudence.sqlite");

            $stmt_art = $legi_db->prepare('SELECT id, code, num, etat, texte FROM articles WHERE code LIKE ? AND num = ? LIMIT 1');
            $stmt_art->execute(['%' . $code . '%', $num]);
            $article = $stmt_art->fetch(PDO::FETCH_ASSOC);

            $stmt_cross = $juri_db->prepare('
                SELECT d.id, d.ecli, d.date_decision, d.chambre, d.solution, d.sommaire
                FROM decision_articles da
                JOIN decisions d ON da.decision_id = d.id
                WHERE da.code LIKE ? AND da.num = ?
                ORDER BY d.date_decision DESC
                LIMIT 5
            ');
            $stmt_cross->execute(['%' . $code . '%', $clean_num]);
            $decisions = $stmt_cross->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'status' => 'success',
                'article' => $article ?: null,
                'jurisprudence_count' => count($decisions),
                'related_decisions' => $decisions
            ];
        }

    // 4. CONVENTIONS COLLECTIVES (KALI)
    } elseif ($endpoint === 'kali') {
        $cost = 1;
        $idcc = trim($_GET['idcc'] ?? '');
        $num = trim($_GET['num'] ?? '');
        $q = trim($_GET['q'] ?? '');

        if (!$idcc) {
            http_response_code(422);
            $response = [
                'status' => 'error',
                'message' => 'Paramètre requis manquant : idcc.',
                'usage' => [
                    'endpoint' => '/api.php?endpoint=kali',
                    'params' => ['idcc' => 'Identifiant de convention (obligatoire)', 'q' => 'Recherche texte (optionnel)', 'num' => 'Numéro d\'article (optionnel)'],
                    'example' => '/api.php?endpoint=kali&idcc=1486&q=preavis'
                ]
            ];
        } elseif (!file_exists("{$base_dir}/kali.sqlite")) {
            http_response_code(503);
            $response = ['status' => 'error', 'message' => 'Base KALI indisponible.'];
        } else {
            $kali_db = new PDO("sqlite:{$base_dir}/kali.sqlite", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            if ($q) {
                $safeQ = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
                $stmt = $kali_db->prepare('
                    SELECT f.article_id, f.idcc, f.num, f.titre, snippet(kali_fts, 4, "<b>", "</b>", "...", 25) AS extrait
                    FROM kali_fts f
                    WHERE kali_fts MATCH ? AND f.idcc = ?
                    ORDER BY rank
                    LIMIT 10
                ');
                $stmt->execute([$safeQ, $idcc]);
                $results = $stmt->fetchAll();

                $response = [
                    'status' => 'success',
                    'idcc' => $idcc,
                    'query' => $q,
                    'count' => count($results),
                    'results' => $results
                ];
            } elseif ($num) {
                $stmt = $kali_db->prepare('
                    SELECT a.id, a.idcc, a.num, a.titre, a.etat, a.texte, c.titre AS convention_titre
                    FROM kali_articles a
                    LEFT JOIN conventions c ON a.cid = c.id
                    WHERE a.idcc = ? AND a.num = ?
                    LIMIT 1
                ');
                $stmt->execute([$idcc, $num]);
                $article = $stmt->fetch();

                if ($article) {
                    $response = ['status' => 'success', 'article' => $article];
                } else {
                    http_response_code(404);
                    $response = ['status' => 'not_found', 'message' => "Article {$num} introuvable pour l'IDCC {$idcc}."];
                }
            } else {
                http_response_code(400);
                $response = ['status' => 'error', 'message' => 'Préciser un numéro (&num=) ou un terme de recherche (&q=).'];
            }
        }

    // ==========================================
    // TIER 1 : /v1/search (1 crédit)
    // Recherche FTS5 directe et rapide
    // ==========================================
    } elseif ($endpoint === 'v1/search' || $endpoint === 'search') {
        $cost = 1;
        $jurisdiction = strtoupper($_GET['jurisdiction'] ?? '');
        $corpus = strtolower($_GET['corpus'] ?? 'legi');
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));

        if (empty($q)) {
            $response = ['status' => 'error', 'message' => 'Paramètre de recherche requis (&q=).'];
        } elseif ($jurisdiction === 'EU' || $corpus === 'eu') {
            $eu_db = new PDO("sqlite:{$base_dir}/europe.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $safeQ = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
            $terms = array_filter(explode(' ', $safeQ));
            $match = implode(' AND ', array_map(fn($t) => "\"{$t}\"*", $terms));

            $stmt = $eu_db->prepare("
                SELECT a.id, a.act_code, a.article_num, a.title, a.text_fr, a.text_en
                FROM eu_fts f
                JOIN eu_articles a ON f.rowid = a.rowid
                WHERE eu_fts MATCH ?
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute([$match]);
            $results = $stmt->fetchAll();

            $response = [
                'tier' => 'Basic (/v1/search)',
                'cost_credits' => $cost,
                'jurisdiction' => 'EU',
                'query' => $q,
                'returned' => count($results),
                'results' => $results
            ];
        } elseif ($jurisdiction === 'CN' || $corpus === 'intl' || $corpus === 'cn') {
            $intl_db = new PDO("sqlite:{$base_dir}/international.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $stmt = $intl_db->prepare("
                SELECT id, jurisdiction, code_name, article_num, title, text_original, text_translated
                FROM foreign_articles
                WHERE code_name LIKE ? OR text_original LIKE ? OR title LIKE ?
                LIMIT {$limit} OFFSET {$offset}
            ");
            $like = "%{$q}%";
            $stmt->execute([$like, $like, $like]);
            $results = $stmt->fetchAll();

            $response = [
                'tier' => 'Basic (/v1/search)',
                'cost_credits' => $cost,
                'jurisdiction' => 'CN',
                'query' => $q,
                'returned' => count($results),
                'results' => $results
            ];
        } else {
            // Recherche droit français (Légifrance / KALI / Judilibre)
            if ($corpus === 'kali') {
                $k_db = new PDO("sqlite:{$base_dir}/kali.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
                $stmt = $k_db->prepare('
                    SELECT a.id, a.idcc, a.num, a.titre, SUBSTR(a.texte, 1, 200) as snippet
                    FROM articles a
                    JOIN conventions c ON a.idcc = c.idcc
                    WHERE a.texte LIKE ? OR a.num LIKE ?
                    LIMIT ? OFFSET ?
                ');
                $stmt->execute(['%' . $q . '%', '%' . $q . '%', $limit, $offset]);
                $results = $stmt->fetchAll();
            } elseif ($corpus === 'juri') {
                $j_db = new PDO("sqlite:{$base_dir}/jurisprudence.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
                $stmt = $j_db->prepare('
                    SELECT j.id, j.numero, j.decision_date, j.formation, SUBSTR(j.sommaire, 1, 200) as snippet
                    FROM decisions_fts f
                    JOIN decisions j ON f.rowid = j.rowid
                    WHERE decisions_fts MATCH ?
                    LIMIT ? OFFSET ?
                ');
                $stmt->execute([$q, $limit, $offset]);
                $results = $stmt->fetchAll();
            } else {
                $l_db = new PDO("sqlite:{$base_dir}/legifrance.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
                $stmt = $l_db->prepare('
                    SELECT a.id, a.code, a.num, a.titre, SUBSTR(a.texte, 1, 200) as snippet
                    FROM articles a
                    WHERE a.texte LIKE ? OR a.num LIKE ?
                    LIMIT ? OFFSET ?
                ');
                $stmt->execute(['%' . $q . '%', '%' . $q . '%', $limit, $offset]);
                $results = $stmt->fetchAll();
            }

            $response = [
                'tier' => 'Basic (/v1/search)',
                'cost_credits' => $cost,
                'corpus' => $corpus,
                'query' => $q,
                'returned' => count($results),
                'results' => $results
            ];
        }

    // ==========================================
        // ==========================================
    // TIER 2 : /v1/inspect (5 crédits)
    // Inspection exhaustive d'article (FR / EU / CN)
    // ==========================================
    } elseif ($endpoint === 'v1/inspect' || $endpoint === 'inspect') {
        $cost = 5;
        $id = trim((string)($_GET['id'] ?? ''));

        if (empty($id)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Paramètre &id= requis.'];
        } elseif (str_starts_with($id, 'EU_')) {
            $eu_db = new PDO("sqlite:{$base_dir}/europe.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $stmt = $eu_db->prepare('SELECT * FROM eu_articles WHERE id = ?');
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if (!$item) {
                http_response_code(404);
                $response = ['status' => 'error', 'message' => 'Article européen introuvable pour cet identifiant.'];
            } else {
                $response = [
                    'tier' => 'Pro (/v1/inspect)',
                    'cost_credits' => $cost,
                    'jurisdiction' => 'EU',
                    'id' => $item['id'],
                    'act_code' => $item['act_code'],
                    'act_title' => $item['act_title'],
                    'celex' => $item['celex'],
                    'article_num' => $item['article_num'],
                    'title' => $item['title'],
                    'text_fr' => $item['text_fr'],
                    'text_en' => $item['text_en'],
                    'source_url' => $item['source_url']
                ];
            }
        } elseif (str_starts_with($id, 'CN_')) {
            $intl_db = new PDO("sqlite:{$base_dir}/international.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $stmt = $intl_db->prepare('SELECT * FROM foreign_articles WHERE id = ?');
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if (!$item) {
                http_response_code(404);
                $response = ['status' => 'error', 'message' => 'Article chinois introuvable pour cet identifiant.'];
            } else {
                $response = [
                    'tier' => 'Pro (/v1/inspect)',
                    'cost_credits' => $cost,
                    'jurisdiction' => 'CN',
                    'id' => $item['id'],
                    'code_name' => $item['code_name'],
                    'article_num' => $item['article_num'],
                    'title' => $item['title'],
                    'text_original' => $item['text_original'],
                    'text_translated' => $item['text_translated'],
                    'source_url' => $item['source_url'],
                    'status' => $item['status']
                ];
            }
        } else {
            // Recherche par défaut : France (Légifrance / KALI)
            $l_db = new PDO("sqlite:{$base_dir}/legifrance.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $stmt = $l_db->prepare('SELECT * FROM articles WHERE id = ?');
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if (!$item) {
                // Fallback KALI
                $k_db = new PDO("sqlite:{$base_dir}/kali.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
                $stmtK = $k_db->prepare('SELECT * FROM articles WHERE id = ?');
                $stmtK->execute([$id]);
                $item = $stmtK->fetch();
                $jurisdiction = 'FR_KALI';
            } else {
                $jurisdiction = 'FR_LEGI';
            }

            if (!$item) {
                http_response_code(404);
                $response = ['status' => 'error', 'message' => 'Article introuvable dans le corpus français.'];
            } else {
                $response = [
                    'tier' => 'Pro (/v1/inspect)',
                    'cost_credits' => $cost,
                    'jurisdiction' => $jurisdiction,
                    'article' => $item
                ];
            }
        }

    // TIER 2 : /v1/cross (5 crédits)
    // Pivot Code + KALI + Cassation liée
    // ==========================================
    } elseif ($endpoint === 'v1/cross') {
        $cost = 5;
        $article_num = trim((string)($_GET['article'] ?? ''));
        $code = trim((string)($_GET['code'] ?? 'Code du travail'));
        $idcc = trim((string)($_GET['idcc'] ?? ''));

        if (empty($article_num)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Paramètre &article= requis (ex: L1234-1).'];
        } else {
            $l_db = new PDO("sqlite:{$base_dir}/legifrance.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $j_db = new PDO("sqlite:{$base_dir}/jurisprudence.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $k_db = new PDO("sqlite:{$base_dir}/kali.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

            // 1. Article de loi
            $stmtArt = $l_db->prepare('SELECT id, code, num, texte FROM articles WHERE code = ? AND num = ? LIMIT 1');
            $stmtArt->execute([$code, $article_num]);
            $lawArticle = $stmtArt->fetch();

            // 2. Décisions de cassation citant cet article
            $stmtJuri = $j_db->prepare('
                SELECT d.id, d.chambre, d.date_decision, d.solution, d.sommaire
                FROM decisions d
                JOIN decision_articles da ON d.id = da.decision_id
                WHERE da.num = ?
                ORDER BY d.date_decision DESC
                LIMIT 5
            ');
            $stmtJuri->execute([$article_num]);
            $jurisprudence = $stmtJuri->fetchAll();

            // 3. Rapprochement conventionnel si IDCC spécifié
            $kaliArticles = [];
            if (!empty($idcc)) {
                $stmtKali = $k_db->prepare('
                    SELECT a.id, a.idcc, a.num, a.titre, a.texte, c.titre as convention_titre
                    FROM kali_articles a
                    LEFT JOIN conventions c ON a.cid = c.id
                    WHERE a.idcc = ? AND a.texte LIKE ?
                    LIMIT 3
                ');
                $stmtKali->execute([$idcc, "%" . $article_num . "%"]);
                $kaliArticles = $stmtKali->fetchAll();
            }

            $response = [
                'tier' => 'Medium (/v1/cross)',
                'cost_credits' => $cost,
                'target' => ['code' => $code, 'article' => $article_num, 'idcc' => $idcc ?: null],
                'law_article' => $lawArticle ?: null,
                'jurisprudence_count' => count($jurisprudence),
                'jurisprudence' => $jurisprudence,
                'collective_agreements' => $kaliArticles
            ];
        }

    // TIER 3 : /v1/oracle (20 crédits)
    // Synthèse décisionnelle et arbitrage comparatif bilatéral (FR / EU / CN)
    // ==========================================
    } elseif ($endpoint === 'v1/oracle' || $endpoint === 'oracle') {
        $cost = 20;
        $subject = trim((string)($_GET['subject'] ?? ''));

        if (empty($subject)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Paramètre &subject= requis.'];
        } else {
            $t0 = microtime(true);

            // Thésaurus bilatéral d'alignement rapide
            $thesaurus = [
                'ia' => ['eu_terms' => 'intelligence artificielle IA système', 'cn_code' => 'Loi sur les brevets de la RPC (2020)', 'cn_terms' => ['发明', '专利', '技术'], 'risk' => 'HIGH_REGULATORY_DIVERGENCE'],
                'donnees' => ['eu_terms' => 'données personnelles traitement responsable', 'cn_code' => 'Loi sur la protection des informations personnelles (PIPL)', 'cn_terms' => ['个人信息', '处理', '出境'], 'risk' => 'CRITICAL_CROSS_BORDER_RESTRICTION'],
                'travail' => ['eu_terms' => 'travailleur directive conditions', 'cn_code' => 'Loi sur le contrat de travail de la RPC', 'cn_terms' => ['劳动合同', '解除', '经济补偿'], 'risk' => 'MEDIUM_STATUTORY_VARIANCE'],
                'brevet' => ['eu_terms' => 'brevet propriété intellectuelle contrefaçon', 'cn_code' => 'Loi sur les brevets de la RPC (2020)', 'cn_terms' => ['专利权', '侵权', '许可'], 'risk' => 'HIGH_LITIGATION_EXPOSURE'],
                'litige' => ['eu_terms' => 'tribunal compétence juridiction arbitrage', 'cn_code' => 'Loi sur la procédure civile de la RPC (2023)', 'cn_terms' => ['管辖', '诉讼', '仲裁', '执行'], 'risk' => 'HIGH_ENFORCEMENT_FRICTION']
            ];

            // Détection du domaine
            $subLower = strtolower($subject);
            $profile = null;
            foreach ($thesaurus as $k => $v) {
                if (str_contains($subLower, $k)) {
                    $profile = $v;
                    break;
                }
            }
            if (!$profile) {
                $profile = [
                    'eu_terms' => $subject,
                    'cn_code' => 'Corpus RPC',
                    'cn_terms' => [$subject],
                    'risk' => 'ASSESSMENT_REQUIRED'
                ];
            }

            // 1. Recherche Europe via FTS5
            $eu_db = new PDO("sqlite:{$base_dir}/europe.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $safeEu = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $profile['eu_terms']);
            $euTerms = array_filter(explode(' ', $safeEu));
            $euMatch = implode(' OR ', array_map(fn($t) => "\"{$t}\"*", array_slice($euTerms, 0, 3)));

            $stmtEu = $eu_db->prepare("
                SELECT a.id, a.act_code, a.article_num, a.title, SUBSTR(a.text_fr, 1, 300) as snippet_fr, SUBSTR(a.text_en, 1, 300) as snippet_en
                FROM eu_fts f
                JOIN eu_articles a ON f.rowid = a.rowid
                WHERE eu_fts MATCH ?
                LIMIT 3
            ");
            $stmtEu->execute([$euMatch]);
            $euMatches = $stmtEu->fetchAll();

            // 2. Recherche Chine ciblée
            $intl_db = new PDO("sqlite:{$base_dir}/international.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $searchWord = $profile['cn_terms'][0] ?? $subject;
            
            $stmtCn = $intl_db->prepare("
                SELECT id, code_name, article_num, title, SUBSTR(text_original, 1, 300) as snippet_cn, source_url
                FROM foreign_articles
                WHERE text_original LIKE ?
                LIMIT 3
            ");
            $stmtCn->execute(["%{$searchWord}%"]);
            $cnMatches = $stmtCn->fetchAll();

            // 3. Cadre France (Légifrance avec limite stricte)
            $l_db = new PDO("sqlite:{$base_dir}/legifrance.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $stmtFr = $l_db->prepare("
                SELECT a.id, a.code, a.num, SUBSTR(a.texte, 1, 300) as snippet_fr
                FROM articles a
                WHERE a.texte LIKE ?
                LIMIT 2
            ");
            $stmtFr->execute(["%{$subject}%"]);
            $frMatches = $stmtFr->fetchAll();

            $executionTimeMs = round((microtime(true) - $t0) * 1000, 2);

            $response = [
                'tier' => 'Oracle (/v1/oracle)',
                'cost_credits' => $cost,
                'subject' => $subject,
                'execution_time_ms' => $executionTimeMs,
                'alignment_matrix' => [
                    'risk_level' => $profile['risk'],
                    'european_union' => [
                        'count' => count($euMatches),
                        'provisions' => $euMatches
                    ],
                    'china' => [
                        'count' => count($cnMatches),
                        'provisions' => $cnMatches
                    ],
                    'france' => [
                        'count' => count($frMatches),
                        'provisions' => $frMatches
                    ]
                ],
                'actionable_notes' => [
                    'drill_down_ready' => 'Utiliser /v1/inspect?id={id} pour obtenir le texte intégral canonique.',
                    'bilateral_compliance_advice' => 'Vérifier la stricte conformité des transferts transfrontières et des dépôts de brevets selon les articles listés.'
                ]
            ];
        }

    // ==========================================
    // INGESTION INTERNATIONALE : /v1/sync
    // Réservé aux agents authentifiés
    // ==========================================
    } elseif ($endpoint === 'v1/sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $articles = $input['articles'] ?? [];

        if (empty($articles) || !is_array($articles)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Tableau d\'articles manquant ou invalide.'];
        } else {
            $intl_db = new PDO("sqlite:{$base_dir}/international.sqlite", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            $stmt = $intl_db->prepare('
                INSERT OR REPLACE INTO foreign_articles 
                (id, jurisdiction, code_name, article_num, title, text_original, text_translated, source_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $intl_db->beginTransaction();
            $inserted = 0;
            foreach ($articles as $art) {
                if (empty($art['id']) || empty($art['text_original'])) continue;
                $stmt->execute([
                    $art['id'],
                    $art['jurisdiction'] ?? 'UNKNOWN',
                    $art['code_name'] ?? 'General',
                    $art['article_num'] ?? '',
                    $art['title'] ?? null,
                    $art['text_original'],
                    $art['text_translated'] ?? null,
                    $art['source_url'] ?? null
                ]);
                $inserted++;
            }
            $intl_db->commit();

            $response = [
                'status' => 'success',
                'synced_count' => $inserted
            ];
        }

        // POINT D ACCÈS INCONNU
    } else {
        http_response_code(404);
        $response = ['status' => 'error', 'message' => 'Point d\'accès inconnu.'];
    }

    $status_code = http_response_code();

    // Décrémentation selon le coût de la route
    if ($key_data && $status_code === 200) {
        $dec = $gateway_db->prepare('UPDATE api_keys SET balance_credits = balance_credits - ? WHERE id = ?');
        $dec->execute([$cost, $key_data['id']]);
        $remaining_credits = max(0, $remaining_credits - $cost);
        header("X-Credits-Remaining: {$remaining_credits}");
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    $status_code = 500;
    echo json_encode(['error' => 'Erreur serveur', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// Enregistrement métrique
$latency = round((microtime(true) - $start_time) * 1000, 2);
if (isset($gateway_db)) {
    try {
        $log = $gateway_db->prepare('INSERT INTO api_logs (ip_address, key_id, endpoint, http_status, latency_ms) VALUES (?, ?, ?, ?, ?)');
        $log->execute([$client_ip, $key_data['id'] ?? null, $endpoint ?? 'unknown', $status_code, $latency]);
    } catch (Throwable $ignored) {}
}
