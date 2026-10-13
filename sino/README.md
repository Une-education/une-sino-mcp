# UNE Sino-Gateway — Local Operational Reference

Composant déterministe M2M pour le règlement, la prévention des litiges commerciaux transfrontaliers et la liquidation douanière bilatérale (Droit chinois RPC & douane GACC vs standards occidentaux CISG / Droit européen).

---

## Architecture du Compartiment (/var/www/atoa-api/sino/)

- `index.php` : Routeur principal sub-milliseconde (actions : ping, matrix, inspection_delay, defect_notice, cietac_clause, customs_valuation).
- `admin.php` : Console de supervision interne et banc de test interactif (?k=une2026).
- `mcp.php` : Serveur Model Context Protocol standardisé (transport JSON-RPC 2.0 avec sceau d'état déterministe SHA-256).
- `openapi.json` : Spécification d'API normalisée 3.1.
- `llms.txt` : Manifeste machine pour crawlers et contextes agents.
- `china.sqlite` : Base miroir des articles du Code civil de la RPC (textes bilingues ZH / FR).
- `concordance.sqlite` : Matrice précalculée d'équivalences de normes (FR, EU, US, CISG, RPC).
- `sino_gateway.sqlite` : Tarifs GACC des 97 chapitres SH et journal d'audit des requêtes entrantes.

---

## Points d'Accès Publics & M2M

Base URL : `https://legal.une.education/sino/`

- `index.php?action=ping` : Statut de disponibilité & horodatage UTC
- `index.php?action=matrix&topic={topic}` : Articles miroirs FR / EU / US / CISG / RPC
- `index.php?action=inspection_delay&arrival_date={YYYY-MM-DD}&defect_type={apparent|hidden}` : Calcul de forclusion (Art. 621 RPC / Art. 38 CISG)
- `index.php?action=defect_notice` : Avis formel d'opposition bilingue EN / ZH scellé SHA-256
- `index.php?action=cietac_clause` : Clause arbitrale conforme CIETAC 2024 & Loi arbitrage RPC
- `index.php?action=customs_valuation&hs_code={HS}&cif_value_eur={EUR}` : Simulation douanière GACC (Droits MFN, TVA, Taxe de consommation)

---

## Contact Officiel & Supervision
- Empreinte cryptographique : SHA-256 déterministe (`state_proof`) sur chaque notification et liquidation.
- Supervision : `admin.php?k=une2026`
- Contact machine officiel : `ops@une.education`