# Auth flows

Each flow below is the abstract sequence of operations needed to implement that feature. Translate each step into your framework's idioms (controller / route handler / middleware / dependency).

For all flows, assume:
- An auth HTTP client `auth` is configured with base URL = `STUDIZZ_AUTH_URL` and `x-api-key` = `STUDIZZ_AUTH_KEY` (see `client-patterns.md`).
- A JWT decoder `decodeJwt(token) -> payload` is available.
- The framework can attach an authenticated user object to the current request.

---

## Flow 1 — Login (email + password → JWT)

Used by: classic web app login form, or an API that accepts credentials directly.

1. Receive `email` and `password` from the request (form post or JSON, depending on the project's UX).
2. Call `auth.post('login_check', { _username: email, _password: password }, type: form)`.
3. If the response has a `code` key (error shape) or no `token` field → reject with the framework's "authentication failed" mechanism. Surface `response.message` to the user where appropriate.
4. Else: take `response.token` (and optionally `response.refresh_token`) and persist them according to the project's session model:
   - **Web app with sessions**: store the token server-side in the session, instantiate the user object from the decoded payload, and let the framework's session/cookie machinery do the rest.
   - **Stateless API**: return the token in the JSON response body so the client stores it.
5. On subsequent requests, the **request guard** (see `client-patterns.md`) is what re-instantiates the user from the token — login itself doesn't need to run on every request.

Edge cases:
- If `auth.getUserByEmail(email)` returns a user with `enabled: false`, the account exists but hasn't confirmed its email — redirect to a "resend confirmation" page rather than showing a generic "wrong password".

---

## Flow 2 — API Bearer authentication

Used by: every protected route in a stateless API (or `^/secure` routes in the Symfony reference).

This is a **request guard / middleware**, not a one-shot handler. It runs on every incoming request to a protected route.

1. Read `Authorization` header. Strip the `Bearer ` prefix. If absent → 401.
2. Split the JWT on `.` — must yield exactly 3 parts, otherwise → 401 ("Wrong token format").
3. Base64-decode the middle segment, JSON-parse. The payload must contain `username` and `exp`. Missing either → 401.
4. If `exp + 120 <= now()` → 401 ("Token expired"). The 120-second grace lets a client refresh proactively without races.
5. Build the user object from the payload (`email`, `roles`, `uuid` as id, raw `token`). Attach to the request.
6. Continue to the route handler.

The reference Symfony code (`UserProvider::getPayloadForToken`) does **not** verify the JWT signature. If you want to add that, fetch the auth service's public key at boot and use a standard JWT lib — but it's not required for parity with the rest of the Studizz stack.

---

## Flow 3 — Registration + email confirmation

Used by: web sign-up forms.

### Sign-up handler
1. Validate input (email format, password strength, captcha if applicable). The reference platform also calls `getUserByEmail` first to bail out on disposable-mail domains.
2. Call `auth.post('register', { email, plain_password, username: email, enabled: true, roles: ['ROLE_USER'] }, type: json)`.
3. If the response has `code: 409` → "user already exists". `code: 4xx` → surface the message. Otherwise the new user's `id` is in `response.id`.
4. Call `auth.get('user/{id}/confirm/token')` to get the confirmation token.
5. Send a confirmation email containing a link to your app's `confirmAccount(token)` route. The email body is your project's responsibility (template / mail service).

### Confirm-account handler (the link recipient)
1. Receive the confirmation token from the URL.
2. Call `auth.get('confirm/{token}')`.
3. On success (`code: 200` or no `code` key), redirect the user to the login page with a "your account is confirmed" message.
4. On failure (`code: 404`), show "this confirmation link is invalid or expired".

---

## Flow 4 — Password reset (request → confirm)

Used by: "forgot password" flow.

### Request reset
1. Receive `email` from the form.
2. Call `auth.post('password/reset', { email }, type: form)`.
3. Always show a generic "if the email exists, a reset link has been sent" message — don't leak whether the email is known.

The auth service handles sending the reset email (with its own templates). The user clicks the link, which lands on your app's "set new password" page with a `token` query parameter.

### Confirm reset
1. Receive `token` and the new `password` from the form.
2. Call `auth.post('password/confirm', { token, password }, type: form)`.
3. On success → redirect to login. On failure → show the message and let the user retry.

---

## Flow 5 — Refresh token

Used by: keeping a session alive without forcing the user to re-enter credentials.

1. The current JWT is about to expire (or already did within the 120s grace window).
2. Read the stored `refresh_token` (session for web app, secure storage for SPA / mobile).
3. Call `auth.post('token/refresh', { refreshToken }, type: form)`.
4. Replace the stored `token` (and `refresh_token` if rotated) with the new values.
5. On failure → force a full re-login.

Where to put the refresh logic:
- **Web app with sessions**: in the same place that loads the user from the session at request start. If the token is about to expire, refresh it before the route handler runs.
- **Stateless API**: expose a `/auth/refresh` endpoint that the SPA calls when it gets a 401. Or do silent refresh on a timer.

---

## Flow 6 — OAuth / social login

Used by: "Sign in with Google / Facebook / LinkedIn" buttons.

This flow has two halves: the OAuth dance with the social provider (handled by a library like Symfony's `KnpUOAuth2ClientBundle`, `passport-google-oauth20` for Express, `python-social-auth` for FastAPI, Laravel Socialite, etc.), and the exchange with `studizz-auth` once you have a verified email.

1. User clicks "Sign in with Google" → your app redirects to Google's OAuth endpoint with the right scopes (`profile`, `email` for Google; `public_profile`, `email` for Facebook; `r_liteprofile`, `r_emailaddress` for LinkedIn).
2. Google redirects back to your callback URL with an authorization code.
3. Your OAuth library exchanges the code for an access token and fetches the user's profile (email, name, picture).
4. Call `auth.get('lite/user/email/{email}')`:
   - **If a user exists** (no `code` key): call `auth.post('oauth/login', { email, provider, userId: existingUser.id }, type: json)` → you get back `{ token: ... }`.
   - **If no user exists** (`code: 404`): register them first.
     - `auth.post('register', { email, enabled: true, roles: ['ROLE_USER'] }, type: json)` — no password (social-only account).
     - `auth.get('user/{newId}/confirm/token')` then `auth.get('confirm/{token}')` to mark them enabled (social-verified emails are inherently confirmed).
     - `auth.post('oauth/login', { email, provider, userId: newId }, type: json)` for the JWT.
     - Optionally call your project's profile-creation flow with the social profile data (firstname, lastname, picture).
5. With the JWT in hand, behave exactly like the end of Flow 1 (login).

Mark "first-connection" state somewhere if your UX shows a "welcome / complete your profile" step on first social login.

---

## Flow 7 — Logout

Used by: "log out" button.

For a web app with sessions: clear the framework's session (which holds the token + refresh token). The framework's logout primitive usually does this for you (`/logout` route + firewall config in Symfony, `req.logout()` in Passport, etc.).

For a stateless API: optionally call `auth.post('token/refresh/invalidate', { refreshToken })` to invalidate the refresh token server-side, then have the client drop the JWT. The access token remains valid until `exp` — that's a property of stateless JWTs and is fine for short token lifetimes.
