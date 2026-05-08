# Story 4.7: JWT Gap Closure — SOLID Compliance

Status: done

## Story

As a developer,
I want the JWT auth layer to satisfy SOLID design principles with proper interfaces and typed middleware,
so that the codebase is maintainable and dependencies are invertible.

## Acceptance Criteria

1. `JwtServiceInterface` exists in `app/Services/Contracts/` with all methods declared (`fromUser`, `attempt`, `refresh`, `invalidate`, `user`, `payload`, `ttl`, `tokenResponse`)

2. `AuthServiceInterface` exists in `app/Services/Contracts/` with all methods declared

3. `RepositoryServiceProvider` binds `JwtServiceInterface → JwtService` and `AuthServiceInterface → AuthService`

4. `AuthController` type-hints `AuthServiceInterface` (not the concrete class)

5. `AuthService` type-hints `JwtServiceInterface` (not the concrete class)

6. A custom `JwtAuthenticate` middleware exists with typed 401 responses: `"Token has expired. Use /api/v1/refresh to obtain a new token."` and `"Token is invalid or missing."` — via `ApiResponse::error()`, not the generic Laravel auth response

7. `jwt.authenticate` alias is registered in `bootstrap/app.php`

8. All protected routes use the `jwt.authenticate` alias

9. The full test suite (24 auth tests / 71 assertions) passes after all changes

## Tasks / Subtasks

- [x] Task 1: Create `JwtServiceInterface` (AC: 1)
  - [x] Create `app/Services/Contracts/JwtServiceInterface.php` with all 8 methods declared
  - [x] Verify interface covers: `fromUser`, `attempt`, `refresh`, `invalidate`, `user`, `payload`, `ttl`, `tokenResponse`

- [x] Task 2: Create `AuthServiceInterface` (AC: 2)
  - [x] Create `app/Services/Contracts/AuthServiceInterface.php` with all methods declared
  - [x] Verify interface covers: `register`, `login`, `refresh`, `logout`, `forgotPassword`, `resetPassword`

- [x] Task 3: Bind interfaces in RepositoryServiceProvider (AC: 3)
  - [x] Add `JwtServiceInterface::class → JwtService::class` binding
  - [x] Add `AuthServiceInterface::class → AuthService::class` binding
  - [x] Verify `RepositoryServiceProvider` is registered in `bootstrap/providers.php`

- [x] Task 4: Update AuthController to type-hint interface (AC: 4)
  - [x] `AuthController::__construct` uses `AuthServiceInterface $auth`
  - [x] No direct reference to `AuthService` concrete class in controller

- [x] Task 5: Update AuthService to type-hint interface (AC: 5)
  - [x] `AuthService::__construct` uses `JwtServiceInterface $jwt`
  - [x] No direct reference to `JwtService` concrete class in service

- [x] Task 6: Create `JwtAuthenticate` middleware (AC: 6, 7)
  - [x] Create `app/Http/Middleware/JwtAuthenticate.php`
  - [x] Catches `TokenExpiredException` → `ApiResponse::error('Token has expired...', status: 401)`
  - [x] Catches `JWTException` → `ApiResponse::error('Token is invalid or missing.', status: 401)`
  - [x] Register alias `jwt.authenticate` in `bootstrap/app.php`

- [x] Task 7: Update routes to use `jwt.authenticate` (AC: 8)
  - [x] All protected route groups in `routes/api.php` use `middleware('jwt.authenticate')` — no `auth:api` usage

- [x] Task 8: Verify test suite (AC: 9)
  - [x] Run `php artisan test --filter="Auth"` → 24 tests, 71 assertions, all pass

## Dev Agent Record

**Completed:** 2026-04-30 (pre-existing implementation detected during correct-course workflow)
**Agent:** Pre-existing — implemented alongside AuthService/JwtService on 2026-04-28

### Implementation Notes

All four gaps listed in `jwt-auth-architecture.md` Section 9 were already resolved in the pre-existing implementation before the story was formally tracked:
- Interfaces existed in `app/Services/Contracts/`
- Bindings existed in `RepositoryServiceProvider`
- `JwtAuthenticate` middleware with typed 401 messages existed and was registered
- All routes already used `jwt.authenticate` alias

Story file created retrospectively after correct-course artifact realignment confirmed implementation completeness.
