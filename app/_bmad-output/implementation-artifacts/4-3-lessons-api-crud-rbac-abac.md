# Story 4.3: Lessons API (CRUD + RBAC/ABAC)

Status: in-progress

## Story

As an instructor or enrolled student,
I want to create and view lessons nested within courses,
so that course content is organized and access-controlled by enrollment.

## Acceptance Criteria

1. GET `/api/v1/courses/{courseId}/lessons` with `view-lessons` permission AND (enrolled OR instructor/admin) returns HTTP 200 with paginated lessons ordered by `order` field

2. POST `/api/v1/courses/{courseId}/lessons` with `create-lessons` permission AND instructor owns course (ABAC) returns HTTP 201 with the new lesson

3. GET `/api/v1/lessons/{id}` with `view-lessons` permission AND authorized by LessonPolicy returns HTTP 200

4. PATCH `/api/v1/lessons/{id}` with `edit-lessons` permission AND instructor owns parent course (ABAC) returns HTTP 200 with updated lesson

5. Unauthorized: HTTP 403; Unauthenticated: HTTP 401; Course/Lesson not found: HTTP 404

## Tasks / Subtasks

- [x] Task 1: Routes (AC: 1–5)
  - [x] All 4 routes registered inside `jwt.authenticate` group with `permission:*` middleware

- [x] Task 2: LessonController + LessonService (AC: 1–4)
  - [x] `listForCourse` — checks course-level ABAC (`view-courses` on course), paginates 30/page ordered by `order`
  - [x] `create` — checks `create-lessons` ABAC on parent course (instructor must own it)
  - [x] `get` — checks `view-lessons` ABAC on lesson via `LessonPolicy`
  - [x] `update` — checks `edit-lessons` ABAC on lesson via `LessonPolicy`

- [x] Task 3: LessonPolicy ABAC
  - [x] `LessonPolicy` registered for attribute-based checks

- [x] Task 4: StoreLessonRequest validation
  - [x] `title` required, `content` nullable, `order` integer, `published` boolean

- [ ] Task 5: Tests (AC: 1–5)
  - [ ] Feature tests: list, show, create, update — happy path + 401/403/404 scenarios
  - [ ] Test: student can list lessons for enrolled course, cannot for non-enrolled

## Dev Agent Record

**Implementation:** 2026-04-28 (pre-existing)
**Story file created:** 2026-04-30 (retrospective)
**Remaining work:** Test coverage (Task 5)
