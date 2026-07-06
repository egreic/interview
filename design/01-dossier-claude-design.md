# 01 · Dossier Claude Design — Brique Interview & Avatars

## Mission

Concevoir l'identité visuelle et les écrans d'une plateforme à deux faces : côté interviewé, une expérience d'interview vocale par IA qui transforme un alumni en avatar conversationnel ; côté école, un Studio de pilotage (trames, campagnes, revue des avatars). Public interviewé : 18-30 ans, sur mobile d'abord. Public Studio : responsables communication et admissions d'écoles supérieures françaises.

**Demande n°1 : propose 3 à 4 pistes d'identité distinctes** (moodboard, palette, typographies, et deux écrans clés déclinés par piste). Contrainte structurante : la face interviewé est thémable aux couleurs de chaque école (les couleurs sont extraites automatiquement de leur site) — l'identité doit donc définir un système neutre-chaleureux qui accueille une couleur d'accent variable. Le Studio, lui, garde une identité fixe. Après choix d'une piste : déclinaison complète des écrans P1 et export des tokens (couleurs, typo, espacements, rayons) pour l'industrialisation.

## Langage d'interaction (non négociable, issu du cadrage)

- **Split-view à artefact vivant** : pendant tout échange conversationnel, un panneau latéral construit le résultat en direct (le parcours de l'interviewé ; la trame côté Studio). Sur mobile : volet bas escamotable + récapitulatif entre les thèmes.
- **Suggestion → validation d'un tap** : partout où une décision est attendue, 2-4 options tapables plutôt qu'un champ vide. Zéro saisie évitable — tout est pré-rempli ou proposé.
- **Une action principale par écran.** La voix est le premier mode d'entrée, le texte toujours à un tap.
- **Conversation en bulles** : l'avatar (et l'intervieweur) répond en messages courts découpés en 2-3 bulles avec délai de frappe — jamais de pavé.
- Mobile-first strict : Safari iOS et ses contraintes audio sont un cas de conception, pas un cas limite.

## Écrans à maquetter (priorisés)

**P1 — Le parcours interviewé (le cœur)**
1. Accueil d'invitation : durée annoncée, « pas le bon moment ? choisis quand » (report planifié), preuve sociale (« 3 anciens de ta promo ont déjà leur avatar »).
2. Check technique : micro, sortie audio, réseau — rassurant, 10 secondes.
3. Profil : nom/photo pré-remplis, année de sortie, formation (sélecteur avec autocomplétion, alias historiques, « ajouter la mienne »).
4. Retrouver son LinkedIn : cartes de profils candidats avec bouton « C'est moi », replis « aucun de ces profils / je n'ai pas LinkedIn ». *(maquette basse-fi validée : cartes photo + titre + ville)*
5. Confirmation de la timeline : expériences importées, chacune validable/corrigeable d'un tap.
6. Consentements : trois cases distinctes (audio, image, LinkedIn), langage clair, 18+.
7. **La session d'interview** — l'écran signature : conversation vocale à gauche, parcours qui se construit à droite (timeline qui se remplit, citations retenues avec badge public/privé). États vocaux à dessiner : IA parle / IA écoute (waveform) / pause auto (appel entrant, perte de focus) / reprise. Bouton « garder privé » à la volée, « passer », « reformule ». Progression par thèmes, temps restant. *(maquette basse-fi validée)*
8. Validation finale : sections relisibles avec toggle à trois niveaux — public / réservé à l'école / privé.
9. Choix du ton : une question de Léa, trois réponses candidates tirées de ses propres mots, écoutables en TTS, « C'est moi » d'un tap. *(maquette basse-fi validée)*

**P2 — L'Atelier avatar (la personne) et le Studio (l'école)**
10. Self-test : chat avec son propre avatar en bulles ; jauge « ton avatar connaît 68 % de ton histoire » avec zones grises ; suggestions à la première personne de l'avatar (« J'aimerais savoir quoi répondre quand on me demande le rythme ») ; lien à envoyer aux proches ; notation « c'est moi / pas moi » par réponse.
11. Studio — stratégie éditoriale (ouverture du Studio) : l'IA présente les axes qu'elle propose d'appuyer dans les interviews (« j'ai regardé votre site et vos formations… »), chacun avec sa justification et sa source (site / interview du personnel / émergent des interviews) ; l'école active, écarte, surenchérit ou ajoute d'un tap — même pattern que l'onboarding. Les axes émergents arrivent ensuite en suggestions continues.
12. Studio — générateur de trames en split-view : l'admin décrit son objectif, la trame se construit à droite (pondérée par la stratégie, axes visibles par thème), sections éditables ; bouton « me faire interviewer » (prévisualisation).
13. Studio — campagnes : import CSV avec mapping de colonnes, ciblage promo/formation, estimation de coût avant lancement, suivi complétion et relances.
14. Studio — file de revue : par avatar, fiche persona + 5 extraits saillants écoutables + passages flagués + verdict du banc d'essai (positif/doute/négatif) avec transcripts, actions d'un tap (valider, refuser avec motifs proposés, mettre en avant, non public). Workflow tricolore : les enrichissements « orange » avec fenêtre d'opposition 72 h.
15. Studio — réconciliation des formations proposées par les alumni (suggestion IA, validation d'un tap : historique datée / renommée-fusion / correction).
16. Digest hebdomadaire (email + écran) : « vos avatars cette semaine : 3 enrichissements, 47 conversations, 1 point d'attention ».

**P3 — Périphériques**
17. Page publique partageable d'un avatar (belle carte d'aperçu pour LinkedIn), sous contrôle du statut école.
18. Espace personnel permanent de l'alumni : éditer, re-tester, révoquer un consentement, supprimer.

## Micro-copies (ton « biographe », à respecter et enrichir)

- Bouton d'amélioration : « Pas tout à fait toi ? Affinons-le. » — jamais de formulation côté école.
- Suggestions : toujours à la première personne de l'avatar.
- Notification d'impact : « 12 lycéens ont parlé avec ton avatar cette semaine. Question la plus posée : les débouchés à l'international — tu veux lui apprendre ta réponse ? »
- Présentation entre étudiants (relance inter-avatars, phase 2 — à maquetter comme message d'ouverture) : « Salut ! C'est Marc, promo 2020. Sacha m'a dit que vous aviez parlé de partir étudier au Canada — c'est exactement ce que j'ai fait. Voilà tout ce qu'il m'a transmis : ton intérêt pour l'international, rien d'autre. Si tu veux, je te raconte. (Tu peux couper ces messages d'un tap.) » — et l'offre d'opt-in côté avatar source : « Tu veux que je te présente quelqu'un qui l'a vraiment vécu ? »
- Encouragements par la précision, jamais de flatterie générique ; aucune question rhétorique ou vendeuse.

## Arbitrages ouverts (à trancher par les pistes)

Emoji dans les bulles de l'avatar : oui/non/selon le ton choisi par la personne. Densité d'information du Studio (tableau de bord riche vs épuré). Degré d'illustration (illustrations chaleureuses vs sobriété documentaire).

## Livrables attendus

1. 3-4 pistes d'identité (moodboard, palette avec slot « couleur école », typos, écrans 7 et 13 déclinés).
2. Après choix : les écrans P1 en haute fidélité, les états vocaux, la version mobile de l'écran 7.
3. Export des design tokens (JSON ou CSS variables) — ils seront figés dans le skill de développement.

Note de méthode : ce dossier sert à choisir la piste. Le détail écran par écran (sommaire complet, workflows de navigation, fiches autonomes E01-E22) arrive ensuite via le document 05 — les fiches seront envoyées une par une dans ce même projet, dans l'ordre recommandé.
