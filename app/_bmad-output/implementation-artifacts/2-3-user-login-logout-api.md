# Story 2.3: User Login & Logout API

Status: done

## Story

As a registered user,
I want to log in with my email and password and receive a JWT access token,
so that I can make authenticated API requests and use the platform.

## Acceptance Criteria

1. POST `/api/v1/login` with valid `email` and `password` returns HTTP 200 with the standard envelope containing: `user`, `token`, `token_type: "bearer"`, `expires_in: 3600`

2. When credentials are invalid, HTTP 401 is returned with `"message": "Invalid credentials."`

3. POST `/api/v1/auth/refresh` — implemented as POST `/api/v1/refresh` — with a valid Bearer token returns HTTP 200 with a new token; the old token is blacklisted in Redis

4. POST `/api/v1/auth/logout` — implemented as POST `/api/v1/logout` — with a valid Bearer token blacklists the token and returns HTTP 200 with `"message": "Logged out successfully."`

5. After logout, the blacklisted token returns HTTP 401 with `"message": "Unauthenticated."`

6. Without a valid Bearer token, protected routes return HTTP 401 via `JwtAuthenticate` middleware with typed message

## Tasks / Subtasks

- [x] Task 1: Login endpoint (AC: 1, 2)
  - [x] `POST /api/v1/login` route registered in `routes/api.php` (public, no auth middleware)
  - [x] `AuthController::login` delegates to `AuthServiceInterface::login`
  - [x] `AuthService::login` calls `JwtServiceInterface::attempt` — throws on invalid credentials
  - [x] Response: `{ user (UserResource), token, token_type, expires_in }` via `ApiResponse::success`
  - [x] Invalid credentials → 401 from `JwtException` caught in `JwtAuthenticate` or propagated as `AuthenticationException`

- [x] Task 2: Refresh endpoint (AC: 3)
  - [x] `POST /api/v1/refresh` route registered (public — uses Bearer token internally)
  - [x] `AuthController::refresh` delegates to `AuthServiceInterface::refresh`
  - [x] `AuthService::refresh` calls `JwtServiceInterface::refresh` — old token blacklisted, new token returned
  - [x] Redis JWT blacklist enabled (`JWT_BLACKLIST_ENABLED=true`)

- [x] Task 3: Logout endpoint (AC: 4, 5)
  - [x] `POST /api/v1/logout` route registered inside `jwt.authenticate` middleware group
  - [x] `AuthController::logout` delegates to `AuthServiceInterface::logout`
  - [x] `AuthService::logout` calls `JwtServiceInterface::invalidate` — token blacklisted in Redis
  - [x] Response: HTTP 200 `"message": "Logged out successfully."`

- [x] Task 4: Middleware (AC: 6)
  - [x] `JwtAuthenticate` middleware catches `TokenExpiredException` and `JWTException` with typed 401 messages
  - [x] Alias `jwt.authenticate` registered in `bootstrap/app.php`

- [x] Task 5: Tests (AC: 1–6)
  - [x] `login_returns_200_with_token_payload`
  - [x] `login_401_on_wrong_password`
  - [x] `login_401_on_unknown_email`
  - [x] `protected_route_returns_401_without_token`
  - [x] `protected_route_returns_200_with_valid_token`
  - [x] `refresh_returns_new_token`
  - [x] `old_token_rejected_after_refresh`
  - [x] `logout_returns_200`
  - [x] `token_rejected_after_logout`

## Dev Agent Record

**Completed:** 2026-04-28 (pre-existing implementation detected during correct-course workflow)
**Story file created:** 2026-04-30 (retrospective after artifact realignment)
**Tests:** 9 passing (part of 44-test suite / 124 assertions)
