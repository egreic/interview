# 03 · Cahier des charges technique — Brique Interview & Avatars (V1)

Document de référence pour Claude Code. À lire avec le skill de développement (04) chargé. Ce dépôt est la source de vérité du CDC ; les points restants « À CONFIRMER CTO » sont des hypothèses par défaut, remplaçables sans refonte.

## 0. Décisions actées (arbitrages scaffold Studizz / CDC) et hypothèses restantes

**Décisions actées :**

1. **Infra** : dev via docker-compose (modèle scaffold Studizz) ; prod = modèle maison VPS OVH (Ubuntu + Apache + PHP, déploiement git + scripts, workers sous systemd). Exigence : vhost compatible WebSocket (`mod_proxy_wstunnel`, timeouts longs) pour la passerelle vocale — test de tenue 20 min au Jalon 4.
2. **Multi-tenant et utilisateurs** : isolation par champ `tenantId` (invariant). Identité des admins Studio et des comptes techniques déléguée au service central `studizz-auth` (skill `studizz-auth-integration`). Référentiel écoles : hypothèse inchangée — réutiliser l'existant si présent, sinon la brique crée le sien.
4. **Emails** : service transactionnel maison `studizz-api-mailer` (skill `studizz-api-mailer-client`) — `POST /mail`, URL via `STUDIZZ_API_MAILER_URL`, expéditeur plateforme + reply-to école portés par le payload.
6. **Versions pincées** : PHP 8.3, Symfony 6.4 LTS, MongoDB 7, Python 3.11, React 18 + Vite, Node non requis côté serveur.
7. **Jobs IA** : RabbitMQ maison — workers Python pika pour les transformateurs ; le backend publie via une interface `JobDispatcher` implémentée sur la gateway `studizz-api-amqp` (skill `studizz-api-amqp-client`). Symfony Messenger abandonné. La gateway AMQP n'est jamais exposée publiquement (contrôle en checklist de déploiement).

**Hypothèses restantes (à confirmer CTO) :**

3. **Fournisseurs** : LLM via adaptateur (Claude par défaut) ; STT/TTS via adaptateurs (fournisseurs à brancher) ; API de recherche/récupération de profils LinkedIn = le fournisseur existant de l'équipe (2 méthodes : `search(nom, prenom, indices)` et `fetch(profil_id)`). 5. **Stockage** : hypothèse objet S3-compatible pour l'audio.

## 1. Stack et principes

- **Backend** : Symfony, contrôleurs REST maison (pas d'API Platform), **OpenAPI code-first** : doc dans les contrôleurs (skill `openapi-controller-doc`), spec dumpée (`nelmio:apidoc:dump`) commitée dans le dépôt, CI en échec si divergence. MongoDB via Doctrine ODM ; asynchrone via RabbitMQ — publication par l'interface `JobDispatcher` (gateway `studizz-api-amqp`), consommation par les workers Python pika.
- **Front** : React SPA (Vite), deux espaces (interviewé / Studio), design tokens issus du dossier Design (04-skill, section à figer).
- **Passerelle vocale** : service Python FastAPI (ASGI), WebSocket, sans état durable (conteneur en dev, service systemd derrière le vhost Apache `mod_proxy_wstunnel` en prod). Aucune logique métier : elle pipe l'audio et appelle l'API Symfony.
- **Monolithe modulaire** : une brique = un module Symfony (`src/Module/<Brique>/`) avec contrôleurs, services, documents ODM, événements — communication inter-briques UNIQUEMENT par interfaces de service et événements. Séparable plus tard sans réécriture.
- **Multi-tenant day one** : `tenantId` sur chaque document ; garde d'accès systématique (voir skill).
- **Adaptateurs partout** : LLM, STT, TTS, LinkedIn, email, stockage, contexte école — une interface + implémentations, config par env.
- **Auth double** : magic links pour les interviewés/alumni (non négociable) — tokens signés à TTL, renouvelables, espace personnel permanent via le même mécanisme, échangeables contre un JWT à scope limité côté API ; `studizz-auth` (JWT central, skill `studizz-auth-integration`) pour les admins Studio et les comptes techniques.
- Hébergement UE. Secrets uniquement par variables d'environnement.

## 2. Les briques (responsabilité · données · API · événements)

### 2.1 Profils & enrichissement
Gère `Personne` (identité, photo, promo, formationId, tenantId) et l'import LinkedIn. Flux : le profil saisi déclenche `POST /api/v1/personnes/{id}/linkedin/recherche` (adaptateur `search` avec nom + école + promo en indices) → cartes candidates → `POST .../linkedin/import {profil_id}` (adaptateur `fetch`) → `Experience[]` en statut `a_confirmer` → confirmation/correction unitaire. Repli sans LinkedIn : les expériences seront créées par le moteur au thème « parcours ». Chaque appel fournisseur est compté (métrologie). Émet `personne.creee`, `timeline.confirmee`.

### 2.2 Contexte école (client)
Le collecteur est un **service externe** (hors périmètre). Notre côté : un adaptateur + un **stub de dev** (fixtures) respectant ce contrat d'échange :
```json
{"version": "...", "horodatage": "...", "ecole": {"nom": "...", "recit": "...", "differenciateurs": []},
 "formations": [{"nom": "...", "diplome": "...", "rythme": "...", "statut": "active", "alias": []}],
 "vie_etudiante": [], "admissions": {}, "chiffres": [], "vocabulaire_maison": [{"terme": "...", "definition": "..."}],
 "regles_communication": {"arguments": [], "interdits": [], "questions_pieges": []}}
```
Les fiches reçues passent en file de validation admin avant usage. Stockage en fiches structurées + chunks RAG tenant (`source: ecole`). Version horodatée référencée dans chaque enregistrement d'interview. Quatre consommateurs : générateur de trames, moteur (relances contextualisées), juge, avatars (mode « aujourd'hui »). Règle : ne sert JAMAIS à contredire l'interviewé — divergence = tag `historique` pour revue.

### 2.3 Référentiel formations
Trois couches : officielles (contexte école ou saisie admin), proposées par alumni (texte libre, utilisables immédiatement par l'auteur), réconciliées. Réconciliation école (suggestion IA + tap) : `historique` (année de fin choisie), `renommee` (fusion, l'ancien nom devient alias), `corrigee` (doublon). Alias et dates de validité alimentent l'autocomplétion (tri par année de sortie saisie) et préservent le matching avatar↔formation à travers les renommages. Pattern générique (resservira pour entreprises/postes en RH).

### 2.4 Trames et Stratégie éditoriale (Studio)
**Stratégie éditoriale** (nouvel objet, un par tenant, versionné) : `axes[] {titre, justification, source: site|personnel|emergent, poids, statut: propose|actif|ecarte|surenchere_ecole}` + notes école. Constitution : à l'ouverture du Studio, l'IA propose les axes depuis le contexte école (« voici ce que je propose d'appuyer : … »), l'école réagit en toggles (pattern onboarding réutilisé : activer, écarter, surenchérir, ajouter) ; l'interview du personnel enrichit ; les **axes émergents** sont détectés dans le corpus des enregistrements (récurrences spontanées) et proposés en continu. Garde-fous non négociables : la stratégie pondère les THÈMES, jamais les réponses ; le curseur adaptatif prime sur la stratégie (l'anecdote de la personne dispose) ; couverture des axes mesurée au niveau du corpus d'avatars, pas par individu ; alerte analytics de redondance inter-avatars. Consommateurs : générateur de trames (pondération), moteur (relances, en douceur), juge (couverture corpus), digest école.

Modèle `Trame` : métadonnées (cas d'usage, durée cible, ton intervieweur, langue) + `Theme[]` ordonnés `{titre, objectif, curseur_conduite: fixe|guide|adaptatif, budget_minutes, questions[], qcm[], obligatoire, axes[]}`. Générateur IA : stratégie + objectif décrit → trame proposée (nourrie du contexte école), éditée en split-view. Prévisualisation = appel de l'orchestrateur de test (l'admin se fait interviewer, ou persona IA joue l'alumni). Trames versionnées ; les campagnes référencent une version figée.

**Gouvernance** : un seul système + matrice de permissions par tenant (proposer/éditer/valider la stratégie et les trames). Deux modes V1 activables par le super-admin : `pilote` (la plateforme construit, l'école valide) et `assiste` (l'IA propose, l'école ajuste — défaut). Mode `autonome` : phase 2.

### 2.5 Campagnes
Import CSV (mapping de colonnes assisté, dédoublonnage par email, rejets listés). Deux modes : invitations nominatives (token pré-rempli : nom, promo, formation) et lien public de campagne (captcha léger + revue école systématique). Emails : invitation (objet avec preuve sociale quand vraie), relances programmées (J+3, J+10, plafond 3), expéditeur plateforme + nom d'affichage et reply-to école. Estimation de coût affichée avant lancement (métrologie × volume). Suivi : envoyés/ouverts/démarrés/complétés. Émet `invitation.envoyee`, `interview.demarree`.

### 2.6 Session temps réel
Machine à états : `creee → profil → consentements → en_cours(theme_i) ⇄ en_pause → validation → close | reportee | abandonnee`. Report planifié (date choisie → email de rappel). Check technique préalable (micro, sortie, réseau). **Pause intra-session obligatoire V1** : perte de focus, appel entrant, coupure WS → pause auto, reprise immédiate même session (≠ reprise multi-sessions, phase 2). Sauvegarde continue : chaque tour persiste immédiatement — un crash ne perd jamais plus d'un tour. Commandes utilisateur : `passer`, `reformule`, `privé` (marquage à la volée du dernier échange).

### 2.7 Voix (passerelle Python)
FastAPI + WebSocket `/ws/session/{token}`. Protocole de messages JSON : `{type: audio_chunk|transcript_partiel|transcript_final|tts_chunk|barge_in|pause|reprise|commande}`. Pipeline : audio navigateur → adaptateur STT streaming → transcript final → `POST` moteur (API Symfony) → réponse texte (découpée en bulles sur `\n`) → adaptateur TTS streaming → navigateur. **Barge-in** : détection de parole pendant le TTS → arrêt immédiat de la synthèse. Cibles : latence perçue < 1,5 s entre fin de parole et début de réponse. Contraintes Safari iOS documentées dans le skill (déblocage audio sur geste utilisateur, AudioContext). Repli texte : le même endpoint moteur, sans la passerelle.

### 2.8 Moteur de conduite
Assemble le contexte par tour : trame (thème courant, curseur, budget), profil + timeline confirmée, contexte école (RAG tenant), historique de session, score de complétude courant. Politique d'élicitation (prompt versionné dans `/prompts`) : posture biographe, questions épisodiques, audience imaginée (Léa) convoquée sur les thèmes « conseils », reformulation-compilation en fin de thème, budget de valence (max 1 marqueur d'enthousiasme/thème, spécificité > flatterie), une seule question à la fois. Time-boxing : priorisation des thèmes restants à l'approche de la durée cible, clôture élégante peak-end. Sujets sensibles : ne pas creuser, rediriger avec tact, flag `sensible` pour revue, jamais dans la couche publique. Signal de fatigue (réponses raccourcissantes) → proposer d'accélérer. Sortie : `score_completude` par thème (déclencheur des questions complémentaires).

### 2.9 Enregistrements canoniques
Document **immuable** par session : transcript horodaté par tour (+ références audio S3), réponses QCM, marquages de confidentialité, flags, métadonnées (trame+version, contexte école version, consentements, durée, device). **Correctifs d'auteur** : couche d'annotations postérieure (jamais de mutation) à priorité maximale pour tous les transformateurs. Rétention audio configurable par tenant (défaut : purge à 12 mois, transcript conservé).

### 2.10 Profil vivant
`Personne` → `Experience[]` typées (`formation|stage|emploi|projet|affiliation`, organisation, période, source, confiance) → `Chunk[]` : `{texte, type: fait|anecdote|opinion|conseil|competence, periode, experienceId?, theme, source: interview|ecole|public, visibilite: public|reserve_ecole|prive, verbatim, confiance, version, enregistrementId}`. Requêtes par métadonnées (année, expérience, thème). Conflits : correctif d'auteur > validé par la personne > plus récent. V1 : un profil ↔ une interview + enrichissement API ; l'agrégation multi-sources est prévue par le modèle, activée en phase 2.

### 2.11 Transformateurs (jobs asynchrones)
Quatre transformateurs versionnés, re-exécutables en masse (nouvelle version de prompt → re-run batch, sorties versionnées, jamais d'écrasement) : **chunks** (découpe le canonique en Chunks, applique visibilités et exclusions), **fiche persona** (arguments clés sourcés, personnalité indicée, ton, expressions, sujets à éviter, verbatims autorisés ≤ 25 mots), **JSON structuré** (export générique), **synthèse** (revue école : 1 page + 5 extraits saillants pointés). Routage modèles : persona et juge = meilleur tier ; chunks et synthèse = tier standard (calibré par l'orchestrateur de test).

**Compilateur de prompt** : le prompt d'un avatar = compilation `socle générique (versionné — v1 = gabarit Sacha v1.4 généralisé) + couche cas d'usage + couche école (règles, arguments, interdits) + couche personne (identité, ton choisi, verbatims, interdits perso) + few-shot générés depuis SES verbatims`. Lois d'ingénierie (voir skill) : règles de contenu en consignes, règles de forme en exemples ; les exemples few-shot ne recoupent JAMAIS les scénarios de test. Les exclusions (privé, interdits) sont injectées comme règles explicites du prompt, pas seulement retirées des chunks.

### 2.12 Atelier avatar
Statuts : `brouillon → test_perso → banc_essai → soumis → valide → actif | mis_en_avant | non_public | refuse` (+ `modifie_fenetre_opposition`). Self-test (chat avec son avatar, notation « c'est moi/pas moi » par réponse), jauge de complétude et zones grises, suggestions à la première personne de l'avatar (sources agrégées : tests auto, feedback des proches, scores de complétude), lien proches (conversations loggées + mini-feedback de fin : « ça lui ressemble ? »), choix du ton par 3 exemples TTS. **Modifications par la personne (tricolore)** : instructions en langage naturel → patch de la couche personne (le prompt n'est jamais montré), journalisé. Vert (ton, corrections mineures, ajout d'interdits) : immédiat + re-test auto. Orange (nouveau contenu public) : banc d'essai auto + notification école, publication à 72 h sauf opposition. Rouge (sensible, verdict doute/négatif, règles école) : validation école bloquante. La personne ne peut jamais retirer un interdit de sécurité ni toucher socle/école. **Passation — deux formes.** Synchrone (en conversation) : métadonnées de pertinence par avatar + brief de passation `{sujet, contexte_resume, question_en_cours}` exposés par API. Asynchrone (« présentation entre étudiants », relance inter-avatars) : si le visiteur a opté in au moment chaud — l'avatar A propose en fin de conversation « tu veux que je te présente quelqu'un qui l'a vraiment vécu ? » — création d'un objet `PassationAsync {visiteurCanal, interets[], resume_minimal, avatarSource, avatarCible, consentementRef, expireLe}` (TTL 90 jours, consentement au registre) + événement `passation.proposee`. L'avatar cible ouvre par le gabarit de légitimité transparent (il dit exactement ce qui lui a été transmis, rien d'autre) ; une seule relance maximum, jamais de chaîne, opt-out un tap, ton jamais pressant (public souvent mineur). La brique fournit protocole, consentement, API de matching et gabarit ; l'exécution (canal, notifications) est côté widget public, phase 2. Page publique partageable (OG card) si statut le permet. Émet `avatar.valide`, `avatar.publie`, `avatar.modifie`.

### 2.13 Orchestrateur de test
Contrat = celui du **script V0 livré** (`banc-essai/`) : entrée `{prompt, chunks, scenario (script|persona), grille}` → sortie `{transcript, verdict: positif|doute|negatif, notes, par_message}`. V1 produit : 1 persona simulé + 1 juge, orchestrateur conçu pour N (config). Consommateurs : CLI interne (existe), Atelier avatar, prévisualisation de trame, tiers (phase 2). Les scénarios YAML du script sont le format canonique.

### Modules transverses
**Analytics** : événements (invitation.envoyee, interview.demarree/complétée/abandonnée+thème, avatar.valide/publie, conversation.testee) → complétion par campagne, durée moyenne, thèmes d'abandon, avatars actifs. **Notifications** (email) : invitation, relance, rappel de report, « avatar en attente de revue », « ton avatar est en ligne », opposition 72 h, digest hebdomadaire école. **Métrologie** : compteurs par appel (`stt_secondes, tts_caracteres, llm_tokens_in/out, linkedin_lookups`) agrégés par tenant/campagne/session ; quotas configurables ; estimation pré-campagne.

## 3. Collections MongoDB (champs clés)

`tenants` · `utilisateurs` (rôles) · `personnes` · `experiences` · `formations` (statut, alias[], validite) · `trames` (+versions) · `campagnes` · `invitations` (token, statut) · `sessions` (état, thème courant, scores) · `enregistrements` (immuable) · `correctifs_auteur` · `chunks` · `fiches_persona` (versionnées) · `avatars` (statut, couches de prompt, version socle) · `patches_persona` (journal tricolore) · `tests_avatar` (rapports orchestrateur) · `conversations_test` (proches + feedback) · `consentements` (registre versionné, révocations) · `evenements_metrologie` · `notifications`. Tous portent `tenantId` + horodatages.

## 4. API et événements

Préfixe `/api/v1`, scoping tenant sur chaque route, erreurs normalisées `{code, message, details}`. OpenAPI maintenu à chaque endpoint ajouté (CI vérifie la synchro). **Webhooks sortants** (HMAC signé, retry) : `interview.completed`, `enregistrement.cree`, `avatar.valide`, `avatar.publie`, `personne.supprimee`, `transformateur.termine` — c'est le branchement vers l'écosystème (widget avatars, CRM).

## 5. Sécurité et conformité

Magic links : tokens signés, TTL 7 jours invitation / 30 min session admin sensible, usage unique pour actions critiques. RBAC : super-admin (tout), admin école (son tenant), contributeur (trames, campagnes, revue), viewer (lecture, analytics). Consentements : trois objets distincts (audio, image, LinkedIn), version du texte + horodatage, révocation depuis l'espace personnel avec propagation (révocation image → photo retirée ; audio → purge audio). Suppression de profil : cascade complète (avatar désactivé, chunks purgés, enregistrement anonymisé ou purgé selon politique tenant, webhooks émis). Déclaration 18+. Lien public : rate limiting + captcha + revue systématique. Aucun secret en dur (revue CI).

## 6. Prompts (dossier `/prompts`, versionné)

`socle-avatar.md` (v1 = gabarit Sacha v1.4 généralisé : 7 lois de conversation, 11 règles absolues paramétrées, incompétences, arrêts, transparence) · `intervieweur.md` (politique d'élicitation §2.8) · `generateur-trames.md` · `juge.md` (grille : fidélité, règles, ton, format, conduite → verdict tricolore) · `persona-simule.md` · `transformateur-*.md`. Tout changement de prompt passe par l'orchestrateur de test avant merge (scénarios de non-régression).

## 7. Plan de build (jalons à critère vérifiable)

**J1 Fondations** — modules, tenant, magic links, OpenAPI, CI. ✓ : deux tenants seedés, isolation prouvée par test.
**J2 Texte bout-en-bout** — trame simple → session chat → enregistrement → transformateurs chunks+persona → avatar compilé testable. ✓ : rejouer le cas Sacha (transcript seedé) et retrouver une fiche persona équivalente ; scénario `transfert-5` passe au banc.
**J3 Amont** — profil, référentiel formations, LinkedIn (stub fournisseur), consentements, timeline. ✓ : parcours 1→6 complet sur mobile.
**J4 Voix** — passerelle Python, barge-in, pause intra-session, Safari iOS. ✓ : interview vocale 15 min complétée sur iPhone réel, zéro perte de données sur coupure simulée.
**J5 Atelier + banc** — statuts, self-test, ton par l'exemple, tricolore, orchestrateur branché (réutiliser le script V0). ✓ : patch orange publié après 72 h simulées ; patch rouge bloqué.
**J6 Studio** — générateur de trames, prévisualisation, campagnes CSV, file de revue, réconciliation. ✓ : Sophie déroule campagne → revue → activation sans aide.
**J7 Conformité + transverses** — registre, suppressions cascade, rétention, analytics, digest, métrologie. ✓ : suppression d'une personne vérifiée de bout en bout ; digest généré.

## 8. Seeds et environnements

École fictive complète (« École Démo » : contexte, formations avec un renommage historique, vocabulaire maison), 5 alumni fictifs dont le cas Sacha anonymisé comme référence, scénarios de banc importés du script V0. Environnements : dev (stubs partout), staging (vrais adaptateurs, quotas bas), prod.

## 9. Hors périmètre V1

Vidéo · reprise multi-sessions · widget intégrable · API publique tiers documentée/facturée · banc multi-personas · agrégation multi-sources du profil vivant · éditeur de trames avancé · thèmes conditionnels au parcours · i18n (prévu, non activé) · cas RH.
