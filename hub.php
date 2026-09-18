<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtoA — Multi-Jurisdiction Legal API Hub</title>
    <style>
        :root {
            --bg: #0d1117;
            --surface: #161b22;
            --border: #30363d;
            --text: #c9d1d9;
            --heading: #f0f6fc;
            --accent: #58a6ff;
            --success: #2ea043;
            --warning: #d29922;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 24px;
            max-width: 1100px;
            margin: 0 auto;
        }
        h1, h2, h3, h4 { color: var(--heading); margin-top: 0; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 24px;
        }
        pre {
            background: #111827;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 1rem;
            color: #e5e7eb;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-x: auto;
        }
        .tag {
            background: #1f2937;
            border: 1px solid #374151;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #d1d5db;
        }
    </style>
</head>
<body>

    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1>AtoA — Multi-Jurisdiction Legal API Hub</h1>
            <p style="margin: 0; font-size: 14px; color: #8b949e;">Interface M2M haute performance pour l'interrogation et l'arbitrage croisé (France · UE · Chine)</p>
        </div>
        <div>
            <a href="/openapi.json" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 13px; margin-right: 15px;">OpenAPI Spec (v2.4)</a>
            <a href="/llms.txt" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 13px;">llms.txt</a>
        </div>
    </header>

    <!-- Couverture Juridique Tripartite -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-bottom: 24px;">
        <div class="card" style="border-left: 4px solid #3b82f6; margin-bottom: 0;">
            <h4 style="color: #60a5fa; margin-bottom: 0.5rem;">Droit Français (FR)</h4>
            <p style="color: #9ca3af; font-size: 0.85rem; margin: 0 0 0.5rem 0;">Légifrance (codes consolidés), KALI (conventions collectives) et jurisprudence Judilibre.</p>
            <span class="tag">FTS5 natif</span> <span class="tag">Codes & KALI</span>
        </div>
        <div class="card" style="border-left: 4px solid #10b981; margin-bottom: 0;">
            <h4 style="color: #34d399; margin-bottom: 0.5rem;">Union Européenne (EU)</h4>
            <p style="color: #9ca3af; font-size: 0.85rem; margin: 0 0 0.5rem 0;">Directives et règlements majeurs (AI Act, RGPD, DSA, DMA, NIS2, Data Act) bilingues FR/EN.</p>
            <span class="tag">FTS5 bilingue</span> <span class="tag">CELEX</span>
        </div>
        <div class="card" style="border-left: 4px solid #ef4444; margin-bottom: 0;">
            <h4 style="color: #f87171; margin-bottom: 0.5rem;">République Populaire de Chine (CN)</h4>
            <p style="color: #9ca3af; font-size: 0.85rem; margin: 0 0 0.5rem 0;">Code civil 2020, PIPL, Lois sur les brevets 2020 et la procédure civile 2023 en version originale.</p>
            <span class="tag">Texte source</span> <span class="tag">PIPL & IP</span>
        </div>
    </div>

    <!-- Console de Test Interactive -->
    <div class="card" style="border-left: 4px solid #3b82f6;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; color: #60a5fa;">Console de Test Interactive</h3>
            <span id="pg-latency" style="display: none; background: #374151; color: #10b981; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; font-family: monospace;">0 ms</span>
        </div>
        <p style="color: #9ca3af; font-size: 0.9rem; margin-bottom: 1.25rem;">
            Testez les routes de l'API en direct. Choisissez un palier d'accès, ajustez les paramètres et observez la réponse JSON.
        </p>

        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
            <button type="button" class="pg-tab active" data-ep="v1/search" style="background: #2563eb; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">Tier 1 : /v1/search</button>
            <button type="button" class="pg-tab" data-ep="v1/inspect" style="background: #374151; color: #d1d5db; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">Tier 2 : /v1/inspect</button>
            <button type="button" class="pg-tab" data-ep="v1/oracle" style="background: #374151; color: #d1d5db; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">Tier 3 : /v1/oracle</button>
            <button type="button" class="pg-tab" data-ep="kali" style="background: #374151; color: #d1d5db; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">KALI (Conventions)</button>
            <button type="button" class="pg-tab" data-ep="cross" style="background: #374151; color: #d1d5db; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">Cross (Légifrance + Cassation)</button>
        </div>

        <div id="pg-inputs" style="display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;"></div>

        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <button type="button" id="pg-submit" style="background: #10b981; color: #fff; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-size: 0.9rem; font-weight: 600;">Exécuter la requête</button>
        </div>

        <div style="position: relative;">
            <pre id="pg-output" style="max-height: 380px; overflow-y: auto; margin: 0; white-space: pre-wrap; word-break: break-word;">Cliquez sur « Exécuter la requête » pour tester l'endpoint sélectionné.</pre>
        </div>
    </div>

    <script>
    (function() {
        const tabs = document.querySelectorAll('.pg-tab');
        const inputsDiv = document.getElementById('pg-inputs');
        const submitBtn = document.getElementById('pg-submit');
        const outputPre = document.getElementById('pg-output');
        const latencySpan = document.getElementById('pg-latency');

        let currentEp = 'v1/search';

        const schemas = {
            'v1/search': [
                { name: 'q', label: 'Mot-clé ou concept', value: 'transparence', placeholder: 'ex: transparence, 专利' },
                { name: 'jurisdiction', label: 'Juridiction (EU, CN, FR)', value: 'EU', placeholder: 'EU, CN, FR' },
                { name: 'limit', label: 'Limite', value: '2', placeholder: '2' }
            ],
            'v1/inspect': [
                { name: 'id', label: 'Identifiant canonique', value: 'EU_AI_ACT_ART_6', placeholder: 'ex: EU_AI_ACT_ART_6, CN_...' }
            ],
            'v1/oracle': [
                { name: 'subject', label: 'Domaine d\'alignement', value: 'ia', placeholder: 'ia, donnees, brevets, travail, litige' }
            ],
            'kali': [
                { name: 'idcc', label: 'IDCC', value: '1486', placeholder: '1486' },
                { name: 'q', label: 'Recherche plein texte', value: 'rupture conventionnelle', placeholder: 'ex: préavis' }
            ],
            'cross': [
                { name: 'code', label: 'Code', value: 'travail', placeholder: 'civil, travail' },
                { name: 'num', label: 'N° Article', value: 'L1232-1', placeholder: '1240, L1232-1' }
            ]
        };

        function renderInputs(ep) {
            inputsDiv.innerHTML = '';
            schemas[ep].forEach(field => {
                const wrap = document.createElement('div');
                wrap.style.display = 'flex';
                wrap.style.flexDirection = 'column';
                wrap.style.gap = '4px';
                wrap.style.flex = '1';
                wrap.style.minWidth = '180px';

                const lbl = document.createElement('label');
                lbl.textContent = field.label;
                lbl.style.fontSize = '0.75rem';
                lbl.style.color = '#9ca3af';
                lbl.style.textTransform = 'uppercase';

                const inp = document.createElement('input');
                inp.type = 'text';
                inp.id = 'pg-field-' + field.name;
                inp.value = field.value || '';
                inp.placeholder = field.placeholder || '';
                inp.style.background = '#111827';
                inp.style.border = '1px solid #4b5563';
                inp.style.borderRadius = '4px';
                inp.style.padding = '8px 10px';
                inp.style.color = '#fff';
                inp.style.fontSize = '0.85rem';

                wrap.appendChild(lbl);
                wrap.appendChild(inp);
                inputsDiv.appendChild(wrap);
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => {
                    t.style.background = '#374151';
                    t.style.color = '#d1d5db';
                });
                this.style.background = '#2563eb';
                this.style.color = '#fff';
                currentEp = this.dataset.ep;
                renderInputs(currentEp);
            });
        });

        submitBtn.addEventListener('click', async function() {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Requête en cours...';
            outputPre.textContent = 'Chargement...';
            latencySpan.style.display = 'none';

            const params = new URLSearchParams();
            params.set('endpoint', currentEp);

            schemas[currentEp].forEach(f => {
                const el = document.getElementById('pg-field-' + f.name);
                if (el && el.value.trim()) {
                    params.set(f.name, el.value.trim());
                }
            });

            const t0 = performance.now();
            try {
                const res = await fetch('/api.php?' + params.toString());
                const t1 = performance.now();
                const latency = Math.round(t1 - t0);

                const data = await res.json();
                outputPre.textContent = JSON.stringify(data, null, 2);

                latencySpan.textContent = latency + ' ms (HTTP ' + res.status + ')';
                latencySpan.style.color = res.ok ? '#10b981' : '#f59e0b';
                latencySpan.style.display = 'inline-block';
            } catch (err) {
                outputPre.textContent = 'Erreur réseau : ' + err.message;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Exécuter la requête';
            }
        });

        renderInputs(currentEp);
    })();
    </script>
</body>
</html>
