# Story 4.5: Submissions API (RBAC/ABAC)

Status: in-progress

## Story

As a student or instructor,
I want to submit assignments and grade submissions,
so that the assessment lifecycle is complete from submission to graded result.

## Acceptance Criteria

1. GET `/api/v1/assignments/{assignmentId}/submissions` with `grade-submissions` permission (instructor/admin) returns HTTP 200 with paginated submissions

2. POST `/api/v1/assignments/{assignmentId}/submissions` with `submit-assignments` permission AND authorized by AssignmentPolicy (`submit` ability — verifies enrollment + deadline) returns HTTP 201 with the submission record

3. GET `/api/v1/submissions/{id}` returns HTTP 200 when user owns the submission OR has `grade-submissions` (SubmissionPolicy `view` ability)

4. PATCH `/api/v1/submissions/{id}/grade` with `grade-submissions` permission AND authorized by SubmissionPolicy (`grade` ability) returns HTTP 200 with graded submission

5. Unauthorized: HTTP 403; Unauthenticated: HTTP 401; Resource not found: HTTP 404

## Tasks / Subtasks

- [x] Task 1: Routes (AC: 1–5)
  - [x] 4 routes registered inside `jwt.authenticate` group; list/grade use `permission:grade-submissions`, submit uses `permission:submit-assignments`

- [x] Task 2: SubmissionController + SubmissionService (AC: 1–4)
  - [x] `listForAssignment` — instructor/admin only via ABAC on assignment
  - [x] `submit` — `SubmissionService::submit` with ABAC `'submit'` ability on assignment
  - [x] `get` — ABAC `'view'` ability on submission (own or instructor)
  - [x] `grade` — ABAC `'grade'` ability on submission; updates `grade` field

- [x] Task 3: GradeSubmissionRequest validation
  - [x] `grade` required integer, `feedback` nullable string

- [ ] Task 4: Tests (AC: 1–5)
  - [ ] Feature tests: list, submit, show, grade — happy path + 401/403 scenarios
  - [ ] Test: student cannot submit after deadline (AssignmentPolicy deadline check)
  - [ ] Test: student cannot view another student's submission
  - [ ] Test: instructor can grade; student cannot grade

## Dev Agent Record

**Implementation:** 2026-04-28 (pre-existing)
**Story file created:** 2026-04-30 (retrospective)
**Remaining work:** Test coverage (Task 4)

### Implementation Notes

SubmissionService uses short ability names (`'view'`, `'submit'`, `'grade'`) for Gate/Policy dispatch, distinct from Spatie permission names (`'submit-assignments'`, `'grade-submissions'`). The deadline check is implemented in `AssignmentPolicy::submit()`. Verify that policy handles the 422 vs 403 distinction for deadline-exceeded (story AC says 422, Laravel Gates typically return 403).
