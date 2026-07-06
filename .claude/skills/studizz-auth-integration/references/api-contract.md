# `studizz-auth` HTTP API contract

This is the actual surface exposed by the `studizz-auth` Symfony service. Every endpoint is mounted under `STUDIZZ_AUTH_URL` (which **must end with a trailing slash** — relative paths are joined to it).

Every request must carry the project's API key in `x-api-key`. Authenticated user requests additionally carry `Authorization: Bearer <jwt>`. Endpoints marked **(form)** expect `application/x-www-form-urlencoded`; everything else expects JSON.

## Auth lifecycle

### `POST /login_check` **(form)**
Exchanges email+password for a JWT.

Request body (form):
```
_username=<email>
_password=<plain-password>
```

Success response (200):
```json
{
  "token": "<jwt>",
  "refresh_token": "<refresh-jwt>"
}
```

Failure: HTTP 401 with `{ "code": 401, "message": "Bad credentials" }` or similar — caller should surface it as an authentication error.

### `POST /api/login_check` **(JSON)**
Same as above but JSON-encoded. Use this if the project naturally posts JSON.

### `POST /token/refresh` **(form)**
Exchanges a refresh token for a new JWT.

Request body (form):
```
refreshToken=<refresh-jwt>
```

Success response (200): same shape as `login_check` (`token` + `refresh_token`).

### `POST /token/refresh/invalidate`
Invalidates a refresh token (logout for stateless API usage).

## Registration

### `POST /register` **(JSON)**
Creates a new user.

Request body (JSON):
```json
{
  "email": "user@example.com",
  "username": "user@example.com",
  "plain_password": "<plain-password>",
  "enabled": true,
  "roles": ["ROLE_USER"]
}
```

Notes:
- `username` defaults to `email` if omitted.
- `plain_password` is the canonical field name (the platform reference also accepts `password` and aliases it to `plain_password` client-side).
- `roles` must include `ROLE_USER` at minimum.
- If `STUDIZZ_REGISTRATION_CONFIRMATION=true` on the auth service, a confirmation token is generated and the account starts disabled.

Success response (201): the created user object including `id` (mongo uuid).
Conflict (409): `{ "code": 409, "message": "This user already exists" }`.

### `GET /user/{uid}/confirm/token`
Returns the email-confirmation token for a freshly registered user. Requires `x-api-key` matching the auth service's `APP_SECRET` env var (server-to-server only).

### `GET /confirm/{token}`
Confirms a user account from the link in the confirmation email.

Success response (200): `{ "code": 200, "data": "the user is confirmed" }`.
Not found (404): `{ "code": 404, "message": "the user with confirmation token <t> not found" }`.

## OAuth / social

### `POST /oauth/login` **(JSON or form)**
Exchanges a verified social identity (already authenticated by Google/Facebook/LinkedIn) for a JWT.

Request body:
```json
{
  "email": "user@example.com",
  "provider": "google" | "facebook" | "linkedin",
  "userId": "<id-from-studizz-auth-after-getUserByEmail-or-register>"
}
```

The project is responsible for: (1) running the OAuth dance with the social provider, (2) extracting the user's verified email, (3) checking `studizz-auth` for an existing user with that email (`/lite/user/email/{email}`) — registering a new one if not — and only then calling `/oauth/login`.

Success response (200): `{ "token": "<jwt>", ... }`.

## Password management

### `POST /password/reset` **(form)**
Triggers a password-reset flow (sends an email with a reset token, depending on auth service config).

Request body (form):
```
email=<user-email>
```

### `POST /password/confirm` **(form)**
Confirms a password reset using the token from the email.

Request body (form):
```
token=<reset-token>
password=<new-plain-password>
```

## User lookup and management

### `GET /lite/user/email/{email}`
Returns minimal user data for a given email (id, email, enabled, roles). Does not require Bearer.

Success response (200): user object.
Not found: `{ "code": 404, ... }`. Callers use the presence of a `code` key as the "not found" signal.

### `PUT /api/user`
Updates the current user's profile fields. Requires `Authorization: Bearer <jwt>`.

### `DELETE /api/user/{uid}`
Deletes a user by id. Requires `Authorization: Bearer <jwt>`.

## JWT payload

The token returned by `login_check` / `oauth/login` / `token/refresh` is a standard JWT (`header.payload.signature`). The payload (base64-decoded middle segment) contains:

```json
{
  "iat": 1700000000,
  "exp": 1700003600,
  "roles": ["ROLE_USER", "ROLE_MANAGER"],
  "username": "user@example.com",
  "email": "user@example.com",
  "uuid": "<user-id>"
}
```

The reference Symfony client decodes this locally and treats the token as expired when `exp + 120 <= now()` (a 2-minute grace window for refresh). It does **not** validate the signature locally — trust comes from the API-key-authenticated channel.

## Conventions for error handling

`studizz-auth` returns either:
- a success body (object/array, no `code` key), or
- an error body shaped `{ "code": <http-status>, "message": "<human message>" }`.

The reference client treats "the response has a `code` key" as the failure signal. Apply the same convention so calling code stays uniform across endpoints.
