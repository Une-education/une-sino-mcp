<?php
/**
 * ATOA Global Telemetry & MCP Network Pulse
 */

// Initialisation fenêtre 14 jours
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = [
        'customs'  => 0,
        'sino'     => 0,
        'france'   => 0,
        'resolver' => 0,
        'errors'   => 0
    ];
}

$nodes = [
    'customs'  => ['name' => 'Customs MCP', 'path' => '/var/www/customs-api/data/customs.db', 'color' => '#38bdf8'],
    'sino'     => ['name' => 'Sino Gateway', 'path' => __DIR__ . '/sino/sino_gateway.sqlite', 'color' => '#4ade80'],
    'france'   => ['name' => 'France Legal', 'path' => __DIR__ . '/france/auth.sqlite', 'color' => '#a78bfa'],
    'resolver' => ['name' => 'Resolver API', 'path' => '/var/www/resolver-api/data/resolver.sqlite', 'color' => '#fbbf24']
];

$counts = ['customs' => 0, 'sino' => 0, 'france' => 0, 'resolver' => 0];
$recentLogs = [];
$totalSuccess = 0;
$totalErrors = 0;

foreach ($nodes as $key => $conf) {
    if (!file_exists($conf['path'])) continue;
    try {
        $db = new PDO('sqlite:' . $conf['path'], '', '', [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        
        // Comptage total
        $cnt = (int)$db->query("SELECT COUNT(*) FROM mcp_logs")->fetchColumn();
        $counts[$key] = $cnt;
        $totalSuccess += $cnt;

        // Agrégation par jour
        $stmt = $db->query("
            SELECT substr(timestamp, 1, 10) as jour, COUNT(*) as total
            FROM mcp_logs
            WHERE timestamp >= date('now', '-14 days')
            GROUP BY jour
        ");
        foreach ($stmt->fetchAll() as $row) {
            $j = $row['jour'];
            if (isset($days[$j])) {
                $days[$j][$key] += (int)$row['total'];
            }
        }

        // 20 derniers logs de chaque nœud
        $logs = $db->query("
            SELECT '{$key}' as node, id, timestamp, ip, method, user_agent, payload, exec_time_ms
            FROM mcp_logs
            ORDER BY id DESC LIMIT 20
        ")->fetchAll();
        $recentLogs = array_merge($recentLogs, $logs);

    } catch (Exception $e) {}
}

// Fusion et tri chronologique descendant des logs
usort($recentLogs, function($a, $b) {
    return strcmp($b['timestamp'], $a['timestamp']);
});
$recentLogs = array_slice($recentLogs, 0, 50);

// Typologie visiteurs basée sur User-Agent des logs MCP
$trafficTypes = ['Agents & IA' => 0, 'Crawlers' => 0, 'Direct / Outils' => 0];
foreach ($recentLogs as $l) {
    $ua = strtolower($l['user_agent'] ?? '');
    if (preg_match('/(claude|gpt|agent|mcp|langchain|cursor|smithery|fetcher)/', $ua)) {
        $trafficTypes['Agents & IA']++;
    } elseif (preg_match('/(bot|crawl|spider|slurp|bing|google)/', $ua)) {
        $trafficTypes['Crawlers']++;
    } else {
        $trafficTypes['Direct / Outils']++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>AtoA Network Telemetry</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6 font-sans">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-3">
                    <span class="inline-block w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                    Supervision Télémétrique & Réseau M2M (AtoA)
                </h1>
                <p class="text-xs text-slate-400 mt-1">Nœuds standardisés : JSON-RPC 2.0 / Model Context Protocol</p>
            </div>
            <div class="text-right text-xs text-slate-500 font-mono">
                Sync : <?= date('Y-m-d H:i:s') ?> UTC
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <span class="text-xs font-semibold text-sky-400 uppercase tracking-wider">Customs MCP</span>
                <p class="text-3xl font-black text-white mt-1"><?= number_format($counts['customs']) ?></p>
                <span class="text-[11px] text-slate-500 font-mono">taric / customs-bridge</span>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Sino Gateway</span>
                <p class="text-3xl font-black text-white mt-1"><?= number_format($counts['sino']) ?></p>
                <span class="text-[11px] text-slate-500 font-mono">prc / cisg bridge</span>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <span class="text-xs font-semibold text-violet-400 uppercase tracking-wider">France Legal</span>
                <p class="text-3xl font-black text-white mt-1"><?= number_format($counts['france']) ?></p>
                <span class="text-[11px] text-slate-500 font-mono">legifrance / kali</span>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <span class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Resolver API</span>
                <p class="text-3xl font-black text-white mt-1"><?= number_format($counts['resolver']) ?></p>
                <span class="text-[11px] text-slate-500 font-mono">graphs / constraints</span>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-xl p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-300 mb-4">Activité & Traction (14 Derniers Jours)</h2>
                <div class="h-64">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-300 mb-4">Profil Consommateurs</h2>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Table des Logs Récents -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-300">Flux d'Exécution Récent — Réseau AtoA Global</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider border-b border-slate-800 font-mono text-[11px]">
                        <tr>
                            <th class="py-3 px-4">Date (UTC)</th>
                            <th class="py-3 px-4">Nœud</th>
                            <th class="py-3 px-4">IP</th>
                            <th class="py-3 px-4">Méthode MCP</th>
                            <th class="py-3 px-4">User-Agent</th>
                            <th class="py-3 px-4 text-right">Latence</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 font-mono">
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">Aucune activité enregistrée</td></tr>
                        <?php else: foreach ($recentLogs as $log): 
                            $nodeColor = [
                                'customs'  => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                'sino'     => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                'france'   => 'bg-violet-500/10 text-violet-400 border-violet-500/30',
                                'resolver' => 'bg-amber-500/10 text-amber-400 border-amber-500/30'
                            ][$log['node']] ?? 'bg-slate-700 text-slate-300';
                        ?>
                            <tr class="hover:bg-slate-800/50 transition">
                                <td class="py-3 px-4 text-slate-400"><?= htmlspecialchars($log['timestamp']) ?></td>
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2 py-0.5 rounded border text-[10px] uppercase font-bold <?= $nodeColor ?>">
                                        <?= htmlspecialchars($log['node']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-300"><?= htmlspecialchars($log['ip']) ?></td>
                                <td class="py-3 px-4 text-emerald-400 font-semibold"><?= htmlspecialchars($log['method']) ?></td>
                                <td class="py-3 px-4 text-slate-400 truncate max-w-xs" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                    <?= htmlspecialchars(substr($log['user_agent'] ?? 'unknown', 0, 45)) ?>
                                </td>
                                <td class="py-3 px-4 text-right text-slate-300"><?= number_format((float)($log['exec_time_ms'] ?? 0), 2) ?> ms</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        const daysLabels = <?= json_encode(array_map(function($d) { return substr($d, 5); }, array_keys($days))) ?>;
        
        new Chart(document.getElementById('trafficChart'), {
            type: 'line',
            data: {
                labels: daysLabels,
                datasets: [
                    {
                        label: 'Customs',
                        data: <?= json_encode(array_column($days, 'customs')) ?>,
                        borderColor: '#38bdf8',
                        backgroundColor: 'rgba(56, 189, 248, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Sino',
                        data: <?= json_encode(array_column($days, 'sino')) ?>,
                        borderColor: '#4ade80',
                        backgroundColor: 'transparent',
                        tension: 0.3
                    },
                    {
                        label: 'France',
                        data: <?= json_encode(array_column($days, 'france')) ?>,
                        borderColor: '#a78bfa',
                        backgroundColor: 'transparent',
                        tension: 0.3
                    },
                    {
                        label: 'Resolver',
                        data: <?= json_encode(array_column($days, 'resolver')) ?>,
                        borderColor: '#fbbf24',
                        backgroundColor: 'transparent',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8', font: { size: 11 } } } },
                scales: {
                    x: { grid: { color: 'rgba(51, 65, 85, 0.4)' }, ticks: { color: '#64748b' } },
                    y: { grid: { color: 'rgba(51, 65, 85, 0.4)' }, ticks: { color: '#64748b' } }
                }
            }
        });

        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($trafficTypes)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($trafficTypes)) ?>,
                    backgroundColor: ['#a855f7', '#f59e0b', '#3b82f6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 11 } } } }
            }
        });
    </script>
</body>
</html>
