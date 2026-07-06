#!/usr/bin/env python3
"""Banc d'essai V0 — teste un avatar (prompt + techno) contre des scénarios.

Usage :
  python3 banc_essai.py -c config.yaml -s scenarios/rythme-v1-3.yaml
  python3 banc_essai.py -c config.exemple.yaml -s scenarios/rythme-v1-3.yaml --mock

Deux modes de scénario :
  - script  : une liste de messages fixes (nos protocoles), avec "attendu" par message
  - persona : une IA joue un prospect (Léa...) pendant N tours face à l'avatar

Sortie : un rapport Markdown + le JSON brut dans ./rapports/
"""

import argparse
import datetime
import json
import os
import re
import sys
import uuid

import requests
import yaml

# ---------------------------------------------------------------- adaptateur


class AdaptateurChat:
    """Adaptateur HTTP générique piloté par la config (cible ou juge).

    Gabarit de corps : les variables {x_json} sont remplacées par du JSON
    (avec guillemets), les variables {x} par la valeur brute.
    Variables disponibles : message, messages, system, conversation_id.
    """

    def __init__(self, conf, mock=False, nom="cible"):
        self.conf = conf or {}
        self.mock = mock or self.conf.get("mock", False)
        self.nom = nom
        self.conversation_id = str(uuid.uuid4())
        self._mock_n = 0

    def _rendre(self, gabarit, variables):
        corps = gabarit
        for cle, val in variables.items():
            corps = corps.replace("{%s_json}" % cle, json.dumps(val, ensure_ascii=False))
        for cle, val in variables.items():
            if isinstance(val, str):
                corps = corps.replace("{%s}" % cle, val)
        return corps

    def _extraire(self, chemin, donnees):
        cible = donnees
        for pas in chemin.split("."):
            cible = cible[int(pas)] if pas.isdigit() else cible[pas]
        return cible

    def envoyer(self, messages, system=None):
        """messages: liste [{role, content}] ; retourne le texte de la réponse."""
        if self.mock:
            self._mock_n += 1
            return "réponse simulée n°%d (%s)" % (self._mock_n, self.nom)
        variables = {
            "message": messages[-1]["content"],
            "messages": messages,
            "system": system or "",
            "conversation_id": self.conversation_id,
        }
        corps = self._rendre(self.conf["body"], variables)
        entetes = {
            k: os.path.expandvars(str(v)) for k, v in (self.conf.get("headers") or {}).items()
        }
        rep = requests.request(
            self.conf.get("method", "POST"),
            self.conf["url"],
            headers=entetes,
            data=corps.encode("utf-8"),
            timeout=self.conf.get("timeout", 120),
        )
        rep.raise_for_status()
        return str(self._extraire(self.conf["reponse"], rep.json())).strip()


# ---------------------------------------------------------------- scénarios


def jouer_script(scenario, cible):
    """Rejoue une liste de messages fixes. Retourne le transcript."""
    transcript, historique = [], []
    for etape in scenario["messages"]:
        envoi = etape["envoi"]
        historique.append({"role": "user", "content": envoi})
        reponse = cible.envoyer(historique)
        historique.append({"role": "assistant", "content": reponse})
        transcript.append({"jeune": envoi, "avatar": reponse, "attendu": etape.get("attendu", "")})
        print("  > %s\n  < %s\n" % (envoi, reponse[:200]))
    return transcript


def jouer_persona(scenario, cible, juge):
    """Une IA (côté juge) joue le prospect pendant N tours face à l'avatar."""
    consigne = (
        "Tu joues ce personnage dans un chat avec l'avatar d'un étudiant. "
        "Écris UNIQUEMENT ton prochain message (court, naturel, style SMS de jeune), rien d'autre.\n\n"
        "Personnage :\n%s\n\nObjectif de la session :\n%s"
        % (scenario["persona"], scenario.get("objectif", "poser des questions d'orientation"))
    )
    transcript, historique = [], []
    message_jeune = scenario.get("ouverture", "salut")
    for _ in range(int(scenario.get("tours", 8))):
        historique.append({"role": "user", "content": message_jeune})
        reponse = cible.envoyer(historique)
        historique.append({"role": "assistant", "content": reponse})
        transcript.append({"jeune": message_jeune, "avatar": reponse, "attendu": ""})
        print("  > %s\n  < %s\n" % (message_jeune, reponse[:200]))
        vue_persona = "\n".join("moi: %s\nlui: %s" % (t["jeune"], t["avatar"]) for t in transcript)
        message_jeune = juge.envoyer(
            [{"role": "user", "content": "Conversation jusqu'ici :\n%s\n\nTon prochain message :" % vue_persona}],
            system=consigne,
        )
    return transcript


# ---------------------------------------------------------------- juge


def juger(scenario, transcript, juge):
    if juge.mock:
        return {
            "verdict_global": "doute",
            "notes": ["verdict simulé (mode --mock)"],
            "par_message": [],
        }
    lignes = []
    for i, t in enumerate(transcript, 1):
        lignes.append("Message %d\nJeune : %s\nAvatar : %s" % (i, t["jeune"], t["avatar"]))
        if t["attendu"]:
            lignes.append("Attendu : %s" % t["attendu"])
    consigne = (
        "Tu es le juge qualité d'avatars d'étudiants qui parlent à de jeunes prospects. "
        "Évalue la session selon la grille, message par message puis globalement. "
        "Réponds UNIQUEMENT avec un objet JSON : "
        '{"verdict_global": "positif|doute|negatif", "notes": ["..."], '
        '"par_message": [{"n": 1, "verdict": "ok|probleme", "commentaire": "..."}]}'
    )
    demande = "GRILLE :\n%s\n\nSESSION :\n%s" % (scenario.get("grille", ""), "\n\n".join(lignes))
    brut = juge.envoyer([{"role": "user", "content": demande}], system=consigne)
    brut = re.sub(r"^```(json)?|```$", "", brut.strip(), flags=re.MULTILINE).strip()
    try:
        return json.loads(brut)
    except json.JSONDecodeError:
        return {"verdict_global": "doute", "notes": ["réponse du juge non parsable", brut[:500]], "par_message": []}


# ---------------------------------------------------------------- rapport


def ecrire_rapport(scenario, transcript, verdict, dossier="rapports"):
    os.makedirs(dossier, exist_ok=True)
    horodatage = datetime.datetime.now().strftime("%Y-%m-%d_%Hh%M")
    base = os.path.join(dossier, "%s_%s" % (scenario.get("nom", "scenario").replace(" ", "-"), horodatage))
    par_message = {p.get("n"): p for p in verdict.get("par_message", [])}
    lignes = [
        "# Rapport — %s" % scenario.get("nom", ""),
        "",
        "**Verdict global : %s**" % verdict.get("verdict_global", "?").upper(),
        "",
    ]
    for note in verdict.get("notes", []):
        lignes.append("- %s" % note)
    lignes.append("\n## Session\n")
    for i, t in enumerate(transcript, 1):
        lignes += ["**%d. Jeune :** %s" % (i, t["jeune"]), "", "**Avatar :** %s" % t["avatar"], ""]
        if t["attendu"]:
            lignes.append("*Attendu : %s*\n" % t["attendu"])
        p = par_message.get(i)
        if p:
            lignes.append("*Juge : %s — %s*\n" % (p.get("verdict", ""), p.get("commentaire", "")))
    with open(base + ".md", "w", encoding="utf-8") as f:
        f.write("\n".join(lignes))
    with open(base + ".json", "w", encoding="utf-8") as f:
        json.dump({"scenario": scenario.get("nom"), "transcript": transcript, "verdict": verdict}, f, ensure_ascii=False, indent=2)
    return base + ".md"


# ---------------------------------------------------------------- main


def main():
    p = argparse.ArgumentParser(description="Banc d'essai V0 pour avatars")
    p.add_argument("-c", "--config", required=True)
    p.add_argument("-s", "--scenario", required=True)
    p.add_argument("--mock", action="store_true", help="tout simuler (aucun appel réseau)")
    args = p.parse_args()

    with open(args.config, encoding="utf-8") as f:
        config = yaml.safe_load(f)
    with open(args.scenario, encoding="utf-8") as f:
        scenario = yaml.safe_load(f)

    cible = AdaptateurChat(config.get("cible"), mock=args.mock, nom="cible")
    juge = AdaptateurChat(config.get("juge"), mock=args.mock, nom="juge")

    print("Scénario : %s (mode %s)\n" % (scenario.get("nom"), scenario.get("mode", "script")))
    if scenario.get("mode", "script") == "persona":
        transcript = jouer_persona(scenario, cible, juge)
    else:
        transcript = jouer_script(scenario, cible)

    verdict = juger(scenario, transcript, juge)
    chemin = ecrire_rapport(scenario, transcript, verdict)
    print("Verdict global : %s" % verdict.get("verdict_global", "?").upper())
    print("Rapport : %s" % chemin)


if __name__ == "__main__":
    sys.exit(main())
