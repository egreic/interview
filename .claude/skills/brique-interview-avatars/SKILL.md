---
name: brique-interview-avatars
description: "Développement de la brique Interview & Avatars (interviews vocales IA → avatars d'alumni pour écoles supérieures). Charger ce skill pour TOUT travail sur ce projet : modules Symfony, front React, passerelle vocale Python, prompts, transformateurs, banc d'essai, migrations. Déclencheurs : brique interview, avatar alumni, moteur de conduite, enregistrement canonique, profil vivant, atelier avatar, orchestrateur de test, Sup de V, trame, campagne alumni. Contient les invariants d'architecture, les lois d'ingénierie des prompts apprises sur le terrain, les conventions et les pièges connus. À lire AVANT d'écrire la moindre ligne."
---

# Skill — Brique Interview & Avatars

Se lit avec `03-cahier-des-charges-technique.md` (le QUOI). Ce skill est le COMMENT : invariants, conventions, checklists, pièges. Le skill `karpathy-coding-guidelines` s'applique en permanence (simplicité d'abord, changements chirurgicaux, objectifs vérifiables).

## Invariants d'architecture — ne JAMAIS violer

1. **Immutabilité** : un enregistrement canonique ne se modifie pas. Toute évolution = correctif d'auteur (annotation) ou nouvelle version de sortie de transformateur.
2. **Isolation tenant** : chaque requête ODM passe par la garde tenant. Aucune requête cross-tenant hors super-admin explicite. Test d'isolation obligatoire pour toute nouvelle collection.
3. **Adaptateurs** : aucun appel direct à un fournisseur (LLM, STT, TTS, LinkedIn, email, stockage, contexte école). Interface + implémentations + stub de dev. Un nouveau fournisseur = une classe, zéro changement ailleurs.
4. **Contrats de briques** : une brique n'importe jamais les documents ODM d'une autre — interfaces de service et événements uniquement. Si tu as besoin d'un document d'une autre brique, tu as besoin d'une méthode de son service.
5. **API-first** : tout ce que fait l'UI passe par un endpoint documenté dans OpenAPI. La page hébergée V1 consomme la même API que les futurs tiers.
6. **Exclusions injectées** : quand une source intégrale (transcript) alimente un avatar, la liste d'exclusions est AUSSI injectée en interdits explicites du prompt — retirer des chunks ne suffit pas (leçon terrain : la « mère » de Sacha).
7. **Tricolore** : aucun patch de persona ne contourne la classification vert/orange/rouge. La personne peut ajouter des interdits, jamais en retirer ; socle et couche école lui sont inaccessibles.
8. **Versionnage** : socle de prompt, trames, transformateurs et fiches persona sont versionnés ; re-run en masse = nouvelles sorties, jamais d'écrasement.
9. **Sauvegarde continue** : en session, chaque tour persiste avant de répondre. Un crash ne coûte jamais plus d'un tour.
10. **Événements** : toute transition d'état significative émet un événement (webhooks vers l'écosystème). Pas d'état changé silencieusement.

## Lois d'ingénierie des prompts (apprises sur le cas Sacha, non négociables)

- **Consignes vs exemples** : les règles de CONTENU (interdits, périmètre, redirections) s'écrivent en consignes — elles sont obéies. Les règles de FORME (longueur, tics, structure, rythme) s'écrivent en EXEMPLES few-shot — les quotas chiffrés sont ignorés par les modèles.
- **Train/test** : les exemples few-shot d'un persona ne recoupent JAMAIS les questions des scénarios de test — sinon on mesure la mémorisation, pas le transfert (le modèle récite les exemples au mot près).
- **Compilation en couches** : personne n'écrit un prompt d'avatar à la main. Socle générique versionné → cas d'usage → école → personne → few-shot générés depuis SES verbatims. Améliorer le socle = recompiler tous les avatars.
- **Anti-broderie** : interdire explicitement l'invention de détails plausibles non sourcés (« deux de mes meilleurs amis ») et les demi-affirmations sociales (« j'ai suivi ça vite fait » = mensonge).
- **Défaut sans question** : pas de question de relance par défaut ; question seulement si une info manque pour aider ; jamais de double branche ; offres (« si tu veux je te raconte… ») plutôt que questions.
- **Bulles** : les réponses conversationnelles se découpent sur `\n` (une ligne = une bulle, ≤ 3) ; l'affichage ajoute le délai de frappe.
- **Routage modèles** : fiche persona et juge = meilleur tier ; chunking, synthèse, extraction = tier standard. Tout changement de prompt passe au banc (`banc-essai/`, scénarios de non-régression) avant merge.
- **Calibration du juge** : ses critères (met en valeur / sincère / à risque) se calibrent sur des cas réels ; conserver le corpus de sessions annotées.

## Conventions

- **Langue** : code, identifiants et commits en anglais ; le vocabulaire métier français est mappé une fois (`docs/ubiquitous-language.md`) : Trame→InterviewTemplate, Enregistrement canonique→CanonicalRecord, Profil vivant→LivingProfile, Atelier→AvatarWorkshop, Banc d'essai→TestBench, Fiche persona→PersonaSheet.
- **Symfony** : `src/Module/<Brick>/{Controller,Service,Document,Event,Adapter}` ; DTO d'entrée validés ; pas de logique en contrôleur ; contrôleurs REST maison, OpenAPI mis à jour dans la même PR (la CI compare).
- **Python (passerelle)** : FastAPI, typé, sans état, aucune dépendance à Mongo — elle ne parle qu'à l'API Symfony.
- **React** : espaces `apps/interviewee` et `apps/studio`, composants du design system uniquement (tokens ci-dessous), pas de style ad hoc.
- **Tests** : chaque jalon du CDC a son critère vérifiable — l'écrire en test AVANT de coder le jalon (goal-driven). Fixtures = seeds « École Démo » + cas Sacha anonymisé.
- **Prompts** : dans `/prompts`, un fichier = une responsabilité, front-matter `version:` ; jamais de prompt inline dans le code.

## Checklists par type de tâche

**Nouvel endpoint** : DTO validé → garde tenant → service de brique → OpenAPI → test d'isolation + test métier → événement si transition d'état.
**Nouvelle brique/module** : contrat d'interface d'abord → stub → documents ODM avec tenantId → événements → entrée dans ce skill si nouveau piège découvert.
**Nouveau transformateur** : contrat entrée/sortie → version → job Messenger idempotent → re-run masse testé → routage modèle justifié.
**Nouveau prompt ou modification** : scénario de non-régression au banc → run → verdict positif exigé → bump de version → recompilation des avatars si socle.
**Nouvel écran** : tokens du design system uniquement → une action principale → suggestion→tap si décision → état mobile → états vides/erreur/chargement.
**Toucher aux données personnelles** : vérifier consentement requis, propagation de révocation, cascade de suppression, visibilité (public/réservé/privé) respectée jusqu'au prompt.

## Pièges connus (payés une fois, plus jamais)

- **Safari iOS** : l'audio ne démarre que sur geste utilisateur (débloquer l'AudioContext au tap « commencer ») ; WebSocket coupé en arrière-plan → c'est la pause intra-session, pas une erreur ; tester sur iPhone réel, pas simulateur.
- **Transcriptions** : les noms propres sont massacrés (SubDV pour Sup de V, Auchamp pour Auchan) — toujours des champs `a_confirmer` et une passe de résolution, jamais de confiance aveugle.
- **JSON de LLM** : toujours parser avec tolérance (strip des fences ```), repli « doute » si non parsable — jamais de crash sur une réponse de juge.
- **API à état vs sans état** : l'adaptateur cible du banc gère les deux (`conversation_id` vs historique complet) — vérifier lequel avant de brancher.
- **Overfit few-shot** : si les réponses de test sont identiques aux exemples mot à mot, le test ne prouve rien — changer les questions.
- **Enthousiasme sur réponse vide** : « Top ! » après un « oui » — bannir, c'est un tell d'IA.

## Design system

[À FIGER après le choix de piste Claude Design : coller ici les tokens exportés — palette avec slot couleur-école, typos, espacements, rayons — et les règles d'usage. Jusque-là : aucun style en dur, tout en variables.]

## Fichiers de référence

`03-cahier-des-charges-technique.md` (spec) · `02-brief-produit.md` (le pourquoi) · `/prompts/socle-avatar.md` (v1 = gabarit Sacha v1.4) · `banc-essai/` (orchestrateur V0 + scénarios canoniques) · cas d'école complet Sacha : transcript → analyses → prompt v1→v1.4 → transcripts de test → critiques (le corpus de calibration).
