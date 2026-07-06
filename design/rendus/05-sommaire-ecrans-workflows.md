# 05 · Sommaire des écrans & workflows — Brique Interview & Avatars

Complément du dossier 01 (identité, langage d'interaction). Usage avec Claude Design : d'abord choisir la piste d'identité (session du dossier 01), puis envoyer les fiches ci-dessous **une par une** dans le même projet, dans l'ordre recommandé (section 4). Chaque fiche est autonome.

Amorce type à coller avec chaque fiche : « Dans la piste d'identité retenue, maquette l'écran suivant. Respecte le langage d'interaction du dossier initial (suggestion→tap, une action principale, bulles courtes, mobile-first pour l'espace interviewé). Voici la fiche : … »

## 1. Arborescence

**Espace Interviewé** (mobile-first, thémé aux couleurs de l'école)
- E19 Accès par lien magique (et états d'erreur)
- E01 Accueil d'invitation → E02 Check technique → E03 Profil → E04 LinkedIn « C'est moi » → E05 Timeline → E06 Consentements → E07 Session d'interview → E08 Validation par sections → E09 Choix du ton → E20 Fin & fierté
- E10 Atelier avatar (self-test, jauge, suggestions, lien proches)
- E18 Espace personnel permanent
- E21 Conversation d'un proche + mini-feedback

**Espace Studio** (desktop-first, identité fixe)
- Connexion (lien magique) → E16 Accueil / digest
- E11 Stratégie éditoriale · E12 Générateur de trames · E13 Campagnes · E14 File de revue · E15 Réconciliation des formations · Analytics (vue simple V1)

**Public**
- E17 Page avatar partageable · E22 Page de campagne publique (captcha)

## 2. Workflows

**Flux A — Interviewé nominal** : E19 → E01 → E02 → E03 → E04 → E05 → E06 → E07 → E08 → E09 → E20 → (email « avatar en ligne ») → E10.
Embranchements : E01 « pas le bon moment » → choix de date → email de rappel → retour E19. E04 « aucun de ces profils / pas de LinkedIn » → saut vers E06 (la timeline se construira en interview). E07 pause auto (appel, perte de focus) → reprise même session. E07 abandon → sauvegarde + email de reprise (V1 : reprise intra-session uniquement).

**Flux B — École** : E11 (axes validés) → E12 (trame, prévisualisation en se faisant interviewer) → E13 (campagne : import CSV, estimation, lancement) → interviews → E14 (revue : valider / refuser / mettre en avant) → E17 publiée → E16 (digest hebdo, alertes).

**Flux C — Amélioration continue** : E10 (instruction en langage naturel ou suggestion acceptée) → patch tricolore : vert → re-test auto → publié ; orange → banc d'essai → notification école (E16) → publié à 72 h sauf opposition (E14) ; rouge → bloqué en E14.

**Flux D — Visiteur (fondations V1, exécution phase 2)** : E17/widget → conversation → opt-in « présentation entre étudiants » → relance par l'avatar cible (1 max, opt-out).

## 3. Fiches écran

**E01 — Accueil d'invitation** · Objectif : donner envie et lever la friction en 5 secondes. Contenu : photo/nom de l'école (thème), « Ton école t'invite à créer ton avatar », durée annoncée (15-20 min), preuve sociale si vraie (« 3 anciens de ta promo ont le leur »), CTA « Commencer », lien secondaire « Pas le bon moment ? Choisis quand ». États : invitation nominative (prénom affiché) vs lien public. Sorties : E02, ou sélecteur de date → confirmation.

**E02 — Check technique** · Objectif : sécuriser l'audio sans inquiéter. Contenu : trois vérifications animées (micro, sortie, réseau) avec coche verte, phrase rassurante, CTA « C'est bon ». États : micro refusé (guide de déblocage par navigateur), réseau faible (proposer de continuer en texte). Sorties : E03.

**E03 — Profil** · Objectif : identité minimale sans saisie. Contenu : nom pré-rempli, photo (upload ou initiales), année de sortie (roue), formation (autocomplétion sur le référentiel, alias historiques, option « Ajouter la mienne »). Sorties : E04.

**E04 — Retrouver son LinkedIn** · Objectif : enrichir sans effort. Contenu : « On a peut-être retrouvé ton profil » + 1-3 cartes (photo, titre, ville) avec bouton « C'est moi », replis « Aucun de ces profils » / « Je n'ai pas LinkedIn ». États : recherche en cours, zéro résultat. Sorties : E05 (import) ou E06 (repli). *(maquette basse-fi validée)*

**E05 — Timeline confirmable** · Objectif : transformer l'import en données validées. Contenu : expériences en cartes chronologiques, chacune : coche « C'est juste », crayon (édition inline), croix. CTA « Tout est bon ». Sorties : E06.

**E06 — Consentements** · Objectif : confiance par la clarté. Contenu : trois blocs distincts (voix, image, données LinkedIn) en langage simple, cases séparées, mention 18+, lien texte complet. États : refus partiel (conséquences expliquées calmement). Sorties : E07.

**E07 — Session d'interview (écran signature)** · Objectif : la conversation qui construit. Contenu desktop : conversation vocale à gauche (bulles), panneau « Ton parcours » à droite qui se remplit en direct (timeline, citations retenues avec badge public/privé). Contenu mobile : conversation plein écran + volet bas escamotable + récap entre thèmes. Contrôles permanents : pause, « garder privé », « passer », « reformule », bascule texte. États à dessiner : IA parle / IA écoute (waveform) / pause auto (appel, perte de focus) avec reprise en un tap / mode texte. Progression par thèmes + temps restant discret. Sorties : E08. *(maquette basse-fi validée)*

**E08 — Validation par sections** · Objectif : contrôle total, effort minimal. Contenu : sections par thème, chaque élément avec toggle trois niveaux (public / réservé à l'école / privé), tout en « public proposé » sauf flags. CTA « Valider mon histoire ». Sorties : E09.

**E09 — Choix du ton** · Objectif : « c'est moi » en 30 secondes. Contenu : une question type de prospecte, trois réponses candidates tirées de ses propres mots, chacune écoutable (TTS), sélection d'un tap, option « Aucune ne me ressemble » → 3 nouvelles. Sorties : E20. *(maquette basse-fi validée)*

**E20 — Fin & fierté (peak-end)** · Objectif : terminer haut. Contenu : « Ton avatar est en préparation », aperçu carte avatar, ce qui se passe ensuite (validation école), CTA « Tester mon avatar » (→ E10) et « Prévenir un proche » (lien E21). Sorties : E10, partage.

**E10 — Atelier avatar** · Objectif : le tamagotchi. Contenu : chat de self-test (bulles, notation « c'est moi / pas moi » par réponse), jauge « Ton avatar connaît 68 % de ton histoire » avec zones grises tapables, suggestions à la première personne de l'avatar (« J'aimerais savoir quoi répondre quand on me demande le rythme »), champ « Dis-moi quoi changer » (langage naturel), bouton lien proches, historique des améliorations en langage simple (jamais le prompt). États : patch vert appliqué / orange « l'école y jette un œil » / rouge bloqué. Sorties : E18.

**E11 — Stratégie éditoriale (Studio)** · Objectif : l'IA propose, l'école arbitre. Contenu : « J'ai regardé votre site et vos formations — voici les axes que je propose d'appuyer », cartes d'axes avec justification + source (site / interview du personnel / émergent), toggles activer/écarter, bouton « surenchérir » (note), « Ajouter un axe ». Bandeau garde-fou : « Les axes orientent les questions, jamais les réponses. » États : suggestions émergentes entrantes (badge nouveau). Sorties : E12.

**E12 — Générateur de trames (Studio)** · Objectif : de l'objectif à la trame en split-view. Contenu : chat à gauche (l'admin décrit), trame à droite (thèmes ordonnés, durées, curseur fixe/guidé/adaptatif par thème, axes stratégiques visibles en tags), édition inline, boutons « Me faire interviewer » (prévisualisation) et « L'IA joue un alumni ». Sorties : E13.

**E13 — Campagnes (Studio)** · Objectif : lancer sans peur. Contenu : import CSV avec mapping de colonnes assisté (aperçu, rejets listés), ciblage promo/formation, estimation de coût avant lancement, aperçu de l'email d'invitation, planification des relances, tableau de suivi (envoyés/ouverts/démarrés/complétés). Sorties : E14, E16.

**E14 — File de revue (Studio)** · Objectif : décider vite et bien. Contenu : liste des avatars en attente ; fiche : persona résumé, 5 extraits saillants écoutables, passages flagués, verdict du banc d'essai (positif/doute/négatif) avec transcripts dépliables, actions d'un tap : Valider / Mettre en avant / Non public / Refuser (motifs proposés). Onglet « Modifications en attente » : patches orange avec compte à rebours 72 h et bouton « M'opposer ». Sorties : E17, E16.

**E15 — Réconciliation des formations (Studio)** · Objectif : garder le référentiel propre. Contenu : formations proposées par les alumni avec suggestion IA (« probablement l'ancien nom de X »), trois actions : Historique (année de fin), Renommée (fusion, alias conservé), Corriger (doublon). Sorties : retour E16.

**E16 — Accueil / digest (Studio)** · Objectif : la brosse à dents. Contenu : « Vos avatars cette semaine : 3 enrichissements, 47 conversations, 1 point d'attention », accès rapides (revue en attente avec badge, campagnes actives, question la plus posée aux avatars), alertes de redondance inter-avatars. Sorties : tous les écrans Studio.

**E17 — Page publique d'un avatar** · Objectif : donner envie de discuter et d'être partagée. Contenu : photo, prénom, promo, formation, 2-3 sujets forts, CTA « Discuter avec moi », carte d'aperçu OG soignée pour LinkedIn. États : avatar non public (page introuvable propre). Sorties : widget de conversation.

**E18 — Espace personnel** · Objectif : le contrôle dans la durée. Contenu : mon avatar (statut, stats d'impact), mes contenus par niveau de confidentialité, mes consentements (révocables un par un avec conséquences expliquées), « Reprendre une interview » (phase 2, grisé), zone rouge : supprimer (cascade expliquée). Sorties : E10.

**E19 — Accès par lien magique** · Objectif : entrer sans mot de passe. Contenu : validation du lien, redirection ; états : lien expiré (« On t'en renvoie un ? » + champ email pré-rempli), lien déjà utilisé, appareil différent. Sorties : E01 ou E10/E18 selon le contexte.

**E21 — Conversation d'un proche** · Objectif : tester et nourrir. Contenu : bandeau « Tu discutes avec l'avatar de Sacha — il lit vos échanges pour s'améliorer », chat en bulles, à la fin : mini-feedback deux questions (« Ça lui ressemble ? » 👍/👎 + « Qu'est-ce qui cloche ? » optionnel). Sorties : fin simple.

**E22 — Page de campagne publique** · Objectif : recruter au-delà du fichier. Contenu : présentation courte de la démarche par l'école, preuve sociale, captcha léger, champs minimaux (nom, email, promo), mention « soumis à validation de l'école ». Sorties : E19 (lien envoyé par email).

## 4. Ordre d'envoi recommandé dans Claude Design

1. E07 (l'écran signature — il fixe le système) → 2. E04 → 3. E09 → 4. E01, E02, E03, E05, E06, E08, E20 (le parcours complet) → 5. E14 puis E11, E12 (les écrans Studio structurants) → 6. E13, E15, E16 → 7. E10, E17, E18 → 8. E19, E21, E22. Après chaque écran validé : « applique ce composant au design system du projet » pour capitaliser.
