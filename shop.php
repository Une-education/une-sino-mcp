<?php
declare(strict_types=1);

$base_dir = __DIR__;

// 1. Inventaire Droit Français (Légifrance)
$legi_counts = [];
$legi_total = 0;
if (file_exists("{$base_dir}/legifrance.sqlite")) {
    $db = new PDO("sqlite:{$base_dir}/legifrance.sqlite");
    $stmt = $db->query("SELECT code, count(*) as cnt FROM articles GROUP BY code ORDER BY cnt DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $legi_counts[$row['code']] = (int)$row['cnt'];
        $legi_total += (int)$row['cnt'];
    }
}

// 2. Inventaire Conventions (KALI)
$kali_count = 0;
if (file_exists("{$base_dir}/kali.sqlite")) {
    $db = new PDO("sqlite:{$base_dir}/kali.sqlite");
    $kali_count = (int)$db->query("SELECT count(*) FROM kali_articles")->fetchColumn();
}

// 3. Inventaire Jurisprudence
$juris_count = 0;
if (file_exists("{$base_dir}/jurisprudence.sqlite")) {
    $db = new PDO("sqlite:{$base_dir}/jurisprudence.sqlite");
    $juris_count = (int)$db->query("SELECT count(*) FROM decisions")->fetchColumn();
}

// 4. Inventaire International (RPC)
$intl_counts = [];
$intl_total = 0;
if (file_exists("{$base_dir}/international.sqlite")) {
    $db = new PDO("sqlite:{$base_dir}/international.sqlite");
    $stmt = $db->query("SELECT code_name, count(*) as cnt FROM foreign_articles WHERE jurisdiction='CN' GROUP BY code_name ORDER BY cnt DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $intl_counts[$row['code_name']] = (int)$row['cnt'];
        $intl_total += (int)$row['cnt'];
    }
}


// 4b. Inventaire Union Européenne (EUR-Lex)
$eu_counts = [];
$eu_total = 0;
if (file_exists("{$base_dir}/europe.sqlite")) {
    try {
        $db_eu = new PDO("sqlite:{$base_dir}/europe.sqlite");
        $stmt = $db_eu->query("SELECT act_code, count(*) as cnt FROM eu_articles GROUP BY act_code ORDER BY cnt DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eu_counts[$row['act_code']] = (int)$row['cnt'];
            $eu_total += (int)$row['cnt'];
        }
    } catch (Exception $e) {}
}

// État récolte en cours
$harvest_state = file_exists("{$base_dir}/harvest_legifrance_state.txt")
    ? trim(file_get_contents("{$base_dir}/harvest_legifrance_state.txt"))
    : "Inactif";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>AtoA Legal Supermarché - Vue Aérienne</title>
    <style>
        :root {
            --bg: #0f172a;
            --aisle: #1e293b;
            --shelf: #334155;
            --accent-fr: #3b82f6;
            --accent-eu: #38bdf8;
        --accent-cn: #ef4444;
            --accent-kali: #10b981;
            --accent-juris: #f59e0b;
            --text: #f8fafc;
            --text-dim: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--aisle);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .header-title h1 { font-size: 1.4rem; letter-spacing: 1px; }
        .header-stats { display: flex; gap: 20px; font-size: 0.9rem; }
        .badge {
            background: var(--aisle);
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--shelf);
        }
        .badge strong { color: #38bdf8; }
        .live-status {
            animation: pulse 2s infinite;
            color: #4ade80;
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        /* Supermarket Layout Blueprint */
        .store-map {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr;
            gap: 24px;
        }
        .aisle-zone {
            background: var(--aisle);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #293548;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .zone-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 1rem;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--shelf);
        }
        .zone-fr { border-top: 4px solid var(--accent-fr); }
        .zone-cn { border-top: 4px solid var(--accent-cn); }
        .zone-branches { border-top: 4px solid var(--accent-kali); }

        .shelves-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        .shelf-item {
            background: var(--shelf);
            padding: 12px;
            border-radius: 6px;
            position: relative;
            overflow: hidden;
            border-left: 3px solid #64748b;
        }
        .shelf-item.active-restock {
            border-left-color: #f59e0b;
            background: #242f44;
        }
        .shelf-title {
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .shelf-qty {
            font-size: 1.15rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        .shelf-sub {
            font-size: 0.72rem;
            color: var(--text-dim);
            margin-top: 2px;
        }
        .aisle-walkway {
            text-align: center;
            font-size: 0.75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #64748b;
            padding: 6px 0;
            background: rgba(0,0,0,0.15);
            border-radius: 4px;
        }
    </style>
</head>
<body>

<header>
    <div class="header-title">
        <h1>🏪 ATOA CENTRAL STORE // VUE RAYONNAGE</h1>
        <div style="font-size: 0.8rem; color: var(--text-dim); margin-top: 4px;">Surveillance volumétrique et cartographie des stocks juridiques</div>
    </div>
    <div class="header-stats">
        <div class="badge">Stock total : <strong><?= number_format($legi_total + $kali_count + $juris_count + $intl_total, 0, ',', ' ') ?></strong> articles</div>
        <div class="badge">Réassort : <span class="live-status"><?= htmlspecialchars($harvest_state) ?></span></div>
    </div>
</header>

<div class="store-map">
    <!-- RAYON 1 : DROIT NATIONAL FRANÇAIS -->
    <div class="aisle-zone zone-fr">
        <div class="zone-header">
            <span>🇫🇷 ALLÉE NATIONALE // LÉGIFRANCE</span>
            <span style="font-size: 0.85rem; color: var(--accent-fr);"><?= number_format($legi_total, 0, ',', ' ') ?> articles</span>
        </div>
        <div class="aisle-walkway">▲ Allée Entreprises & Relations Sociales ▲</div>
        <div class="shelves-grid">
            <?php foreach ($legi_counts as $name => $count): 
                $is_restocking = (str_contains($harvest_state, 'procédures fiscales') && str_contains($name, 'procédures fiscales'));
            ?>
                <div class="shelf-item <?= $is_restocking ? 'active-restock' : '' ?>">
                    <div class="shelf-title" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></div>
                    <div class="shelf-qty"><?= number_format($count, 0, ',', ' ') ?></div>
                    <div class="shelf-sub"><?= $is_restocking ? '⚡ Réassort direct...' : 'En rayon' ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RAYON 2 : CONVENTIONS & JURISPRUDENCE -->
    <div class="aisle-zone zone-branches">
        <div class="zone-header">
            <span>⚖️ ALLÉE NÉGOCIATION & PRECEDENTS</span>
            <span style="font-size: 0.85rem; color: var(--accent-kali);"><?= number_format($kali_count + $juris_count, 0, ',', ' ') ?> docs</span>
        </div>
        <div class="aisle-walkway">▲ Accords & Jurisprudence ▲</div>
        <div class="shelves-grid" style="grid-template-columns: 1fr;">
            <div class="shelf-item" style="border-left-color: var(--accent-kali);">
                <div class="shelf-title">Conventions Collectives (KALI)</div>
                <div class="shelf-qty"><?= number_format($kali_count, 0, ',', ' ') ?></div>
                <div class="shelf-sub">Accords de branches étendus</div>
            </div>
            <div class="shelf-item" style="border-left-color: var(--accent-juris);">
                <div class="shelf-title">Jurisprudence (Judilibre)</div>
                <div class="shelf-qty"><?= number_format($juris_count, 0, ',', ' ') ?></div>
                <div class="shelf-sub">Arrêts de cassation & cadrages</div>
            </div>
        </div>
    </div>

    
    <!-- RAYON 2B : DROIT EUROPÉEN (EUR-LEX) -->
    <div class="aisle-zone zone-eu" style="border-left: 4px solid var(--accent-eu);">
        <div class="zone-header">
            <span>🇪🇺 ALLÉE UNION EUROPÉENNE (EUR-LEX)</span>
            <span style="font-size: 0.85rem; color: var(--accent-eu);"><?= number_format($eu_total, 0, ',', ' ') ?> articles bilingues</span>
        </div>
        <div class="aisle-walkway">▲ Règlements & Directives Majeurs (FR / EN) ▲</div>
        <div class="shelves-grid" style="grid-template-columns: 1fr;">
            <?php if (empty($eu_counts)): ?>
                <div class="shelf-item" style="border-left-color: var(--accent-eu);">
                    <div class="shelf-title">Corpus Européen</div>
                    <div class="shelf-qty">0</div>
                    <div class="shelf-sub">europe.sqlite en cours d'initialisation</div>
                </div>
            <?php else: ?>
                <?php foreach ($eu_counts as $name => $count): ?>
                    <div class="shelf-item" style="border-left-color: var(--accent-eu);">
                        <div class="shelf-title" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></div>
                        <div class="shelf-qty"><?= number_format($count, 0, ',', ' ') ?></div>
                        <div class="shelf-sub">Miroir officiel FR / EN (CELEX)</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RAYON 3 : IMPORT / INTERNATIONAL (RPC) -->
    <div class="aisle-zone zone-cn">
        <div class="zone-header">
            <span>🇨🇳 ALLÉE IMPORT // RPC (CHINE)</span>
            <span style="font-size: 0.85rem; color: var(--accent-cn);"><?= number_format($intl_total, 0, ',', ' ') ?> textes</span>
        </div>
        <div class="aisle-walkway">▲ Corpus Codifié Étranger ▲</div>
        <div class="shelves-grid" style="grid-template-columns: 1fr;">
            <?php foreach ($intl_counts as $name => $count): ?>
                <div class="shelf-item" style="border-left-color: var(--accent-cn);">
                    <div class="shelf-title" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></div>
                    <div class="shelf-qty"><?= number_format($count, 0, ',', ' ') ?></div>
                    <div class="shelf-sub">Version officielle en vigueur</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</body>
</html>
