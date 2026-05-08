# Story 2.5: Profile Management API

Status: done

## Story

As an authenticated user,
I want to view and update my profile information via the API,
so that my account data stays current and accurate.

## Acceptance Criteria

1. GET `/api/v1/profile` with a valid Bearer token returns HTTP 200 with the user's profile data (`id`, `name`, `email`, `email_verified_at`, `created_at`) via `UserResource`

2. PATCH `/api/v1/profile` with valid `name` and/or `email` updates the user record and returns HTTP 200 with the updated profile

3. Password update is supported: PATCH `/api/v1/profile` with `password` + `password_confirmation` (min 8 chars) updates the password securely

4. Unauthenticated requests to both endpoints return HTTP 401 via `jwt.authenticate` middleware

## Tasks / Subtasks

- [x] Task 1: Show profile endpoint (AC: 1, 4)
  - [x] `GET /api/v1/profile` route registered inside `jwt.authenticate` group
  - [x] `ProfileController::show` calls `ProfileService::getProfile(userId)`
  - [x] `ProfileService::getProfile` fetches user via `UserRepositoryInterface::findById`
  - [x] Response: `UserResource` (id, name, email, email_verified_at, created_at)

- [x] Task 2: Update profile endpoint (AC: 2, 3, 4)
  - [x] `PATCH /api/v1/profile` route registered inside `jwt.authenticate` group
  - [x] `UpdateProfileRequest` validates: `name` sometimes string max:255, `email` sometimes unique (ignores own), `password` sometimes min:8 confirmed
  - [x] `ProfileController::update` calls `ProfileService::updateProfile(userId, validated)`
  - [x] `ProfileService::updateProfile` uses `Arr::only($data, ['name', 'email', 'password'])` — updates only allowed fields
  - [x] Response: HTTP 200 with updated `UserResource` + `"message": "Profile updated successfully."`

## Dev Agent Record

**Completed:** 2026-04-28 (pre-existing implementation detected during correct-course workflow)
**Story file created:** 2026-04-30 (retrospective after artifact realignment)

### Deviations from Original Story Plan

- **Endpoint path:** Implemented as `/profile` (not `/users/me` as originally planned) — simpler and equally valid for MVP
- **HTTP method:** PATCH (not PUT) — correct REST semantics for partial update
- **Avatar field:** Not implemented — no `avatar` column in `users` table; deferred to future enhancement
- **Email update:** Allowed (with uniqueness validation) rather than blocked — implementation chose a permissive MVP approach
