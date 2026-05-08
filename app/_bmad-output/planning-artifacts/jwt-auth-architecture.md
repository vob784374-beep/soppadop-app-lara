---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
status: 'complete'
completedAt: '2026-04-28'
workflowType: 'architecture'
project_name: 'app'
user_name: 'Bang'
date: '2026-04-28'
scope: 'JWT Authentication Module'
parentDocument: 'architecture.md'
---

# JWT Authentication Module — Architecture Decision Document

> **Scope:** This document defines the module-level architecture for the JWT authentication subsystem only. For project-level decisions (guard selection, package choice, infrastructure) see `architecture.md`. These two documents are complementary — `architecture.md` is the project source of truth; this document is the JWT module source of truth.

---

## 1. Context & Requirements

### What this module covers

- User login → issue access token
- Token validation on protected routes
- Token refresh (rotate with blacklist)
- Logout (blacklist current token)
- Standardised error responses for all token failure modes
- Unit + integration test coverage

### Existing implementation state

| Component | Status | File |
|---|---|---|
| JWT package | ✅ Installed | `composer.json` (`php-open-source-saver/jwt-auth ^2.9`) |
| JWT config | ✅ Published | `config/jwt.php` |
| JWT secret | ✅ Generated | `backend/.env` (`JWT_SECRET`) |
| API guard | ✅ Configured | `config/auth.php` (`driver: jwt`) |
| `JwtService` | ✅ Built | `app/Services/JwtService.php` |
| `AuthService` | ✅ Built | `app/Services/AuthService.php` |
| `AuthController` | ✅ Built | `app/Http/Controllers/Api/V1/AuthController.php` |
| Form Requests | ✅ Built | `app/Http/Requests/Auth/` |
| `UserResource` | ✅ Built | `app/Http/Resources/UserResource.php` |
| Routes | ✅ Wired | `routes/api.php` |
| `JwtServiceInterface` | ❌ Missing | — |
| `AuthServiceInterface` | ❌ Missing | — |
| Custom JWT middleware | ❌ Missing | — |
| Auth unit tests | ❌ Missing | — |
| Auth integration tests | ❌ Missing | — |

---

## 2. SOLID Design Decomposition

### Single Responsibility

Each class has one reason to change:

| Class | Single Responsibility |
|---|---|
| `JwtService` | All JWT token operations (issue, validate, refresh, blacklist). Changes only when token mechanics change. |
| `AuthService` | Authentication business logic (register, login, logout, password reset). Changes only when auth flows change. |
| `AuthController` | HTTP boundary — translate request → service call → response. Changes only when HTTP interface changes. |
| `UserRepository` | User data access. Changes only when DB schema or query strategy changes. |
| `ForceJsonResponse` | Force `Accept: application/json` on API requests. |
| Form Requests | Per-endpoint input validation. One class per endpoint. |
| `UserResource` | User model → JSON shape transformation. |
| `ApiResponse` | Standard envelope construction. |

**Anti-pattern explicitly prohibited:** business logic in controllers, DB queries in services, token operations scattered across multiple classes.

### Open/Closed

The system is open for extension, closed for modification via:

- **Interfaces** — swap implementations without touching callers (`JwtServiceInterface`, `AuthServiceInterface`)
- **`AuthService` constructor injection** — replace `JwtService` with any `JwtServiceInterface` implementation (e.g. asymmetric RS256, or a stub for testing)
- **`BaseRepository`** — extend per model without modifying the base
- **`ApiResponse`** — add new response types without changing existing success/error methods
- **`bootstrap/app.php` exception renderer** — add new exception→HTTP mappings without modifying existing ones

### Dependency Inversion

High-level modules depend on abstractions:

```
AuthController  →  AuthServiceInterface
AuthService     →  JwtServiceInterface
AuthService     →  UserRepositoryInterface
JwtService      →  auth('api') [Laravel guard abstraction]
```

Concrete classes are bound in service providers — callers never `new` them directly.

---

## 3. Interface Contracts (Missing — Required)

### `JwtServiceInterface`

```php
namespace App\Services\Contracts;

use App\Models\User;

interface JwtServiceInterface
{
    public function fromUser(User $user): string;
    public function attempt(string $email, string $password): string;
    public function refresh(): string;
    public function invalidate(): void;
    public function user(): ?User;
    public function payload(): array;
    public function ttl(): int;
    public function tokenResponse(string $token): array;
}
```

### `AuthServiceInterface`

```php
namespace App\Services\Contracts;

interface AuthServiceInterface
{
    public function register(array $data): array;
    public function login(string $email, string $password): array;
    public function refresh(): array;
    public function logout(): void;
    public function forgotPassword(string $email): void;
    public function resetPassword(array $data): void;
}
```

### Binding (add to `RepositoryServiceProvider` or a new `ServiceProvider`)

```php
$this->app->bind(JwtServiceInterface::class, JwtService::class);
$this->app->bind(AuthServiceInterface::class, AuthService::class);
```

---

## 4. Token Flow Architecture

### Chosen strategy: single JWT with blacklist rotation

```
Login:
  Client → POST /api/v1/login
  → AuthService::login()
  → JwtService::attempt() → auth('api')->attempt()
  ← { token, token_type: "bearer", expires_in: 3600 }

Authenticated request:
  Client → GET /api/v1/profile
    Authorization: Bearer <token>
  → auth:api middleware → JWTAuth validates signature + expiry + blacklist
  → Controller → Service → Response

Refresh (within 2-week window):
  Client → POST /api/v1/refresh
    Authorization: Bearer <expired-or-valid-token>
  → JwtService::refresh() → JWTAuth::parseToken()->refresh()
    [old token added to Redis blacklist]
    [new token issued with fresh expiry]
  ← { token, token_type: "bearer", expires_in: 3600 }

Logout:
  Client → POST /api/v1/logout
    Authorization: Bearer <token>
  → JwtService::invalidate() → auth('api')->logout()
    [token added to Redis blacklist]
  ← { message: "Logged out successfully." }
```

### Why single JWT (not dual access+refresh token)

A true dual-token strategy (short-lived JWT access token + long-lived opaque refresh token in DB) adds a `refresh_tokens` table, a `RefreshTokenRepository`, and DB reads on every refresh. The benefit — completely stateless access token verification — is neutralised because:

1. Access tokens still require a blacklist lookup on logout
2. Redis is already in the stack for the blacklist
3. The package's `refresh_ttl` (2 weeks) provides the same client UX as a long-lived refresh token

The current approach achieves the same security profile with less infrastructure. If stateless multi-region scaling becomes a requirement, the `JwtServiceInterface` extension point allows a dual-token implementation without touching `AuthService` or controllers.

### Token configuration

| Setting | Value | Source |
|---|---|---|
| Algorithm | HS256 | `JWT_ALGO=HS256` in `.env` |
| Access token TTL | 60 minutes | `JWT_TTL=60` in `config/jwt.php` |
| Refresh window | 2 weeks (20160 min) | `JWT_REFRESH_TTL=20160` |
| Blacklist | Enabled | `JWT_BLACKLIST_ENABLED=true` |
| Blacklist storage | Redis (Laravel cache) | `config/jwt.php` storage provider |
| Grace period | 0 seconds | `JWT_BLACKLIST_GRACE_PERIOD=0` |

---

## 5. Security Decisions

### Secret key management
- `JWT_SECRET` generated via `php artisan jwt:secret` — 64-byte random string
- Never hardcoded — read from `.env` at runtime
- Never committed to version control
- Rotate by re-running `jwt:secret` and restarting containers (invalidates all live tokens)

### Token replay prevention
- **Blacklist on logout** — `auth('api')->logout()` writes the token's `jti` claim to Redis with TTL = remaining token lifetime
- **Blacklist on refresh** — old token blacklisted before new token issued; prevents parallel use of both
- **`jti` claim** — unique per token, enforced by package's `required_claims` config

### Token validation chain (every protected request)
1. `auth:api` middleware calls `JWTAuth::parseToken()`
2. Signature verification (HS256 + `JWT_SECRET`)
3. Claims validation (`iss`, `iat`, `exp`, `nbf`, `sub`, `jti` all required)
4. Expiry check (`exp` vs current time, with `JWT_LEEWAY` tolerance)
5. Blacklist check (`jti` lookup in Redis)
6. User hydration from `sub` claim

Any failure at steps 2–5 throws a typed exception caught by the global exception renderer → 401 with standard envelope.

### Password hashing
- Laravel `password` cast with `hashed` driver → Argon2id
- `forceFill(['password' => Hash::make($password)])` on reset — bypasses mass assignment, no plain text in DB

### Error message discipline
- Token expired: `"Unauthenticated."` (401) — no expiry timestamp exposed
- Token invalid/blacklisted: `"Unauthenticated."` (401) — no blacklist detail exposed
- Refresh window expired: `"Token has expired and can no longer be refreshed. Please log in again."` (401)
- Invalid credentials: `"Unauthenticated."` (401) — same message as token failure to prevent email enumeration

---

## 6. Custom JWT Middleware (Missing — Required)

The package's `auth:api` guard handles validation but returns Laravel's default unauthenticated response, which may not match the standard envelope on all failure modes. A thin custom middleware wraps it with typed exception handling:

```php
namespace App\Http\Middleware;

// File: app/Http/Middleware/JwtAuthenticate.php

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Http\Responses\ApiResponse;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException) {
            return ApiResponse::error('Token has expired. Use /refresh to get a new token.', status: 401);
        } catch (JWTException) {
            return ApiResponse::error('Token is invalid or missing.', status: 401);
        }

        return $next($request);
    }
}
```

Register in `bootstrap/app.php` and replace `auth:api` in routes with `jwt.auth` alias.

**Why this matters:** distinct 401 messages for "expired" vs "invalid/missing" help frontend clients decide whether to attempt a silent refresh or redirect to login — without exposing implementation detail.

---

## 7. Layer Diagram

```
HTTP Request
    │
    ▼
ForceJsonResponse          ← sets Accept: application/json
    │
    ▼
JwtAuthenticate (custom)   ← validates token, typed 401 messages
    │
    ▼
AuthController             ← thin: validate input, call service, return response
    │
    ▼
AuthService                ← business flow (register/login/refresh/logout/password)
    │           │
    ▼           ▼
JwtService    UserRepository
    │               │
    ▼               ▼
auth('api')      Eloquent/MySQL
    │
    ▼
Redis (blacklist)
```

---

## 8. Test Strategy

### Unit tests — `tests/Unit/Auth/`

**`JwtServiceTest`**
- `fromUser()` returns a non-empty string
- `attempt()` with valid credentials returns token
- `attempt()` with invalid credentials throws `AuthenticationException`
- `refresh()` returns a new token string
- `invalidate()` does not throw
- `tokenResponse()` returns array with `token`, `token_type`, `expires_in` keys
- `ttl()` returns integer > 0

**`AuthServiceTest`** (mock `JwtServiceInterface` + `UserRepositoryInterface`)
- `register()` returns array with `user`, `token`, `token_type`, `expires_in`
- `register()` calls `users->create()` once with correct fields
- `login()` calls `jwt->attempt()` with email + password
- `login()` returns array with `user` key populated
- `refresh()` delegates to `jwt->refresh()`
- `logout()` delegates to `jwt->invalidate()`

### Integration tests — `tests/Feature/Auth/`

**`AuthFlowTest`** (uses `RefreshDatabase`, hits real routes)

```
POST /api/v1/register
  - 201 with {data: {user, token, token_type, expires_in}, message, errors: null, meta: null}
  - 422 on duplicate email
  - 422 on missing fields
  - 422 on password mismatch

POST /api/v1/login
  - 200 with token payload
  - 401 on wrong password (message: "Unauthenticated.")
  - 401 on unknown email

POST /api/v1/refresh
  - 200 with new token
  - old token rejected after refresh (blacklisted)

POST /api/v1/logout
  - 200
  - token rejected after logout

GET /api/v1/profile
  - 200 with user data when valid token
  - 401 when no token
  - 401 when expired token (distinct message from custom middleware)
  - 401 when blacklisted token
```

---

## 9. Implementation Gaps (Ordered by Priority)

| # | Gap | Impact | Files to Create/Modify |
|---|---|---|---|
| 1 | `JwtServiceInterface` | Breaks DIP — `AuthService` depends on concrete | `app/Services/Contracts/JwtServiceInterface.php` |
| 2 | `AuthServiceInterface` | Breaks DIP — `AuthController` depends on concrete | `app/Services/Contracts/AuthServiceInterface.php` |
| 3 | Bind interfaces in provider | Without bindings, DI container can't resolve interfaces | `app/Providers/RepositoryServiceProvider.php` |
| 4 | `JwtAuthenticate` middleware | `auth:api` doesn't distinguish expired vs invalid | `app/Http/Middleware/JwtAuthenticate.php` |
| 5 | Update `AuthController` to type-hint `AuthServiceInterface` | Needed for DIP compliance | `AuthController.php` |
| 6 | Update `AuthService` to type-hint `JwtServiceInterface` | Needed for DIP compliance | `AuthService.php` |
| 7 | Auth unit tests | No coverage on business logic | `tests/Unit/Auth/JwtServiceTest.php`, `AuthServiceTest.php` |
| 8 | Auth integration tests | No coverage on HTTP auth flows | `tests/Feature/Auth/AuthFlowTest.php` |

---

## 10. Enforcement Rules for AI Agents

**ALL agents implementing auth features MUST:**
- Depend on `JwtServiceInterface`, never `JwtService` directly
- Depend on `AuthServiceInterface`, never `AuthService` directly
- Never call `auth('api')` outside of `JwtService`
- Never call `Hash::make()` outside of repository/model layers
- Never expose raw JWT exception messages to API responses — always map to standard envelope
- Use `jwt.auth` middleware alias (custom), not `auth:api`, on protected routes
- Place all token operations in `JwtService`, all auth flows in `AuthService`, all HTTP concerns in controller

**Anti-patterns (never do these):**
- ❌ `auth('api')->attempt()` in a controller or repository
- ❌ `new JwtService()` anywhere — always inject via interface
- ❌ Returning the raw `TokenExpiredException` message to the client
- ❌ Storing JWT secret in code or config files — only in `.env`
- ❌ Skipping blacklist check — never set `JWT_BLACKLIST_ENABLED=false` in non-test environments
