# Client-side patterns

Every integration with `studizz-auth` reduces to the same four building blocks. This file describes the contract of each one, language- and framework-agnostic. Use this when you're building the integration in a stack that doesn't have a dedicated `frameworks/` file — and as the source of truth for what each piece must do regardless of stack.

---

## Block 1 — Auth HTTP client

A thin wrapper around an HTTP library (Guzzle, axios, fetch, httpx, requests, …) that knows how to talk to `studizz-auth`.

**Construction:**
- Takes `baseUrl` = `STUDIZZ_AUTH_URL` (with trailing slash).
- Takes `apiKey` = `STUDIZZ_AUTH_KEY` (mandatory; this authenticates your project to the auth service).
- Takes a logger, ideally — every Studizz service logs its outbound HTTP for debugging. Match the existing project's logging.

**One core method, conceptually:**
```
request(userToken | null, method, path, body | null, encoding: 'json' | 'form') -> parsed-response
```

Behavior:
1. Always set header `x-api-key: <apiKey>`.
2. If `userToken` is provided, set header `Authorization: Bearer <userToken>`.
3. If `encoding == 'json'`: set `Accept: application/json`, send `body` as a JSON body.
4. If `encoding == 'form'`: send `body` as `application/x-www-form-urlencoded`.
5. Follow redirects (the auth service has some). Cap at ~10.
6. Catch HTTP and transport errors and return them as `{ code: <status>, message: <text> }` rather than throwing — calling code uses the presence of `code` as the error signal (see `api-contract.md` "Conventions for error handling").
7. Decode the response body as JSON if possible. Empty/`"[]"` responses → empty object/array.

**Convenience methods** wrap the above for each endpoint (`loginCheck`, `register`, `oauthLogin`, `refreshToken`, `resetPassword`, `confirmPassword`, `getUserByEmail`, `getConfirmationToken`, `confirmUserToken`, `updateUser`, `deleteUser`). Each method just picks the right method/path/encoding and forwards the parameters. Don't add validation in this layer — that belongs in the controllers.

**Don't:** don't bake retry logic in here unless you have a clear reason. Auth calls should fail fast and let the framework's error handling decide what to show.

---

## Block 2 — JWT decoder / user loader

Turns a raw JWT string into an in-memory representation of the user.

**Steps:**
1. Split the token on `.` → must produce exactly 3 segments (header, payload, signature). Otherwise the token is malformed → reject.
2. Base64-decode (URL-safe) the middle segment. Parse as JSON.
3. The payload must contain at least `username` and `exp`. Missing either → reject.
4. Check expiration: if `exp + 120 <= now_unix()` → reject ("expired"). The 120-second grace is so that a client can detect "about to expire" and refresh proactively.
5. Build the user object (Block 3) from the payload + the original token string.

**Don't verify the signature locally** unless you have an explicit need and access to the auth service's public key. The reference Symfony implementation skips signature validation; trust comes from the API-key-authenticated channel and HTTPS in production.

**Don't make remote calls in the decoder.** The JWT is self-contained; that's the point. If you find yourself calling `getUserByEmail` to "verify" the user on every request, you've defeated the purpose.

---

## Block 3 — User model

The framework's notion of "the authenticated user". Each web framework has its own (`Symfony\Component\Security\Core\User\UserInterface`, Laravel's `Authenticatable`, Express's `req.user`, FastAPI dependency-injected `User`, NestJS's request-scoped user, …). Use the framework's idiom — don't invent a new one.

**Required fields**, derived from the JWT payload:
- `email` (= `payload.email`, falls back to `payload.username`)
- `id` / `uuid` (= `payload.uuid`) — used everywhere to reference the user in other Studizz services
- `roles` (= `payload.roles`, defaults to `['ROLE_USER']` — always include `ROLE_USER` even if not listed)
- `token` (the raw JWT string) — needed for outbound calls to other Studizz services on behalf of this user
- `payload` (the full decoded payload) — handy for less-common claims

**Useful helpers** to expose: `hasRole(role) -> bool`, `isSuperAdmin() -> bool` (= `hasRole('ROLE_SUPER_ADMIN')`), `getToken()`.

**Important:** when this user calls *other* Studizz APIs (`studizz-platform`, `studizz-formations`, etc.), those calls must include `Authorization: Bearer ${user.token}`. So the `token` field is not just for show — downstream services rely on it.

---

## Block 4 — Request guard / middleware

The thing that runs on every request to a protected route, decides whether the caller is authenticated, and attaches the user.

There are typically two flavors in the same project:

### 4a — Bearer middleware (stateless API)
- Trigger: any request to a protected API route.
- Read `Authorization` header → extract token (strip `Bearer `).
- Pass through Block 2 (JWT decoder).
- On success: attach the user to the request and continue.
- On failure: return 401 with a JSON body. Don't redirect (this is an API).

### 4b — Form-login authenticator (web app)
- Trigger: a POST to the login route (e.g. `POST /login`).
- Read `email` and `password` from the form body. Validate CSRF if the framework requires it.
- Call `auth.loginCheck(email, password)`.
- On success: take `response.token`, run Block 2 to build the user, save the user to the session, redirect to the post-login page.
- On failure: re-render the login page with the error message. Optionally, if `auth.getUserByEmail(email)` returns an unconfirmed user, redirect to the resend-confirmation page instead.

In Symfony these two flavors are different `Authenticator` classes attached to different firewalls. In Express they are different `passport` strategies or two pieces of middleware. In FastAPI they are two different dependencies. The pattern is the same; the names differ.

---

## Composition

Wiring it together for a typical project:

```
[ HTTP request ]
       |
       v
[ Request guard ] -- reads token from session / header
       |
       v
[ JWT decoder ] -- validates structure + expiration
       |
       v
[ User model ] -- attached to request context
       |
       v
[ Route handler ] -- can call other Studizz APIs using user.token
                     via [ Auth HTTP client ]
```

When the user logs in (Flow 1), the form-login authenticator (Block 4b) calls Block 1 to exchange credentials for a JWT, then Block 2 to build the user, then stores it via the framework's session. On every subsequent request, Block 4a/4b's session-restoration path uses Block 2 again to rebuild the user from the stored JWT.
