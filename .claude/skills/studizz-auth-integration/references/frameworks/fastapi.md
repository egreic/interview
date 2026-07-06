# FastAPI / Python

Where to plug each of the four building blocks in a FastAPI project. Same pattern works in Flask with minor changes (use `flask.g` instead of dependency injection, use `before_request` for the middleware).

Recommended deps:
- `httpx` for the HTTP client (async-friendly, closest to Guzzle in spirit).
- `pydantic` (already in FastAPI) for request/response models.
- `python-jose` or `pyjwt` if you ever want signature validation; for parity with the reference you can just `base64.urlsafe_b64decode` + `json.loads`.

## Block 1 — Auth HTTP client

`app/services/studizz_auth.py`:

```python
class StudizzAuthClient:
    def __init__(self, base_url: str, api_key: str, logger):
        self.base_url = base_url.rstrip('/') + '/'
        self.api_key = api_key
        self.logger = logger
        self._client = httpx.AsyncClient(base_url=self.base_url, follow_redirects=True, timeout=30)

    async def _request(self, user_token, method, path, body=None, encoding='json'):
        headers = {'x-api-key': self.api_key}
        if user_token:
            headers['Authorization'] = f'Bearer {user_token}'
        if encoding == 'json':
            headers['Accept'] = 'application/json'
        kwargs = {'headers': headers}
        if body is not None:
            if encoding == 'json':
                kwargs['json'] = body
            else:
                kwargs['data'] = body
        try:
            r = await self._client.request(method, path, **kwargs)
            r.raise_for_status()
            return r.json() if r.content else {}
        except httpx.HTTPStatusError as e:
            return {'code': e.response.status_code, 'message': e.response.text}
        except httpx.HTTPError as e:
            return {'code': 500, 'message': str(e)}

    async def login_check(self, email: str, password: str):
        return await self._request(None, 'POST', 'login_check',
                                   {'_username': email, '_password': password}, 'form')
    async def register(self, payload: dict): ...
    async def oauth_login(self, email, provider, user_id): ...
    async def refresh_token(self, refresh_token): ...
    async def reset_password(self, email): ...
    async def confirm_password(self, token, password): ...
    async def get_user_by_email(self, email): ...
    async def get_confirmation_token(self, uid): ...
    async def confirm_user_token(self, token): ...
```

Wire it as a singleton via FastAPI's dependency system or a startup hook reading `os.environ['STUDIZZ_AUTH_URL']` / `STUDIZZ_AUTH_KEY`.

## Block 2 — JWT decoder

```python
def decode_studizz_jwt(token: str) -> dict:
    parts = token.split('.')
    if len(parts) != 3:
        raise AuthError('Wrong token format')
    try:
        padded = parts[1] + '=' * (-len(parts[1]) % 4)
        payload = json.loads(base64.urlsafe_b64decode(padded))
    except Exception:
        raise AuthError('Cannot decode token')
    if 'username' not in payload:
        raise AuthError('No username in token')
    if 'exp' not in payload:
        raise AuthError('No exp in token')
    if payload['exp'] + 120 <= int(time.time()):
        raise AuthError('Token expired')
    return payload
```

## Block 3 — User model

A pydantic model:

```python
class StudizzUser(BaseModel):
    id: str
    email: str
    roles: list[str]
    token: str
    payload: dict

    def has_role(self, role: str) -> bool:
        return role in self.roles

    def is_super_admin(self) -> bool:
        return self.has_role('ROLE_SUPER_ADMIN')

def build_user_from_payload(payload: dict, token: str) -> StudizzUser:
    roles = list(payload.get('roles') or [])
    if 'ROLE_USER' not in roles:
        roles.append('ROLE_USER')
    return StudizzUser(
        id=payload['uuid'],
        email=payload.get('email') or payload['username'],
        roles=roles,
        token=token,
        payload=payload,
    )
```

## Block 4 — Dependency

FastAPI's idiom for "auth middleware" is a dependency:

```python
bearer_scheme = HTTPBearer(auto_error=False)

async def current_user(creds: HTTPAuthorizationCredentials = Depends(bearer_scheme)) -> StudizzUser:
    if creds is None:
        raise HTTPException(status_code=401, detail='Authentication Required')
    try:
        payload = decode_studizz_jwt(creds.credentials)
    except AuthError as e:
        raise HTTPException(status_code=401, detail=str(e))
    return build_user_from_payload(payload, creds.credentials)
```

Use it on protected routes:

```python
@app.get('/secure/me')
async def me(user: StudizzUser = Depends(current_user)):
    return user
```

For role gating, build small wrappers:

```python
def require_role(role: str):
    async def _dep(user: StudizzUser = Depends(current_user)) -> StudizzUser:
        if not user.has_role(role):
            raise HTTPException(403, 'Forbidden')
        return user
    return _dep

@app.get('/admin')
async def admin(user = Depends(require_role('ROLE_SUPER_ADMIN'))): ...
```

## Login / register / OAuth routes

Plain FastAPI endpoints:

```python
@app.post('/login')
async def login(req: LoginRequest, auth: StudizzAuthClient = Depends(get_auth)):
    res = await auth.login_check(req.email, req.password)
    if 'code' in res or 'token' not in res:
        raise HTTPException(401, res.get('message', 'Authentication failed'))
    return {'token': res['token'], 'refresh_token': res.get('refresh_token')}
```

For session-based web apps, use `starlette.middleware.sessions.SessionMiddleware` to store the JWT, and read it back in `current_user` instead of from the header. Most FastAPI projects are stateless APIs though — Bearer is the more common case.

## Don't

- Don't synchronously block on httpx — use `AsyncClient` so the dependency stays async-friendly. Mixing blocking calls into FastAPI handlers tanks throughput.
- Don't put the auth client in module-level state without an `await self._client.aclose()` on shutdown — use FastAPI's `lifespan`.
