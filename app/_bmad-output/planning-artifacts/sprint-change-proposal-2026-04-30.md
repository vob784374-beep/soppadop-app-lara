# Sprint Change Proposal
**Date:** 2026-04-30
**Prepared by:** Bob (Scrum Master)
**Project:** app
**Scope Classification:** MAJOR — Fundamental artifact realignment required

---

## Section 1: Issue Summary

### Problem Statement

Two deliberate architectural decisions were made on 2026-04-28 — after epics.md and architecture.md were finalized on 2026-04-26 — but were never back-propagated to the planning artifacts:

1. **Auth pivot:** Sanctum → JWT (`php-open-source-saver/jwt-auth`), documented in `jwt-auth-architecture.md`
2. **Domain pivot:** Generic "items" app → IELTS Learning Management System with RBAC/ABAC, documented in `rbac-abac-architecture.md`

### Discovery Context

Discovered on 2026-04-30 during sprint planning after Epic 1 completion. The test suite revealed 44 passing tests (124 assertions) including auth unit tests, JWT integration tests, and LMS controllers — all implemented but untracked.

### Evidence

| File | Status | Notes |
|------|--------|-------|
| `jwt-auth-architecture.md` | Complete (2026-04-28) | Explicit JWT decision with full module spec |
| `rbac-abac-architecture.md` | Complete (2026-04-28) | IELTS LMS domain, RBAC/ABAC permission matrix |
| `backend/app/Services/AuthService.php` | Implemented | JWT-based |
| `backend/app/Services/JwtService.php` | Implemented | JWT token operations |
| `backend/app/Services/Authorization/` | Implemented | AbacChecker, RbacChecker, AuthorizationService |
| `backend/app/Http/Controllers/Api/V1/` | 8 controllers | Auth, Profile, Item, Course, Lesson, Assignment, Submission, Enrollment |
| `backend/tests/Feature/Auth/AuthFlowTest.php` | 12 tests passing | JWT-based auth flow |
| `backend/tests/Unit/Auth/` | 12 unit tests passing | JwtService + AuthService |

### What the Planning Artifacts Currently Say (Outdated)

- `architecture.md`: "Auth method: Laravel Sanctum"
- `epics.md` Story 2.1: "response returns HTTP 201 with user data and a **Sanctum bearer token**"
- `epics.md` Story 2.3: "response returns HTTP 200 with user data and a **Sanctum bearer token**"
- `epics.md` Story 2.4: "all existing **tokens** for that user are revoked after reset"
- `epics.md` Epic 3: "Core Item Management" (generic CRUD — no LMS context)
- No Epic 4 exists for LMS domain features

---

## Section 2: Impact Analysis

### Epic Impact

| Epic | Impact | Action |
|------|--------|--------|
| Epic 1 | None — complete | No change |
| Epic 2 | Moderate — all Sanctum ACs must become JWT; email verification unimplemented | Update ACs, create story files after verification |
| Epic 3 | Low — items CRUD pattern is correct; LMS context missing | Minor AC updates |
| Epic 4 (new) | New — entire LMS domain untracked | Add new epic |

### Story-Level Impact

**Epic 2 stories with Sanctum references to fix:**

| Story | Outdated AC | Replacement |
|-------|-------------|-------------|
| 2.1 User Registration | "Sanctum bearer token in the envelope" | JWT: `{ token, token_type: "bearer", expires_in: 3600 }` |
| 2.3 Login/Logout | "Sanctum bearer token" + "HTTP-only Sanctum session cookie" | JWT bearer token; remove cookie AC; add POST `/refresh` AC |
| 2.4 Password Reset | "All existing tokens revoked after reset" | JWT: invalidate current token; Sanctum-style revocation N/A |

**Implementation gaps (not yet built, remain as planned stories):**

| Story | Gap |
|-------|-----|
| 2.2 Email Verification | No implementation found — remains as-is |
| 2.5 Profile Management API | `ProfileController` exists but AC verification needed |
| 2.6 Auth UI | No frontend auth pages exist — remains as-is |
| 2.7 Profile UI | No frontend profile page exists — remains as-is |

### Artifact Conflicts

| Artifact | Conflict | Required Update |
|----------|----------|-----------------|
| `architecture.md` | Auth section says Sanctum | Update to JWT; reference `jwt-auth-architecture.md` |
| `prd.md` | Placeholder — no domain defined | Update with IELTS LMS purpose statement |
| `epics.md` | No Epic 4; Epic 2/3 reference Sanctum | Add Epic 4; update Epic 2/3 ACs |
| `sprint-status.yaml` | No Epic 4 entries | Add after epics.md update |

### Technical Impact

**Already resolved by existing implementation:**
- JWT package installed + configured
- `AuthController`, `AuthService`, `JwtService` built and tested
- RBAC/ABAC: `AuthorizationService`, `AbacChecker`, `RbacChecker` built
- LMS controllers: Course, Lesson, Assignment, Submission, Enrollment built

**Remaining gaps (from `jwt-auth-architecture.md` Section 9):**
- `JwtServiceInterface` missing — breaks DIP
- `AuthServiceInterface` missing — breaks DIP
- Custom `JwtAuthenticate` middleware missing — `auth:api` doesn't give typed 401 messages
- Interface bindings in service provider missing

---

## Section 3: Recommended Approach

**Hybrid Option 1 (Direct Adjustment) + Option 3 (MVP Review)**

### Rationale

- **Don't rollback.** 44 passing tests represent real value. The JWT and RBAC/ABAC decisions are well-reasoned in their architecture documents. Reverting would destroy working, tested code for no benefit.
- **Update artifacts to match reality.** The code is ahead of the plan — bring the plan forward, not the code backward.
- **Fill the gaps.** The `jwt-auth-architecture.md` lists 5 remaining implementation gaps that must be completed to satisfy SOLID design. These become story tasks.
- **Track the LMS domain.** Add Epic 4 so Courses/Lessons/Assignments/Submissions/Enrollments are formally tracked.

### Effort: Medium | Risk: Low

The implementation is working. The risk is only documentation debt, not technical debt (beyond the known gaps in `jwt-auth-architecture.md`).

---

## Section 4: Detailed Change Proposals

---

### Change A: `architecture.md` — Auth Section Update

**Section:** Authentication & Security

OLD:
```
- **Auth method:** Laravel Sanctum
  - SPA: HTTP-only cookie session (CSRF-protected)
  - API clients: Opaque bearer tokens via `Authorization: Bearer`
  - Token revocation: DELETE from `personal_access_tokens` table
```

NEW:
```
- **Auth method:** JWT (`php-open-source-saver/jwt-auth`)
  - API clients: JWT bearer tokens via `Authorization: Bearer`
  - Token TTL: 60 minutes; refresh window: 2 weeks
  - Token blacklist: Redis (on logout + refresh rotation)
  - See `jwt-auth-architecture.md` for full module specification
```

**Rationale:** Sanctum was the original choice. JWT was selected on 2026-04-28 as documented in `jwt-auth-architecture.md`. The main architecture doc must reflect this.

---

### Change B: `prd.md` — Domain Definition

**Section:** Purpose + Problem Statement

OLD:
```
## 1. Purpose
Describe the product purpose, target users, and success metrics.

## 2. Problem Statement
What problem are we solving? Who is impacted?
```

NEW:
```
## 1. Purpose
An IELTS Learning Management System (LMS) enabling students to enroll in
courses, complete lessons and assignments, and track progress — while
instructors create and manage course content and grade submissions.

## 2. Problem Statement
IELTS candidates lack a structured, self-paced platform to prepare for the
exam. Instructors lack tooling to deliver content and track student progress
at scale. This platform bridges both gaps with role-appropriate experiences.

## Target Users
- Students: IELTS candidates seeking structured exam preparation
- Instructors: IELTS educators creating and managing course content
- Admins: Platform operators managing users and system configuration
```

**Rationale:** PRD is a placeholder. The `rbac-abac-architecture.md` clearly defines the domain as IELTS LMS. This aligns the PRD with the actual product.

---

### Change C: `epics.md` — Epic 2 Story ACs (Sanctum → JWT)

**Story 2.1 — User Registration API**

OLD AC:
```
the response returns HTTP 201 with the user data and a Sanctum bearer token in the envelope
```

NEW AC:
```
the response returns HTTP 201 with the standard envelope:
{ data: { user: {...}, token: "...", token_type: "bearer", expires_in: 3600 }, message, errors: null, meta: null }
```

---

**Story 2.3 — User Login & Logout API**

OLD ACs:
```
the response returns HTTP 200 with the user data and a Sanctum bearer token
An HTTP-only Sanctum session cookie is set for SPA usage
POST /api/v1/auth/logout with a valid bearer token revokes the token and returns HTTP 200
After logout, the revoked token returns HTTP 401 on subsequent requests
```

NEW ACs:
```
the response returns HTTP 200 with the standard envelope containing: user, token, token_type: "bearer", expires_in: 3600
POST /api/v1/refresh with a valid Bearer token returns HTTP 200 with a new token; the old token is blacklisted in Redis
POST /api/v1/logout with a valid Bearer token blacklists the token and returns HTTP 200 with message: "Logged out successfully."
After logout, the blacklisted token returns HTTP 401 with message: "Unauthenticated."
```

---

**Story 2.4 — Password Reset API**

OLD AC:
```
all existing tokens for that user are revoked after a successful reset
```

NEW AC:
```
the current JWT token (if provided) is invalidated after a successful reset; the user must log in again to obtain a new token
```

---

### Change D: `epics.md` — Add Epic 4: LMS Domain

Add after Epic 3:

```markdown
## Epic 4: IELTS LMS Domain

Instructors can create and manage courses, lessons, and assignments.
Students can enroll in courses, complete lessons, submit assignments,
and receive grades. Admins manage users and platform configuration.
Role-based (RBAC) and attribute-based (ABAC) access control enforces
appropriate access at every endpoint.

**Stories:**

### Story 4.1: RBAC/ABAC Foundation — Roles, Permissions & Seeder
- Roles: admin, instructor, student
- Permission matrix seeded via RolePermissionSeeder (using Spatie laravel-permission)
- `AuthorizationService` with `RbacChecker` and `AbacChecker` wired
- `permission` middleware registered and applied to protected routes
- Tests: seeder runs without errors; role/permission assignments correct

### Story 4.2: Courses API (CRUD + RBAC)
- GET /api/v1/courses — list (view-courses)
- POST /api/v1/courses — create (create-courses)
- GET /api/v1/courses/{id} — show (view-courses + enrollment ABAC for students)
- PATCH /api/v1/courses/{id} — update (edit-courses + instructor owns course ABAC)
- DELETE /api/v1/courses/{id} — delete (delete-courses)

### Story 4.3: Lessons API (CRUD + RBAC/ABAC)
- GET /api/v1/courses/{courseId}/lessons — list (view-lessons)
- POST /api/v1/courses/{courseId}/lessons — create (create-lessons)
- GET /api/v1/lessons/{id} — show (view-lessons + enrollment ABAC)
- PATCH /api/v1/lessons/{id} — update (edit-lessons + instructor owns course ABAC)

### Story 4.4: Assignments API (CRUD + RBAC/ABAC)
- GET /api/v1/courses/{courseId}/assignments — list (view-assignments)
- POST /api/v1/courses/{courseId}/assignments — create (create-assignments)
- GET /api/v1/assignments/{id} — show (view-assignments)

### Story 4.5: Submissions API (RBAC/ABAC)
- GET /api/v1/assignments/{assignmentId}/submissions — list (grade-submissions)
- POST /api/v1/assignments/{assignmentId}/submissions — submit (submit-assignments + deadline ABAC)
- GET /api/v1/submissions/{id} — show (own submission ABAC)
- PATCH /api/v1/submissions/{id}/grade — grade (grade-submissions + instructor owns assignment ABAC)

### Story 4.6: Enrollments API (RBAC/ABAC)
- POST /api/v1/courses/{courseId}/enroll — enroll (enroll-courses)

### Story 4.7: JWT Gap Closure (SOLID compliance)
- Create JwtServiceInterface + AuthServiceInterface
- Bind interfaces in RepositoryServiceProvider
- Create JwtAuthenticate custom middleware (typed 401 messages)
- Update AuthController to type-hint AuthServiceInterface
- Update AuthService to type-hint JwtServiceInterface
- Replace auth:api with jwt.authenticate alias in routes
```

---

### Change E: `sprint-status.yaml` — Add Epic 4

Add entries for Epic 4 and its 7 stories.

---

## Section 5: Implementation Handoff

**Scope: MAJOR** — Requires PM/Architect review + SM backlog update

### Handoff Plan

| Role | Responsibility |
|------|----------------|
| Bang (Project Lead) | Approve this proposal |
| Bob (SM) | Update `epics.md`, `sprint-status.yaml`; create story files for Epic 4 |
| Winston (Architect) | Update `architecture.md` auth section |
| John (PM) | Update `prd.md` with IELTS LMS domain definition |
| Amelia (Dev) | Story 4.7 — close JWT SOLID gaps (first priority before any Epic 4 feature stories) |

### Implementation Sequence

1. **Approve this proposal** (Bang)
2. **Update `architecture.md`** auth section → JWT (Change A)
3. **Update `prd.md`** → IELTS LMS domain (Change B)
4. **Update `epics.md`** → Epic 2 JWT ACs + add Epic 4 (Changes C + D)
5. **Update `sprint-status.yaml`** → add Epic 4 entries (Change E)
6. **Story 4.7 first** → close JWT SOLID gaps before any LMS feature stories
7. **Verify Epic 2 stories** against existing code before creating story files

### Success Criteria

- [ ] All planning artifacts reflect JWT (not Sanctum)
- [ ] PRD defines IELTS LMS domain
- [ ] Epic 4 tracked in sprint-status.yaml
- [ ] `JwtServiceInterface`, `AuthServiceInterface`, `JwtAuthenticate` middleware created
- [ ] Full test suite still passes after gap closure
- [ ] Story files for Epic 2 created only after AC verification against existing code

---

*Sprint Change Proposal prepared by Bob (Scrum Master) — 2026-04-30*
