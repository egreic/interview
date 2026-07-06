# Ubiquitous language — FR métier ↔ EN code

Mapped once, used everywhere. Code, identifiers and commits are English; the
French business vocabulary below is the one of the CDC and the Studio UI.

| Français (métier) | English (code) |
|---|---|
| Trame | `InterviewTemplate` |
| Stratégie éditoriale | `EditorialStrategy` |
| Enregistrement canonique | `CanonicalRecord` |
| Correctif d'auteur | `AuthorCorrection` |
| Profil vivant | `LivingProfile` (module) / `Chunk`, `Experience` |
| Atelier avatar | `AvatarWorkshop` |
| Patch de persona (tricolore) | `PersonaPatch` |
| Banc d'essai / orchestrateur de test | `TestBench` |
| Fiche persona | `PersonaSheet` |
| Moteur de conduite | `InterviewEngine` |
| Formation (référentiel) | `Program` / `ProgramReferential` |
| Campagne / invitation | `Campaign` / `Invitation` |
| Session temps réel | `InterviewSession` |
| École (client) | `Tenant` |
| Contexte école | `SchoolContext` |
| Personne / interviewé / alumni | `Person` |
| Passerelle vocale | `voice-gateway` |
| Passation | `Handoff` (sync) / `AsyncHandoff` (phase 2) |
| Lien magique | magic link (`MagicLinkTokenService`) |

State/enum values that are part of the FRENCH business contract stay French in
data (`a_confirmer`, `positif|doute|negatif`, `vert|orange|rouge`,
`public|reserve_ecole|prive`, session states `creee…abandonnee`) — they cross
API and prompt boundaries and are asserted by the test bench scenarios.
