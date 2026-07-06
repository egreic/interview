# Symfony

Where to plug each of the four building blocks in a Symfony 4.4 / 5.x / 6.x project. The reference implementation is `studizz-platform`.

## Block 1 — Auth HTTP client

- Create `App\Service\StudizzService` as a Guzzle wrapper holding `baseUrl`, `apiKey`, `logger`, with the protected `request()` method described in `client-patterns.md`.
- Subclass into `App\Service\StudizzAuthService extends StudizzService` exposing `loginCheck`, `registerUser`, `oauthLogin`, `refreshToken`, `resetPassword`, `confirmPassword`, `getUserByEmail`, `getConfirmationToken`, `confirmUserToken`, `updateUser`, `deleteUser`.
- Wire DI in `config/services.yaml`:
  ```yaml
  App\Service\StudizzAuthService:
      arguments:
          - '@logger'
          - '%env(resolve:STUDIZZ_AUTH_URL)%'
          - '%env(resolve:STUDIZZ_AUTH_KEY)%'
  ```
- `.env`: `STUDIZZ_AUTH_URL=...` (trailing slash) and `STUDIZZ_AUTH_KEY=...`.

## Block 2 — JWT decoder

Lives inside the `UserProvider` as a private method `getPayloadForToken($token)`. Throws `Symfony\Component\Security\Core\Exception\AuthenticationException` on malformed/expired tokens; the firewall converts those into 401s.

## Block 3 — User model

- `App\Security\User implements Symfony\Component\Security\Core\User\UserInterface`.
- Fields: `email`, `roles`, `token`, `payload`.
- Method `initializeUser($email, $token, array $payload)` to populate from the JWT.
- `getRoles()` always appends `ROLE_USER`.
- `getId()` returns `payload['uuid']`.
- `getPassword()` / `getSalt()` / `eraseCredentials()` are no-ops — the auth service owns credentials.

## Block 4 — Authenticators

Symfony's "guard" auth system (Symfony 4.4 / 5.x; for Symfony 6 use the new authenticator system but the same flow). Two classes, registered in two different firewalls in `security.yaml`:

### `App\Security\StudizzAuthenticator extends AbstractFormLoginAuthenticator`
For the web login form.
- `supports(Request)`: `POST` to `app_login` route.
- `getCredentials(Request)`: pull `email`, `password`, `_csrf_token` from the form.
- `getUser($credentials, UserProviderInterface $userProvider)`: validate CSRF → call `studizzAuthService->loginCheck($email, $password)` → if response has `token`, call `$userProvider->getUserForToken($token)`, else throw `CustomUserMessageAuthenticationException`.
- `checkCredentials()`: return true (already verified by the auth service).
- `onAuthenticationSuccess()`: redirect to the post-login route.
- `onAuthenticationFailure()`: optionally check for unconfirmed account via `getUserByEmail` and redirect to the resend-confirmation page; otherwise back to login with the error.

### `App\Security\StudizzAppAuthenticator extends AbstractGuardAuthenticator`
For Bearer-token API routes (e.g. anything under `^/secure`).
- `supports(Request)`: presence of `Authorization` header.
- `getCredentials(Request)`: strip `Bearer ` and return `['token' => $token]`.
- `getUser($credentials, UserProviderInterface $userProvider)`: `$userProvider->getUserForToken($credentials['token'])`.
- `start()`: return JSON 401 (this is an API, no redirect).
- `onAuthenticationSuccess()` returns null (let the controller continue).

### `App\Security\StudizzSocialAuthenticator extends KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator`
For OAuth (Google / Facebook / LinkedIn).
- `supports(Request)`: route is `auth_provider_check`.
- `getCredentials(Request)`: fetch the access token from the social provider via the `ClientRegistry`.
- `getUser()`: fetch the social user → `getUserByEmail` → either `oauthLogin` (existing) or `register` + `confirm` + `oauthLogin` (new) → `getUserForToken`.

Add `KnpUOAuth2ClientBundle` to the project and configure providers in `config/packages/knpu_oauth2_client.yaml`.

## `App\Security\UserProvider`

Implements `UserProviderInterface`. Key methods:
- `getUserForToken($token)`: decode payload (Block 2), build `User` (Block 3).
- `loadUserByUsername($username, $payload = [])`: thin support for `switch_user` / `remember_me` (rarely used here, but Symfony requires the method).
- `refreshUser(UserInterface $user)`: return `$user` unchanged (the JWT is the source of truth on every request).
- `supportsClass($class)`: return `User::class === $class`.

## `config/packages/security.yaml`

The reference uses **two firewalls**:
- `app` (pattern `^/secure`) → API routes, authenticator `StudizzAppAuthenticator`, no form login.
- `main` (pattern `^/`) → web routes, authenticators `StudizzAuthenticator` + `StudizzSocialAuthenticator`.

Both share `provider: app_user_provider` (= `App\Security\UserProvider`).

`access_control` whitelists the public auth endpoints (`/login`, `/secure/auth/login/check`, `/secure/auth/signup`, `/secure/auth/token/refresh`, `/secure/auth/password/reset`, etc.) as `IS_AUTHENTICATED_ANONYMOUSLY`, and protects everything else.

`role_hierarchy` mirrors the role taxonomy used across Studizz services (`ROLE_VENDOR`, `ROLE_MANAGER`, `ROLE_SUPER_ADMIN`, …).

## `App\Controller\SecurityController`

Holds the user-facing routes that aren't covered by the firewall itself: `/login` (renders form + last error), `/logout` (empty — intercepted by the firewall), `/subscribe`, `/auth/subscribe` (AJAX register endpoint), `/auth/confirm/email` (resend confirmation), `/subscribe/confirm/{token}` (confirmation link target), `/oauth/connect`, `/oauth/{provider}/check`. Inject `StudizzAuthService` to call the auth service.

## Symfony 6 note

`AbstractFormLoginAuthenticator` and `AbstractGuardAuthenticator` are gone in Symfony 6's new security system. The mapping is:
- `AbstractFormLoginAuthenticator` → custom authenticator implementing `Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface` (and `InteractiveAuthenticatorInterface` for form login).
- `AbstractGuardAuthenticator` → `Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator` with a `Passport`.
- The flow (read credentials → call auth service → load user → success/failure handler) stays the same; only the class names and the way you build a `Passport` change.
