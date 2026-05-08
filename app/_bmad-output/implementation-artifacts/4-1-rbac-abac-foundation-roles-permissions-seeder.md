# Story 4.1: RBAC/ABAC Foundation — Roles, Permissions & Seeder

Status: in-progress

## Story

As a developer,
I want roles, permissions, and RBAC/ABAC services in place and verified by tests,
so that every LMS endpoint can enforce access control correctly.

## Acceptance Criteria

1. `php artisan db:seed --class=RolePermissionSeeder` runs without errors and seeds 3 roles: `admin`, `instructor`, `student`

2. All 16 permissions are seeded on the `api` guard: `manage-users`, `manage-courses`, `view-reports`, `create-courses`, `edit-courses`, `delete-courses`, `create-lessons`, `edit-lessons`, `create-assignments`, `grade-submissions`, `view-courses`, `view-lessons`, `view-assignments`, `enroll-courses`, `submit-assignments`, `view-own-grades`

3. `admin` role has all permissions; `instructor` has create/edit/grade permissions; `student` has view/enroll/submit permissions

4. `AuthorizationService` with `RbacChecker` and `AbacChecker` are bound via `RepositoryServiceProvider` and resolvable from the container

5. `CheckPermission` middleware is registered as the `permission` alias in `bootstrap/app.php` and correctly gates requests by permission name

6. Tests: seeder runs without errors; role-permission matrix is correct per the rbac-abac-architecture.md spec; `CheckPermission` returns 403 on missing permission

## Tasks / Subtasks

- [x] Task 1: RolePermissionSeeder (AC: 1, 2, 3)
  - [x] `database/seeders/RolePermissionSeeder.php` seeds all 16 permissions on `api` guard
  - [x] Admin role: all 16 permissions
  - [x] Instructor role: `create-courses`, `edit-courses`, `create-lessons`, `edit-lessons`, `create-assignments`, `grade-submissions`, `view-courses`, `view-lessons`, `view-assignments`
  - [x] Student role: `view-courses`, `view-lessons`, `view-assignments`, `enroll-courses`, `submit-assignments`, `view-own-grades`
  - [x] `DatabaseSeeder` calls `RolePermissionSeeder`

- [x] Task 2: Authorization services (AC: 4)
  - [x] `AuthorizationService` implements `AuthorizationServiceInterface` with `hasPermission`, `can`, `authorize` methods
  - [x] `RbacChecker` delegates to Spatie permission `hasPermission`
  - [x] `AbacChecker` delegates to Laravel Gate/Policy system
  - [x] All bound in `RepositoryServiceProvider`

- [x] Task 3: CheckPermission middleware (AC: 5)
  - [x] `app/Http/Middleware/CheckPermission.php` — checks `$user->hasPermission($permission)` via `AuthorizationService`
  - [x] Admin bypass: admins pass all permission checks
  - [x] 403 response via `ApiResponse::error` on missing permission
  - [x] Alias `permission` registered in `bootstrap/app.php`

- [ ] Task 4: Tests (AC: 6)
  - [ ] Feature test: seeder runs without errors, roles exist with correct permission counts
  - [ ] Feature test: `CheckPermission` returns 403 when user lacks permission
  - [ ] Feature test: admin bypasses all permission checks

## Dev Agent Record

**Implementation:** 2026-04-28 (pre-existing)
**Story file created:** 2026-04-30 (retrospective)
**Remaining work:** Test coverage (Task 4) — implementation is complete and working
