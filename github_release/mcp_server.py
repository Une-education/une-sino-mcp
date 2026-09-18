#!/usr/bin/env python3
"""
AtoA French Legal Intelligence - MCP Server
Expose l'API juridique française haute performance via le Model Context Protocol (MCP).
"""

import sys
import json
import urllib.request
import urllib.parse

API_BASE_URL = "https://legal.une.education/api.php"

def call_atoa(endpoint: str, params: dict, api_key: str = None) -> dict:
    url = f"{API_BASE_URL}?endpoint={endpoint}&" + urllib.parse.urlencode(params)
    headers = {"User-Agent": "AtoA-MCP-Client/1.0"}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"
    
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            return json.loads(resp.read().decode('utf-8'))
    except Exception as e:
        return {"error": str(e)}

def list_tools():
    return [
        {
            "name": "get_french_article",
            "description": "Consulte un article officiel d'un code juridique français (Code civil, Code du travail, etc.).",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "code": {"type": "string", "description": "Nom du code (ex: 'civil', 'travail', 'penal')"},
                    "num": {"type": "string", "description": "Numéro d'article (ex: '1240', 'L1232-1')"}
                },
                "required": ["code", "num"]
            }
        },
        {
            "name": "get_court_decision",
            "description": "Récupère les métadonnées et le texte d'un arrêt de la Cour de cassation via Judilibre.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "id": {"type": "string", "description": "Identifiant Judilibre de la décision"}
                },
                "required": ["id"]
            }
        },
        {
            "name": "cross_law_caselaw",
            "description": "Récupère le texte de loi ainsi que la jurisprudence de la Cour de cassation qui le cite.",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "code": {"type": "string", "description": "Nom du code (ex: 'civil')"},
                    "num": {"type": "string", "description": "Numéro d'article (ex: '1240')"}
                },
                "required": ["code", "num"]
            }        },
        {
            "name": "search_collective_agreement",
            "description": "Recherche un article ou des dispositions dans une convention collective nationale (KALI) par son code IDCC (ex: 1486 pour Syntec, 1979 pour HCR, 3248 pour Métallurgie).",
            "inputSchema": {
                "type": "object",
                "properties": {
                    "idcc": {"type": "string", "description": "Numéro d'IDCC (ex: '1486')"},
                    "q": {"type": "string", "description": "Terme de recherche plein texte (ex: 'préavis', 'maternité', 'prime')"},
                    "num": {"type": "string", "description": "Numéro d'article précis si connu (ex: '4.1')"}
                },
                "required": ["idcc"]
            }
        }
    ]

def handle_call(tool_name: str, args: dict, api_key: str = None):
    if tool_name == "get_french_article":
        return call_atoa("article", {"code": args.get("code", "civil"), "num": args.get("num")}, api_key)
    elif tool_name == "get_court_decision":
        return call_atoa("decision", {"id": args.get("id")}, api_key)
    elif tool_name == "cross_law_caselaw":
        return call_atoa("cross", {"code": args.get("code", "civil"), "num": args.get("num")}, api_key)
    elif tool_name == "search_collective_agreement":
        params = {"idcc": args.get("idcc")}
        if args.get("q"):
            params["q"] = args.get("q")
        if args.get("num"):
            params["num"] = args.get("num")
        return call_atoa("kali", params, api_key)
    return {"error": f"Outil inconnu : {tool_name}"}

if __name__ == "__main__":
    # Test local autonome si lancé en ligne de commande
    if len(sys.argv) > 1 and sys.argv[1] == "--test":
        print("Test MCP Outil 'cross':")
        res = handle_call("cross_law_caselaw", {"code": "civil", "num": "1240"})
        print(json.dumps(res, indent=2, ensure_ascii=False)[:400] + "...")
    else:
        print("AtoA MCP Tool Server prêt. Utilisez --test pour valider.")
