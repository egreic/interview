# 02 · Brief produit — Brique Interview & Avatars

## Vision

« La mémoire de l'école qu'on ouvre publiquement. » Une brique générique qui interviewe des personnes (par la voix, avec une IA au comportement de biographe) pour nourrir des IA : le pilote transforme les alumni d'écoles supérieures en avatars conversationnels, sincères et validés, avec lesquels les futurs étudiants peuvent discuter. La même brique servira ensuite la capture d'expertise puis les cas RH — le modèle de données est générique dès le premier jour.

## Le problème et la promesse

Les écoles ont besoin de témoignages authentiques à l'échelle : les vidéos coûtent cher, vieillissent, ne répondent pas aux questions. Les alumni veulent bien aider mais pas remplir des formulaires. Les prospects, eux, veulent parler à quelqu'un qui était eux il y a cinq ans. La promesse : vingt minutes de conversation vocale agréable → un avatar fidèle, contrôlé par la personne, validé par l'école, qui répond aux jeunes 24 h/24 — et qui distingue toujours « de mon temps » d'« aujourd'hui ».

## Personas

- **Karim, 24 ans, alumni** : ouvre le lien sur son téléphone, dans le métro. Il veut que ce soit court, fluide, et garder le contrôle de ce qui est public.
- **Sophie, responsable communication** : elle veut du volume, de la qualité, zéro risque de bad buzz, et le moins de clics possible.
- **Léa, 16 ans, prospecte** : elle veut du vrai, du concret, en style SMS — son radar anti-pub est infaillible.
- **Le super-admin (nous)** : il pilote le multi-écoles, les coûts, les gabarits de prompt.

## Parcours interviewé (le fil d'or)

Invitation pré-remplie (ou lien public de campagne) → report planifié si mauvais moment → check technique → profil (nom, photo, promo, formation dans le catalogue vivant) → « retrouver ton profil LinkedIn ? » (recherche par nom, cartes, « C'est moi », import, timeline confirmable) → trois consentements distincts (audio, image, LinkedIn) → **l'interview vocale** : un biographe IA chaleureux qui connaît son parcours et le vocabulaire de l'école, pose des questions épisodiques (« raconte-moi la fois où… »), convoque Léa aux bons moments (« elle te demande si l'alternance est tenable, tu lui dis quoi ? »), pendant que le panneau de droite construit son parcours en direct — marquage « privé » à la volée, pause automatique si appel entrant, 15-20 minutes one-shot avec sauvegarde continue → validation section par section (public / réservé école / privé) → choix du ton par trois exemples tirés de ses propres mots, écoutables → « tester mon avatar » → lien aux proches → publication après validation école → espace personnel permanent (éditer, enrichir, révoquer, supprimer).

## Parcours école

Contexte école chargé (formations, vie étudiante, vocabulaire maison — validé par l'admin) → trame générée depuis un objectif décrit en langage naturel, éditée en split-view, prévisualisée en se faisant interviewer → campagne (import CSV, ciblage, estimation de coût, invitations, relances, preuve sociale) → file de revue : fiche persona, extraits écoutables, verdict du banc d'essai IA (des prospects simulés interrogent l'avatar, un juge évalue : met en valeur ? sincère ? à risque ?) → validation, mise en avant, galerie → vie courante en mode « brosse à dents » : digest hebdomadaire, enrichissements publiés sous 72 h sauf opposition, réconciliation des formations historiques.

## Le système en briques

Onze briques à contrats nets : Profils & enrichissement · Contexte école (client d'un service externe) · Référentiel formations vivant · Trames (Studio) · Campagnes · Session temps réel · Voix · Moteur de conduite · Enregistrements canoniques · Profil vivant · Atelier avatar — plus l'Orchestrateur de test (une brique, quatre consommateurs : outil interne, Atelier, prévisualisation de trame, tiers) et trois modules transverses (analytics, notifications, métrologie des coûts). Le prompt de chaque avatar est **compilé** en couches : socle générique versionné + cas d'usage + école + personne + exemples few-shot générés depuis ses propres verbatims.

## Psychologie du produit (les leviers, tous testés)

L'audience imaginée (on n'est jamais vendeur face à son « toi d'avant », on est mentor) ; l'effet IKEA du panneau qui se construit ; le miroir imparfait du self-test (« pas tout à fait toi ? affinons-le ») ; le gap de complétude (« ton avatar connaît 68 % de ton histoire ») ; l'avatar-tamagotchi (suggestions à sa première personne) ; la preuve sociale contextuelle ; la récompense d'impact (« 12 lycéens ont parlé avec ton avatar ») ; le peak-end (finir sur la fierté). Et la règle d'or : on n'oriente jamais les réponses, seulement les questions — la sincérité EST la stratégie commerciale.

## Règles de confiance

Rien n'est public sans double validation (personne puis école). Trois niveaux de confidentialité. La personne peut ajouter des interdits, jamais retirer ceux de sécurité ; elle modifie son avatar en langage naturel sans jamais voir le prompt ; workflow tricolore (libre / 72 h d'opposition / re-validation bloquante). Public souvent mineur : jamais de coordonnées demandées, jamais de secret vis-à-vis des parents, détresse → ressources. RGPD : registre de consentements versionné, rétention audio configurable, suppression en cascade, hébergement UE.

## Phasage

**V1 (pilote alumni)** : tout le fil d'or ci-dessus, banc d'essai simple (1 prospect + 1 juge, conçu pour N), agrégation mono-interview, page hébergée seulement. **Phase 2** : reprise multi-sessions, vidéo, widget intégrable, API publique tiers facturée, banc multi-personas, agrégation multi-sources du profil vivant, suggestions automatiques avancées, cas RH. **Indicateurs de succès V1** : taux de complétion d'interview > 70 %, délai interview→avatar actif < 7 jours, > 80 % des avatars validés sans retouche école, premières conversations prospects mesurées.

## Modèle économique

Interne d'abord (la brique alimente l'écosystème avatars existant) ; la métrologie des coûts par interview et par école, en place dès la V1, prépare la facturation des tiers en phase 2.
