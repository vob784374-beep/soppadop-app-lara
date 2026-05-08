---
stepsCompleted: [1, 2, 3, 4]
status: 'complete'
completedAt: '2026-04-26'
inputDocuments: ['prd.md', 'architecture.md']
workflowType: 'epics-and-stories'
project_name: 'app'
user_name: 'Bang'
date: '2026-04-26'
---

# app - Epic Breakdown

## Overview

This document provides the complete epic and story breakdown for app, decomposing the requirements from the PRD and Architecture into implementable stories.

## Requirements Inventory

### Functional Requirements

FR1: User registration — The system shall allow a user to register with email and password and receive a verification email.
FR2: User login — The system shall allow a registered user to authenticate and obtain a JWT bearer token for API requests.
FR3: Password reset — The system shall allow users to request a password reset email and reset their password securely.
FR4: Profile management — The system shall allow authenticated users to view and update their profile data (name, avatar, preferences).
FR5: Course management — Instructors shall be able to create, update, and delete courses; students shall be able to view and enroll in courses.
FR6: Lesson management — Instructors shall be able to create and manage lessons nested within courses; enrolled students shall be able to view lesson content.
FR7: Assignment management — Instructors shall be able to create assignments with deadlines; students shall be able to submit assignments before the deadline.
FR8: Grading — Instructors shall be able to grade student submissions; students shall be able to view their own grades.
FR9: Enrollment — Students shall be able to enroll in open courses; enrollment gates access to lessons and assignments.
FR10: Pagination and filtering — The system shall provide paginated APIs with server-side filtering for all list endpoints.

### NonFunctional Requirements

NFR1: Performance — API endpoints shall respond within 300ms p95 under normal load for common GET requests.
NFR2: Availability — The system shall maintain 99.9% uptime for user-facing APIs monthly.
NFR3: Security — All sensitive data at rest and in transit must be encrypted; passwords must be hashed with Argon2id.
NFR4: Scalability — The system shall scale horizontally for stateless services (backend/frontend) and support read-replicas for the DB.
NFR5: Observability — Application logs must be structured JSON exported to stdout; critical errors sent to Sentry.

### Additional Requirements

- AR1: Initialize Laravel backend using `composer create-project laravel/laravel backend` (Laravel 12.x)
- AR2: Initialize Next.js frontend using `pnpm create next-app frontend --typescript --tailwind --eslint --app --turbopack --import-alias "@/*"` (Next.js 16)
- AR3: Docker Compose orchestration with 5 services: backend (8081), frontend (3001), mysql (3307), redis (6380), nginx (80/443) — all ports env-var driven
- AR4: Nginx reverse proxy configuration routing `/api/*` → backend, `/*` → frontend, with multi-env conf files
- AR5: Multi-environment support (dev/staging/prod) with separate `.env` files per environment — secrets never baked into images
- AR6: Laravel Sanctum authentication configured for dual-mode (HTTP-only cookie session for SPA + bearer token for API clients)
- AR7: Centralized exception handler (`Handler.php`) returning standard API response envelope `{ data, message, errors, meta }` for ALL exceptions
- AR8: Repository pattern with interfaces bound in `RepositoryServiceProvider`: `UserRepositoryInterface`, `ItemRepositoryInterface`, plus `BaseRepository` with paginate/filter helpers
- AR9: Redis configured as both cache driver (TTL-based, tag-invalidation) and queue driver (async jobs: email verification, password reset)
- AR10: Sentry SDK integration on backend (Laravel) and frontend (Next.js) with structured JSON logging to stdout via Monolog
- AR11: Frontend dependency installation: `axios`, `@tanstack/react-query`, `zustand`, `react-hook-form`, `zod`
- AR12: Frontend foundation files: `src/lib/apiClient.ts` (Axios + 401 interceptor), `src/lib/queryClient.ts`, `src/lib/queryKeys.ts` (centralized key factory)
- AR13: Database migrations with proper indexing: `email` (unique), `user_id` FK columns, `created_at` on high-volume tables
- AR14: Multi-stage Dockerfiles for backend and frontend (dev target with hot-reload, prod target with optimized minimal image)

### UX Design Requirements

No UX design document found. UI stories will be derived from functional requirements and architecture patterns.

### FR Coverage Map

FR1 → Epic 2 — User registration + email verification
FR2 → Epic 2 — Login with JWT bearer token
FR3 → Epic 2 — Password reset via email
FR4 → Epic 2 — Profile view + update (name, avatar, preferences)
FR5 → Epic 4 — Course management (CRUD + RBAC/ABAC)
FR6 → Epic 4 — Lesson management (CRUD + RBAC/ABAC)
FR7 → Epic 4 — Assignment management (CRUD + RBAC/ABAC)
FR8 → Epic 4 — Grading (submissions + instructor grade)
FR9 → Epic 4 — Enrollment (student self-enroll, gates lesson/assignment access)
FR10 → Epic 3 & 4 — Paginated, server-side filtered lists (items + LMS resources)
AR1–AR14 → Epic 1 — Platform foundation, infrastructure, dev tooling

## Epic List

### Epic 1: Platform Foundation & Infrastructure
Users (and developers) can run the entire system locally and in staging/production via Docker, with all environments, databases, and services properly configured. Every subsequent epic depends on this foundation being in place.
**ARs covered:** AR1, AR2, AR3, AR4, AR5, AR6, AR7, AR8, AR9, AR10, AR11, AR12, AR13, AR14

### Epic 2: User Identity & Account Management
Users can register an account, verify their email, log in securely, recover a forgotten password, and manage their profile — forming a complete, self-service identity lifecycle.
**FRs covered:** FR1, FR2, FR3, FR4

### Epic 3: Core Item Management
Authenticated users can create, view, update, and delete items — the primary product value surface — and browse their items through paginated, server-side filtered lists.
**FRs covered:** FR10 (items domain)

### Epic 4: IELTS LMS Domain
Instructors can create and manage courses, lessons, and assignments. Students can enroll in courses, complete lessons, submit assignments, and receive grades. Role-based (RBAC) and attribute-based (ABAC) access control enforces appropriate access at every endpoint.
**FRs covered:** FR5, FR6, FR7, FR8, FR9, FR10

---

## Epic 1: Platform Foundation & Infrastructure

The full-stack system runs locally and in staging/production via Docker Compose. All environments are configured, all services are healthy, and the codebase follows the layered architecture pattern defined in the architecture document. This epic is a prerequisite for all feature epics.

### Story 1.1: Initialize Laravel Backend Project

As a developer,
I want a clean Laravel 12 backend project initialized with the correct directory structure and configuration,
So that all backend feature stories have a consistent, production-ready foundation to build upon.

**Acceptance Criteria:**

**Given** the project root directory exists
**When** the developer runs `composer create-project laravel/laravel backend`
**Then** a `backend/` directory is created with a working Laravel 12.x installation
**And** `backend/.env` is configured with `APP_ENV=local`, `APP_PORT=8081`, `DB_PORT=3307`, `REDIS_PORT=6380`
**And** the directory structure matches the architecture: `app/Http/Controllers/Api/V1/`, `app/Services/`, `app/Repositories/Contracts/`, `app/Models/`
**And** `backend/Dockerfile` exists with a multi-stage build (dev + prod targets)
**And** `backend/.env.example` is committed with all required keys and no secret values
**And** `php artisan --version` returns Laravel 12.x

---

### Story 1.2: Initialize Next.js Frontend Project

As a developer,
I want a clean Next.js 16 frontend project initialized with TypeScript, Tailwind, and App Router,
So that all frontend feature stories have a consistent, production-ready foundation to build upon.

**Acceptance Criteria:**

**Given** the project root directory exists
**When** the developer runs `pnpm create next-app frontend --typescript --tailwind --eslint --app --turbopack --import-alias "@/*"`
**Then** a `frontend/` directory is created with a working Next.js 16 installation
**And** the directory structure matches the architecture: `src/app/(auth)/`, `src/app/(dashboard)/`, `src/features/`, `src/components/ui/`, `src/lib/`, `src/stores/`, `src/types/`
**And** `frontend/Dockerfile` exists with a multi-stage build (dev + prod targets)
**And** `frontend/.env.local.example` is committed with `NEXT_PUBLIC_API_URL=http://localhost:8081`
**And** `pnpm dev` starts the development server on port 3001 (not default 3000)
**And** TypeScript strict mode is enabled in `tsconfig.json`

---

### Story 1.3: Docker Compose & Nginx Orchestration

As a developer,
I want all services orchestrated via Docker Compose with Nginx as reverse proxy,
So that the full system can be started with a single command and all services communicate correctly on their custom ports.

**Acceptance Criteria:**

**Given** Docker and Docker Compose are installed
**When** the developer runs `docker compose up`
**Then** all 5 services start: backend (8081), frontend (3001), mysql (3307), redis (6380), nginx (80)
**And** all ports are driven by environment variables — no hardcoded port values exist in any config file
**And** Nginx routes `/api/*` requests to `backend:8081` and `/*` requests to `frontend:3001`
**And** all services are on the `app_network` internal bridge — only Nginx exposes ports externally
**And** `docker compose up` from a clean state starts successfully within one command
**And** `.env.example`, `.env.staging.example`, `.env.production.example` are committed to the repo root
**And** `nginx/conf.d/dev.conf` and `nginx/conf.d/prod.conf` exist with correct routing rules

---

### Story 1.4: Database Migrations & Schema Foundation

As a developer,
I want all initial database migrations created and indexed correctly,
So that the schema is version-controlled, reproducible across environments, and performs within NFR1 targets.

**Acceptance Criteria:**

**Given** MySQL is running on port 3307
**When** the developer runs `php artisan migrate`
**Then** four tables are created: `users`, `personal_access_tokens`, `password_reset_tokens`, `items`
**And** `users` table has a unique index on `email` and index on `created_at`
**And** `items` table has an index on `user_id` (FK) and `created_at`
**And** all migrations are idempotent — running `migrate:fresh` produces the same schema
**And** `UserFactory` and `ItemFactory` exist and produce valid model instances
**And** `php artisan db:seed` runs without errors in development

---

### Story 1.5: Redis Cache & Queue Configuration

As a developer,
I want Redis configured as both the cache driver and queue driver,
So that API response caching and async job dispatch (email verification, password reset) work correctly.

**Acceptance Criteria:**

**Given** Redis is running on port 6380
**When** `CACHE_DRIVER=redis` and `QUEUE_CONNECTION=redis` are set in `.env`
**Then** `Cache::put('test', 'value', 60)` stores and retrieves correctly via Redis
**And** `Queue::push(new TestJob())` dispatches a job visible in the Redis queue
**And** a queue worker service is defined in `docker-compose.yml` running `php artisan queue:work`
**And** the queue worker automatically restarts on failure (Docker restart policy: `unless-stopped`)
**And** `REDIS_HOST`, `REDIS_PORT`, and `REDIS_PASSWORD` are all read from environment variables

---

### Story 1.6: API Foundation — Envelope, Exception Handler & Repository Pattern

As a developer,
I want the API response envelope, centralized exception handler, and repository pattern infrastructure in place,
So that every API response is consistent and all feature stories build on a correct, layered architecture.

**Acceptance Criteria:**

**Given** the Laravel backend is running
**When** any API endpoint returns a successful response
**Then** the response follows the envelope: `{ "data": {}, "message": "string", "errors": null, "meta": null }`
**And** when a validation error occurs, the response returns HTTP 422 with `errors` populated
**And** when an unhandled exception occurs, the response returns HTTP 500 with `"message": "An unexpected error occurred."` — no stack trace exposed
**And** `app/Exceptions/Handler.php` handles all exception types and maps them to the standard envelope
**And** `BaseRepository`, `UserRepositoryInterface`, `ItemRepositoryInterface` exist with stub implementations
**And** `RepositoryServiceProvider` binds interfaces to implementations and is registered in `bootstrap/providers.php`
**And** Laravel Sanctum is installed and `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` has been run
**And** `ForceJsonResponse` middleware is registered to ensure all API responses are JSON

---

### Story 1.7: Frontend Foundation — API Client, Query Client & State Management

As a developer,
I want the Axios API client, TanStack Query client, Zustand stores, and all frontend dependencies configured,
So that all frontend feature stories can make API calls and manage state consistently.

**Acceptance Criteria:**

**Given** the frontend project exists
**When** `pnpm install` is run
**Then** `axios`, `@tanstack/react-query`, `zustand`, `react-hook-form`, `zod` are installed
**And** `src/lib/apiClient.ts` exports an Axios instance with `baseURL` from `NEXT_PUBLIC_API_URL` and a 401 interceptor that clears auth state and redirects to `/login`
**And** `src/lib/queryClient.ts` exports a configured `QueryClient` instance
**And** `src/lib/queryKeys.ts` exports the centralized `queryKeys` factory object with keys for `users` and `items`
**And** `src/stores/authStore.ts` exports a Zustand store with `user`, `setUser`, `clearAuth`
**And** `src/stores/uiStore.ts` exports a Zustand store for UI state (toasts, modal visibility)
**And** `src/types/api.types.ts` defines `ApiResponse<T>` and `PaginatedMeta` TypeScript types
**And** `QueryClientProvider` wraps the root layout in `src/app/layout.tsx`

---

### Story 1.8: Observability — Sentry & Structured Logging

As a developer,
I want Sentry error tracking and structured JSON logging configured on both backend and frontend,
So that production errors are captured automatically and logs are machine-readable per NFR5.

**Acceptance Criteria:**

**Given** `SENTRY_DSN` is set in the environment
**When** an unhandled exception occurs in the Laravel backend
**Then** the exception is automatically reported to Sentry
**And** all Laravel logs are written as structured JSON to stdout (not to `storage/logs/laravel.log` in production)
**And** the log format includes: `timestamp`, `level`, `message`, `context` fields
**And** `LOG_CHANNEL=stderr` and `LOG_FORMAT=json` are set in production `.env`
**And** the Next.js frontend has `@sentry/nextjs` installed and configured with `NEXT_PUBLIC_SENTRY_DSN`
**And** Sentry DSN values are read from environment variables — never hardcoded
**And** Sentry is disabled (or uses a test DSN) in development

---

## Epic 2: User Identity & Account Management

Users can register an account, verify their email, log in securely, recover a forgotten password, and manage their profile — forming a complete, self-service identity lifecycle.

### Story 2.1: User Registration API

As a new user,
I want to register an account with my email and password via the API,
So that I can access the platform and receive a verification email.

**Acceptance Criteria:**

**Given** a POST request to `/api/v1/auth/register` with valid `name`, `email`, `password`, `password_confirmation`
**When** the email is not already registered
**Then** a new user record is created in the `users` table with `email_verified_at = null`
**And** the response returns HTTP 201 with the standard envelope: `{ data: { user: {...}, token: "...", token_type: "bearer", expires_in: 3600 }, message, errors: null, meta: null }`
**And** a `UserRegistered` event is dispatched, queuing a `SendWelcomeEmail` job via Redis
**And** the welcome/verification email is sent to the registered email address
**And** when the email is already registered, the response returns HTTP 422 with a validation error on `email`
**And** when `password` does not meet minimum requirements (8+ chars), HTTP 422 is returned
**And** passwords are hashed with Argon2id via `Hash::make()`

---

### Story 2.2: Email Verification

As a registered user,
I want to verify my email address by clicking the link in my welcome email,
So that my account is fully activated and I can access protected features.

**Acceptance Criteria:**

**Given** a registered user with `email_verified_at = null`
**When** the user clicks the verification link (GET `/api/v1/auth/email/verify/{id}/{hash}`) with a valid signature
**Then** `email_verified_at` is set to the current timestamp
**And** the response returns HTTP 200 with `"message": "Email verified successfully."`
**And** when the link has an invalid or expired signature, HTTP 403 is returned
**And** POST `/api/v1/auth/email/resend` resends the verification email and returns HTTP 200
**And** resend is rate-limited to prevent abuse (max 3 requests per minute per user)

---

### Story 2.3: User Login & Logout API

As a registered user,
I want to log in with my email and password and receive an access token,
So that I can make authenticated API requests and use the platform.

**Acceptance Criteria:**

**Given** a POST request to `/api/v1/auth/login` with valid `email` and `password`
**When** the credentials match a verified user record
**Then** the response returns HTTP 200 with the standard envelope containing: `user`, `token`, `token_type: "bearer"`, `expires_in: 3600`
**And** when credentials are invalid, HTTP 401 is returned with `"message": "Invalid credentials."`
**And** when the email is not verified, HTTP 403 is returned with `"message": "Please verify your email."`
**And** POST `/api/v1/auth/refresh` with a valid Bearer token returns HTTP 200 with a new token; the old token is blacklisted in Redis
**And** POST `/api/v1/auth/logout` with a valid Bearer token blacklists the token and returns HTTP 200 with `"message": "Logged out successfully."`
**And** after logout, the blacklisted token returns HTTP 401 with `"message": "Unauthenticated."`

---

### Story 2.4: Password Reset API

As a user who has forgotten their password,
I want to request a password reset link and reset my password securely,
So that I can regain access to my account.

**Acceptance Criteria:**

**Given** a POST request to `/api/v1/auth/forgot-password` with a registered `email`
**When** the email exists in the system
**Then** a password reset email is queued and sent, and HTTP 200 is returned
**And** when the email does not exist, HTTP 200 is still returned (no email enumeration)
**And** given a POST request to `/api/v1/auth/reset-password` with valid `token`, `email`, `password`, `password_confirmation`
**When** the token is valid and not expired
**Then** the user's password is updated with Argon2id hashing and HTTP 200 is returned
**And** the current JWT token (if provided) is invalidated after a successful reset; the user must log in again to obtain a new token
**And** when the token is invalid or expired, HTTP 422 is returned

---

### Story 2.5: Profile Management API

As an authenticated user,
I want to view and update my profile information via the API,
So that my account data stays current and accurate.

**Acceptance Criteria:**

**Given** a GET request to `/api/v1/users/me` with a valid bearer token
**When** the token belongs to an existing user
**Then** HTTP 200 is returned with the user's profile data (`id`, `name`, `email`, `avatar`, `email_verified_at`, `created_at`) via `UserResource`
**And** given a PUT request to `/api/v1/users/me` with valid `name` and/or `avatar` fields
**When** the request is authenticated
**Then** the user record is updated and HTTP 200 is returned with the updated profile
**And** when `email` is included in the update and it differs from the current email, HTTP 422 is returned (email change not supported in MVP)
**And** unauthenticated requests to both endpoints return HTTP 401

---

### Story 2.6: Authentication UI — Register, Login & Password Reset

As a new or returning user,
I want registration, login, and password reset pages in the web app,
So that I can create an account, access it, and recover it without technical knowledge.

**Acceptance Criteria:**

**Given** the user visits `/register`
**When** they submit the registration form with valid data
**Then** the `POST /api/v1/auth/register` API is called via `auth.service.ts`
**And** on success, the user is redirected to a "Check your email" confirmation page
**And** on validation error, inline field errors are displayed below each field
**And** given the user visits `/login` and submits valid credentials
**When** the login API returns HTTP 200
**Then** the `authStore` is populated with the user object and token
**And** the user is redirected to `/` (dashboard)
**And** given the user visits `/forgot-password` and submits their email
**When** the forgot-password API returns HTTP 200
**Then** a success message is displayed regardless of whether the email exists
**And** all forms use React Hook Form + Zod validation with inline error messages
**And** all forms show a loading state (disabled button + spinner) while the API call is in flight
**And** the `(auth)` route group is accessible only to unauthenticated users — authenticated users are redirected to `/`

---

### Story 2.7: Profile Management UI

As an authenticated user,
I want a profile page where I can view and update my name and avatar,
So that I can keep my account information accurate.

**Acceptance Criteria:**

**Given** the authenticated user visits `/profile`
**When** the page loads
**Then** the current profile data is fetched via `useProfile` hook (TanStack Query) and displayed
**And** a skeleton loader is shown while the data is fetching
**And** given the user updates their name and/or avatar and submits the form
**When** the PUT `/api/v1/users/me` API returns HTTP 200
**Then** the profile data in TanStack Query cache is invalidated and refreshed
**And** a success toast notification is displayed
**And** on API error, an inline error message is displayed on the form
**And** the `/profile` route is protected — unauthenticated users are redirected to `/login`

---

## Epic 3: Core Item Management

Authenticated users can create, view, update, and delete items — the primary product value surface — and browse their items through paginated, server-side filtered lists.

### Story 3.1: Items CRUD API

As an authenticated user,
I want to create, read, update, and delete items via the API,
So that I can manage my content through a complete lifecycle.

**Acceptance Criteria:**

**Given** a POST request to `/api/v1/items` with valid `title` and `description` fields and a valid bearer token
**When** the request is authenticated
**Then** a new item is created linked to the authenticated user and HTTP 201 is returned with the item data via `ItemResource`
**And** given a GET request to `/api/v1/items/{id}`
**When** the item belongs to the authenticated user
**Then** HTTP 200 is returned with the item data
**And** when the item belongs to a different user, HTTP 403 is returned
**And** given a PUT request to `/api/v1/items/{id}` with updated fields
**When** the item belongs to the authenticated user
**Then** the item is updated and HTTP 200 is returned with the updated data
**And** given a DELETE request to `/api/v1/items/{id}`
**When** the item belongs to the authenticated user
**Then** the item is deleted and HTTP 204 is returned with no body
**And** all endpoints return HTTP 401 for unauthenticated requests
**And** `ItemController` delegates to `ItemService` which delegates to `ItemRepository` — no DB queries in the controller

---

### Story 3.2: Items List API with Pagination & Filtering

As an authenticated user,
I want to retrieve my items as a paginated, filterable list via the API,
So that I can efficiently browse large numbers of items.

**Acceptance Criteria:**

**Given** a GET request to `/api/v1/items` with a valid bearer token
**When** the request is authenticated
**Then** HTTP 200 is returned with a paginated list of the user's items (default 15 per page)
**And** the response `meta` contains `total`, `per_page`, `current_page`, `last_page`
**And** `?per_page=N` query param overrides the page size (max 100)
**And** `?sort_by=created_at&sort_dir=desc` query params sort the results
**And** list responses are cached in Redis with a TTL of 60 seconds, tagged for invalidation on item create/update/delete
**And** `BaseRepository::paginate()` handles pagination and `BaseRepository::filter()` handles query params
**And** the endpoint responds within 300ms p95 under normal load (NFR1)

---

### Story 3.3: Item Management UI — CRUD

As an authenticated user,
I want pages to create, view, edit, and delete my items in the web app,
So that I can manage my content without using the API directly.

**Acceptance Criteria:**

**Given** the authenticated user visits `/items`
**When** the page loads
**Then** the item list is fetched via `useItems` (TanStack Query) and displayed as `ItemCard` components
**And** a skeleton loader (`Skeleton.tsx`) is shown while fetching
**And** given the user clicks "Create Item" and submits the `ItemForm` with valid data
**When** the POST `/api/v1/items` API succeeds
**Then** the items list query is invalidated and the new item appears in the list
**And** given the user visits `/items/{id}` and clicks "Edit"
**When** the PUT `/api/v1/items/{id}` API succeeds
**Then** an optimistic update immediately reflects the change in the UI before the API confirms
**And** given the user clicks "Delete" on an item
**When** the DELETE `/api/v1/items/{id}` API succeeds
**Then** the item is removed from the list and a success toast is shown
**And** all API calls go through `items.service.ts` — no inline Axios calls in components
**And** the `/items` route group is protected — unauthenticated users are redirected to `/login`

---

### Story 3.4: Item List UI with Pagination & Filtering

As an authenticated user,
I want to paginate through my items and filter them by keyword or sort order,
So that I can efficiently find items when I have many.

**Acceptance Criteria:**

**Given** the user is on `/items` with more than 15 items
**When** the page loads
**Then** only 15 items are shown with pagination controls (Previous / Next / page numbers)
**And** clicking a page number fetches the correct page via `usePaginatedQuery` hook
**And** given the user changes the sort order (e.g., "Newest first")
**When** the sort param changes
**Then** `?sort_by=created_at&sort_dir=desc` is appended to the API request and results update
**And** the current page, per_page, and sort values are reflected in the URL query string for shareability
**And** skeleton loaders are shown during page transitions
**And** `usePaginatedQuery.ts` is the single shared hook for all paginated list views in the app

---

## Epic 4: IELTS LMS Domain

Instructors can create and manage courses, lessons, and assignments. Students can enroll in courses, complete lessons, submit assignments, and receive grades. Admins manage users and platform configuration. Role-based (RBAC) and attribute-based (ABAC) access control enforces appropriate access at every endpoint.

**FRs covered:** FR5, FR6, FR7, FR8, FR9, FR10

---

### Story 4.1: RBAC/ABAC Foundation — Roles, Permissions & Seeder

As a developer,
I want roles, permissions, and RBAC/ABAC services in place and verified by tests,
So that every LMS endpoint can enforce access control correctly.

**Acceptance Criteria:**

**Given** the application is running
**When** `php artisan db:seed --class=RolePermissionSeeder` is run
**Then** three roles exist: `admin`, `instructor`, `student`
**And** the full permission matrix from `rbac-abac-architecture.md` is seeded (view-courses, create-courses, edit-courses, delete-courses, view-lessons, create-lessons, edit-lessons, view-assignments, create-assignments, submit-assignments, grade-submissions, enroll-courses, manage-users)
**And** `AuthorizationService` with `RbacChecker` and `AbacChecker` are wired via `RepositoryServiceProvider`
**And** the `permission` middleware is registered and applicable to protected routes
**And** tests confirm: seeder runs without errors, role-permission assignments match the matrix, `RbacChecker` returns correct booleans, `AbacChecker` correctly gates ownership and enrollment checks

---

### Story 4.2: Courses API (CRUD + RBAC/ABAC)

As an instructor or student,
I want a fully functional Courses API with role-gated endpoints,
So that instructors can manage courses and students can browse and enroll.

**Acceptance Criteria:**

**Given** a GET request to `/api/v1/courses` with any authenticated user
**When** the user has the `view-courses` permission
**Then** HTTP 200 is returned with a paginated list of courses
**And** given a POST request to `/api/v1/courses` with valid `title`, `description`
**When** the user has the `create-courses` permission (instructor/admin)
**Then** HTTP 201 is returned with the created course; `instructor_id` is set to the authenticated user
**And** given a GET request to `/api/v1/courses/{id}`
**When** the user has `view-courses` permission AND (is instructor/admin OR is enrolled in the course)
**Then** HTTP 200 is returned with the course data
**And** given a PATCH request to `/api/v1/courses/{id}`
**When** the user has `edit-courses` AND owns the course (ABAC)
**Then** the course is updated and HTTP 200 is returned
**And** given a DELETE request to `/api/v1/courses/{id}`
**When** the user has `delete-courses` AND owns the course (ABAC)
**Then** the course is deleted and HTTP 204 is returned
**And** unauthorized attempts return HTTP 403; unauthenticated requests return HTTP 401

---

### Story 4.3: Lessons API (CRUD + RBAC/ABAC)

As an instructor or enrolled student,
I want to create and view lessons nested within courses,
So that course content is organized and access-controlled by enrollment.

**Acceptance Criteria:**

**Given** a GET request to `/api/v1/courses/{courseId}/lessons`
**When** the user has `view-lessons` AND is enrolled or is instructor/admin
**Then** HTTP 200 is returned with paginated lessons for the course
**And** given a POST request to `/api/v1/courses/{courseId}/lessons` with valid `title`, `content`, `order`
**When** the user has `create-lessons` AND owns the course (ABAC)
**Then** HTTP 201 is returned with the new lesson
**And** given a GET request to `/api/v1/lessons/{id}`
**When** the user has `view-lessons` AND is enrolled in the parent course or is instructor/admin
**Then** HTTP 200 is returned with lesson data
**And** given a PATCH request to `/api/v1/lessons/{id}`
**When** the user has `edit-lessons` AND owns the parent course (ABAC)
**Then** the lesson is updated and HTTP 200 is returned
**And** unauthorized attempts return HTTP 403; unauthenticated requests return HTTP 401

---

### Story 4.4: Assignments API (CRUD + RBAC/ABAC)

As an instructor or enrolled student,
I want to create and view assignments within courses,
So that instructors can set assessments and students can see their work.

**Acceptance Criteria:**

**Given** a GET request to `/api/v1/courses/{courseId}/assignments`
**When** the user has `view-assignments` AND is enrolled or is instructor/admin
**Then** HTTP 200 is returned with paginated assignments for the course
**And** given a POST request to `/api/v1/courses/{courseId}/assignments` with valid `title`, `description`, `due_date`
**When** the user has `create-assignments` AND owns the course (ABAC)
**Then** HTTP 201 is returned with the new assignment
**And** given a GET request to `/api/v1/assignments/{id}`
**When** the user has `view-assignments` AND is enrolled in the parent course or is instructor/admin
**Then** HTTP 200 is returned with assignment data including `due_date`
**And** unauthorized attempts return HTTP 403; unauthenticated requests return HTTP 401

---

### Story 4.5: Submissions API (RBAC/ABAC)

As a student or instructor,
I want to submit assignments and grade submissions,
So that the assessment lifecycle is complete from submission to graded result.

**Acceptance Criteria:**

**Given** a POST request to `/api/v1/assignments/{assignmentId}/submissions` with valid `content`
**When** the user has `submit-assignments`, is enrolled, AND the `due_date` has not passed (ABAC)
**Then** HTTP 201 is returned with the submission record
**And** when the deadline has passed, HTTP 422 is returned with `"message": "Assignment deadline has passed."`
**And** given a GET request to `/api/v1/assignments/{assignmentId}/submissions`
**When** the user has `grade-submissions` (instructor/admin)
**Then** HTTP 200 is returned with paginated submissions for the assignment
**And** given a GET request to `/api/v1/submissions/{id}`
**When** the user is the submission owner OR has `grade-submissions`
**Then** HTTP 200 is returned with submission data including `grade` and `feedback`
**And** given a PATCH request to `/api/v1/submissions/{id}/grade` with `grade`, `feedback`
**When** the user has `grade-submissions` AND owns the parent assignment's course (ABAC)
**Then** the submission is graded and HTTP 200 is returned
**And** unauthorized attempts return HTTP 403; unauthenticated requests return HTTP 401

---

### Story 4.6: Enrollments API (RBAC/ABAC)

As a student,
I want to enroll in open courses,
So that I gain access to lessons and assignments within that course.

**Acceptance Criteria:**

**Given** a POST request to `/api/v1/courses/{courseId}/enroll`
**When** the user has `enroll-courses` (student) AND is not already enrolled
**Then** an enrollment record is created and HTTP 201 is returned with `"message": "Enrolled successfully."`
**And** when the student is already enrolled, HTTP 409 is returned with `"message": "Already enrolled."`
**And** when the course does not exist, HTTP 404 is returned
**And** after enrollment, the student can access lessons and assignments for that course
**And** unauthenticated requests return HTTP 401; non-student roles attempting enrollment return HTTP 403

---

### Story 4.7: JWT Gap Closure — SOLID Compliance

As a developer,
I want the JWT auth layer to satisfy SOLID design principles with proper interfaces and typed middleware,
So that the codebase is maintainable and dependencies are invertible.

**Acceptance Criteria:**

**Given** the existing `JwtService` and `AuthService` implementations
**When** the gap closure story is complete
**Then** `JwtServiceInterface` exists in `app/Services/Contracts/` with all methods declared
**And** `AuthServiceInterface` exists in `app/Services/Contracts/` with all methods declared
**And** `RepositoryServiceProvider` binds both interfaces to their concrete implementations
**And** `AuthController` type-hints `AuthServiceInterface` (not the concrete class)
**And** `AuthService` type-hints `JwtServiceInterface` (not the concrete class)
**And** a custom `JwtAuthenticate` middleware exists, registered as `jwt.authenticate` alias
**And** `JwtAuthenticate` returns `ApiResponse::unauthorized('Unauthenticated.')` (typed 401) instead of the generic Laravel `auth:api` response
**And** all protected routes use the `jwt.authenticate` alias instead of `auth:api`
**And** the full test suite (44+ tests) still passes after all changes
