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

- **Préséance** : le scaffold Studizz (`studizz-project-scaffold`, disponible dans `.claude/skills/` avec `studizz-auth-integration`, `studizz-api-amqp-client`, `studizz-api-mailer-client`, `openapi-controller-doc`) fait autorité sur l'outillage et les conventions d'infrastructure maison ; le cahier des charges et ce skill font autorité sur l'architecture du domaine (briques, contrats, multi-tenant, immutabilité) ; tout conflit est remonté à l'humain, jamais arbitré silencieusement. Arbitrages scaffold/CDC actés dans le §0 du CDC — le dépôt est la source de vérité.
- **Arborescence (hybride, actée)** : racine `backend/` (Symfony), `frontend/interviewee/`, `frontend/studio/`, `workers/` (Python pika, transformateurs), `voice-gateway/` (FastAPI), `deploy/`. `scaffold.py` ne s'exécute JAMAIS sur ce dépôt : il sert de spécification d'outillage (compose de dev, Makefile, scripts de déploiement, systemd).
- **Prod** : modèle maison VPS OVH (Apache + systemd, déploiement git/scripts) ; vhost WebSocket `mod_proxy_wstunnel` à timeouts longs pour la passerelle vocale (test de tenue 20 min au J4).
- **Auth double** : magic links pour interviewés/alumni (non négociable, échangeables contre un JWT à scope limité) ; `studizz-auth` pour admins Studio et comptes techniques (skill `studizz-auth-integration`).
- **Async** : RabbitMQ — le backend publie uniquement via l'interface `JobDispatcher` (implémentation `studizz-api-amqp-client`, stub en dev) ; workers pika dans `workers/` ; jamais de Messenger ; gateway AMQP jamais exposée publiquement (checklist de déploiement).
- **Langue** : code, identifiants et commits en anglais ; le vocabulaire métier français est mappé une fois (`docs/ubiquitous-language.md`) : Trame→InterviewTemplate, Enregistrement canonique→CanonicalRecord, Profil vivant→LivingProfile, Atelier→AvatarWorkshop, Banc d'essai→TestBench, Fiche persona→PersonaSheet.
- **Symfony** : `backend/src/Module/<Brick>/{Controller,Service,Document,Event,Adapter}` ; DTO d'entrée validés ; pas de logique en contrôleur ; contrôleurs REST maison, doc OpenAPI code-first dans les contrôleurs (skill `openapi-controller-doc`), spec dumpée commitée dans la même PR, CI en échec si divergence.
- **Python (passerelle)** : FastAPI, typé, sans état, aucune dépendance à Mongo — elle ne parle qu'à l'API Symfony.
- **React** : espaces `frontend/interviewee` et `frontend/studio`, composants du design system uniquement (tokens ci-dessous), pas de style ad hoc.
- **Tests** : chaque jalon du CDC a son critère vérifiable — l'écrire en test AVANT de coder le jalon (goal-driven). Fixtures = seeds « École Démo » + cas Sacha anonymisé.
- **Prompts** : dans `/prompts`, un fichier = une responsabilité, front-matter `version:` ; jamais de prompt inline dans le code.

## Checklists par type de tâche

**Nouvel endpoint** : DTO validé → garde tenant → service de brique → OpenAPI → test d'isolation + test métier → événement si transition d'état.
**Nouvelle brique/module** : contrat d'interface d'abord → stub → documents ODM avec tenantId → événements → entrée dans ce skill si nouveau piège découvert.
**Nouveau transformateur** : contrat entrée/sortie → version → worker pika idempotent (publication via `JobDispatcher`) → re-run masse testé → routage modèle justifié.
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

Piste retenue : **« Clair »**. Sources : `design/rendus/tokens.css` et `design/rendus/tokens.json` (règles d'usage complètes dans `design/rendus/README.md`, référence visuelle `design/rendus/Pistes Identité.dc.html`). Aucun style en dur : tout passe par ces variables.

- **Neutres** : `--bg #FFFFFF` · `--surface #F5F5F4` (cartes grises, bulles IA, panneaux) · `--border #ECEAE7` · `--border-subtle #F0EFED` · `--ink #17181A` · `--ink-secondary #6E6F72` · `--ink-muted #A3A4A7` · `--ink-faint #C4C3C0`.
- **Accent école (côté interviewé) — VARIABLE par tenant**, extraite du site de chaque école (démo ESM Lyon `#0E7C66`) : `--accent`, `--accent-on #FFFFFF`, `--accent-tint` (~8 % sur blanc) et `--accent-halo` (10 %) — tint et halo se DÉRIVENT de l'accent (ex. `color-mix(in oklch, var(--accent) 8%, white)`), jamais figés.
- **Studio (identité fixe)** : `--studio-accent #F1662B` · `--studio-tint #FEF0E8`.
- **Sémantiques** : `--success #0E8A5F` / `--success-tint #E8F5EF` · `--warning #B45309` / `--warning-tint #FDF3E7`.
- **Typographie** : une seule famille, **Figtree** (Google Fonts, graisses 400–800). `display` 800 27px/1.12 (letter-spacing -0.02em) · `title` 800 24px/1.15 (-0.02em) · `section` 700 15px · `body` 500 14px/1.55 · `bubble` 500 14.5px/1.45 · `label` 700 11px (0.1em, uppercase) · `caption` 500 12.5px.
- **Rayons** : input 12 · card 16 · card-lg 20 · bubble 16 (coin « queue » 6) · pill 999.
- **Espacements** (échelle de 4) : 4, 8, 12, 16, 20, 24, 28, 32, 40.
- **Ombres quasi nulles** : card `0 2px 12px rgba(23,24,26,.06)` · sheet `0 -4px 20px rgba(23,24,26,.07)` · modal `0 8px 40px rgba(23,24,26,.08)`.
- **Composants clés** : CTA pilule pleine largeur 52px (fond accent), secondaire fantôme 46px toujours DESSOUS · hit targets ≥ 44px · wizard à tirets 22×6px, gap 4, actif = accent · icônes en pastilles teintées (fond tint, glyphe accent) · **zéro emoji** — la chaleur vient de la copie.

## Fichiers de référence

`03-cahier-des-charges-technique.md` (spec) · `02-brief-produit.md` (le pourquoi) · `/prompts/socle-avatar.md` (v1 = gabarit Sacha v1.4) · `banc-essai/` (orchestrateur V0 + scénarios canoniques) · cas d'école complet Sacha : transcript → analyses → prompt v1→v1.4 → transcripts de test → critiques (le corpus de calibration).
