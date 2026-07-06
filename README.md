# Interview & Avatars

AI voice interviews turning alumni into conversational avatars for French
higher-education schools. Product brief: `docs/02-brief-produit.md` · technical
spec: `docs/03-cahier-des-charges-technique.md` (this repo is the source of
truth) · project skill: `.claude/skills/brique-interview-avatars/SKILL.md`.

## Layout (hybrid, decided in the scaffold/CDC arbitration)

| Path | What | Milestone |
|------|------|-----------|
| `backend/` | Symfony 6.4 modular monolith (`src/Module/<Brick>/`), MongoDB ODM | J1 → |
| `frontend/interviewee/` | React + Vite — interviewee mobile-first app | J3 |
| `frontend/studio/` | React + Vite — school Studio (fixed identity) | J6 |
| `workers/` | Python 3.11 + pika — transformer workers | J2 |
| `voice-gateway/` | Python FastAPI WebSocket voice gateway | J4 |
| `deploy/` | OVH VPS deployment (Apache + systemd), see `deploy/README.md` | — |

Dev stack: `make init` (docker-compose is dev-only, never deployed).
`scaffold.py` from the `studizz-project-scaffold` skill is a tooling
specification for this repo — it is never executed here.

## Backend quickstart

```bash
cd backend
composer install
composer lint
vendor/bin/phpunit                 # acceptance tests need ext-mongodb + MongoDB
php bin/console app:seed:demo      # seeds École Démo + Institut Nord
```
