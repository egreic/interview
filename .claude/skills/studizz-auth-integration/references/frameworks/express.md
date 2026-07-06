# Express / Node.js

Where to plug each of the four building blocks in an Express project (TypeScript or JavaScript; same pattern).

Recommended deps:
- An HTTP client: `axios` (closest to Guzzle's API) or native `fetch`.
- `jsonwebtoken` if you want signature validation later, but for parity with the reference you can just `Buffer.from(parts[1], 'base64url').toString()` and `JSON.parse`.
- `passport` + strategies if you need form login or OAuth, though for a stateless API a small custom middleware is usually cleaner.

## Block 1 — Auth HTTP client

Create `src/services/studizzAuthService.ts` exporting a single `StudizzAuthClient` class. Constructor takes `{ baseUrl, apiKey, logger }`. Internal `request(userToken | null, method, path, body, encoding)` method:

```ts
const headers: Record<string, string> = { 'x-api-key': this.apiKey };
if (userToken) headers['Authorization'] = `Bearer ${userToken}`;
if (encoding === 'json') headers['Accept'] = 'application/json';

const config: AxiosRequestConfig = { baseURL: this.baseUrl, method, url: path, headers, maxRedirects: 10 };
if (body && encoding === 'json') config.data = body;
if (body && encoding === 'form') {
  config.data = new URLSearchParams(body as Record<string, string>).toString();
  headers['Content-Type'] = 'application/x-www-form-urlencoded';
}

try {
  const res = await axios.request(config);
  return res.data ?? {};
} catch (err: any) {
  return { code: err.response?.status ?? 500, message: err.message };
}
```

Then convenience methods: `loginCheck(email, password)`, `register(payload)`, `oauthLogin({ email, provider, userId })`, `refreshToken(refreshToken)`, `resetPassword(email)`, `confirmPassword({ token, password })`, `getUserByEmail(email)`, `getConfirmationToken(uid)`, `confirmUserToken(token)`, `updateUser(userToken, payload)`, `deleteUser(userToken, uid)`.

Inject the singleton in your `app.ts` from `process.env.STUDIZZ_AUTH_URL` and `process.env.STUDIZZ_AUTH_KEY` (use `dotenv` for local dev).

## Block 2 — JWT decoder

A tiny pure function:

```ts
export function decodeStudizzJwt(token: string): JwtPayload {
  const parts = token.split('.');
  if (parts.length !== 3) throw new AuthError('Wrong token format');
  let payload: any;
  try { payload = JSON.parse(Buffer.from(parts[1], 'base64url').toString('utf8')); }
  catch { throw new AuthError('Cannot decode token'); }
  if (!payload.username) throw new AuthError('No username in token');
  if (!payload.exp) throw new AuthError('No exp in token');
  if (payload.exp + 120 <= Math.floor(Date.now() / 1000)) throw new AuthError('Token expired');
  return payload;
}
```

`AuthError` is a custom error class that the middleware turns into a 401.

## Block 3 — User model

Idiomatic Express attaches the user to `req.user`. Define the type once (e.g. via TypeScript declaration merging on `Express.Request`):

```ts
declare global {
  namespace Express {
    interface User {
      id: string;
      email: string;
      roles: string[];
      token: string;
      payload: JwtPayload;
      hasRole(role: string): boolean;
      isSuperAdmin(): boolean;
    }
  }
}
```

Build it from the decoded payload + raw token. Always include `'ROLE_USER'` in `roles`.

## Block 4 — Middleware

### Bearer middleware (stateless API)

```ts
export function studizzBearer(req, res, next) {
  const auth = req.headers['authorization'];
  if (!auth) return res.status(401).json({ message: 'Authentication Required' });
  const token = auth.replace(/^Bearer\s+/i, '');
  try {
    const payload = decodeStudizzJwt(token);
    req.user = buildUser(payload, token);
    next();
  } catch (err) {
    res.status(401).json({ message: err.message });
  }
}
```

Apply to protected routes: `app.use('/secure', studizzBearer, secureRouter)`.

### Form login (web app with sessions)

Either roll your own (`POST /login` handler) or use `passport` with a `LocalStrategy` that delegates to `studizzAuth.loginCheck`. On success, store the JWT in `req.session.token` and return the user. On every subsequent request, restore the user from the session (decode `req.session.token`).

### OAuth

Use `passport-google-oauth20` / `passport-facebook` / `passport-linkedin-oauth2`. In the strategy callback, you receive the social profile. From there, follow Flow 6 (`getUserByEmail` → either `oauthLogin` or `register` + `confirm` + `oauthLogin`), persist the JWT in session, done.

## Project structure (suggested)

```
src/
├── services/
│   └── studizzAuthService.ts      # Block 1
├── auth/
│   ├── jwt.ts                     # Block 2
│   ├── user.ts                    # Block 3
│   ├── bearerMiddleware.ts        # Block 4a
│   └── localStrategy.ts           # Block 4b (optional)
├── routes/
│   ├── authRoutes.ts              # /login, /logout, /register, /password/reset, ...
│   └── secureRoutes.ts            # protected by studizzBearer
└── app.ts
```

## Don't

- Don't use `jsonwebtoken.verify()` with a hard-coded secret — you don't have the auth service's private key, and the reference doesn't validate the signature anyway. If you want signature validation later, fetch the public key from the auth service and switch to `verify` properly.
- Don't store the JWT in `localStorage` for a SPA. Use httpOnly cookies (set by the server after login) so XSS can't lift the token.
