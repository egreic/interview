---
name: studizz-api-mailer-client
description: |
  Use this skill whenever the user wants their app to actually send a transactional email or SMS — signup/confirmation mails, password resets, OTP codes, order/shipping notifications, reminders, CSV mail blasts, event SMS, login OTP texts. This is the default mailer/SMS client for the Studizz ecosystem: any send-mail or send-SMS task in a Studizz project (including "our mailer", "internal/central mail or SMS API", `STUDIZZ_API_MAILER_URL`, or providers Mailjet, AWS SES, OVH, Primotexto, 123SMS) goes through `studizz-api-mailer` at `POST /mail` or `POST /sms/send`.

  Works on every stack and HTTP client (Symfony, Laravel, Express/Node, Next.js, Nuxt, Vue+backend, FastAPI, Flask, Python/Node CLI scripts, Go). Trigger even when the user never names the gateway — Studizz context plus a send-mail-or-SMS intent is enough.

  Skip: work inside the `studizz-api-mailer` repo, native SMTP (`MAILER_DSN`, SwiftMailer), campaigns/contact lists/DNS, and auth/AMQP gateway tasks.
---

# Studizz API Mailer — Client Integration

## What this API is

`studizz-api-mailer` is an internal Symfony HTTP service that fronts several mail and SMS providers behind a uniform REST API. Client projects don't talk to Mailjet, AWS SES, OVH, Primotexto or 123SMS directly — they POST a JSON payload to the gateway and pick the backend via a `provider` field.

The two endpoints this skill cares about:

- `POST /mail` — send one transactional email (Mailjet or AWS SES).
- `POST /sms/send` — send one SMS (OVH, Primotexto, or 123SMS).

There is **no authentication**. The API lives on an internal / trusted network; client code must never embed user tokens or API keys for the gateway itself. Provider credentials (Mailjet API key, OVH login, Primotexto API key…) travel **inside the payload**, in the `credentials` field for SMS and via per-request override semantics for mail.

## How to use this skill

1. **Read `references/api-spec.md` first.** It is the canonical contract — endpoints, payload fields, response shapes, error semantics. Everything in the language-specific references assumes you know the spec. If a language reference looks like it contradicts the spec, the spec wins.

2. **Detect the target stack** before writing any code. Look at the project root:
   - `composer.json` → PHP. Read `references/php.md`.
   - `package.json` → JavaScript / TypeScript. Read `references/javascript.md`. Look at `dependencies` to distinguish plain Node, React, Next.js, or browser-only — the reference covers all three.
   - `requirements.txt`, `pyproject.toml`, `Pipfile`, `setup.py` → Python. Read `references/python.md`.

   If the user names a language explicitly, follow them even if the manifest disagrees. If the project is empty or the language is ambiguous, ask.

3. **Read only the matching language reference.** They are independent — don't load all three. This keeps context lean and the generated code idiomatic to the chosen stack.

4. **Match the existing project style** before writing code. Skim the repo:
   - Which HTTP client is already in use? Reuse it instead of pulling a new dependency. (Guzzle vs Symfony HttpClient vs cURL; `requests` vs `httpx` vs `urllib`; `fetch` vs `axios`.)
   - How are URLs and secrets configured? `.env` + `dotenv`, Symfony parameters, `process.env`, Vite's `import.meta.env`, Next.js public/server env split — follow whatever the project already does for the mailer base URL.
   - Is there a `Services/`, `clients/`, `api/`, or `lib/` folder? Put the new client there rather than dropping a loose file at the root.

5. **Use a placeholder for the base URL.** The canonical env var name across every existing Studizz project is **`STUDIZZ_API_MAILER_URL`** (this is the name in `studizz-api`, `studizz-workflows`, etc. — do not invent a new one). Never hardcode an IP or hostname. In the client, strip a trailing slash defensively so both `https://host` and `https://host/` work.

6. **Keep the generated client small.** This skill produces a focused two-function client:

   - `sendMail(payload)` → POST `/mail`
   - `sendSms(payload)` → POST `/sms/send`

   A good client is ~50–120 lines: a small class / module exposing those two methods plus a shared internal `post(path, payload)` helper. Don't add retries, circuit breakers, batch helpers, or "generic request builders" unless the user asks — the gateway is simple and over-engineering hides the real call site.

## Picking the right provider

The skill must always **ask the user which provider** to use if they have not said:

- For mail: `mailjet` (default, historical) or `aws` (AWS SES, preferred for cold senders / verified domains).
- For SMS: `ovh` (historical), `primotexto`, or `123sms`.

Different providers need different credentials in the payload. The defaults in the code should match the provider the user picks — see the API spec and language reference for the exact `credentials` shape per provider.

If the user is just starting out and has no preference, default to **`mailjet`** for mail and **`ovh`** for SMS — those are the most widely deployed across Studizz projects. Flag the choice in a comment near the call site so the user can change it later.

## Credentials — where they live

Provider credentials are NOT global env vars on the gateway side. They are sent **per request, inside the payload**:

- **Mail / Mailjet**: the gateway has built-in defaults for Mailjet. If you want to override (multi-tenant CRM, per-customer Mailjet account), embed `apiKey` and `apiSecret` somewhere the gateway will pick up — typically through provider-specific override mechanisms documented in `references/api-spec.md`. For most basic transactional sends you can omit credentials entirely and the gateway uses its defaults.
- **Mail / AWS SES**: the gateway uses its IAM role / configured AWS credentials. Just set `provider: "aws"` in the payload; you usually don't have to send AWS keys from the client.
- **SMS / OVH / Primotexto / 123SMS**: credentials are **required** in the `credentials` sub-object of the payload. The exact keys depend on the provider — `api-spec.md` enumerates each shape. The client app must source these from its own env / config, not from the gateway.

For SMS, this means the client project will typically have additional env vars like `OVH_SMS_ACCOUNT`, `OVH_LOGIN`, `OVH_PASSWORD`, `PRIMOTEXTO_API_KEY` alongside `STUDIZZ_API_MAILER_URL`. Generate these in the same `.env.example` line block.

## Response & error semantics

Both endpoints return a JSON object. The shape is **not 100% uniform** across providers — that's deliberate; the gateway exposes enough provider detail for callers to debug. The shared minimum:

- `code` (int) — HTTP-style code, **inside the body**. 200 = accepted, 400 = invalid payload, anything else = provider error.
- `message` (string) — human-readable status.
- Provider-specific extras: `messageID` / `MessageUUID` for mail, `responses[]` / `invalids[]` for SMS, etc.

A 200 status code at the HTTP layer is not always a "send succeeded" — for SMS in particular, the HTTP response can be 201 with a body whose `code` field is 400 because at least one recipient was invalid. **Always read `body.code` for the real outcome**, not just the HTTP status. Language references show the right pattern.

On errors, surface the response body to the caller. Do not swallow it silently — debugging email/SMS failures with no provider feedback is a nightmare.

See `references/api-spec.md` for the exact response shape per endpoint and per provider.

## Output checklist

When generating client code for the user, produce:

1. **The client module itself** (class / module / hook), with two methods: `sendMail` and `sendSms`. Skip whichever one the user didn't ask for if scope is clear.
2. **A short usage snippet** showing one realistic call: a transactional email with `fromEmail`, `fromName`, `subject`, `htmlPart`, `recipients`; or an SMS with `provider`, `text`, `from`, `to`, `credentials`.
3. **The environment variables to add** to `.env` / `.env.example` — at minimum `STUDIZZ_API_MAILER_URL`, plus any provider-specific credentials the user picked.
4. **The install command** if a new dependency is introduced (`composer require ...`, `npm install ...`, `pip install ...`). If the project already has a suitable HTTP client, reuse it and skip this step.

Keep the surrounding explanation tight. The user is integrating an existing internal API — they don't need a primer on REST or HTTP.
