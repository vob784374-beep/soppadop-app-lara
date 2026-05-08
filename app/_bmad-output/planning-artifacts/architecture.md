---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
lastStep: 8
status: 'complete'
completedAt: '2026-04-26'
inputDocuments: ['prd.md']
workflowType: 'architecture'
project_name: 'app'
user_name: 'Bang'
date: '2026-04-26'
---

# Architecture Decision Document

_This document builds collaboratively through step-by-step discovery. Sections are appended as we work through each architectural decision together._

## Project Context Analysis

### Requirements Overview

**Functional Requirements:**
- FR1–FR4: Full user lifecycle — registration (email verification), login (HTTP-only cookie session + access token), password reset, profile management
- FR5: Core product domain — item CRUD operations (the primary value-delivery surface)
- FR6: Data access layer — paginated, server-side filtered list endpoints

Architecturally, FR1–FR4 drive a robust auth subsystem. FR5–FR6 drive the repository pattern and query builder strategy.

**Non-Functional Requirements:**
- Performance: <300ms p95 on common GETs — requires DB indexing, query optimization, and potential query caching
- Availability: 99.9% uptime — demands health checks, restart policies, and graceful degradation in Docker/orchestration
- Security: Encryption at rest/transit, Argon2/bcrypt hashing, secure secret handling across environments
- Scalability: Stateless services (backend + frontend) for horizontal scaling; read replica support for MySQL
- Observability: Structured JSON logs, Sentry integration for critical errors

**Scale & Complexity:**
- Primary domain: Full-stack Web (Laravel API + Next.js SSR/SSG)
- Complexity level: Medium (elevated by multi-environment + containerization)
- Estimated architectural components: 8 (API, Frontend, MySQL, Queue/Worker, Cache, Logging, Reverse Proxy, CI/CD pipeline)

### Technical Constraints & Dependencies

- Custom ports mandatory: Laravel 8081, Next.js 3001, MySQL 3307 (all env-var driven)
- Three environments required: development, staging, production
- Docker Compose as the local + staging orchestration layer
- MySQL as the only database (no NoSQL or alternative stores)
- Laravel Sanctum or JWT for auth (to be decided in tech stack step)
- Next.js App Router or Pages Router (to be decided — consistent choice required)

### Cross-Cutting Concerns Identified

- Authentication & Authorization: spans all API routes and frontend sessions
- Centralized Exception Handling: single standardized API error response format
- Observability: structured logging + Sentry wired across all services
- Environment Configuration: .env per environment, secrets never in image layers
- Database Migrations: versioned, idempotent, applied per-environment
- Secret Management: Docker secrets or env injection — no hardcoded credentials

## Starter Template Evaluation

### Primary Technology Domain

Full-stack Web — decoupled architecture:
- Backend API: Laravel (PHP)
- Frontend: Next.js (React/SSR)
- Database: MySQL

The two services are initialized as separate projects and orchestrated via Docker Compose.

### Backend Starter: Laravel via Composer

**Selected:** `composer create-project laravel/laravel backend` (Laravel 12.x — current stable)

**Rationale:** The official Composer installer gives a clean, un-opinionated base. Laravel Sail is NOT used — we build our own production-grade Dockerfile, so a plain install avoids Sail's dev-only opinions bleeding into production architecture.

**Initialization Command:**
```bash
composer create-project laravel/laravel backend
```

**Architectural Decisions Made by Starter:**
- Language: PHP 8.2+
- Routing: Laravel Router (RESTful resource routes)
- ORM: Eloquent (Active Record — Repository pattern layered on top)
- Auth scaffold: None by default (Sanctum added manually)
- Queue: Database/Redis driver (configured via .env)
- Logging: Monolog (structured JSON in production)
- Testing: PHPUnit + Pest-ready

### Frontend Starter: Next.js 16 via create-next-app

**Selected:** App Router (Next.js default — stable in v16)

**Rationale:** App Router is the production-recommended router as of Next.js 13+ and the stable default in v16. It enables React Server Components, streaming, and co-located layouts — ideal for SSR-heavy pages with API data fetching.

**Initialization Command:**
```bash
pnpm create next-app frontend \
  --typescript \
  --tailwind \
  --eslint \
  --app \
  --turbopack \
  --import-alias "@/*"
```

**Architectural Decisions Made by Starter:**
- Language: TypeScript (strict mode)
- Router: App Router (RSC-first, layouts, streaming)
- Styling: Tailwind CSS
- Build: Turbopack (fast dev HMR)
- Linting: ESLint + Next.js config
- Import alias: `@/*` → maps to `src/*`

**Note:** Project initialization for both backend and frontend should be the first two implementation stories before any feature development begins.

## Core Architectural Decisions

### Decision Priority Analysis

**Critical Decisions (Block Implementation):**
- Authentication strategy: Laravel Sanctum (dual-mode session + token)
- API response envelope: standardized `{ data, message, errors, meta }`
- Frontend state management: TanStack Query + Zustand
- Caching layer: Redis (queue + response cache)

**Important Decisions (Shape Architecture):**
- Reverse proxy: Nginx (SSL termination, routing)
- Error monitoring: Sentry (backend + frontend)
- API rate limiting: Laravel throttle middleware
- Password hashing: Argon2id (via Laravel Hash facade)

**Deferred Decisions (Post-MVP):**
- Read replicas for MySQL
- Full CI/CD pipeline implementation
- CDN / object storage (S3)

---

### Data Architecture

- **ORM:** Eloquent (Active Record) with Repository pattern on top
- **Migrations:** Laravel migration system — versioned, per-environment
- **Indexing strategy:** Index on `email` (unique), `user_id` FK columns, `created_at` on high-volume tables
- **Caching:** Redis via Laravel Cache facade (TTL-based, tag-invalidation for list endpoints)
- **Queue driver:** Redis (async jobs: email verification, password reset, notifications)

### Authentication & Security

- **Auth method:** JWT (`php-open-source-saver/jwt-auth`) — supersedes original Sanctum decision (2026-04-28)
  - API clients: JWT bearer tokens via `Authorization: Bearer`
  - Token TTL: 60 minutes; refresh window: 2 weeks (`JWT_REFRESH_TTL=20160`)
  - Token blacklist: Redis (on logout + refresh rotation, `JWT_BLACKLIST_ENABLED=true`)
  - See `jwt-auth-architecture.md` for full module specification
- **Authorization:** RBAC + ABAC via `AuthorizationService` — see `rbac-abac-architecture.md`
  - Roles: `admin`, `instructor`, `student`
  - Coarse-grained: Spatie `laravel-permission` (RBAC)
  - Fine-grained: Policy-based attribute checks (ABAC)
- **Password hashing:** Argon2id (Laravel `Hash::make()` with Argon2 driver)
- **HTTPS:** Enforced in staging + production via Nginx
- **CORS:** Configured in `config/cors.php` per environment
- **Secrets:** Never baked into Docker images — injected via `.env` at runtime

### API & Communication Patterns

- **Style:** RESTful JSON API
- **Versioning:** URI prefix `/api/v1/`
- **Response envelope:**
  ```json
  {
    "data": {},
    "message": "string",
    "errors": null,
    "meta": { "pagination": { "total": 0, "per_page": 15, "page": 1 } }
  }
  ```
- **Exception handling:** Centralized in `app/Exceptions/Handler.php` — maps all exceptions to the standard envelope
- **Rate limiting:** Laravel throttle middleware — 60 req/min (unauthenticated), 300 req/min (authenticated)
- **API docs:** Laravel Scribe (auto-generate from PHPDoc annotations)

### Frontend Architecture

- **Server state:** TanStack Query v5 (API fetching, caching, background refetch, optimistic updates)
- **Client state:** Zustand (UI toggles, form state, user preferences)
- **API service layer:** Axios instance per environment — base URL from `NEXT_PUBLIC_API_URL` env var
- **Folder structure:** Feature-based modules under `src/features/`
  - `auth/`, `profile/`, `items/` each contain: `components/`, `hooks/`, `services/`, `types/`
- **Shared:** `src/components/ui/`, `src/lib/`, `src/hooks/`
- **SSR strategy:** Server Components for initial data fetch; Client Components for interactive/real-time views

### Infrastructure & Deployment

- **Orchestration:** Docker Compose (dev + staging)
  - Services: `backend` (8081), `frontend` (3001), `mysql` (3307), `redis` (6380), `nginx` (80/443)
- **Reverse proxy:** Nginx — routes `/api/*` → backend, `/*` → frontend
- **Environment builds:** Multi-stage Dockerfiles — dev target includes hot-reload, prod target is optimized minimal image
- **Secret management:** `.env` files per environment — never committed, injected at container startup
- **Monitoring:** Sentry DSN configured per environment via env var
- **Logging:** Laravel structured JSON logs → stdout (captured by Docker log driver)
- **CI/CD:** GitHub Actions (deferred) — build, test, push to registry

### Decision Impact Analysis

**Implementation Sequence:**
1. Initialize Laravel + Next.js projects from starters
2. Configure Docker Compose + Nginx with all custom ports
3. Set up MySQL schema + first migrations
4. Implement Sanctum auth (registration, login, logout, password reset)
5. Build Repository layer over Eloquent models
6. Implement standardized API response + exception handler
7. Wire TanStack Query + Axios in Next.js with env-based API URL
8. Build feature modules (auth UI, profile, items CRUD)

**Cross-Component Dependencies:**
- Sanctum auth must be live before any protected API endpoint is built
- Redis must be running before queue jobs are dispatched
- Nginx config depends on final port assignments (locked: 8081/3001/3307)
- TanStack Query setup depends on Axios service layer being configured first

## Architecture Validation Results

### Coherence Validation ✅

**Decision Compatibility:** All technology choices are mutually compatible. Laravel 12 + Sanctum + MySQL + Redis form a standard, proven production stack. Next.js 16 App Router + TanStack Query + Zustand have no state overlap. Custom ports are consistently env-var driven across all services.

**Pattern Consistency:** Naming conventions (snake_case for DB/API, camelCase for PHP methods, PascalCase for TS components) are non-overlapping and explicit. The standard response envelope is defined once and applies to all API endpoints without exception.

**Structure Alignment:** Feature-based folders on the frontend mirror the Controller/Service/Repository groupings on the backend — each FR maps to a corresponding module on both sides.

### Requirements Coverage Validation ✅

| Requirement | Status | Primary Components |
|---|---|---|
| FR1 — Registration + email verify | ✅ Covered | AuthService, UserRegistered event, Redis queue |
| FR2 — Login (session + token) | ✅ Covered | Sanctum dual-mode |
| FR3 — Password reset | ✅ Covered | AuthService, PasswordResetMail |
| FR4 — Profile management | ✅ Covered | ProfileController, ProfileService, UserResource |
| FR5 — Core CRUD | ✅ Covered | ItemController, ItemService, ItemRepository |
| FR6 — Pagination + filtering | ✅ Covered | BaseRepository, response meta, usePaginatedQuery |
| NFR1 — 300ms p95 | ✅ Covered | Redis cache, DB indexing strategy |
| NFR2 — 99.9% uptime | ✅ Covered | Docker restart policies, Nginx, container isolation |
| NFR3 — Security | ✅ Covered | Argon2id, Sanctum, HTTPS, env-injected secrets |
| NFR4 — Horizontal scaling | ✅ Covered | Stateless services; read replicas deferred post-MVP |
| NFR5 — Observability | ✅ Covered | JSON stdout logs, Sentry SDK (BE + FE) |

### Implementation Readiness Validation ✅

**Decision Completeness:** All critical decisions documented with rationale. Technology versions verified via web search. Auth, state management, API format, caching, and infrastructure decisions are all locked.

**Structure Completeness:** Complete directory trees defined for both backend and frontend. All files named, located, and mapped to requirements. No placeholder directories — every folder has a defined purpose.

**Pattern Completeness:** 6 conflict categories addressed. Naming, structure, format, communication, and process patterns all specified with concrete examples and explicit anti-patterns.

### Gap Analysis Results

| Gap | Priority | Resolution |
|---|---|---|
| React Hook Form + Zod not in package list | Important | Add in first frontend implementation story |
| Nginx connection-level rate limiting | Minor | Laravel throttle middleware is sufficient for MVP |
| Docker health check config detail | Minor | Define in Docker Compose implementation story |
| `.env` key inventory | Minor | Covered by `.env.example` files committed to repo |

### Architecture Completeness Checklist

**✅ Requirements Analysis**
- [x] Project context thoroughly analyzed
- [x] Scale and complexity assessed (Medium, elevated by containerization)
- [x] Technical constraints identified (custom ports, multi-env)
- [x] Cross-cutting concerns mapped (auth, logging, error handling, secrets)

**✅ Architectural Decisions**
- [x] Auth: Laravel Sanctum (dual-mode)
- [x] State: TanStack Query v5 + Zustand
- [x] API: RESTful `/api/v1/`, standard envelope, Scribe docs
- [x] Cache/Queue: Redis
- [x] Proxy: Nginx
- [x] Monitoring: Sentry + structured JSON logs

**✅ Implementation Patterns**
- [x] Naming conventions (DB, API, PHP, TypeScript)
- [x] Structure patterns (Laravel layers, Next.js features)
- [x] Format patterns (response envelope, HTTP codes, dates)
- [x] Communication patterns (events, query keys, Zustand slices)
- [x] Process patterns (error handling, loading states, validation)

**✅ Project Structure**
- [x] Complete backend directory tree
- [x] Complete frontend directory tree
- [x] Docker + Nginx configuration files located
- [x] All FRs mapped to specific files/directories

### Architecture Readiness Assessment

**Overall Status: READY FOR IMPLEMENTATION**

**Confidence Level: High**

**Key Strengths:**
- Clean separation of concerns enforced at every layer
- All ports env-configurable — zero hardcoded values
- Standard response envelope eliminates frontend/backend format conflicts
- Centralized `queryKeys.ts` prevents TanStack Query cache key drift
- Feature-based structure scales linearly as new domains are added
- Multi-stage Dockerfiles keep prod images lean and dev images ergonomic

**Areas for Future Enhancement (post-MVP):**
- MySQL read replicas for query offloading
- Full GitHub Actions CI/CD pipeline
- CDN + S3 object storage for media
- WebSocket support for real-time features

### Implementation Handoff

**First implementation stories (in order):**
1. `composer create-project laravel/laravel backend` + configure `.env`
2. `pnpm create next-app frontend ...` + configure `.env.local`
3. Build `docker-compose.yml` with all 5 services + custom ports
4. Configure Nginx reverse proxy routing
5. Run first Laravel migrations + seed dev data
6. Install + configure Laravel Sanctum
7. Install frontend deps: `axios`, `@tanstack/react-query`, `zustand`, `react-hook-form`, `zod`
8. Build `apiClient.ts` + `queryClient.ts` + `queryKeys.ts`

**AI Agent Directive:**
Follow all decisions in this document exactly. When in doubt about naming, structure, or format — this document is the source of truth. Do not deviate from the standard response envelope, the layer separation rules, or the env-var-driven port configuration under any circumstances.

## Implementation Patterns & Consistency Rules

### Pattern Categories Defined

**Critical Conflict Points Identified:** 6 areas where AI agents could make different, incompatible choices without explicit rules.

---

### Naming Patterns

**Database Naming Conventions (Laravel/MySQL):**
- Tables: `snake_case`, plural → `users`, `personal_access_tokens`, `password_reset_tokens`
- Columns: `snake_case` → `user_id`, `created_at`, `email_verified_at`
- Foreign keys: `{singular_table}_id` → `user_id`, `post_id`
- Indexes: `{table}_{column(s)}_index` → `users_email_index`
- Pivot tables: alphabetical singular → `role_user` not `user_role`

**API Endpoint Naming Conventions:**
- Resources: plural nouns, kebab-case → `/api/v1/users`, `/api/v1/password-resets`
- Route parameters: `{id}` → `/api/v1/users/{id}`
- Query params: snake_case → `?per_page=15&sort_by=created_at`
- ✅ `/api/v1/users/{id}/profile`
- ❌ `/api/v1/getUser`, `/api/v1/user_profile`

**JSON Field Naming (API responses):**
- Always snake_case → `user_id`, `created_at`, `email_verified`
- Dates: ISO 8601 string → `"2026-04-26T10:00:00Z"`
- Booleans: `true`/`false` (never `1`/`0`)
- ✅ `{ "user_id": 1, "created_at": "2026-04-26T10:00:00Z" }`
- ❌ `{ "userId": 1, "createdAt": 1714125600 }`

**PHP/Laravel Code Naming:**
- Classes: PascalCase → `UserRepository`, `AuthService`
- Methods/variables: camelCase → `getUserById()`, `$accessToken`
- Constants: SCREAMING_SNAKE → `MAX_LOGIN_ATTEMPTS`
- Events: PascalCase noun-past → `UserRegistered`, `PasswordReset`

**TypeScript/Next.js Code Naming:**
- Components: PascalCase → `UserCard`, `AuthForm`
- Files (components): PascalCase → `UserCard.tsx`, `AuthForm.tsx`
- Files (hooks/utils/services): camelCase → `useAuth.ts`, `apiClient.ts`
- Variables/functions: camelCase → `userId`, `fetchUserProfile()`
- Types/interfaces: PascalCase → `UserProfile`, `ApiResponse<T>`
- Zustand stores: camelCase noun + `Store` → `authStore`, `uiStore`
- TanStack Query keys: arrays of strings → `['users']`, `['users', id]`, `['users', id, 'profile']`

---

### Structure Patterns

**Laravel Backend Structure:**
```
app/
  Http/
    Controllers/Api/V1/   ← thin, delegate to services
    Requests/             ← form request validation classes
    Resources/            ← API resource transformers
  Services/               ← business logic
  Repositories/           ← DB query abstraction
  Models/                 ← Eloquent models
  Exceptions/             ← custom exception classes
routes/
  api.php                 ← all /api/v1/* routes
tests/
  Feature/                ← HTTP/API tests
  Unit/                   ← service/repository unit tests
```

**Next.js Frontend Structure:**
```
src/
  app/                    ← App Router pages and layouts
    (auth)/               ← route group: login, register
    (dashboard)/          ← route group: protected pages
    layout.tsx            ← root layout + providers
  features/               ← feature modules
    auth/
      components/         ← UI components (PascalCase.tsx)
      hooks/              ← useAuth.ts, useLoginForm.ts
      services/           ← auth.service.ts (Axios calls)
      types/              ← auth.types.ts
    profile/
    items/
  components/
    ui/                   ← shared primitive components
    layout/               ← shared layout components
  lib/
    apiClient.ts          ← Axios instance
    queryClient.ts        ← TanStack QueryClient setup
  hooks/                  ← shared hooks
  types/                  ← shared global types
```

---

### Format Patterns

**API Response Envelope (ALL endpoints MUST use this):**
```json
// Success (single resource):
{ "data": { "id": 1, "name": "Bang" }, "message": "OK", "errors": null, "meta": null }

// Success (paginated list):
{ "data": [...], "message": "OK", "errors": null, "meta": { "total": 100, "per_page": 15, "current_page": 1, "last_page": 7 } }

// Validation error (422):
{ "data": null, "message": "Validation failed", "errors": { "email": ["The email field is required."] }, "meta": null }

// Server error (500):
{ "data": null, "message": "An unexpected error occurred.", "errors": null, "meta": null }
```

**HTTP Status Codes — mandatory mapping:**
- `200` GET success / PUT success
- `201` POST created
- `204` DELETE success (no body)
- `400` Bad request
- `401` Unauthenticated
- `403` Unauthorized (authenticated but forbidden)
- `404` Resource not found
- `422` Validation failed
- `429` Rate limit exceeded
- `500` Unexpected server error

---

### Communication Patterns

**Laravel Events:**
- Naming: PascalCase past-tense noun → `UserRegistered`, `ItemCreated`
- Payload: pass the Eloquent model, not raw IDs
- Listeners: one listener class per action → `SendWelcomeEmail`

**TanStack Query Keys (Frontend):**
```ts
// Centralize all keys in src/lib/queryKeys.ts:
export const queryKeys = {
  users: {
    all: ['users'] as const,
    detail: (id: number) => ['users', id] as const,
    profile: (id: number) => ['users', id, 'profile'] as const,
  },
  items: {
    all: ['items'] as const,
    list: (filters: ItemFilters) => ['items', 'list', filters] as const,
    detail: (id: number) => ['items', id] as const,
  },
}
```

**Zustand Store Pattern:**
```ts
// One file per domain slice:
interface AuthState {
  user: User | null
  setUser: (user: User | null) => void
  clearAuth: () => void
}
```

---

### Process Patterns

**Error Handling:**

*Backend (Laravel):*
- ALL exceptions caught in `Handler.php` → returns standard envelope
- Never let raw PHP exceptions reach the client
- Log to stdout as structured JSON: `Log::error('msg', ['context' => $data])`
- Sentry auto-captures 5xx via the Sentry Laravel SDK

*Frontend (Next.js):*
- Axios interceptor handles 401 → redirect to login
- TanStack Query `onError` for query-level errors → toast notification
- React Error Boundary wraps each feature route group
- Never `console.error` raw API error objects — always log `.message`

**Loading State Pattern:**
- TanStack Query `isPending` for initial fetch
- `isFetching` for background refresh (subtle indicator, not full spinner)
- Optimistic updates for mutations (update UI before API confirms)
- Skeleton components for initial page load — never raw "Loading..." text

**Validation Pattern:**
- Backend: Laravel Form Requests — validate before hitting the service layer
- Frontend: React Hook Form + Zod schema — validate on submit, inline errors
- Never duplicate logic: frontend validates for UX, backend validates for correctness

---

### Enforcement Guidelines

**All AI Agents MUST:**
- Use snake_case for all JSON API fields and DB columns
- Wrap every API response in the standard envelope
- Place business logic in Service classes, not Controllers
- Place DB queries in Repository classes, not Services or Controllers
- Use the centralized `queryKeys` object for all TanStack Query keys
- Name feature files according to the structure pattern above
- Use HTTP status codes exactly as mapped above
- Never hardcode ports — always read from env vars

**Anti-Patterns (never do these):**
- ❌ Business logic in Controllers
- ❌ DB queries in Controllers or Services
- ❌ camelCase JSON fields in API responses
- ❌ Raw error messages from 500 errors exposed to clients
- ❌ Inline API calls in React components — always use a service function
- ❌ Hardcoded `localhost:8081` — always use `process.env.NEXT_PUBLIC_API_URL`
- ❌ `useState` for server data — always use TanStack Query
- ❌ Setting `NEXT_PUBLIC_API_URL` to the backend's internal port (8081) — the browser cannot reach that port; it must go through nginx on port 80
- ❌ Setting `APP_URL` to the internal artisan serve address — must match the externally visible nginx URL
- ❌ Running only `docker-compose up` for development — must include `-f docker-compose.dev.yml` for volume mounts

## Project Structure & Boundaries

### Requirements → Structure Mapping

**FR1–FR4 (Auth & User Management):**
- Backend: `app/Http/Controllers/Api/V1/AuthController.php`, `app/Services/AuthService.php`, `app/Repositories/UserRepository.php`
- Frontend: `src/features/auth/`, `src/features/profile/`
- DB: `database/migrations/*_create_users_table.php`, `*_create_personal_access_tokens_table.php`

**FR5 (Core CRUD — Items):**
- Backend: `app/Http/Controllers/Api/V1/ItemController.php`, `app/Services/ItemService.php`, `app/Repositories/ItemRepository.php`
- Frontend: `src/features/items/`
- DB: `database/migrations/*_create_items_table.php`

**FR6 (Pagination & Filtering):**
- Backend: `app/Repositories/BaseRepository.php` (paginate + filter methods)
- Frontend: `src/hooks/usePaginatedQuery.ts`

---

### Complete Project Directory Structure

```
/ (project root)
├── docker-compose.yml
├── docker-compose.staging.yml
├── docker-compose.prod.yml
├── .env.example
├── .env.staging.example
├── .env.production.example
├── .gitignore
├── README.md
├── nginx/
│   ├── nginx.conf
│   └── conf.d/
│       ├── dev.conf
│       └── prod.conf
├── .github/
│   └── workflows/
│       └── ci.yml
│
├── backend/
│   ├── Dockerfile
│   ├── .env.example
│   ├── composer.json
│   ├── artisan
│   ├── phpunit.xml
│   ├── app/
│   │   ├── Exceptions/
│   │   │   ├── Handler.php
│   │   │   └── ApiException.php
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── ProfileController.php
│   │   │   │   └── ItemController.php
│   │   │   ├── Middleware/
│   │   │   │   └── ForceJsonResponse.php
│   │   │   ├── Requests/
│   │   │   │   ├── Auth/
│   │   │   │   │   ├── RegisterRequest.php
│   │   │   │   │   ├── LoginRequest.php
│   │   │   │   │   └── PasswordResetRequest.php
│   │   │   │   └── Item/
│   │   │   │       ├── StoreItemRequest.php
│   │   │   │       └── UpdateItemRequest.php
│   │   │   └── Resources/
│   │   │       ├── UserResource.php
│   │   │       └── ItemResource.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   └── Item.php
│   │   ├── Repositories/
│   │   │   ├── Contracts/
│   │   │   │   ├── UserRepositoryInterface.php
│   │   │   │   └── ItemRepositoryInterface.php
│   │   │   ├── BaseRepository.php
│   │   │   ├── UserRepository.php
│   │   │   └── ItemRepository.php
│   │   ├── Services/
│   │   │   ├── AuthService.php
│   │   │   ├── ProfileService.php
│   │   │   └── ItemService.php
│   │   ├── Events/
│   │   │   └── UserRegistered.php
│   │   ├── Listeners/
│   │   │   └── SendWelcomeEmail.php
│   │   ├── Mail/
│   │   │   ├── WelcomeMail.php
│   │   │   └── PasswordResetMail.php
│   │   └── Providers/
│   │       ├── AppServiceProvider.php
│   │       └── RepositoryServiceProvider.php
│   ├── config/
│   │   ├── cors.php
│   │   ├── logging.php
│   │   ├── queue.php
│   │   └── sanctum.php
│   ├── database/
│   │   ├── factories/
│   │   │   ├── UserFactory.php
│   │   │   └── ItemFactory.php
│   │   ├── migrations/
│   │   │   ├── 2026_04_26_000001_create_users_table.php
│   │   │   ├── 2026_04_26_000002_create_personal_access_tokens_table.php
│   │   │   ├── 2026_04_26_000003_create_password_reset_tokens_table.php
│   │   │   └── 2026_04_26_000004_create_items_table.php
│   │   └── seeders/
│   │       └── DatabaseSeeder.php
│   ├── routes/
│   │   ├── api.php
│   │   └── web.php
│   └── tests/
│       ├── Feature/
│       │   ├── Auth/
│       │   │   ├── RegisterTest.php
│       │   │   ├── LoginTest.php
│       │   │   └── PasswordResetTest.php
│       │   └── Item/
│       │       └── ItemCrudTest.php
│       └── Unit/
│           ├── AuthServiceTest.php
│           └── ItemServiceTest.php
│
└── frontend/
    ├── Dockerfile
    ├── .env.local.example
    ├── .env.production.example
    ├── package.json
    ├── next.config.ts
    ├── tailwind.config.ts
    ├── tsconfig.json
    └── src/
        ├── app/
        │   ├── globals.css
        │   ├── layout.tsx
        │   ├── (auth)/
        │   │   ├── login/page.tsx
        │   │   ├── register/page.tsx
        │   │   └── forgot-password/page.tsx
        │   └── (dashboard)/
        │       ├── layout.tsx
        │       ├── page.tsx
        │       ├── profile/page.tsx
        │       └── items/
        │           ├── page.tsx
        │           └── [id]/page.tsx
        ├── features/
        │   ├── auth/
        │   │   ├── components/
        │   │   │   ├── LoginForm.tsx
        │   │   │   └── RegisterForm.tsx
        │   │   ├── hooks/
        │   │   │   ├── useLogin.ts
        │   │   │   └── useRegister.ts
        │   │   ├── services/
        │   │   │   └── auth.service.ts
        │   │   └── types/
        │   │       └── auth.types.ts
        │   ├── profile/
        │   │   ├── components/ProfileForm.tsx
        │   │   ├── hooks/useProfile.ts
        │   │   ├── services/profile.service.ts
        │   │   └── types/profile.types.ts
        │   └── items/
        │       ├── components/
        │       │   ├── ItemList.tsx
        │       │   ├── ItemCard.tsx
        │       │   └── ItemForm.tsx
        │       ├── hooks/
        │       │   ├── useItems.ts
        │       │   └── useItemMutation.ts
        │       ├── services/items.service.ts
        │       └── types/item.types.ts
        ├── components/
        │   ├── ui/
        │   │   ├── Button.tsx
        │   │   ├── Input.tsx
        │   │   ├── Skeleton.tsx
        │   │   └── ErrorBoundary.tsx
        │   └── layout/
        │       ├── Navbar.tsx
        │       └── Sidebar.tsx
        ├── lib/
        │   ├── apiClient.ts
        │   ├── queryClient.ts
        │   └── queryKeys.ts
        ├── hooks/
        │   └── usePaginatedQuery.ts
        ├── stores/
        │   ├── authStore.ts
        │   └── uiStore.ts
        └── types/
            ├── api.types.ts
            └── global.types.ts
```

### Docker Environment Configuration

This section is the authoritative source for environment variable values and startup procedures. Deviating from these values will break the service boundary model.

**Correct env-var values:**

| Variable | Correct Value | File | Why |
|---|---|---|---|
| `NEXT_PUBLIC_API_URL` | `http://localhost` | `frontend/.env.local` | Browser must go through nginx (port 80). Backend port 8081 is internal-only — never mapped to host. |
| `APP_URL` | `http://localhost` | `backend/.env` | Laravel URL generation must match the externally visible URL (nginx on 80), not the internal artisan serve port. |
| `NGINX_PORT` | `80` | `.env` | nginx is the sole external entrypoint. |
| `DB_PORT` (host-side) | `3307` | `.env` | Only for DBeaver/TablePlus direct access. Internal service always uses `3306`. |
| `REDIS_PORT` (host-side) | `6380` | `.env` | Only for redis-cli direct access. Internal service always uses `6379`. |

**Starting the dev stack (correct command):**
```bash
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up
```
The `-f docker-compose.dev.yml` overlay is required to mount live code volumes for hot-reload. Without it, the container runs the code baked in at image build time.

**Service startup order:**
1. `mysql` + `redis` → healthy
2. `backend` → starts, runs `php artisan migrate --force`, then `php artisan serve`
3. `backend` → healthy (curl `/up` returns 200)
4. `nginx` + `frontend` → start simultaneously (frontend uses `service_started`, not `service_healthy`, to avoid blocking nginx during Turbopack cold compile)

**Why `frontend` uses `service_started` for nginx dependency:**
Next.js with Turbopack can take 60–120s to complete its first compilation. Requiring `service_healthy` (nc -z port check) before nginx starts means the entire stack is inaccessible during that window. nginx will retry upstream connections naturally — setting `service_started` gets the proxy up immediately.

---

### Architectural Boundaries

**API Boundary:**
- All traffic enters via Nginx → `/api/*` proxied to `backend:8081`
- All API routes prefixed `/api/v1/` defined in `routes/api.php`
- Public auth routes: `/register`, `/login`, `/forgot-password`, `/reset-password`
- Protected routes: `auth:sanctum` middleware group

**Frontend ↔ Backend Boundary:**
- `src/lib/apiClient.ts` is the sole Axios integration point
- Base URL driven by `NEXT_PUBLIC_API_URL` env var
- 401 interceptor: clears authStore + redirects to `/login`

**Data Boundary:**
- Repositories are the only layer allowed to query Eloquent/DB
- Redis Cache sits between Repositories and expensive list queries
- Queue jobs dispatched from Services only (never Controllers)

**Docker Network Boundary:**
- All services on `app_network` internal bridge
- Only Nginx exposes ports 80/443 externally
- Internal service ports: backend 8081, frontend 3001, MySQL 3307, Redis 6380

### Data Flow

```
Browser → Nginx (80/443)
  ├── /api/* → backend:8081
  │     └── Controller → Service → Repository → MySQL:3307
  │                             ↕ Redis:6380 (cache + queue)
  └── /*    → frontend:3001 (Next.js RSC + Client Components)
                └── TanStack Query → Axios (apiClient.ts) → /api/*
```
