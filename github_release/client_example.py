#!/usr/bin/env python3
"""
Exemple d'utilisation de l'API AtoA Legal en Python pur.
Interroge les 4 fonds : Légifrance, Judilibre, Cross et Conventions Collectives (KALI).
"""
import urllib.request
import urllib.parse
import json

BASE_URL = "https://legal.une.education/api.php"

def query_atoa(endpoint: str, params: dict, api_key: str = None) -> dict:
    url = f"{BASE_URL}?endpoint={endpoint}&" + urllib.parse.urlencode(params)
    headers = {"User-Agent": "AtoA-Python-Client/1.0"}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"
    
    req = urllib.request.Request(url, headers=headers)
    with urllib.request.urlopen(req) as resp:
        return json.loads(resp.read().decode("utf-8"))

if __name__ == "__main__":
    print("--- 1. Recherche dans la Convention Syntec (IDCC 1486) ---")
    kali_res = query_atoa("kali", {"idcc": "1486", "q": "rupture conventionnelle"})
    print(f"Trouvé {kali_res.get('count', 0)} articles pertinents.")
    for art in kali_res.get("results", [])[:2]:
        print(f" - Article {art['num']}: {art['extrait']}")

    print("\n--- 2. Article de Loi (Code civil, art. 1240) ---")
    art_res = query_atoa("article", {"code": "civil", "num": "1240"})
    if art_res.get("status") == "success":
        print(f"Texte : {art_res['data']['texte'][:120]}...")

    print("\n--- 3. Jurisprudence croisée (Code du travail, L1232-1) ---")
    cross_res = query_atoa("cross", {"code": "travail", "num": "L1232-1"})
    print(f"Décisions de cassation rattachées : {cross_res.get('jurisprudence_count', 0)}")
