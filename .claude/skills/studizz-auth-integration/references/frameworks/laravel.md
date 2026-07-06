# Laravel

Where to plug each of the four building blocks in a Laravel 9/10/11 project.

Recommended approach: **don't use Laravel's built-in `Eloquent`-backed auth** (no `users` table) — register a custom user provider and a custom guard.

## Block 1 — Auth HTTP client

`app/Services/StudizzAuthService.php`:
- A class wrapping Laravel's `Http` facade (which is Guzzle under the hood) with the same `request($userToken, $method, $path, $body, $encoding)` shape as in `client-patterns.md`.
- Construct via the service container: in `app/Providers/AppServiceProvider.php`, bind it as a singleton with `config('services.studizz_auth.url')` and `config('services.studizz_auth.key')`.
- Add to `config/services.php`:
  ```php
  'studizz_auth' => [
      'url' => env('STUDIZZ_AUTH_URL'),
      'key' => env('STUDIZZ_AUTH_KEY'),
  ],
  ```
- `.env`: `STUDIZZ_AUTH_URL` / `STUDIZZ_AUTH_KEY`.
- Type-hint `StudizzAuthService` in controllers — Laravel auto-resolves it.

## Block 2 — JWT decoder

A small static utility class `App\Auth\StudizzJwt::decode($token): array` that does the same split/base64/expiry check as `client-patterns.md`. Throws `Illuminate\Auth\AuthenticationException` on failure.

## Block 3 — User model

`App\Auth\StudizzUser implements Illuminate\Contracts\Auth\Authenticatable`:

```php
class StudizzUser implements Authenticatable
{
    public function __construct(
        public string $id,
        public string $email,
        public array $roles,
        public string $token,
        public array $payload,
    ) {}

    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPassword() { return ''; }
    public function getRememberToken() { return null; }
    public function setRememberToken($value) {}
    public function getRememberTokenName() { return ''; }

    public function hasRole(string $role): bool { return in_array($role, $this->roles); }
    public function isSuperAdmin(): bool { return $this->hasRole('ROLE_SUPER_ADMIN'); }
}
```

## Block 4 — Custom guard + provider

In `App\Providers\AuthServiceProvider::boot()`:

```php
Auth::provider('studizz', fn ($app) => new StudizzUserProvider($app->make(StudizzAuthService::class)));
Auth::extend('studizz-token', fn ($app, $name, $config) =>
    new StudizzTokenGuard($app->make('request'), Auth::createUserProvider($config['provider']))
);
```

`config/auth.php`:
```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'studizz_users'],
    'api' => ['driver' => 'studizz-token', 'provider' => 'studizz_users'],
],
'providers' => [
    'studizz_users' => ['driver' => 'studizz'],
],
```

### `StudizzUserProvider implements UserProvider`
- `retrieveById($id)`: not used in stateless flow; return null.
- `retrieveByCredentials($credentials)`: call `StudizzAuthService::loginCheck`, decode the returned JWT, build `StudizzUser`.
- `validateCredentials(...)`: return true (already validated by `loginCheck`).
- The other methods (`retrieveByToken`, `updateRememberToken`) can be no-ops.

### `StudizzTokenGuard implements Guard`
- `user()`: read `Authorization` header → strip `Bearer ` → decode JWT → return `StudizzUser`. Cache on the instance to avoid decoding twice per request.
- `check()` / `guest()` / `id()`: standard derivations from `user()`.
- `validate()` / `setUser()`: minimal pass-through.

Apply it to API routes:
```php
Route::middleware('auth:api')->group(function () {
    // protected routes
});
```

## Login form (web app)

Standard Laravel login controller, but instead of `Auth::attempt`, call `StudizzAuthService::loginCheck` directly:

```php
$res = $this->auth->loginCheck($req->email, $req->password);
if (!isset($res['token'])) {
    throw ValidationException::withMessages(['email' => 'Bad credentials']);
}
$payload = StudizzJwt::decode($res['token']);
$user = new StudizzUser(...);
Auth::guard('web')->setUser($user);
$req->session()->put('studizz_token', $res['token']);
$req->session()->put('studizz_refresh', $res['refresh_token'] ?? null);
return redirect()->intended('/dashboard');
```

On every web request, re-hydrate the user from the session token in a custom middleware (because `web` guard is session-based but the session only stores the token, not the full user).

## OAuth — Laravel Socialite

Install `laravel/socialite`. Configure the providers in `config/services.php`. In the callback:

```php
public function callback($provider, StudizzAuthService $auth) {
    $social = Socialite::driver($provider)->user();
    $existing = $auth->getUserByEmail($social->getEmail());
    if (!isset($existing['code'])) {
        $res = $auth->oauthLogin($provider, $social->getEmail(), $existing['id']);
    } else {
        $registered = $auth->register([...]);
        $confirmToken = $auth->getConfirmationToken($registered['id']);
        $auth->confirmUserToken($confirmToken);
        $res = $auth->oauthLogin($provider, $social->getEmail(), $registered['id']);
    }
    // store $res['token'] in session, log user in
}
```

## Don't

- Don't extend `Illuminate\Foundation\Auth\User`. It assumes Eloquent. Use the bare `Authenticatable` contract.
- Don't run `php artisan make:auth` / Breeze / Jetstream scaffolding — they create local `users` tables and password hashing you don't want. The auth service owns all of that.
