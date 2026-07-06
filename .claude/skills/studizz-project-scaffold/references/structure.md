# Studizz project structure — annotated reference

This is the canonical layout the scaffolder produces and the reasoning behind
each choice. Read it when the user wants to understand or change a structural
decision.

## Full tree

```
<project>/
├── docker-compose.yml          # DEV ONLY — boots the whole system locally
├── Makefile                    # make up / down / logs / sh-backend / sh-worker
├── .env.example                # compose-level vars (project name, mongo db, rabbit creds)
├── .gitignore
├── README.md
│
├── frontend/                   # React + Vite — deployed by BUILD + TAR + SCP
│   ├── package.json
│   ├── vite.config.js
│   ├── index.html
│   ├── .env.example            # VITE_API_URL (only VITE_* reaches the browser)
│   ├── Dockerfile.dev          # dev server image; prod ships static dist/
│   └── src/
│       ├── main.jsx
│       ├── App.jsx             # calls /health to prove the backend link
│       └── api/client.js       # tiny fetch wrapper, base URL from VITE_API_URL
│
├── backend/                    # Symfony + MongoDB ODM — deployed by GIT
│   ├── composer.json           # PHP 8.2+, doctrine/mongodb-odm-bundle, http-client
│   ├── Dockerfile              # php:8.2-apache + mongodb/redis ext (dev runtime)
│   ├── .env.example            # MONGODB_URL/DB, RABBITMQ_*, REDIS_URL, STUDIZZ_AUTH_URL
│   ├── bin/console
│   ├── public/
│   │   ├── index.php
│   │   └── .htaccess
│   ├── config/
│   │   ├── bundles.php
│   │   ├── services.yaml
│   │   ├── routes.yaml         # attribute routing on src/Controller
│   │   └── packages/
│   │       ├── framework.yaml
│   │       ├── nelmio_cors.yaml         # CORS, origins from CORS_ALLOW_ORIGIN
│   │       └── doctrine_mongodb.yaml   # connection + auto-mapped src/Document
│   └── src/
│       ├── Kernel.php
│       ├── Document/Example.php        # example ODM document
│       └── Controller/
│           ├── HealthController.php     # GET /health -> {status: ok}
│           └── ExampleController.php    # GET/POST /examples (Mongo CRUD sample)
│
├── workers/                    # Python + pika — deployed by GIT, installed by SCRIPT
│   ├── requirements.txt        # pika, pymongo, redis, python-dotenv
│   ├── .env.example
│   ├── Dockerfile              # dev image; prod uses a venv + systemd
│   ├── README.md
│   ├── shared/
│   │   ├── config.py           # env loading (rabbit, mongo, redis)
│   │   └── rabbitmq.py         # consume()/publish() helpers with auto-reconnect
│   └── example_worker/
│       ├── __init__.py
│       └── worker.py           # consumes <project>.example queue
│
└── deploy/
    ├── README.md
    ├── deploy.env.example      # SSH_HOST/USER/PORT, REMOTE_DOCROOT
    ├── deploy-front.sh         # build + tar + scp + extract on VPS (with backup)
    ├── install-workers.sh      # venv + one systemd service per worker
    └── worker.service.template # systemd template unit (instanced with %i)
```

## Why each deployment choice

### docker-compose is dev-only
Production is a plain OVH VPS (Ubuntu + Apache + PHP), not containers. The
compose file exists so a developer gets the whole system — frontend, backend,
Mongo, RabbitMQ, Redis, a worker — with one `make up`. It is never copied to a
server. Keeping this boundary explicit avoids the trap of "deploying the compose
file."

### Frontend → build + tar + scp
Vite produces a static `dist/`. The simplest robust prod story is: build on the
developer machine, pack `dist/` into a tarball, scp it to the VPS, and extract
it into the Apache docroot. `deploy-front.sh` also moves the previous docroot to
a timestamped `.bak-<stamp>` so a rollback is `mv` away. No Node is required on
the server.

### Backend → git
The Symfony app is deployed by pulling the repo on the server, then
`composer install --no-dev` + `cache:clear`. The Apache vhost DocumentRoot
points at `backend/public`. This matches the existing Studizz workflow where the
PHP servers are updated by git.

### Workers → git + systemd
Workers are pulled by git like the backend, but they are long-running processes,
so they need a process manager. `install-workers.sh`:

1. creates/refreshes `workers/.venv` and installs `requirements.txt`,
2. renders `worker.service.template` into
   `/etc/systemd/system/<project>-worker@.service` (a single instanced template
   unit), substituting the app dir and run user,
3. auto-discovers each worker directory (anything under `workers/` that isn't
   `shared/` or `.venv` and has a `worker.py`) and runs
   `systemctl enable --now <project>-worker@<dir>`.

So adding a worker is just copying `example_worker/` and re-running the script —
no unit files to hand-edit. Manage workers with the usual
`systemctl` / `journalctl -u '<project>-worker@<name>'`.

## Async messaging model

The backend publishes to RabbitMQ and the Python workers consume directly via
pika (`workers/shared/rabbitmq.py` provides `consume(queue, handler)` and
`publish(queue, payload)` with JSON bodies, durable queues, prefetch=1, and
ack-after-success so a crash re-delivers). Queue names are namespaced with the
project slug (e.g. `<project>.example`) to avoid collisions on a shared broker.

If the project should instead go through the central `studizz-api-amqp` HTTP
gateway, use the `studizz-api-amqp-client` skill to add the publisher — the
worker side can stay on pika.

## Conventions baked in

- **Project slug everywhere**: folder, `COMPOSE_PROJECT_NAME`, Mongo db, queue
  prefix, systemd prefix. One name to rule them all keeps ops predictable.
- **`.env.example` per component**, real `.env` git-ignored. Dev values point at
  docker-compose service hostnames (`mongodb`, `rabbitmq`, `redis`).
- **Minimal but real sample code** in each tier (health check, one CRUD
  document, one worker) so the system demonstrably works end-to-end before any
  business code is written.
- **CORS is wired from the start** via `nelmio/cors-bundle`. The allowed origins
  are a regex in `CORS_ALLOW_ORIGIN`; the dev default matches any `localhost`
  port (so the Vite front works whatever `FRONTEND_PORT` is), and you point it at
  the real front domain(s) in the server's prod `.env`. No need to add CORS
  later — the browser front can call the API out of the box.
