---
name: studizz-api-amqp-client
description: |
  Use this skill when the user wants to send messages to the Studizz AMQP HTTP gateway from any client app — anything that POSTs to `/api/task` or `/api/call` on `studizz-api-amqp` so RabbitMQ picks it up downstream.

  Trigger on intents like: publish/enqueue/push/send a task, call, async job, async webhook, or background work to studizz-api-amqp, the studizz queue, the studizz amqp/rest gateway, the central rabbitmq pipeline, exchange `async-tasks` or `async-call`. Trigger when adding a `publishTask`/`publishCall`/`publish_call` helper or async client, or replacing `Bus::dispatch`, Symfony Messenger, Celery, or local queues with this gateway. Trigger on ANY stack (PHP/Symfony/Laravel, Python/FastAPI/Flask, Node/Express, Next.js, React, Go) and any HTTP client (Guzzle, fetch, axios, httpx, requests).

  Do NOT trigger for work inside the `studizz-api-amqp` repo itself, raw AMQP libraries bypassing the HTTP gateway (php-amqplib, pika, amqplib), RabbitMQ infra/ops, or unrelated Studizz services.
---

# Studizz AMQP REST API — Client Integration

## What this API is

`studizz-api-amqp` is a lightweight Symfony REST gateway that turns HTTP calls into RabbitMQ messages. Client projects never speak AMQP directly — they `POST` a JSON payload to one of the two endpoints and the gateway publishes it to the right exchange.

- `POST /api/task` → exchange `async-tasks` (asynchronous background tasks)
- `POST /api/call` → exchange `async-call` (asynchronous external calls / webhooks / notifications)

There is **no authentication**. The API is assumed to live on an internal / trusted network; client code should never embed credentials or tokens for it.

## When to trigger this skill

Trigger whenever the user wants to **send a message to the Studizz AMQP stack from a client app**. Concrete cues:

- Mentions of `studizz-api-amqp`, "AMQP API", "Studizz queue", "RabbitMQ Studizz"
- Asks to call `POST /api/task` or `POST /api/call`
- Wants to "publish a task", "enqueue a call", "push an async job", "send to async-tasks", "send to async-call"
- Is starting a PHP, Python, or ReactJS/Node project that needs to talk to this gateway
- Has a snippet hitting `/api/task` or `/api/call` and needs it finished or cleaned up

Do NOT trigger for:
- Work **inside** the `studizz-api-amqp` repo itself (controllers, producers, Symfony config) — that is server-side development, not client integration.
- Generic RabbitMQ / AMQP client code where the HTTP gateway is not involved (e.g. using `php-amqplib`, `pika`, `amqplib` directly).
- The Studizz authentication API (`studizz-auth`) — that is a separate system with its own skill.

## How to use this skill

1. **Read `references/api-spec.md` first.** It contains the canonical contract (endpoints, payload rules, responses, status codes). Everything else derives from it — if anything in a language-specific reference seems to contradict the spec, the spec wins.

2. **Detect the target stack** before generating code. Look at the project's manifest files:
   - `composer.json` → PHP (read `references/php.md`)
   - `requirements.txt`, `pyproject.toml`, `Pipfile`, `setup.py` → Python (read `references/python.md`)
   - `package.json` → JavaScript / TypeScript (read `references/reactjs.md`). Inspect `dependencies` to distinguish plain Node, React, Next.js, etc. — the reference covers both browser and Node usage.

   If the user explicitly names a language, follow their choice even if the manifest suggests another. If the project is empty or the language is ambiguous, ask.

3. **Read only the matching language reference.** The three files are independent — do not load all of them. This keeps context lean and the generated code idiomatic to the chosen stack.

4. **Match the existing project style.** Before writing new code, skim the repo to answer:
   - Which HTTP client is already in use? (Guzzle vs Symfony HttpClient, `requests` vs `httpx`, `fetch` vs `axios`, etc.) Reuse it rather than adding a new dependency.
   - How are secrets / URLs configured? (`.env` + `dotenv`, Symfony parameters, `process.env`, Vite `import.meta.env`, Next.js public/server env split…) Follow the same convention for the base URL.
   - Is there a "services" / "clients" / "api" layer already? Put the new client there rather than dropping it at the root.

5. **Use a placeholder for the base URL.** The user has not pinned a URL — default to an environment variable named `STUDIZZ_API_AMQP_URL` (this is the canonical name across Studizz projects; the framework-idiomatic prefix is added on top in browser-bundled stacks — see each language reference). Never hardcode an IP or hostname. In the client, strip a trailing slash defensively so both `https://host` and `https://host/` work.

6. **Keep the generated client small.** This API has two endpoints and no auth. A good client is ~30–80 lines: a tiny class / module with two methods (`publishTask(payload)`, `publishCall(payload)`) and a shared internal `post(path, payload)` helper. Resist the urge to add retry policies, circuit breakers, or generic request builders unless the user asks — the gateway is internal and simple, and over-engineering just obscures the call site.

## Payload shape

Both endpoints accept **any valid JSON object**. The gateway does not validate the schema — it forwards the raw body to RabbitMQ. That means:

- The shape is defined by **the downstream consumer**, not by this API. Ask the user what the consumer expects (fields, types) if they have not said — do not invent a schema.
- An empty body or `null` / `{}` yields HTTP 400 `{"code":400,"message":"bad parameters"}`. Reject empty payloads client-side with a clear error rather than letting the call fail at the gateway.
- `Content-Type: application/json` is required. The client must set it explicitly.

See `references/api-spec.md` for exact request/response wire formats.

## Error handling expectations

The gateway's responses are trivially uniform:

| Status | Body                                      | Meaning                               |
| ------ | ----------------------------------------- | ------------------------------------- |
| 200    | `{"code":200,"message":"ok"}`             | Message accepted and published        |
| 400    | `{"code":400,"message":"bad parameters"}` | Empty / unparseable JSON body         |
| 5xx    | (varies)                                  | Gateway or broker down                |

A 200 means **accepted by the gateway**, not "processed by the consumer". This is an async pipeline — confirmations of actual work happen out-of-band (logs, downstream side-effects, a separate status API). Make sure the generated code's comments and function names reflect this; calling it `sendTask()` or `publishTask()` is honest, calling it `runTask()` or `executeTask()` is misleading.

On 5xx, surface the error to the caller. Do not silently swallow it — an async pipeline where publish failures are hidden is a debugging nightmare.

## Output checklist

When generating a client for the user, produce:

1. The client module itself (class / module / hook, depending on stack).
2. A usage snippet showing one `publishTask` and one `publishCall` call against a realistic-looking payload.
3. The environment variable entry to add (e.g. a line in `.env.example` or equivalent).
4. If a new dependency is introduced, the exact install command (`composer require …`, `pip install …`, `npm install …`).

Keep the explanation around the code short — the user is integrating, not learning REST from scratch.
