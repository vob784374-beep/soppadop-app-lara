# Story 4.2: Courses API (CRUD + RBAC/ABAC)

Status: in-progress

## Story

As an instructor or student,
I want a fully functional Courses API with role-gated endpoints,
so that instructors can manage courses and students can browse and enroll.

## Acceptance Criteria

1. GET `/api/v1/courses` with `view-courses` permission returns HTTP 200 with a paginated list of courses (admin: all; instructor: own courses; student: enrolled courses)

2. POST `/api/v1/courses` with `create-courses` permission returns HTTP 201 with the created course; `instructor_id` is set to the authenticated user

3. GET `/api/v1/courses/{id}` with `view-courses` permission AND (admin OR instructor-owns OR student-enrolled) returns HTTP 200 with course data

4. PATCH `/api/v1/courses/{id}` with `edit-courses` AND instructor owns course returns HTTP 200 with updated course

5. DELETE `/api/v1/courses/{id}` with `delete-courses` returns HTTP 204 (admin only in practice — CoursePolicy denies for non-admin)

6. Unauthorized: HTTP 403; Unauthenticated: HTTP 401

## Tasks / Subtasks

- [x] Task 1: Routes (AC: 1–6)
  - [x] All 5 routes registered in `routes/api.php` inside `jwt.authenticate` group with `permission:*` middleware

- [x] Task 2: CourseController (AC: 1–5)
  - [x] `index` → `CourseService::list` → paginated response with `meta`
  - [x] `show` → `CourseService::get` → ABAC check via `AuthorizationService::authorize`
  - [x] `store` → `CourseService::create` → sets `instructor_id` → 201
  - [x] `update` → `CourseService::update` → ABAC ownership check → updated `CourseResource`
  - [x] `destroy` → `CourseService::delete` → ABAC → 200 with message (not 204)

- [x] Task 3: CourseService + CoursePolicy ABAC (AC: 1, 3, 4, 5)
  - [x] `CoursePolicy::view` — instructor sees own + published; student checks enrollment
  - [x] `CoursePolicy::update` — instructor must own course
  - [x] `CoursePolicy::delete` — returns `false` (admin bypass in `AuthorizationService`)

- [x] Task 4: StoreCourseRequest / UpdateCourseRequest validation
  - [x] `title` required string, `description` nullable, `published` boolean

- [ ] Task 5: Tests (AC: 1–6)
  - [ ] Feature tests for each endpoint: happy path + 401 + 403 scenarios
  - [ ] Test role-based list filtering (admin/instructor/student see different subsets)
  - [ ] Test ABAC: instructor cannot update another instructor's course

## Dev Agent Record

**Implementation:** 2026-04-28 (pre-existing)
**Story file created:** 2026-04-30 (retrospective)
**Remaining work:** Test coverage (Task 5)

### Implementation Notes

`destroy` returns HTTP 200 with message `"Course deleted successfully."` (not 204). The epics.md AC specifies 204, but the implementation uses 200 for consistency with the API envelope. Update epics.md AC if 200 is the intended behavior.
