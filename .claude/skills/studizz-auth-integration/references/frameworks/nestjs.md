# NestJS

NestJS sits on top of Express, so the underlying patterns from `references/frameworks/express.md` apply. This file covers the Nest-specific extension points (modules, guards, decorators, Passport integration).

## Block 1 — Auth HTTP client (`StudizzAuthService`)

A regular `@Injectable()` provider in an `AuthHttpModule`:

```ts
@Injectable()
export class StudizzAuthService {
  constructor(
    private readonly http: HttpService,
    @Inject('STUDIZZ_AUTH_CONFIG') private readonly cfg: { url: string; apiKey: string },
    private readonly logger: Logger,
  ) {}

  private async request(userToken: string | null, method: Method, path: string, body?: any, enc: 'json' | 'form' = 'json') {
    // same shape as in client-patterns.md
  }

  loginCheck(email: string, password: string) { /* ... */ }
  register(payload: RegisterDto) { /* ... */ }
  // ...
}
```

Bind config in the root `AppModule` from `process.env.STUDIZZ_AUTH_URL` / `STUDIZZ_AUTH_KEY` (use `@nestjs/config`).

## Block 2 — JWT decoder

A pure helper in `auth/jwt.util.ts` (same as Express version). Throws `UnauthorizedException` on bad/expired tokens.

## Block 3 — User model

A class or interface, e.g. `StudizzUser` with `id`, `email`, `roles`, `token`, `payload`. Make a `@CurrentUser()` parameter decorator:

```ts
export const CurrentUser = createParamDecorator(
  (_, ctx: ExecutionContext) => ctx.switchToHttp().getRequest().user as StudizzUser,
);
```

## Block 4 — Guards

### `StudizzBearerGuard implements CanActivate`

```ts
@Injectable()
export class StudizzBearerGuard implements CanActivate {
  canActivate(context: ExecutionContext): boolean {
    const req = context.switchToHttp().getRequest();
    const auth = req.headers['authorization'];
    if (!auth) throw new UnauthorizedException('Authentication Required');
    const token = auth.replace(/^Bearer\s+/i, '');
    const payload = decodeStudizzJwt(token); // throws UnauthorizedException
    req.user = buildUser(payload, token);
    return true;
  }
}
```

Apply globally with `app.useGlobalGuards(...)` if every route is protected, or per-controller / per-route with `@UseGuards(StudizzBearerGuard)`.

### Role guard

```ts
@Injectable()
export class RolesGuard implements CanActivate {
  constructor(private readonly reflector: Reflector) {}
  canActivate(ctx: ExecutionContext): boolean {
    const required = this.reflector.getAllAndOverride<string[]>('roles', [ctx.getHandler(), ctx.getClass()]);
    if (!required?.length) return true;
    const user: StudizzUser = ctx.switchToHttp().getRequest().user;
    return required.every(r => user.roles.includes(r));
  }
}
export const Roles = (...roles: string[]) => SetMetadata('roles', roles);
```

Usage: `@Roles('ROLE_SUPER_ADMIN') @UseGuards(StudizzBearerGuard, RolesGuard)`.

## Login / register controllers

A regular `AuthController` with routes that delegate to `StudizzAuthService`. For OAuth, use `@nestjs/passport` with the Google/Facebook/LinkedIn strategies — in the strategy's `validate()` callback, follow Flow 6 (`getUserByEmail` + either `oauthLogin` or `register` + `confirm` + `oauthLogin`).

## Module structure

```
src/auth/
├── auth.module.ts
├── auth.controller.ts          # /login, /logout, /register, /password/...
├── studizz-auth.service.ts     # Block 1
├── jwt.util.ts                 # Block 2
├── studizz-user.ts             # Block 3
├── studizz-bearer.guard.ts     # Block 4
├── roles.guard.ts
└── current-user.decorator.ts
```

Re-export `StudizzAuthService` and `StudizzBearerGuard` from `AuthModule` so feature modules can import them.
