# Story 4.6: Enrollments API (RBAC/ABAC)

Status: in-progress

## Story

As a student,
I want to enroll in open courses,
so that I gain access to lessons and assignments within that course.

## Acceptance Criteria

1. POST `/api/v1/courses/{courseId}/enroll` with `enroll-courses` permission (student) creates an enrollment record and returns HTTP 201 with `"message": "Enrolled successfully."`

2. When the student is already enrolled, the enrollment is returned silently (idempotent — no 409 error in current implementation)

3. When the course does not exist, HTTP 404 is returned

4. After enrollment, the student can access lessons and assignments for that course (ABAC enrollment checks pass)

5. Unauthenticated requests return HTTP 401; non-student roles attempting enrollment return HTTP 403

## Tasks / Subtasks

- [x] Task 1: Route (AC: 1–5)
  - [x] `POST /api/v1/courses/{courseId}/enroll` registered with `jwt.authenticate` + `permission:enroll-courses`

- [x] Task 2: EnrollmentController + EnrollmentService (AC: 1–4)
  - [x] `EnrollmentService::enroll` uses `Enrollment::firstOrCreate` — idempotent enrollment
  - [x] `enrollments->invalidateCache` called after enrollment (clears ABAC enrollment cache)
  - [x] `Course::findOrFail` throws `ModelNotFoundException` → 404 if course doesn't exist

- [ ] Task 3: Tests (AC: 1–5)
  - [ ] Feature test: student can enroll → 201 enrollment record created
  - [ ] Feature test: re-enrollment returns existing record (idempotent, not 409)
  - [ ] Feature test: instructor/admin cannot enroll (403)
  - [ ] Feature test: enrolling in non-existent course → 404
  - [ ] Feature test: after enrollment, student can access course lessons

## Dev Agent Record

**Implementation:** 2026-04-28 (pre-existing)
**Story file created:** 2026-04-30 (retrospective)
**Remaining work:** Test coverage (Task 3)

### Deviations from Story Plan

- **Duplicate enrollment:** Implementation uses `firstOrCreate` (idempotent — always 201) rather than HTTP 409 on duplicate. This is a simpler MVP approach. Update epics.md AC 2 if 201-idempotent is the intended behavior, or add 409 handling if strict enforcement is needed.
