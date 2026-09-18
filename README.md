# AtoA — Multi-Jurisdiction Legal Gateway (FR · EU · CN)

[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1.0-green.svg)](https://legal.une.education/openapi.json)
[![llms.txt](https://img.shields.io/badge/Agent-llms.txt-blue.svg)](https://legal.une.education/llms.txt)
[![Status](https://img.shields.io/badge/Status-Operational-brightgreen.svg)](https://legal.une.education/hub.php)

API machine-to-machine (M2M) ultra-rapide (< 10 ms) pour agents IA autonomes et systèmes d'audit de conformité, couvrant le droit positif français, le cadre réglementaire européen et le droit statutaire chinois.

---

## 🏛️ Corpus Juridique Indexé

| Juridiction | Périmètre | Format & Particularités |
| :--- | :--- | :--- |
| **France (FR)** | Codes consolidés (Légifrance), conventions collectives (KALI), jurisprudence (Judilibre / Cassation) | Plein texte SQLite FTS5 natif, pivots articles-jurisprudence |
| **Union Européenne (EU)** | Règlements & directives clés (*AI Act, RGPD, DSA, DMA, NIS2, Data Act*) | Bilingue miroir FR/EN, identifiants CELEX, sources EUR-Lex |
| **Chine (CN)** | Code civil 2020, PIPL, Lois sur les brevets 2020 et procédure civile 2023 | Texte officiel chinois natif (caractères simplifiés), sources canoniques |

---

## ⚡ Architecture d'Accès par Tiers

### Tier 1 : `/v1/search` (1 crédit)
Recherche plein texte déterministe via FTS5.
```bash
# Recherche UE (AI Act / transparence)
curl -s "[https://legal.une.education/api.php?endpoint=v1/search&jurisdiction=EU&q=transparence&limit=5](https://legal.une.education/api.php?endpoint=v1/search&jurisdiction=EU&q=transparence&limit=5)"

# Recherche Chine (Brevets)
curl -s "[https://legal.une.education/api.php?endpoint=v1/search&jurisdiction=CN&q=专利&limit=5](https://legal.une.education/api.php?endpoint=v1/search&jurisdiction=CN&q=专利&limit=5)"
