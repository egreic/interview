# Brique Interview & Avatars — Pack de démarrage Claude Code

Ce dossier contient tout ce qu'il faut pour lancer le développement. Ouvre Claude Code À LA RACINE de ce dossier : le skill (`.claude/skills/brique-interview-avatars/SKILL.md`) sera découvert et chargé automatiquement.

## Message d'amorçage — à coller tel quel dans Claude Code

> Tu démarres le développement de la brique Interview & Avatars (interviews vocales IA → avatars d'alumni pour écoles supérieures françaises). Étape 1 : lis en entier, dans cet ordre, `docs/03-cahier-des-charges-technique.md` puis `docs/02-brief-produit.md` — le skill du projet t'a déjà donné les invariants et conventions. Étape 2 : confirme ta compréhension en me listant les 7 jalons et leur critère d'acceptation, plus les hypothèses du §0 que tu adoptes. Étape 3 : commence le Jalon 1 uniquement — propose l'arborescence du monorepo (Symfony modules + React deux espaces + passerelle vocale Python), la CI et le squelette multi-tenant, et attends ma validation de l'arborescence avant d'écrire les briques métier. Règles : suis les invariants du skill à la lettre ; ne me pose aucune question dont la réponse est dans le cahier des charges ; travaille en français avec moi, code et commits en anglais.

## Contenu du pack

- `docs/` — le brief produit (le pourquoi) et le cahier des charges technique (le quoi : 11 briques, orchestrateur, jalons J1→J7 à critères vérifiables).
- `.claude/skills/brique-interview-avatars/SKILL.md` — le comment : 10 invariants d'architecture, 8 lois d'ingénierie des prompts apprises sur le terrain, conventions, checklists, pièges connus. Chargé automatiquement par Claude Code.
- `design/` — le dossier d'identité (01) et le sommaire des 22 écrans avec workflows (05). Référence pour les écrans en attendant le bundle Claude Design.
- `prompts/` — le socle avatar v1 (gabarit issu de Sacha v1.4, à généraliser en compilateur de couches, cf. CDC §2.11 et §6).
- `banc-essai/` — l'orchestrateur de test V0, fonctionnel (script Python + adaptateur HTTP générique + 3 scénarios). C'est la brique 2.13 : la réutiliser, pas la réécrire.
- `reference/cas-sacha/` — le corpus de calibration : versions successives du prompt (v1→v1.4), chunks RAG, protocoles de test. Le Jalon 2 doit « rejouer le cas Sacha ».
- `guides/` — les guides d'interview manuelle et le kit d'analyse (opérationnel, pas du dev — utile pour comprendre le domaine).

## Trois zones en attente (n'empêchent PAS de démarrer)

1. **Design tokens** : après le choix de piste dans Claude Design, coller les tokens dans la section « Design system » du SKILL.md (+ déposer le bundle de handoff dans `design/`). Jalons J1-J2 n'en dépendent pas.
2. **Réponses CTO** : les 7 points du §0 du CDC ont des hypothèses par défaut. Remplacer la section à réception.
3. **Interview du personnel école** : remplira `prompts/` (section « Règles de l'école ») et la base contexte école. Le stub du CDC §2.2 suffit d'ici là.

## Rappels non négociables

Multi-tenant et immutabilité dès le premier commit. Tout changement de prompt passe par `banc-essai/` avant merge. OpenAPI mis à jour dans la même PR que chaque endpoint. Jalon suivant seulement quand le critère du jalon courant est vert.
