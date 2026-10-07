# UNE Sino-Gateway — Local Operational Reference

Composant déterministe M2M pour le règlement et la prévention des litiges commerciaux transfrontaliers (Droit chinois RPC vs standards occidentaux CISG / Droit français).

---

## Architecture du Compartiment (/var/www/atoa-api/sino/)

* index.php : Routeur principal sub-milliseconde (actions : ping, matrix, inspection_delay, defect_notice, cietac_clause).
* admin.php : Console de supervision interne et banc de test interactif (?k=une2026).
* mcp_server.py : Serveur Model Context Protocol standardisé (entrée/sortie JSON-RPC via STDIO).
* openapi.json : Spécification d API normalisée 3.1.
* llms.txt : Manifeste machine pour crawlers et contextes agents.
* china.sqlite : Base miroir des articles du Code civil de la RPC (textes bilingues ZH / FR).
* concordance.sqlite : Matrice précalculée d équivalences de normes (FR, EU, US, CISG, RPC).
* sino_gateway.sqlite : Journal d audit local des requêtes entrantes.

---

## Points d Accès Publics & M2M

Base URL : https://legal.une.education/sino/

* index.php?action=ping : Statut de disponibilité & horodatage UTC
* index.php?action=matrix&topic={topic} : Articles miroirs FR / EU / US / CISG / RPC
* index.php?action=inspection_delay&arrival_date={YYYY-MM-DD}&defect_type={apparent|hidden} : Calcul de forclusion (Art. 621 RPC / Art. 38 CISG)
* index.php?action=defect_notice : Avis formel d opposition bilingue EN / ZH scellé SHA-256
* index.php?action=cietac_clause : Clause arbitrale conforme CIETAC 2024 & Loi arbitrage RPC

---

## Contact Officiel & Supervision
* Empreinte cryptographique : SHA-256 déterministe sur chaque notification.
* Supervision : admin.php?k=une2026
* Contact machine officiel : ops@une.education
