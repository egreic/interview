# Handoff : Brique Interview & Avatars — Flux A (interviewé) · Piste « Clair »

## Vue d'ensemble
Plateforme à deux faces pour écoles supérieures françaises :
- **Côté interviewé** (mobile-first, 18-30 ans) : une interview vocale menée par « Léa » (IA) transforme un alumni en avatar conversationnel. **Thémable** : la couleur d'accent est extraite automatiquement du site de chaque école ; le reste du système est neutre-chaleureux.
- **Côté Studio** (desktop, responsables com/admissions) : pilotage — stratégie, trames, campagnes, revue. **Identité fixe** (accent orange).

Ce package couvre le **Flux A complet** (email d'invitation → E19 → E01…E09 → E20) plus les écrans de référence E07 desktop et E13 Studio.

## À propos des fichiers de design
Les fichiers de ce bundle sont des **références de design créées en HTML** — des prototypes montrant le rendu et le comportement attendus, **pas du code de production**. La tâche est de **recréer ces designs dans l'environnement du codebase cible** (React, Vue, etc.) avec ses patterns et librairies existants — ou, si aucun environnement n'existe encore, de choisir le framework le plus adapté et d'y implémenter les designs.

Note : `Pistes Identité.dc.html` est un format de l'outil de design (il référence un runtime local) ; il sert de référence visuelle. Ce README est autosuffisant.

## Fidélité
**Haute fidélité (hifi)** : couleurs, typographies, espacements, rayons et copies sont finaux. Recréer au pixel avec les tokens fournis (`tokens.css` / `tokens.json`).

## Langage d'interaction (non négociable)
1. **Split-view à artefact vivant** : pendant toute conversation, un panneau construit le résultat en direct. Mobile : volet bas escamotable + récap entre thèmes.
2. **Suggestion → validation d'un tap** : 2-4 options tapables partout où une décision est attendue ; zéro saisie évitable, tout pré-rempli.
3. **Une action principale par écran** : CTA pilule pleine largeur (52px, fond accent), secondaire fantôme (46px, bordure) toujours dessous. Voix = mode d'entrée premier, texte à un tap.
4. **Bulles courtes** : 2-3 bulles avec délai de frappe, jamais de pavé.
5. **Mobile-first strict** : Safari iOS et ses contraintes audio (autoplay, perte de focus) sont un cas de conception.
6. **Zéro emoji** dans cette piste ; la chaleur vient de la copie (ton « biographe » : encouragements par la précision, jamais de flatterie ; suggestions à la première personne de l'avatar).

## Système visuel (piste « Clair »)
- Fond blanc généreux ; surfaces gris chaud `#F5F5F4` ; hairlines `#ECEAE7` ; ombres quasi nulles.
- **Une seule famille : Figtree** (Google Fonts, 400-800). Titres 800, letter-spacing -0.02em.
- **Accent école** (`--accent`, démo ESM Lyon `#0E7C66`) utilisé en 3 formes : plein (CTA, badges), teinte ~8 % (`--accent-tint`, pastilles, bandeaux), halo (états vocaux). Dériver la teinte et le halo depuis l'accent (ex. `color-mix(in oklch, var(--accent) 8%, white)`).
- Wizard à **tirets** (22×6px, rayon pilule, gap 4 ; actif = accent, inactif `#E7E6E3`).
- Icônes dans des **pastilles teintées** (rounded 12-14px, fond tint, glyphe accent).
- Rayons : inputs 12, cartes 16-20, bulles 16 (coin « queue » 6), boutons pilule 999.
- Hit targets ≥ 44px.

## Écrans (Flux A, dans l'ordre)

### 00 · Email d'invitation
Objet : « Sacha, ta promo raconte son histoire ». Carte blanche sur fond `--surface` : pastille logo école (44px, fond accent), titre display « Salut {prénom}, ton école t'invite à créer ton avatar. », corps (15-20 min, ce que ça apporte), preuve sociale (3 avatars 26px chevauchés -8px + « 3 anciens de ta promo ont déjà le leur »), CTA « Créer mon avatar — 15 min », secondaire « Pas le bon moment ? Choisis quand ». Footer : « Lien personnel, valable 7 jours. Tu restes maître de ce que ton avatar raconte. »

### E19 · Lien magique
Centré : pastille check 84px avec halo (`box-shadow: 0 0 0 12px accent-8%`), « C'est bien toi, {prénom} », « Pas de mot de passe ici — ton lien suffit. On te connecte… », 3 points de chargement.
États : lien expiré (« On t'en renvoie un ? » + email pré-rempli), lien déjà utilisé / appareil différent (renvoi d'un tap). Redirige vers E01, ou E10/E18 si avatar existant.

### E01 · Accueil d'invitation
Bandeau haut fond `--accent-tint` : logo + nom école, titre display « Salut {prénom}. Raconte ton parcours, une fois — il servira à des centaines de lycéens. » Corps : 2 cartes stats (« 15-20 min / à la voix, tranquille », « Toi d'abord / tu valides tout avant publication »), preuve sociale nominative si vraie. CTA « Commencer », secondaire « Pas le bon moment ? Choisis quand » → sélecteur de date → email de rappel.
État lien public (E22) : sans prénom, preuve sociale générique.

### E02 · Check technique
Titre « 10 secondes pour vérifier que tout roule » + « Rien n'est enregistré pour l'instant. » 3 lignes de vérification (cartes bordées, 14-16px padding) : micro (coche verte accent + mini-waveform live), son (bouton texte « Écouter »), réseau (spinner). Vérifications séquentielles animées. CTA « C'est bon, on y va » (activé quand 3 ✓).
États : micro refusé → guide par navigateur ; réseau faible → « continue en texte ».

### E03 · Profil
« On a presque tout — vérifie juste ». Carte identité (avatar initiales 54px fond tint, nom pré-rempli, action « Photo »). Année de sortie : roue horizontale (valeur active en 800/24px sur fond tint). Formation : champ autocomplétion sur référentiel — résultats : match direct (fond tint), **alias historique** (« BTS Communication des entreprises · ancien nom, avant 2012 »), « + Ajouter la mienne » (bordure pointillée). CTA « Continuer ».

### E04 · Retrouver son LinkedIn
« On a peut-être retrouvé ton profil » + « Un tap et ta timeline se remplit toute seule. » 1-3 cartes candidates (photo 52px, nom, titre · ville, bouton « C'est moi » — 1ʳᵉ carte pré-sélectionnée : bordure accent 1.5px, bouton plein). Replis : « Aucun de ces profils » (fantôme) et « Je n'ai pas LinkedIn — pas grave, Léa s'en charge » (lien texte). Repli → **saut direct vers E06**.
États : recherche en cours (skeletons), zéro résultat.

### E05 · Timeline confirmable
« Voilà ce qu'on a importé » + « Un tap pour confirmer, le crayon pour corriger. » Cartes chronologiques : titre + dates, coche ronde 34px (validée = fond accent) + crayon (édition inline : champ + bouton OK, carte passe en bordure accent), croix pour supprimer. CTA « Tout est bon ».

### E06 · Consentements
« Trois choses, clairement » + « Chaque accord est séparé et révocable quand tu veux. » 3 blocs (voix / photo / données LinkedIn) : titre + explication en langage simple, case à cocher carrée 26px (cochée = fond accent) séparée par bloc. Bandeau « Réservé aux 18 ans et plus · texte complet » (lien). CTA « J'accepte — on commence ».
État refus partiel : la suite s'adapte, conséquences expliquées calmement (ex. sans photo → initiales).

### E07 · Session d'interview — MOBILE (écran signature)
Structure verticale : header (pause ⏸, chip thème « Premier emploi · 2/4 », timer discret 06:12) ; **module vocal** : avatar Léa 64px avec halo double anneau + pilule d'état (« Léa t'écoute » + waveform 4 barres) ; conversation en bulles (IA fond `--surface` coin queue, réponse utilisateur fond accent à droite) ; rangée de contrôles permanents : « Garder privé » / « Passer » / « Reformule » / ⌨ (bascule texte) ; **volet bas escamotable** (fond surface, radius 22 haut, poignée 38×4) : « Ton parcours se construit » + « Tout voir ↑ » + 3 mini-cartes (thème validé ✓ / en cours / citations).
**États vocaux à implémenter** : IA parle (halo pulse) / IA écoute (waveform live) / **pause auto** (appel entrant, perte de focus — écran dédié : ⏸ 84px, « On t'attend, prends ton appel », « Rien n'est perdu… », chip thème en cours, CTA « Reprendre », lien « Continuer par écrit ») / mode texte.
Entre chaque thème : récapitulatif plein écran. Abandon → sauvegarde + email de reprise.

### E07 · Desktop (référence dans le canvas, section 2a)
Split-view : header (quitter, wizard à tirets centrés, chips thème + timer), conversation à gauche (module vocal centré en haut), panneau droit 400px fond `--surface` : carte-artefact blanche (photo, nom, promo, badge école, timeline avec états validé/en cours/à venir) + citations retenues avec badges Public (tint) / Réservé école (bordure) / Privé.

### E08 · Validation par sections
« Ton histoire, ligne par ligne » + « Tout est proposé en public — change ce que tu veux. » Sections par thème (label uppercase). Chaque citation : carte bordée + **toggle segmenté 3 positions** (Public / École / Privé) sur fond `--surface` pilule — Public actif = fond accent, École actif = fond ink. CTA « Valider mon histoire ».

### E09 · Choix du ton
« Lequel te ressemble le plus ? » + « Trois réponses, toutes tirées de tes propres mots. » Bulle question (« Un lycéen demande : … »), 3 cartes réponses : texte, bouton play 32px + waveform (TTS), bouton « C'est moi » (sélectionnée : bordure accent + bouton plein). Lien bas : « Aucune ne me ressemble — propose-m'en trois autres » → 3 nouvelles.

### E20 · Fin & fierté (peak-end)
Carte avatar en avant-première (ombre teintée accent, badge « En validation par l'école », photo/initiales, nom, promo, 3 sujets forts en chips), titre « Ton avatar est en préparation », corps « 17 minutes de ta vie, des centaines de lycéens éclairés. » CTA « Tester mon avatar » (→ E10), secondaire « Prévenir un proche » (→ lien E21).

### E13 · Studio — Campagnes (référence, section 2a du canvas)
Identité fixe orange. Top bar : logo, nav en pilules (active = fond `--studio-tint` texte `--studio-accent`, badge compteur), recherche ⌘K. Sidebar 220px. Contenu : wizard (import CSV avec mapping colonnes → statuts ✓/à confirmer ; ciblage en chips noires supprimables ; trame pondérée + « prévisualiser en me faisant interviewer ») ; CTA « Lancer — 87 invitations · ≈ 43 € » + secondaire « Programmer pour mardi 9h — recommandé » ; colonne droite : campagnes en cours (barre de progression, « Relancer les N »), encart conseil.

## Interactions & comportements
- **Navigation** : wizard linéaire, retour ← en haut à gauche, progression persistée (reprise intra-session).
- **Bulles IA** : apparition séquentielle avec indicateur de frappe (400-800ms par bulle).
- **États vocaux** : transitions halo/waveform en CSS (pulse 1.2s ease-in-out infinite sur le halo quand l'IA parle). Pause auto déclenchée par `visibilitychange` / interruption audio (Safari iOS).
- **Toggles 3 niveaux (E08)** : segmented control, transition 150ms ease-out.
- **Autocomplétion (E03)** : résultats dès 2 caractères, alias historiques signalés, création libre en dernier item.
- **Skeletons** : E04 recherche, E19 connexion.
- **Emails** : invitation (00), rappel (report), reprise (abandon E07), « avatar en ligne » (après validation école).

## Gestion d'état (côté interviewé)
- `session` : étape courante, thème courant (n/4), temps restant, transcript, statut (active/paused/abandoned).
- `profile` : nom, photo, année, formation (id référentiel ou libre).
- `timeline[]` : expériences {titre, org, dates, source: linkedin|interview, status: confirmed|edited|removed}.
- `quotes[]` : {texte, thème, visibility: public|school|private} — défaut `public` sauf flags.
- `consents` : {voice, photo, linkedin} — indépendants, révocables.
- `tone` : réponse candidate choisie.

## Design tokens
Voir `tokens.css` (CSS variables) et `tokens.json`. Point crucial : **`--accent` est injectée par école** ; dériver `--accent-tint` et `--accent-halo` depuis l'accent (color-mix ou oklch) plutôt que de les figer.

## Assets
Aucun asset binaire : logos école = pastille initiales ; photos = placeholders (cercles teintés). Prévoir les vraies photos/logos par école. Police : Figtree (Google Fonts).

## Fichiers
- `Pistes Identité.dc.html` — canvas de référence : section 3a = Flux A mobile complet ; section 2a = piste « Clair » (moodboard, E07 desktop, E13 Studio) ; section 1 = pistes écartées (Braise, Archive, Écho).
- `tokens.css` / `tokens.json` — tokens de la piste retenue.

## Contexte produit complet
L'arborescence complète (E01-E22), les workflows B/C/D et les fiches écran restantes sont dans le document source `05-sommaire-ecrans-workflows.md` (inclus dans ce bundle).
