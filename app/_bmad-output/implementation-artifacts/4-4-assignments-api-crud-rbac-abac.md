# Story 4.4: Assignments API (CRUD + RBAC/ABAC)

Status: in-progress

## Story

As an instructor or enrolled student,
I want to create and view assignments within courses,
so that instructors can set assessments and students can see their work.

## Acceptance Criteria

1. GET `/api/v1/courses/{courseId}/assignments` with `view-assignments` permission AND (enrolled OR instructor/admin) returns HTTP 200 with paginated assignments for the course

2. POST `/api/v1/courses/{courseId}/assignments` with `create-assignments` permission AND instructor owns course (ABAC) returns HTTP 201 with the new assignment including `due_date`

3. GET `/api/v1/assignments/{id}` with `view-assignments` permission AND authorized by AssignmentPolicy returns HTTP 200 with assignment data including `due_date`

4. Unauthorized: HTTP 403; Unauthenticated: HTTP 401; Course/Assignment not found: HTTP 404

## Tasks / Subtasks

- [x] Task 1: Routes (AC: 1–4)
  - [x] 3 routes registered inside `jwt.authenticate` group with `permission:*` middleware

- [x] Task 2: AssignmentController + AssignmentService (AC: 1–3)
  - [x] `listForCourse` — paginated assignments for a course with ABAC check
  - [x] `create` — creates assignment with `due_date`, sets `course_id` and `instructor_id`
  - [x] `get` — fetches assignment with ABAC via `AssignmentPolicy`

- [x] Task 3: StoreAssignmentRequest validation
  - [x] `title` required, `description` nullable, `due_date` required date

- [ ] Task 4: Tests (AC: 1–4)
  - [ ] Feature tests: list, show, create — happy path + 401/403/404 scenarios
  - [ ] Test: student can list assignments only for enrolled courses

## Dev Agent Record

**Implementation:** 2026-04-28 (pre-existing)
**Story file created:** 2026-04-30 (retrospective)
**Remaining work:** Test coverage (Task 4)
