---
name: studizz-project-scaffold
description: |
  Use this skill to bootstrap/scaffold a brand-new project with the standard Studizz stack and folder layout: a React (Vite) frontend, a Symfony + MongoDB (Doctrine ODM) backend wired for Studizz auth, Python RabbitMQ workers (pika), plus MongoDB/RabbitMQ/Redis, a dev docker-compose, and OVH-VPS deployment scripts (build+tar+scp for the front, git + systemd install for the workers).

  Trigger whenever the user wants to START a new Studizz project, app, service, or repo from scratch and needs the base structure — phrases like "crée un nouveau projet", "nouveau projet Studizz", "structure de base", "scaffold a project", "bootstrap a new app", "set up a new repo with our stack", "monte-moi un projet React + Symfony + Mongo + RabbitMQ", "initialise le squelette", "I need the standard layout with front/back/workers + docker-compose + deploy scripts". Trigger even if the user lists only part of the stack but clearly means a fresh Studizz project.

  Do NOT trigger for: adding a single feature/client to an existing project (use studizz-auth-integration, studizz-api-amqp-client, or studizz-api-mailer-client instead), generating API docs (openapi-controller-doc), or working inside an already-scaffolded repo.
---

# Studizz Project Scaffold

Bootstraps a new project following the standard Studizz architecture in one shot,
via a single deterministic script. The whole point is that every new Studizz
project starts from the same predictable layout, so a developer can clone, run
`make up`, and have the full system (React + Symfony + Mongo + RabbitMQ + Redis)
booting locally — while production stays a plain OVH VPS deployed by git + scripts.

## The stack this produces

| Layer | Tech | Dev | Prod (OVH VPS, Ubuntu + Apache + PHP) |
|-------|------|-----|----------------------------------------|
| Frontend | React + **Vite** | docker-compose | `deploy/deploy-front.sh` — build locally, tar, scp to Apache docroot |
| Backend | **Symfony + Doctrine MongoDB ODM** (+ Studizz auth) | docker-compose (php-apache) | **git pull** + `composer install` |
| Workers | **Python + pika** (RabbitMQ) | docker-compose | **git pull** + `deploy/install-workers.sh` (one **systemd** service per worker) |
| Database | MongoDB | docker-compose | on the VPS |
| Queue | RabbitMQ | docker-compose | on the VPS |
| Cache | Redis (optional) | docker-compose | on the VPS |

Async model: the Symfony backend publishes to RabbitMQ and the Python workers
consume directly (pika / `php-amqplib`). The dev docker-compose includes a
RabbitMQ service with the management UI.

## How to use this skill

1. **Get the project name.** Ask the user for a slug if they haven't given one
   (lowercase letters, digits, dashes — e.g. `mon-projet`). It becomes the
   folder name, the docker-compose project name, the Mongo DB name, the default
   RabbitMQ queue prefix, and the systemd service prefix
   (`<project>-worker@<name>`).

2. **Run the scaffolder, bringing up the dev env by default.** From this skill
   directory:

   ```bash
   python3 scripts/scaffold.py <project-name> [target-directory] --up
   ```

   - `--up` is the standard, "accompany the user" path: after writing the
     files it copies each `.env.example` to `.env` and starts the full stack
     with `docker compose up --build -d`. The backend's `STUDIZZ_AUTH_URL`
     already defaults to the shared dev auth service
     (`https://rancher.studizz.fr/studizz-auth`), so the app talks to dev auth
     out of the box. If Docker is missing the script prints manual steps instead
     of failing.
   - Drop `--up` only if the user explicitly just wants the files without
     starting containers.
   - `target-directory` defaults to `./<project-name>`.
   - It refuses to write into a non-empty directory unless you pass `--force`.
   - It substitutes the project name everywhere and marks the shell scripts and
     `bin/console` executable.

   Prefer running the script over hand-creating files — it is the source of
   truth for the layout and keeps every project identical. The same bring-up is
   available inside a generated project as `make init`.

3. **Confirm the stack is up and show the user what was generated.** When run
   with `--up`, the first build pulls images and runs `composer install` /
   `npm install`, so it takes a few minutes — let it finish, then report the
   live URLs (front `:5173`, API `:8080`, RabbitMQ UI `:15672`). A quick
   `docker compose ps` confirms the services are healthy; a good smoke test is
   `curl localhost:<BACKEND_PORT>/health` returning `{"status":"ok"}`. Don't
   dump every file's contents; summarize the tree and point at the parts
   they'll touch first.

   **Port collisions.** Developers often run several Studizz stacks at once, so
   `docker compose up` can fail with `port is already allocated` (e.g. another
   project already binds Redis 6379 or Mongo 27017). When that happens, set
   alternative host ports in the project's root `.env`
   (`FRONTEND_PORT`/`BACKEND_PORT`/`MONGO_PORT`/`RABBITMQ_PORT`/`RABBITMQ_MGMT_PORT`/`REDIS_PORT`)
   to free values and re-run `docker compose up -d`. Only the host side changes;
   container-internal ports stay standard, so no app config needs touching. The
   backend's `VITE_API_URL` already follows `BACKEND_PORT` automatically.

4. **Adapt only if asked.** The generated code is intentionally minimal and
   idiomatic — a health endpoint, one example Mongo Document + controller, one
   example worker, a fetch-based API client. If the user wants their first real
   entity/route/worker, edit the generated files in place rather than
   regenerating.

## What gets generated

Read `references/structure.md` for the full annotated tree and the rationale
behind each deployment choice (why the front is tar+scp, why workers are
systemd, why docker-compose is dev-only). Load it when the user asks "why is it
set up this way?" or wants to change a structural decision.

High-level:

```
<project>/
├── docker-compose.yml      # dev only: mongo, rabbitmq, redis, backend, frontend, worker
├── .env.example  Makefile  README.md  .gitignore
├── frontend/               # Vite + React: package.json, vite.config, src/, Dockerfile.dev
├── backend/                # Symfony skeleton + MongoDB ODM: composer.json, config/, src/, Dockerfile
├── workers/                # Python: shared/ (pika helper + config), example_worker/, requirements.txt
└── deploy/                 # deploy-front.sh, install-workers.sh, worker.service.template, README
```

## Wiring the Studizz services

The scaffold lays down the structure and env placeholders but deliberately does
**not** reimplement the shared Studizz integrations — those have their own
skills that produce idiomatic, up-to-date clients. After scaffolding, when the
user wants the real thing, hand off:

- **Authentication** (login, JWT/refresh, guards) → `studizz-auth-integration`.
  The backend ships a `STUDIZZ_AUTH_URL` env placeholder ready for it.
- **Publishing to the central AMQP HTTP gateway** (instead of, or in addition
  to, direct pika) → `studizz-api-amqp-client`.
- **Sending transactional mail / SMS** → `studizz-api-mailer-client`.

Mention these so the user knows the next step, but only invoke them when the
user actually asks to wire that capability.

## Notes

- `docker-compose.yml` is **dev-only** and never deployed — production is a bare
  VPS. Keep that boundary clear if the user asks about "deploying the compose
  file."
- The backend `composer.json` targets PHP 8.2+ (constructor property promotion,
  attribute routes). It won't lint under PHP 7.x — that's expected.
- New workers: copy `workers/example_worker/` to a new directory, set its
  `QUEUE` and `handle()`. `install-workers.sh` auto-discovers every worker
  directory and registers a systemd service for it — no script edit needed.
