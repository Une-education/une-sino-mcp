# UNE Sino-Gateway — MCP Server

[![Status](https://img.shields.io/badge/status-operational-3fb950.svg)](https://une.education)
[![Latency](https://img.shields.io/badge/latency-<10ms-58a6ff.svg)](https://legal.une.education/sino/index.php?action=ping)
[![Protocol](https://img.shields.io/badge/MCP-standardized-orange.svg)](https://modelcontextprotocol.io)

Official **Model Context Protocol (MCP)** server for **UNE (Unified Norms Engine)**.  
Enables LLM agents (Claude Desktop, Cursor, LibreChat, Devin) to perform sub-10ms **deterministic legal computations** for cross-border trade disputes between Western/International standards (**CISG**, French Law) and the **Civil Code of the People's Republic of China (PRC
Book III)**.

---

## Core Capabilities

- __Zero-Hallucination Inspection Cutoffs__: Strictly computes statutory inspection deadlines and statutes	of repose under PRC Art. 621 & CISG Art. 38/39.
- __Opposable Dispute Notice Generation__: Formats bilingual (English/Chinese) notices of non-conformity with statutory remedies under PRC Art. 582/615.
- __CIETAC Arbitration Clause Enforcement__: Generates compliant model arbitration clauses under PRC Arbitration Law Art. 16/18.
- __Cryptographic Audit Seal__: Appends deterministic SHA-256 signatures and UTC timestamps to every output for legal traceability.

---

## Available Tools

* `sino_inspection_delay`: Evaluates actionable periods and forclusion dates.
* `sino_defect_notice`: Emits a formal bilingual notice opposable under PRC law.
* `sino_cietac_clause`: Produces a strictly valid CIETAC arbitration clause.
* `sino_matrix_concordance`: Cross-references articles across FR, EU, US, CISG, and PRC.

---

## Claude Desktop Configuration

```json
{
  "mcpServers": {
    "une-sino-gateway": {
      "command": "python3",
      "args": ["/path/to/mcp_server.py"]
    }
  }
}
```

Zero external dependencies (uses Python standard library).

---

## Endpoints & Metadata

- Langing Page: https://une.education
- OpenAPI 3.1 Spec: https://legal.une.education/sino/openapi.json
- Agent Manifest (llms.txt): https://legal.une.education/sino/llms.txt
- Operations & Security: ops@une.education

---

## License
MIT License. Engineered by UNE Operations (2026).
