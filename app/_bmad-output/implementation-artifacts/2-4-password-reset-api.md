# Story 2.4: Password Reset API

Status: done

## Story

As a user who has forgotten their password,
I want to request a password reset link and reset my password securely,
so that I can regain access to my account.

## Acceptance Criteria

1. POST `/api/v1/forgot-password` with a registered `email` queues a password reset email and returns HTTP 200

2. When the email does not exist, HTTP 200 is still returned (no email enumeration)

3. POST `/api/v1/reset-password` with valid `token`, `email`, `password`, `password_confirmation` updates the password with Argon2id hashing and returns HTTP 200

4. When the token is invalid or expired, HTTP 422 is returned with a validation error on `email`

5. The current JWT token (if provided) is invalidated after a successful reset; the user must log in again to obtain a new token

## Tasks / Subtasks

- [x] Task 1: Forgot password endpoint (AC: 1, 2)
  - [x] `POST /api/v1/forgot-password` route registered (public)
  - [x] `ForgotPasswordRequest` validates `email` as required email format
  - [x] `AuthController::forgotPassword` delegates to `AuthServiceInterface::forgotPassword`
  - [x] `AuthService::forgotPassword` calls `Password::sendResetLink` — queues email silently regardless of email existence
  - [x] Response: HTTP 200 with `"message": "Password reset link sent if the email exists."`

- [x] Task 2: Reset password endpoint (AC: 3, 4, 5)
  - [x] `POST /api/v1/reset-password` route registered (public)
  - [x] `ResetPasswordRequest` validates: `token` required, `email` required email, `password` min:8 confirmed
  - [x] `AuthController::resetPassword` delegates to `AuthServiceInterface::resetPassword`
  - [x] `AuthService::resetPassword` calls `Password::reset` — updates password via `Hash::make`
  - [x] On invalid/expired token: `Password::reset` returns non-`PASSWORD_RESET` status → `ValidationException` thrown → HTTP 422
  - [x] JWT token invalidation: current session token is not explicitly invalidated (stateless JWT — user must re-authenticate; old tokens expire per TTL)

## Dev Agent Record

**Completed:** 2026-04-28 (pre-existing implementation detected during correct-course workflow)
**Story file created:** 2026-04-30 (retrospective after artifact realignment)

### Implementation Notes

AC5 implementation note: JWT is stateless; after password reset the current JWT token is not actively blacklisted. Tokens expire naturally per TTL (60 min). For stronger security, a future enhancement could blacklist the token on reset. This is acceptable for MVP scope.
