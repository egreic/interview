---
name: studizz-auth-integration
description: >-
  Use this skill whenever the user wants to add, build, or fix authentication in a project — login pages, register/signup, logout, forgot-password and reset flows, social/OAuth login (Google, Facebook, LinkedIn), JWT Bearer middleware, protected API routes, refresh tokens, identifying the current user from a token (decorators, guards, dependencies, `req.user`, `current_user`), httpOnly cookie sessions, and technical/service-account auth for scripts or cron jobs calling internal APIs.

  Trigger across any framework (Symfony, Laravel, Express, NestJS, Next.js, FastAPI, Flask, Go, Python) and even when the user never says "studizz-auth" — phrases like "our auth service", "internal auth", "auth interne", "the token comes from us / vient de chez nous", "the studizz stack", or any `studizz-*` repo plus an auth need are enough.

  Do NOT trigger for: work inside the `studizz-auth` repo itself, browser token-storage choices (localStorage vs cookie), CORS, audit logging, or DB field encryption.
---

# Studizz Auth Integration

This skill helps you implement a secure authentication layer in any project by delegating identity to the **`studizz-auth`** service. The auth service is the single source of truth for users, credentials, and JWTs across the whole Studizz stack — your project never stores passwords or runs its own auth logic. It just talks HTTP to `studizz-auth` and trusts the JWTs it returns.

The reference Symfony implementation lives in `studizz-platform`, but this skill is **framework- and language-agnostic**. Your job is to translate the patterns into the user's stack — Symfony, Laravel, Express, NestJS, FastAPI, Flask, Go, … — not to copy the PHP files verbatim.

## Mental model

Think of `studizz-auth` as a remote auth provider, similar to Auth0 or Cognito. It exposes a small HTTP API:

- The client posts credentials → gets a JWT back.
- The client decodes the JWT locally to know who the user is and what roles they have — there is no per-request "introspect" call.
- The client refreshes the token before it expires using a refresh-token endpoint.
- For OAuth/social, the client gets the email from the social provider, hands it to `studizz-auth`, and gets a JWT back the same way.

Two requests use an `x-api-key` header (server-to-server identification of the calling project), and authenticated requests add `Authorization: Bearer <jwt>` on top.

That's the whole picture. Everything else is plumbing in the user's framework.

## When to read which reference

- **Always read** `references/api-contract.md` first — it defines what the `studizz-auth` HTTP API actually accepts and returns. If you skip it you'll invent endpoints that don't exist.
- **Read `references/flows.md`** when implementing a specific flow (login, register, OAuth, refresh, password reset). It walks through each flow as a sequence of HTTP calls and what to do with the responses.
- **Read `references/client-patterns.md`** to understand the four reusable building blocks (HTTP client, JWT decoder, user model, request guard/middleware) that any integration boils down to. These are described abstractly so you can map them to the user's stack.
- **Read `references/frameworks/<framework>.md`** when you've identified the user's framework — it tells you *which existing classes/decorators/middleware in that framework you should plug the building blocks into*. If no file matches the user's framework, fall back to client-patterns.md and use idiomatic equivalents.

Don't read all references upfront — pull each one in when the workflow below tells you to.

## Workflow

Follow these steps in order. Don't skip the framework-detection or scope steps; getting them wrong wastes a lot of work.

### 1. Detect the target stack

Look at the project root for a manifest (`composer.json`, `package.json`, `pyproject.toml`, `requirements.txt`, `go.mod`, etc.) and identify:
- the language
- the web framework (Symfony / Laravel / Express / NestJS / FastAPI / Flask / …)
- existing auth-related code (a `User` class, a `security.yaml`, an auth middleware, etc.) — you'll either extend it or replace it

If the project is empty or a fresh skeleton, ask the user to confirm which framework they want before proceeding.

### 2. Clarify scope with the user

Ask which flows to implement. Don't implement all of them by default — most projects only need a subset, and unused code rots. Offer this checklist:

- Login form (email + password) → JWT
- API Bearer authentication (protect routes with `Authorization: Bearer <jwt>`)
- Registration + email confirmation
- Password reset (request reset → confirm with token)
- Refresh token
- OAuth / social login (Google, Facebook, LinkedIn, …)

Also ask whether the project should support **session-based login (web app)**, **stateless Bearer (API)**, or **both** — they map to different patterns (cookie/session vs. header-only middleware).

### 3. Wire configuration

The project needs two values: the auth service base URL and the API key. Use the project's standard env mechanism (`.env`, `config/`, `os.environ`, etc.):

```
STUDIZZ_AUTH_URL=http://localhost/studizz-auth/public/   # trailing slash matters
STUDIZZ_AUTH_KEY=<api-key-issued-by-studizz-auth>
```

Both are required. The key authenticates the *project* to the auth service (it travels in the `x-api-key` header on every request); the JWT later authenticates the *user*.

### 4. Build the four building blocks

For every framework, the integration reduces to the same four pieces. Create or adapt them in the user's stack — read `references/client-patterns.md` for the abstract contract of each one:

1. **Auth HTTP client** — a thin wrapper that calls `studizz-auth` endpoints, attaches `x-api-key` on every request and `Authorization: Bearer <jwt>` when authenticated.
2. **JWT decoder / user loader** — turns a JWT string into an in-memory user object (email, roles, payload, expiration). No remote call; just base64-decode the middle segment.
3. **User model** — whatever the framework calls "the authenticated user" (Symfony `UserInterface`, Express `req.user`, FastAPI dependency, Laravel `Authenticatable`). Holds at minimum `email`, `roles`, raw `token`.
4. **Request guard / middleware** — extracts the JWT from the request (form login → call `loginCheck`; API → read `Authorization` header), runs the decoder, attaches the user to the request, and rejects expired/invalid tokens.

### 5. Implement the chosen flows

Read `references/flows.md` and implement only the flows the user asked for in step 2. Each flow is described as a sequence of HTTP calls + what to do with the response — translate those steps into the framework's idioms (controllers, routes, handlers).

For Symfony / Laravel / Express / NestJS / FastAPI, also read the matching file under `references/frameworks/` for the concrete extension points (which class to extend, which middleware to register, which security config key to set). If the user's framework isn't covered, fall back to the patterns file and use the closest idiomatic equivalent — there's nothing magic about the listed frameworks, the contract is the same everywhere.

### 6. Verify

Before declaring done:
- The project boots and `STUDIZZ_AUTH_URL` / `STUDIZZ_AUTH_KEY` are reachable from the framework's config layer.
- A protected route returns 401 without a token and 200 with a valid JWT.
- For each implemented flow, do a manual smoke test with `curl` or the framework's test client against a real `studizz-auth` instance. Don't rely on type-checks alone — the integration is HTTP, the only ground truth is "did the auth service accept it."

## Things to avoid

- **Don't reimplement password hashing or user storage.** `studizz-auth` owns that. Your project must never see plaintext passwords except as a transient field forwarded directly to `loginCheck` / `register`.
- **Don't validate the JWT signature locally** unless you have the `studizz-auth` public key and a real reason to. The reference implementation trusts the token because it came from a trusted origin (the auth service answered over HTTPS with the project's API key) and only checks the `exp` claim. Adding signature validation is fine but not required for parity.
- **Don't cache the API key in client-side code.** It's a project secret — server-side only.
- **Don't paste Symfony-specific class names (`AbstractGuardAuthenticator`, `UserProviderInterface`, …) into Laravel/Node/Python projects.** They mean nothing there. Use the framework's own auth primitives and the *patterns* from this skill.
- **Don't add features the user didn't ask for.** If they only need login + Bearer middleware, don't also scaffold OAuth and password reset. Each unused flow is dead code that will drift.

## Things to remember

- The auth service base URL **needs a trailing slash** (`http://.../studizz-auth/public/`) — relative paths like `login_check` are joined to it. This is a common source of 404s.
- `loginCheck` and `refreshToken` use `application/x-www-form-urlencoded` (`form_params`), not JSON. Most other endpoints use JSON. The contract file lists which is which — don't assume.
- The JWT payload contains `email`, `roles`, `uuid` (used as user id), `username`, `exp`. The reference implementation refreshes the token if `exp + 120s <= now`.
- Roles use the Symfony convention `ROLE_USER`, `ROLE_SUPER_ADMIN`, etc. Even in non-Symfony projects, keep the same string values so the auth service stays the source of truth.
