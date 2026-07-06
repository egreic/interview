# Banc d'essai V0 — tests automatiques d'avatars

Rejoue des scénarios contre un avatar (ta plateforme) et fait noter la session
par un juge IA selon une grille. Rapport Markdown + JSON dans `rapports/`.

## Démarrage rapide

1. `pip install pyyaml requests`
2. Copie `config.exemple.yaml` en `config.yaml` et remplis le bloc `cible`
   (l'endpoint de chat de ta plateforme) — voir les deux gabarits selon que
   ton API garde l'historique (à état) ou non.
3. Exporte les clés : `export MA_CLE_PLATEFORME=...` et `export ANTHROPIC_API_KEY=...`
4. Lance :
   `python3 banc_essai.py -c config.yaml -s scenarios/rythme-v1-4.yaml`

Test à blanc sans aucun appel réseau : ajoute `--mock`.

## Les deux modes de scénario

- `mode: script` — messages fixes avec un « attendu » par message
  (protocoles reproductibles : `rythme-v1-4.yaml`, `transfert-5.yaml`).
- `mode: persona` — une IA joue un prospect pendant N tours face à l'avatar
  (`persona-lea.yaml`). C'est l'embryon du banc d'essai produit.

## Ajouter un scénario

Copie un YAML existant : `nom`, `mode`, `grille` (les critères du juge),
puis `messages` (script) ou `persona/ouverture/tours/objectif` (persona).

## Notes

- Jamais de clé en dur : les `${VARIABLES}` des headers sont lues dans
  l'environnement.
- Le juge par défaut est l'API Anthropic (modèle réglable dans la config) ;
  n'importe quel endpoint de chat JSON fait l'affaire.
- Ce script est la V0 de la brique « orchestrateur de test » du cadrage :
  même contrat, futurs consommateurs = Atelier avatar, prévisualisation de
  trame, offre tiers.
