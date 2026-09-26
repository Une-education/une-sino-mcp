#!/usr/bin/env python3
"""
UNE Sino-Gateway - MCP Server
Provides deterministic legal reasoning tools for Sino-Occidental trade disputes.
"""

import sys
import json
import urllib.request
import urllib.parse

API_BASE = "https://legal.une.education/sino/index.php"

def call_api(params: dict) -> dict:
    url = f"{API_BASE}?{urllib.parse.urlencode(params)}"
    req = urllib.request.Request(url, headers={"User-Agent": "UNE-MCP-Client/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            return json.loads(response.read().decode("utf-8"))
    except Exception as e:
        return {"error": str(e), "status": "failed"}

def list_tools():
    return [
        {
            "name": "sino_inspection_delay",
            "description": "Calculates strict statutory inspection deadlines and statute of repose under PRC Civil Code Art. 621 and CISG Art. 38/39.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "arrival_date": {
                        "type": "string",
                        "description": "Arrival date of cargo in YYYY-MM-DD format"
                    },
                    "defect_type": {
                        "type": "string",
                        "enum": ["apparent", "hidden"],
                        "description": "Nature of defect: 'apparent' (visible/surface) or 'hidden' (latent/quality)"
                    }
                },
                "required": ["arrival_date"]
            }
        },
        {
            "name": "sino_defect_notice",
            "description": "Generates a formal bilingual (English/Chinese) dispute notice opposable under PRC law and CISG with cryptographic audit seal.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "contract_ref": {"type": "string", "description": "Purchase order or contract reference"},
                    "bl_number": {"type": "string", "description": "Bill of Lading or Airway Bill number"},
                    "cargo_desc": {"type": "string", "description": "Description of the shipment"},
                    "defect_summary": {"type": "string", "description": "Specific non-conformity ascertained upon inspection"},
                    "remedy": {
                        "type": "string",
                        "enum": ["replacement", "price_reduction", "rescission"],
                        "description": "Remedy sought under PRC Art. 582 & CISG Art. 46-52"
                    }
                },
                "required": ["contract_ref", "bl_number", "defect_summary"]
            }
        },
        {
            "name": "sino_cietac_clause",
            "description": "Generates a legally enforceable bilingual (EN/ZH) CIETAC arbitration model clause compliant with PRC Arbitration Law.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "seat": {"type": "string", "description": "Seat of arbitration (e.g., Beijing, Shanghai, Shenzhen)"},
                    "lang": {"type": "string", "description": "Language of arbitration (e.g., English, Chinese)"},
                    "arbitrators": {"type": "integer", "enum": [1, 3], "description": "Number of arbitrators"}
                }
            }
        },
        {
            "name": "sino_matrix_concordance",
            "description": "Queries statutory concordance across French Law, EU Law, CISG, and PRC Civil Code for a given legal topic.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "topic": {"type": "string", "description": "Topic key, e.g. 'conformite_marchandises', 'force_majeure'"}
                }
            }
        }
    ]

def handle_call(tool_name: str, arguments: dict):
    if tool_name == "sino_inspection_delay":
        params = {
            "action": "inspection_delay",
            "arrival_date": arguments.get("arrival_date"),
            "defect_type": arguments.get("defect_type", "apparent")
        }
        return call_api(params)

    elif tool_name == "sino_defect_notice":
        params = {
            "action": "defect_notice",
            "contract_ref": arguments.get("contract_ref", ""),
            "bl_number": arguments.get("bl_number", ""),
            "cargo_desc": arguments.get("cargo_desc", "Cargo"),
            "defect_summary": arguments.get("defect_summary", ""),
            "remedy": arguments.get("remedy", "replacement")
        }
        return call_api(params)

    elif tool_name == "sino_cietac_clause":
        params = {
            "action": "cietac_clause",
            "seat": arguments.get("seat", "Beijing"),
            "lang": arguments.get("lang", "English"),
            "arbitrators": arguments.get("arbitrators", 3)
        }
        return call_api(params)

    elif tool_name == "sino_matrix_concordance":
        params = {
            "action": "matrix",
            "topic": arguments.get("topic", "")
        }
        return call_api(params)

    return {"error": f"Unknown tool: {tool_name}"}

def main():
    # Fast standalone JSON-RPC / MCP CLI protocol runner
    if len(sys.argv) > 1 and sys.argv[1] == "--tools":
        print(json.dumps(list_tools(), indent=2))
        return

    # Basic STDIO loop for MCP agents
    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            req = json.loads(line)
            method = req.get("method")
            msg_id = req.get("id")

            if method == "tools/list":
                res = {"jsonrpc": "2.0", "id": msg_id, "result": {"tools": list_tools()}}
            elif method == "tools/call":
                params = req.get("params", {})
                name = params.get("name")
                args = params.get("arguments", {})
                tool_result = handle_call(name, args)
                res = {
                    "jsonrpc": "2.0",
                    "id": msg_id,
                    "result": {
                        "content": [{"type": "text", "text": json.dumps(tool_result, ensure_ascii=False, indent=2)}]
                    }
                }
            else:
                res = {"jsonrpc": "2.0", "id": msg_id, "result": {}}

            sys.stdout.write(json.dumps(res) + "\n")
            sys.stdout.flush()
        except Exception as e:
            err_res = {"jsonrpc": "2.0", "id": None, "error": {"code": -32603, "message": str(e)}}
            sys.stdout.write(json.dumps(err_res) + "\n")
            sys.stdout.flush()

if __name__ == "__main__":
    main()
