#!/usr/bin/env python3
"""
Studizz project scaffolder.

Generates the standard Studizz monorepo layout:

    <project>/
      docker-compose.yml          # dev only: mongo, rabbitmq, redis, backend, frontend, worker
      .env.example
      Makefile
      frontend/                   # Vite + React (deployed by build+tar+scp)
      backend/                    # Symfony + Doctrine MongoDB ODM + Studizz auth (deployed by git)
      workers/                    # Python + pika RabbitMQ workers (deployed by git, installed by script)
      deploy/                     # deploy-front.sh + install-workers.sh + systemd unit

Usage:
    python scaffold.py <project-name> [target-directory] [--up] [--force]

`project-name` must be a slug (lowercase, digits, dashes). It is used for the
folder name, docker-compose project, and the systemd service prefix.
The target directory defaults to ./<project-name>. The script refuses to write
into a non-empty directory unless --force is passed.

With --up, after generating the files the script also sets up the dev
environment: it copies each .env.example to .env (where missing) and starts the
whole stack with `docker compose up --build -d`. If Docker isn't installed it
prints the manual steps instead. The same thing is available inside the
generated project as `make init`.
"""

import os
import re
import sys
import stat
import shutil
import subprocess

PLACEHOLDER = "__PROJECT_NAME__"

# Files that must be executable after writing.
EXECUTABLE = {
    "deploy/deploy-front.sh",
    "deploy/install-workers.sh",
    "backend/bin/console",
    "backend/docker-entrypoint.sh",
}

# -- File templates ---------------------------------------------------------
# Keys are relative paths (may contain __PROJECT_NAME__, substituted on write).
# Values are file contents (__PROJECT_NAME__ substituted on write).
# We deliberately avoid str.format() so the shell/YAML braces stay intact.

FILES = {}

FILES["README.md"] = """\
# __PROJECT_NAME__

Studizz standard stack:

| Component | Tech | Dev | Prod (OVH VPS, Ubuntu + Apache) |
|-----------|------|-----|----------------------------------|
| Frontend  | React + Vite | docker-compose / `npm run dev` | build local → `deploy/deploy-front.sh` (tar + scp to Apache docroot) |
| Backend   | Symfony + MongoDB ODM + Studizz auth | docker-compose (php-apache) | deployed by **git pull** on the server |
| Workers   | Python + pika (RabbitMQ) | docker-compose | deployed by **git pull**, installed/run via `deploy/install-workers.sh` (systemd) |
| Database  | MongoDB | docker-compose | managed on the VPS |
| Queue     | RabbitMQ | docker-compose | managed on the VPS |
| Cache     | Redis | docker-compose | managed on the VPS (optional) |

## Quick start (dev)

```bash
cp .env.example .env
# fill frontend/.env, backend/.env, workers/.env from their .env.example
make up           # or: docker compose up --build
```

- Frontend dev server: http://localhost:5173
- Backend API: http://localhost:8080
- RabbitMQ management UI: http://localhost:15672 (guest / guest)
- MongoDB: localhost:27017
- Redis: localhost:6379

## Deployment

See `deploy/README.md`. In short:

- **Frontend**: `./deploy/deploy-front.sh` builds locally and ships `dist/` to the VPS.
- **Backend**: `git pull` on the server, then `composer install` + clear cache.
- **Workers**: `git pull` on the server, then `sudo ./deploy/install-workers.sh` (creates a venv and a systemd service per worker).
"""

FILES[".gitignore"] = """\
# Node
node_modules/
frontend/dist/
frontend/.env

# PHP / Symfony
backend/vendor/
backend/.env
backend/.env.local
backend/var/

# Python
workers/.venv/
workers/.env
__pycache__/
*.pyc

# Env / misc
.env
deploy/deploy.env
*.tar.gz
.DS_Store
"""

FILES[".env.example"] = """\
# Shared docker-compose project name (keeps container names predictable)
COMPOSE_PROJECT_NAME=__PROJECT_NAME__

# These are the dev credentials used by docker-compose. Override per-service
# in frontend/.env, backend/.env and workers/.env.
MONGO_INITDB_DATABASE=__PROJECT_NAME__
RABBITMQ_DEFAULT_USER=guest
RABBITMQ_DEFAULT_PASS=guest

# Host ports exposed by docker-compose. Change these if another project (or a
# host service) already uses the default — that's how you run several Studizz
# stacks at once without collisions. Only the host side changes; the containers
# keep their standard internal ports.
FRONTEND_PORT=5173
BACKEND_PORT=8080
MONGO_PORT=27017
RABBITMQ_PORT=5672
RABBITMQ_MGMT_PORT=15672
REDIS_PORT=6379
"""

FILES["Makefile"] = """\
.PHONY: init up down build logs ps sh-backend sh-worker

init:      ## First run: copy missing .env files then start the full dev stack
\t@for d in . frontend backend workers; do \\
\t\tif [ -f "$$d/.env.example" ] && [ ! -f "$$d/.env" ]; then \\
\t\t\tcp "$$d/.env.example" "$$d/.env"; echo "created $$d/.env"; \\
\t\tfi; \\
\tdone
\tdocker compose up --build -d
\t@echo "Dev stack up — front http://localhost:5173  api http://localhost:8080  rabbit http://localhost:15672"

up:        ## Start the full dev stack
\tdocker compose up --build -d

down:      ## Stop and remove containers
\tdocker compose down

build:     ## Rebuild images
\tdocker compose build

logs:      ## Tail all logs
\tdocker compose logs -f

ps:        ## List running services
\tdocker compose ps

sh-backend: ## Shell into the backend container
\tdocker compose exec backend bash

sh-worker:  ## Shell into the worker container
\tdocker compose exec worker bash
"""

FILES["docker-compose.yml"] = """\
# Dev-only stack. Production runs on OVH VPS (Apache + git deploy); this file is
# never deployed. It exists so the whole system boots locally with one command.
services:
  mongodb:
    image: mongo:7
    restart: unless-stopped
    environment:
      MONGO_INITDB_DATABASE: ${MONGO_INITDB_DATABASE:-__PROJECT_NAME__}
    ports:
      - "${MONGO_PORT:-27017}:27017"
    volumes:
      - mongo_data:/data/db

  rabbitmq:
    image: rabbitmq:3-management
    restart: unless-stopped
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_DEFAULT_USER:-guest}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_DEFAULT_PASS:-guest}
    ports:
      - "${RABBITMQ_PORT:-5672}:5672"          # AMQP
      - "${RABBITMQ_MGMT_PORT:-15672}:15672"   # management UI

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    ports:
      - "${REDIS_PORT:-6379}:6379"

  backend:
    build: ./backend
    restart: unless-stopped
    depends_on:
      - mongodb
      - rabbitmq
      - redis
    environment:
      MONGODB_URL: "mongodb://mongodb:27017"
      MONGODB_DB: "__PROJECT_NAME__"
      RABBITMQ_HOST: "rabbitmq"
      REDIS_URL: "redis://redis:6379"
      STUDIZZ_AUTH_URL: "https://rancher.studizz.fr/studizz-auth"
    ports:
      - "${BACKEND_PORT:-8080}:80"
    volumes:
      - ./backend:/var/www/html

  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile.dev
    restart: unless-stopped
    depends_on:
      - backend
    environment:
      # Must point at the host-side backend port (the browser hits localhost).
      VITE_API_URL: "http://localhost:${BACKEND_PORT:-8080}"
    ports:
      - "${FRONTEND_PORT:-5173}:5173"
    volumes:
      - ./frontend:/app
      - /app/node_modules

  worker:
    build: ./workers
    restart: unless-stopped
    depends_on:
      - rabbitmq
      - mongodb
      - redis
    environment:
      PYTHONUNBUFFERED: "1"
      RABBITMQ_HOST: "rabbitmq"
      MONGODB_URL: "mongodb://mongodb:27017"
      MONGODB_DB: "__PROJECT_NAME__"
      REDIS_URL: "redis://redis:6379"
    volumes:
      - ./workers:/app

volumes:
  mongo_data:
"""

# -- Frontend (Vite + React) ------------------------------------------------

FILES["frontend/package.json"] = """\
{
  "name": "__PROJECT_NAME__-frontend",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "vite --host",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "react": "^18.3.1",
    "react-dom": "^18.3.1"
  },
  "devDependencies": {
    "@vitejs/plugin-react": "^4.3.1",
    "vite": "^5.4.0"
  }
}
"""

FILES["frontend/vite.config.js"] = """\
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
  },
})
"""

FILES["frontend/index.html"] = """\
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>__PROJECT_NAME__</title>
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.jsx"></script>
  </body>
</html>
"""

FILES["frontend/.env.example"] = """\
# Vite exposes only variables prefixed with VITE_ to the browser bundle.
VITE_API_URL=http://localhost:8080
"""

FILES["frontend/.dockerignore"] = """\
node_modules
dist
.env
"""

FILES["frontend/Dockerfile.dev"] = """\
# Dev image only. Production is a static build shipped by deploy/deploy-front.sh.
FROM node:20-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
EXPOSE 5173
CMD ["npm", "run", "dev"]
"""

FILES["frontend/src/main.jsx"] = """\
import React from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.jsx'

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
"""

FILES["frontend/src/App.jsx"] = """\
import { useEffect, useState } from 'react'
import { api } from './api/client.js'

export default function App() {
  const [health, setHealth] = useState('…')

  useEffect(() => {
    api.get('/health')
      .then((data) => setHealth(data.status ?? 'ok'))
      .catch(() => setHealth('unreachable'))
  }, [])

  return (
    <main style={{ fontFamily: 'system-ui', padding: '2rem' }}>
      <h1>__PROJECT_NAME__</h1>
      <p>Backend health: <strong>{health}</strong></p>
    </main>
  )
}
"""

FILES["frontend/src/api/client.js"] = """\
// Minimal fetch wrapper around the Symfony backend.
// Base URL comes from Vite env (VITE_API_URL), defaulting to the dev port.
const BASE_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8080').replace(/\\/$/, '')

async function request(method, path, body) {
  const res = await fetch(BASE_URL + path, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body == null ? undefined : JSON.stringify(body),
  })
  if (!res.ok) throw new Error(`${method} ${path} -> ${res.status}`)
  const text = await res.text()
  return text ? JSON.parse(text) : null
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, body),
  put: (path, body) => request('PUT', path, body),
  del: (path) => request('DELETE', path),
}
"""

# -- Backend (Symfony + MongoDB ODM) ----------------------------------------

FILES["backend/composer.json"] = """\
{
    "name": "studizz/__PROJECT_NAME__-backend",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=8.2",
        "ext-ctype": "*",
        "ext-iconv": "*",
        "ext-mongodb": "*",
        "doctrine/mongodb-odm-bundle": "^5.0",
        "nelmio/cors-bundle": "^2.5",
        "symfony/console": "^7.0",
        "symfony/dotenv": "^7.0",
        "symfony/framework-bundle": "^7.0",
        "symfony/http-client": "^7.0",
        "symfony/runtime": "^7.0",
        "symfony/yaml": "^7.0"
    },
    "config": {
        "allow-plugins": {
            "symfony/runtime": true,
            "symfony/flex": true
        }
    },
    "autoload": {
        "psr-4": {
            "App\\\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\\\Tests\\\\": "tests/"
        }
    }
}
"""

FILES["backend/Dockerfile"] = """\
# Mirrors prod: Ubuntu-ish PHP + Apache. In prod the code arrives by git pull;
# here we just provide the runtime so the API boots in dev.
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \\
        git unzip libssl-dev pkg-config \\
    && pecl install mongodb redis \\
    && docker-php-ext-enable mongodb redis \\
    && a2enmod rewrite \\
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \\
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
EXPOSE 80
# The entrypoint runs `composer install` if vendor/ is missing, then starts
# Apache. This makes the dev bind-mount (which shadows any image-baked vendor)
# self-healing: first boot installs into the mounted dir and it persists.
ENTRYPOINT ["docker-entrypoint.sh"]
"""

FILES["backend/docker-entrypoint.sh"] = """\
#!/bin/sh
set -e

# Install PHP deps on first boot (the bind mount has no vendor/ yet).
if [ ! -f vendor/autoload_runtime.php ]; then
    echo "[entrypoint] vendor/ missing — running composer install…"
    composer install --no-interaction --no-progress
fi

# Symfony writes cache/logs under var/. The bind mount is owned by the host
# user, so ensure the Apache user (www-data) can write here.
mkdir -p var
chown -R www-data:www-data var

exec apache2-foreground
"""

FILES["backend/.env.example"] = """\
APP_ENV=dev
APP_SECRET=change-me-in-prod

# CORS — regex of allowed browser origins. Dev default = any localhost port
# (the Vite front runs on FRONTEND_PORT). In prod, set this to your real front
# domain(s), e.g. ^https://(www\\.)?monsite\\.fr$
CORS_ALLOW_ORIGIN='^https?://(localhost|127\\.0\\.0\\.1)(:[0-9]+)?$'

# MongoDB
MONGODB_URL=mongodb://mongodb:27017
MONGODB_DB=__PROJECT_NAME__

# RabbitMQ (publishing async tasks consumed by the Python workers)
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASS=guest

# Redis (optional)
REDIS_URL=redis://redis:6379

# Studizz auth — defaults to the shared DEV auth service. Point it at the prod
# auth URL in the server's .env. Use the studizz-auth-integration skill to wire
# the actual client/guard.
STUDIZZ_AUTH_URL=https://rancher.studizz.fr/studizz-auth
"""

FILES["backend/public/index.php"] = """\
<?php

use App\\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
"""

FILES["backend/public/.htaccess"] = """\
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]
</IfModule>
"""

FILES["backend/src/Kernel.php"] = """\
<?php

namespace App;

use Symfony\\Bundle\\FrameworkBundle\\Kernel\\MicroKernelTrait;
use Symfony\\Component\\HttpKernel\\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
"""

FILES["backend/config/bundles.php"] = """\
<?php

return [
    Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle::class => ['all' => true],
    Doctrine\\Bundle\\MongoDBBundle\\DoctrineMongoDBBundle::class => ['all' => true],
    Nelmio\\CorsBundle\\NelmioCorsBundle::class => ['all' => true],
];
"""

FILES["backend/config/services.yaml"] = """\
parameters:

services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\\:
        resource: '../src/'
        exclude:
            - '../src/Kernel.php'
"""

FILES["backend/config/packages/framework.yaml"] = """\
framework:
    secret: '%env(APP_SECRET)%'
    http_method_override: false
    handle_all_throwables: true
    php_errors:
        log: true
"""

FILES["backend/config/packages/doctrine_mongodb.yaml"] = """\
doctrine_mongodb:
    connections:
        default:
            server: '%env(MONGODB_URL)%'
            options: {}
    default_database: '%env(MONGODB_DB)%'
    document_managers:
        default:
            auto_mapping: true
            mappings:
                App:
                    type: attribute
                    dir: '%kernel.project_dir%/src/Document'
                    prefix: 'App\\Document'
                    is_bundle: false
"""

FILES["backend/config/packages/nelmio_cors.yaml"] = """\
nelmio_cors:
    defaults:
        origin_regex: true
        # Allowed origins come from CORS_ALLOW_ORIGIN (a regex). In dev it matches
        # any localhost port so the Vite front can call the API whatever
        # FRONTEND_PORT is. Set it to the real front domain(s) in prod.
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization']
        expose_headers: ['Link']
        max_age: 3600
    paths:
        '^/': ~
"""

FILES["backend/config/routes.yaml"] = """\
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\\Controller
    type: attribute
"""

FILES["backend/src/Document/Example.php"] = """\
<?php

namespace App\\Document;

use Doctrine\\ODM\\MongoDB\\Mapping\\Annotations as ODM;

#[ODM\\Document(collection: 'examples')]
class Example
{
    #[ODM\\Id]
    private ?string $id = null;

    #[ODM\\Field(type: 'string')]
    private string $name = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
"""

FILES["backend/src/Controller/HealthController.php"] = """\
<?php

namespace App\\Controller;

use Symfony\\Component\\HttpFoundation\\JsonResponse;
use Symfony\\Component\\Routing\\Attribute\\Route;

class HealthController
{
    #[Route('/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
"""

FILES["backend/src/Controller/ExampleController.php"] = """\
<?php

namespace App\\Controller;

use App\\Document\\Example;
use Doctrine\\ODM\\MongoDB\\DocumentManager;
use Symfony\\Component\\HttpFoundation\\JsonResponse;
use Symfony\\Component\\HttpFoundation\\Request;
use Symfony\\Component\\Routing\\Attribute\\Route;

class ExampleController
{
    public function __construct(private DocumentManager $dm)
    {
    }

    #[Route('/examples', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->dm->getRepository(Example::class)->findAll();
        $data = array_map(static fn (Example $e) => [
            'id' => $e->getId(),
            'name' => $e->getName(),
        ], $items);

        return new JsonResponse($data);
    }

    #[Route('/examples', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $example = (new Example())->setName($payload['name'] ?? '');
        $this->dm->persist($example);
        $this->dm->flush();

        return new JsonResponse(['id' => $example->getId()], 201);
    }
}
"""

FILES["backend/bin/console"] = """\
#!/usr/bin/env php
<?php

use App\\Kernel;
use Symfony\\Bundle\\FrameworkBundle\\Console\\Application;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    return new Application($kernel);
};
"""

# -- Workers (Python + pika) ------------------------------------------------

FILES["workers/requirements.txt"] = """\
pika==1.3.2
pymongo==4.8.0
redis==5.0.8
python-dotenv==1.0.1
"""

FILES["workers/.env.example"] = """\
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASS=guest

MONGODB_URL=mongodb://mongodb:27017
MONGODB_DB=__PROJECT_NAME__

REDIS_URL=redis://redis:6379
"""

FILES["workers/Dockerfile"] = """\
FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY . .
# Dev default: run the example worker. In prod, systemd runs one service per worker.
CMD ["python", "-m", "example_worker.worker"]
"""

FILES["workers/shared/__init__.py"] = ""

FILES["workers/shared/config.py"] = """\
\"\"\"Shared configuration loaded from environment variables.\"\"\"
import os

from dotenv import load_dotenv

load_dotenv()

RABBITMQ_HOST = os.getenv("RABBITMQ_HOST", "localhost")
RABBITMQ_PORT = int(os.getenv("RABBITMQ_PORT", "5672"))
RABBITMQ_USER = os.getenv("RABBITMQ_USER", "guest")
RABBITMQ_PASS = os.getenv("RABBITMQ_PASS", "guest")

MONGODB_URL = os.getenv("MONGODB_URL", "mongodb://localhost:27017")
MONGODB_DB = os.getenv("MONGODB_DB", "__PROJECT_NAME__")

REDIS_URL = os.getenv("REDIS_URL", "redis://localhost:6379")
"""

FILES["workers/shared/rabbitmq.py"] = """\
\"\"\"Tiny pika helper: blocking connection + a consume loop with auto-reconnect.\"\"\"
import json
import time

import pika

from . import config


def _connection() -> pika.BlockingConnection:
    credentials = pika.PlainCredentials(config.RABBITMQ_USER, config.RABBITMQ_PASS)
    params = pika.ConnectionParameters(
        host=config.RABBITMQ_HOST,
        port=config.RABBITMQ_PORT,
        credentials=credentials,
        heartbeat=60,
        blocked_connection_timeout=300,
    )
    return pika.BlockingConnection(params)


def consume(queue: str, handler):
    \"\"\"Consume `queue` forever, decoding each message body as JSON and passing
    it to `handler(payload)`. Reconnects on connection loss. Acks only after the
    handler returns without raising, so a crash re-delivers the message.\"\"\"
    while True:
        try:
            conn = _connection()
            channel = conn.channel()
            channel.queue_declare(queue=queue, durable=True)
            channel.basic_qos(prefetch_count=1)

            def _on_message(ch, method, _props, body):
                try:
                    payload = json.loads(body)
                except json.JSONDecodeError:
                    payload = body.decode("utf-8", "replace")
                handler(payload)
                ch.basic_ack(delivery_tag=method.delivery_tag)

            channel.basic_consume(queue=queue, on_message_callback=_on_message)
            print(f"[*] Waiting for messages on '{queue}'. CTRL+C to exit.")
            channel.start_consuming()
        except pika.exceptions.AMQPConnectionError:
            print("[!] RabbitMQ connection lost, retrying in 5s…")
            time.sleep(5)
        except KeyboardInterrupt:
            print("Bye.")
            return


def publish(queue: str, payload: dict):
    \"\"\"Publish a single durable JSON message to `queue`.\"\"\"
    conn = _connection()
    channel = conn.channel()
    channel.queue_declare(queue=queue, durable=True)
    channel.basic_publish(
        exchange="",
        routing_key=queue,
        body=json.dumps(payload),
        properties=pika.BasicProperties(delivery_mode=2),
    )
    conn.close()
"""

FILES["workers/example_worker/__init__.py"] = ""

FILES["workers/example_worker/worker.py"] = """\
\"\"\"Example worker. Copy this directory to add a new worker; the install script
discovers each worker directory and registers one systemd service per worker.\"\"\"
from shared.rabbitmq import consume

QUEUE = "__PROJECT_NAME__.example"


def handle(payload):
    print(f"[example_worker] received: {payload!r}")
    # TODO: do real work here (write to Mongo, call an API, etc.)


def main():
    consume(QUEUE, handle)


if __name__ == "__main__":
    main()
"""

FILES["workers/README.md"] = """\
# Workers

Each subdirectory (other than `shared/`) is one worker, runnable as a module:

```bash
python -m example_worker.worker
```

`shared/` holds the RabbitMQ helper and config. To add a worker, copy
`example_worker/`, rename it, set its `QUEUE`, and implement `handle()`.

In production the workers are deployed by `git pull` and installed with
`sudo ../deploy/install-workers.sh`, which creates a venv and a systemd
service per worker (`__PROJECT_NAME__-worker@<dir>`).
"""

# -- Deploy -----------------------------------------------------------------

FILES["deploy/README.md"] = """\
# Deployment

Production = OVH VPS (Ubuntu + Apache + PHP). No Docker in prod.

## Frontend — `deploy-front.sh`

Builds the Vite app locally and ships the static `dist/` to the Apache docroot
via tar + scp. Configure `deploy/deploy.env` first (copy `deploy.env.example`).

```bash
cp deploy/deploy.env.example deploy/deploy.env   # then edit
./deploy/deploy-front.sh
```

It keeps a timestamped backup of the previous docroot on the server so you can
roll back.

## Backend — git

The Symfony backend is deployed by pulling the repo on the server:

```bash
ssh user@vps
cd /var/www/__PROJECT_NAME__   # the repo checkout
git pull
cd backend
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
```

(Point the Apache vhost DocumentRoot at `backend/public`.)

## Workers — git + `install-workers.sh`

The workers are deployed by `git pull` as well, then installed as systemd
services:

```bash
ssh user@vps
cd /var/www/__PROJECT_NAME__
git pull
sudo ./deploy/install-workers.sh
```

`install-workers.sh` creates `workers/.venv`, installs `requirements.txt`, and
registers one systemd service per worker directory. Manage them with:

```bash
systemctl status '__PROJECT_NAME__-worker@example_worker'
journalctl -u '__PROJECT_NAME__-worker@example_worker' -f
sudo systemctl restart '__PROJECT_NAME__-worker@example_worker'
```
"""

FILES["deploy/deploy.env.example"] = """\
# Copy to deploy/deploy.env and fill in. Never commit deploy.env.
SSH_HOST=vps.example.ovh
SSH_USER=ubuntu
SSH_PORT=22
# Absolute path of the Apache docroot that serves the frontend on the VPS.
REMOTE_DOCROOT=/var/www/__PROJECT_NAME__-front
"""

FILES["deploy/deploy-front.sh"] = """\
#!/usr/bin/env bash
# Build the Vite frontend locally and ship it to the OVH VPS Apache docroot
# via tar + scp. Keeps a timestamped backup of the previous docroot.
#
# All LOCAL paths are relative to the project root, which is resolved from this
# script's own location at runtime — so you can move/rename the project folder
# anywhere and this keeps working with no edits. The only absolute path is
# REMOTE_DOCROOT, which is the docroot ON THE VPS and necessarily server-side.
set -euo pipefail

# Move to the project root (the parent of deploy/), wherever the repo lives.
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# shellcheck disable=SC1091
if [[ -f deploy/deploy.env ]]; then
    source deploy/deploy.env
else
    echo "Missing deploy/deploy.env (copy deploy/deploy.env.example and fill it in)." >&2
    exit 1
fi

: "${SSH_HOST:?}" "${SSH_USER:?}" "${REMOTE_DOCROOT:?}"
SSH_PORT="${SSH_PORT:-22}"
STAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE="dist-__PROJECT_NAME__-${STAMP}.tar.gz"   # written in the project root, git-ignored

echo "==> Building frontend"
( cd frontend && npm ci && npm run build )

echo "==> Packing frontend/dist/"
tar -czf "$ARCHIVE" -C frontend/dist .

echo "==> Uploading to ${SSH_USER}@${SSH_HOST}:${REMOTE_DOCROOT}"
scp -P "$SSH_PORT" "$ARCHIVE" "${SSH_USER}@${SSH_HOST}:/tmp/"
REMOTE_ARCHIVE="/tmp/${ARCHIVE}"

ssh -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" bash -se <<EOF
set -euo pipefail
DOCROOT="${REMOTE_DOCROOT}"
if [[ -d "\\$DOCROOT" ]]; then
    sudo mv "\\$DOCROOT" "\\${DOCROOT}.bak-${STAMP}"
fi
sudo mkdir -p "\\$DOCROOT"
sudo tar -xzf "${REMOTE_ARCHIVE}" -C "\\$DOCROOT"
sudo chown -R www-data:www-data "\\$DOCROOT"
rm -f "${REMOTE_ARCHIVE}"
echo "Deployed to \\$DOCROOT (backup: \\${DOCROOT}.bak-${STAMP})"
EOF

rm -f "$ARCHIVE"
echo "==> Frontend deployed."
"""

FILES["deploy/install-workers.sh"] = """\
#!/usr/bin/env bash
# Install/refresh the Python workers as systemd services on the VPS.
# Run as root (sudo) after `git pull`; it can be invoked from anywhere.
#   sudo ./deploy/install-workers.sh
#
# Local paths are relative to the project root, resolved from this script's own
# location, so the project folder can be moved/renamed freely. systemd units
# require ABSOLUTE paths, so the unit is (re)generated from the CURRENT location
# every time you run this script — migrating the project = just re-run it.
set -euo pipefail

# Move to the project root (the parent of deploy/), wherever the repo lives.
cd "$(dirname "${BASH_SOURCE[0]}")/.."
PROJECT="__PROJECT_NAME__"
RUN_USER="${SUDO_USER:-$(whoami)}"
PYTHON="${PYTHON:-python3}"

if [[ "$(id -u)" -ne 0 ]]; then
    echo "Please run as root (sudo)." >&2
    exit 1
fi

echo "==> Creating/refreshing virtualenv"
"$PYTHON" -m venv workers/.venv
workers/.venv/bin/pip install --upgrade pip
workers/.venv/bin/pip install -r workers/requirements.txt

# systemd has no working directory context, so the unit needs the absolute path.
# Resolve it from wherever the project currently sits.
APP_DIR="$(pwd)"

echo "==> Installing systemd template unit"
UNIT_PATH="/etc/systemd/system/${PROJECT}-worker@.service"
sed -e "s#__APP_DIR__#${APP_DIR}#g" \\
    -e "s#__RUN_USER__#${RUN_USER}#g" \\
    deploy/worker.service.template > "$UNIT_PATH"

systemctl daemon-reload

echo "==> Enabling a service per worker"
for dir in workers/*/; do
    name="$(basename "$dir")"
    [[ "$name" == "shared" || "$name" == ".venv" ]] && continue
    [[ -f "${dir}worker.py" ]] || continue
    echo "    - ${PROJECT}-worker@${name}"
    systemctl enable --now "${PROJECT}-worker@${name}"
    systemctl restart "${PROJECT}-worker@${name}"
done

echo "==> Done. Check with: systemctl list-units '${PROJECT}-worker@*'"
"""

FILES["deploy/worker.service.template"] = """\
[Unit]
Description=Studizz __PROJECT_NAME__ worker (%i)
After=network.target

[Service]
Type=simple
User=__RUN_USER__
WorkingDirectory=__APP_DIR__/workers
EnvironmentFile=__APP_DIR__/workers/.env
ExecStart=__APP_DIR__/workers/.venv/bin/python -m %i.worker
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
"""


def slugify_ok(name: str) -> bool:
    return bool(re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", name))


# Directories whose .env.example becomes a real .env on `--up` / `make init`.
# deploy/deploy.env is intentionally excluded — it holds prod SSH/server values.
ENV_DIRS = ("", "frontend", "backend", "workers")


def copy_envs(target: str) -> None:
    """Copy each <dir>/.env.example to <dir>/.env when the .env is missing."""
    for d in ENV_DIRS:
        example = os.path.join(target, d, ".env.example")
        env = os.path.join(target, d, ".env")
        if os.path.isfile(example) and not os.path.isfile(env):
            shutil.copyfile(example, env)
            print(f"  created {os.path.join(d, '.env') if d else '.env'}")


def read_env(target: str) -> dict:
    """Parse the root .env into a dict (best-effort, KEY=VALUE lines)."""
    values = {}
    path = os.path.join(target, ".env")
    if not os.path.isfile(path):
        return values
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, val = line.partition("=")
            values[key.strip()] = val.strip()
    return values


def docker_compose_cmd():
    """Return the available compose invocation, or None if Docker is absent."""
    if shutil.which("docker") and subprocess.run(
        ["docker", "compose", "version"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    ).returncode == 0:
        return ["docker", "compose"]
    if shutil.which("docker-compose"):
        return ["docker-compose"]
    return None


def bring_up(target: str) -> int:
    """Copy envs and start the dev stack. Returns a process-style exit code."""
    print("==> Preparing dev environment")
    copy_envs(target)

    compose = docker_compose_cmd()
    if compose is None:
        print("  Docker not found — skipping container start.", file=sys.stderr)
        print("  Install Docker, then run `make up` (or `docker compose up --build -d`).",
              file=sys.stderr)
        return 0  # scaffolding itself succeeded; the bring-up is best-effort

    print("==> Starting containers (this builds images on first run, be patient)")
    result = subprocess.run(compose + ["up", "--build", "-d"], cwd=target)
    if result.returncode != 0:
        print("  docker compose failed — see the output above.", file=sys.stderr)
        return result.returncode

    env = read_env(target)
    front = env.get("FRONTEND_PORT", "5173")
    back = env.get("BACKEND_PORT", "8080")
    mgmt = env.get("RABBITMQ_MGMT_PORT", "15672")
    print("==> Dev stack up:")
    print(f"    frontend  http://localhost:{front}")
    print(f"    backend   http://localhost:{back}")
    print(f"    rabbitmq  http://localhost:{mgmt}  (guest/guest)")
    return 0


def main(argv):
    force = "--force" in argv
    up = "--up" in argv
    argv = [a for a in argv if a not in ("--force", "--up")]

    if len(argv) < 1:
        print(__doc__)
        return 1

    project = argv[0]
    if not slugify_ok(project):
        print(f"Invalid project name '{project}'. Use lowercase letters, digits and dashes "
              "(e.g. 'mon-projet').", file=sys.stderr)
        return 1

    target = argv[1] if len(argv) > 1 else project
    target = os.path.abspath(target)

    if os.path.isdir(target) and os.listdir(target) and not force:
        print(f"Target directory '{target}' exists and is not empty. "
              "Use --force to write into it anyway.", file=sys.stderr)
        return 1

    for rel_path, content in FILES.items():
        out_rel = rel_path.replace(PLACEHOLDER, project)
        out_path = os.path.join(target, out_rel)
        os.makedirs(os.path.dirname(out_path), exist_ok=True)
        with open(out_path, "w", encoding="utf-8") as fh:
            fh.write(content.replace(PLACEHOLDER, project))
        if rel_path in EXECUTABLE:
            st = os.stat(out_path)
            os.chmod(out_path, st.st_mode | stat.S_IEXEC | stat.S_IXGRP | stat.S_IXOTH)

    print(f"Scaffolded Studizz project '{project}' into {target}")

    if up:
        return bring_up(target)

    print("Next steps:")
    print(f"  cd {target}")
    print("  make init      # copy .env files and start the dev stack")
    print("  # …or do it by hand:")
    print("  cp .env.example .env && cp frontend/.env.example frontend/.env \\")
    print("    && cp backend/.env.example backend/.env && cp workers/.env.example workers/.env")
    print("  make up        # or: docker compose up --build -d")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
