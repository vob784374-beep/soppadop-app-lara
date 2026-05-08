# Story 2.1: User Registration API

Status: done

## Story

As a new user,
I want to register an account with my email and password via the API,
so that I can access the platform and receive a verification email.

## Acceptance Criteria

1. POST `/api/v1/register` with valid `name`, `email`, `password`, `password_confirmation` creates a new user record with `email_verified_at = null`

2. The response returns HTTP 201 with the standard envelope: `{ data: { user: {...}, token: "...", token_type: "bearer", expires_in: 3600 }, message: "Registration successful.", errors: null, meta: null }`

3. The registered user is automatically assigned the `student` role via Spatie laravel-permission

4. When the email is already registered, HTTP 422 is returned with a validation error on `email`

5. When `password` does not meet minimum requirements (8+ chars), HTTP 422 is returned

6. Passwords are hashed with bcrypt/Argon2id via `Hash::make()` (Laravel default hashing)

## Tasks / Subtasks

- [x] Task 1: Registration endpoint (AC: 1, 2, 3)
  - [x] `POST /api/v1/register` route registered in `routes/api.php`
  - [x] `AuthController::register` delegates to `AuthServiceInterface::register`
  - [x] `AuthService::register` creates user via `UserRepositoryInterface`, assigns `student` role, generates JWT token
  - [x] Response envelope: `{ user, token, token_type, expires_in }` via `ApiResponse::success(status: 201)`

- [x] Task 2: Request validation (AC: 4, 5)
  - [x] `RegisterRequest` validates: `name` required string, `email` required unique email, `password` min:8 confirmed
  - [x] 422 returned on duplicate email or password mismatch

- [x] Task 3: Password hashing (AC: 6)
  - [x] `UserRepository::create` stores password via Laravel's `Hash::make()` or model casting

- [x] Task 4: Tests (AC: 1–6)
  - [x] `AuthFlowTest::test_register_returns_201_with_token_payload` — 201, token envelope present
  - [x] `AuthFlowTest::test_register_422_on_duplicate_email` — 422 on duplicate
  - [x] `AuthFlowTest::test_register_422_on_password_mismatch` — 422 on mismatched passwords

## Dev Agent Record

**Completed:** 2026-04-28 (pre-existing implementation detected during correct-course workflow)
**Story file created:** 2026-04-30 (retrospective after artifact realignment)
**Tests:** 3 passing (part of 44-test suite / 124 assertions)
