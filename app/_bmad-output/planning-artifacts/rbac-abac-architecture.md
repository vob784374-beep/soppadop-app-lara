---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
status: 'complete'
completedAt: '2026-04-28'
workflowType: 'architecture'
project_name: 'app'
user_name: 'Bang'
date: '2026-04-28'
scope: 'RBAC + ABAC Authorization — IELTS LMS'
parentDocument: 'architecture.md'
---

# RBAC + ABAC Authorization Module — Architecture Decision Document

> **Scope:** Authorization system for an IELTS Learning Management System. Covers role-based coarse-grained access (RBAC) and attribute-based fine-grained access (ABAC) using a unified authorization service. See `architecture.md` for project-level decisions and `jwt-auth-architecture.md` for authentication.

---

## 1. Domain Model

### Roles

| Role | Description |
|---|---|
| `admin` | Full system access — manages users, courses, reports |
| `instructor` | Creates and manages own courses, lessons, grades submissions |
| `student` | Enrolls in courses, views content, submits assignments |

### Resources

| Resource | Owner | Key Attributes |
|---|---|---|
| `Course` | Instructor | `instructor_id`, `published`, `enrollment_open` |
| `Lesson` | Course (instructor) | `course_id`, `published`, `order` |
| `Assignment` | Course (instructor) | `course_id`, `deadline` |
| `Submission` | Student | `assignment_id`, `student_id`, `submitted_at` |
| `Enrollment` | Student | `student_id`, `course_id`, `enrolled_at` |

### Permission Matrix (RBAC — coarse-grained)

| Permission | admin | instructor | student |
|---|---|---|---|
| `manage-users` | ✅ | ❌ | ❌ |
| `manage-courses` | ✅ | ❌ | ❌ |
| `view-reports` | ✅ | ❌ | ❌ |
| `create-courses` | ✅ | ✅ | ❌ |
| `edit-courses` | ✅ | ✅ | ❌ |
| `delete-courses` | ✅ | ❌ | ❌ |
| `create-lessons` | ✅ | ✅ | ❌ |
| `edit-lessons` | ✅ | ✅ | ❌ |
| `create-assignments` | ✅ | ✅ | ❌ |
| `grade-submissions` | ✅ | ✅ | ❌ |
| `view-courses` | ✅ | ✅ | ✅ |
| `enroll-courses` | ❌ | ❌ | ✅ |
| `submit-assignments` | ❌ | ❌ | ✅ |
| `view-own-grades` | ❌ | ❌ | ✅ |

### ABAC Rules (fine-grained — contextual)

| Rule | Condition | Enforced by |
|---|---|---|
| Student views course | Must be enrolled | `CoursePolicy::view` |
| Student views lesson | Must be enrolled in course | `LessonPolicy::view` |
| Student submits assignment | Must be enrolled + deadline not passed | `AssignmentPolicy::submit` |
| Instructor edits course | Must own the course | `CoursePolicy::update` |
| Instructor edits lesson | Must own the parent course | `LessonPolicy::update` |
| Instructor grades submission | Must own the assignment's course | `SubmissionPolicy::grade` |
| Student views submission | Must be own submission | `SubmissionPolicy::view` |
| Admin | All ABAC checks pass automatically | `AuthorizationService` short-circuit |

---

## 2. SOLID Design Decomposition

### Single Responsibility

| Class | Single Responsibility |
|---|---|
| `AuthorizationService` | Unified entry point: runs RBAC check then ABAC check |
| `RbacChecker` | Only evaluates role-based permissions (Spatie lookup) |
| `AbacChecker` | Only delegates to the correct Laravel Policy |
| `CheckPermission` (middleware) | Coarse-grained gate — blocks unauthorized roles before controller |
| `CoursePolicy` | ABAC rules for Course resource only |
| `LessonPolicy` | ABAC rules for Lesson resource only |
| `AssignmentPolicy` | ABAC rules for Assignment resource only |
| `SubmissionPolicy` | ABAC rules for Submission resource only |
| `EnrollmentRepository` | Enrollment lookup + caching only |

### Open/Closed

- New roles → seed new role + assign permissions, zero code changes
- New permissions → add to seeder + assign to roles, zero code changes
- New resource type → add new Policy class implementing `PolicyInterface`, register in `AuthServiceProvider`
- New ABAC rule → add method to existing Policy, update `AuthorizationService` dispatch map

### Dependency Inversion

```
Controller         → AuthorizationServiceInterface
CheckPermission    → AuthorizationServiceInterface
AuthorizationService → RbacCheckerInterface
AuthorizationService → AbacCheckerInterface
AbacChecker        → Laravel Gate (policy dispatch)
RbacChecker        → Spatie HasRoles (via User model)
```

---

## 3. Package Decision: Spatie laravel-permission

**Chosen:** `spatie/laravel-permission`

**Rationale:**
- Industry standard — 24M+ downloads, maintained, Laravel 12 compatible
- Handles DB schema (roles, permissions, pivot tables), model traits, caching
- Laravel Gate integration out of the box via `@can`, `Gate::allows`, middleware
- Redis-backed permission cache — critical for performance at scale (no per-request DB hits)
- Extensible: custom guards, team-based permissions, wildcard matching

**Tradeoff:** Adds a dependency with opinionated schema. Acceptable — the schema is stable and well-understood.

**NOT used for ABAC:** Spatie handles RBAC only. ABAC lives in Laravel Policies + custom `AbacChecker`.

---

## 4. Database Schema

### Spatie tables (auto-migrated by package)

```
roles                  (id, name, guard_name, created_at, updated_at)
permissions            (id, name, guard_name, created_at, updated_at)
role_has_permissions   (permission_id, role_id)
model_has_roles        (role_id, model_type, model_id)
model_has_permissions  (permission_id, model_type, model_id)
```

### LMS domain tables

```sql
-- courses
CREATE TABLE courses (
    id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    instructor_id BIGINT UNSIGNED NOT NULL REFERENCES users(id),
    title        VARCHAR(255) NOT NULL,
    description  TEXT,
    published    BOOLEAN DEFAULT FALSE,
    created_at   TIMESTAMP,
    updated_at   TIMESTAMP,
    INDEX (instructor_id)
);

-- lessons
CREATE TABLE lessons (
    id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    course_id  BIGINT UNSIGNED NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    title      VARCHAR(255) NOT NULL,
    content    TEXT,
    `order`    SMALLINT UNSIGNED DEFAULT 0,
    published  BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (course_id)
);

-- assignments
CREATE TABLE assignments (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    course_id   BIGINT UNSIGNED NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    deadline    TIMESTAMP NOT NULL,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP,
    INDEX (course_id)
);

-- enrollments
CREATE TABLE enrollments (
    id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id  BIGINT UNSIGNED NOT NULL REFERENCES users(id),
    course_id   BIGINT UNSIGNED NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment (student_id, course_id),
    INDEX (student_id),
    INDEX (course_id)
);

-- submissions
CREATE TABLE submissions (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    assignment_id BIGINT UNSIGNED NOT NULL REFERENCES assignments(id) ON DELETE CASCADE,
    student_id    BIGINT UNSIGNED NOT NULL REFERENCES users(id),
    content       TEXT NOT NULL,
    grade         TINYINT UNSIGNED,
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_submission (assignment_id, student_id),
    INDEX (assignment_id),
    INDEX (student_id)
);
```

---

## 5. Interface Contracts

### `AuthorizationServiceInterface`

```php
namespace App\Services\Authorization\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AuthorizationServiceInterface
{
    // RBAC: does user have this permission?
    public function hasPermission(User $user, string $permission): bool;

    // ABAC: can user perform ability on resource?
    public function can(User $user, string $ability, Model $resource): bool;

    // Combined: RBAC gate first, then ABAC check
    public function authorize(User $user, string $permission, ?Model $resource = null): bool;
}
```

### `RbacCheckerInterface`

```php
namespace App\Services\Authorization\Contracts;

use App\Models\User;

interface RbacCheckerInterface
{
    public function hasPermission(User $user, string $permission): bool;
    public function hasRole(User $user, string $role): bool;
    public function getRoles(User $user): array;
}
```

### `AbacCheckerInterface`

```php
namespace App\Services\Authorization\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AbacCheckerInterface
{
    public function can(User $user, string $ability, Model $resource): bool;
}
```

---

## 6. Component Architecture

### `AuthorizationService` (unified)

```
authorize(user, permission, resource):
  1. If user is admin → return true (admin short-circuit)
  2. RBAC: hasPermission(user, permission) → false = deny immediately
  3. If resource is null → return true (RBAC passed, no resource to check)
  4. ABAC: can(user, permission, resource) → return result
```

### `RbacChecker`

```
hasPermission(user, permission):
  → user->hasPermissionTo(permission)   [Spatie — cached in Redis]
```

### `AbacChecker`

```
can(user, ability, resource):
  → Gate::forUser(user)->inspect(ability, resource)
  → Dispatches to registered Policy class
```

### `CheckPermission` middleware

```
handle(request, next, permission):
  1. Get authenticated user
  2. authService->hasPermission(user, permission) → 403 if false
  3. Continue to controller
```

---

## 7. Caching Strategy

### RBAC cache (Spatie — automatic)

- Permission/role lookups cached per user in Redis
- Cache key: `spatie.permission.cache`
- TTL: configurable via `config/permission.php` (`cache_expiration_time`)
- Automatic invalidation: when user's roles/permissions change

### ABAC cache (enrollment lookups)

Enrollment checks are the most frequent ABAC DB query. Cache them:

```
Cache key: "enrollment:{user_id}:{course_id}"
TTL: 1 hour (enrollments change infrequently)
Invalidation: on enrollment create/delete events
```

### Implementation

```php
// EnrollmentRepository
public function isEnrolled(int $studentId, int $courseId): bool
{
    return Cache::remember(
        "enrollment:{$studentId}:{$courseId}",
        now()->addHour(),
        fn () => Enrollment::where([
            'student_id' => $studentId,
            'course_id'  => $courseId,
        ])->exists()
    );
}
```

---

## 8. Policy Design (ABAC)

Each policy implements a shared structure. Admin short-circuit is handled at `AuthorizationService` level — policies never need to check `isAdmin`.

### `CoursePolicy`

```php
view(User $user, Course $course):
  - instructor: owns course OR course is published
  - student: is enrolled

update(User $user, Course $course):
  - instructor: owns course (course->instructor_id == user->id)

delete(User $user, Course $course):
  - [handled at RBAC level — only admin has delete-courses]
  - policy returns false for non-admin (belt-and-suspenders)
```

### `LessonPolicy`

```php
view(User $user, Lesson $lesson):
  - instructor: owns parent course
  - student: enrolled in parent course

update(User $user, Lesson $lesson):
  - instructor: owns parent course
```

### `AssignmentPolicy`

```php
view(User $user, Assignment $assignment):
  - instructor: owns course
  - student: enrolled in course

submit(User $user, Assignment $assignment):
  - student: enrolled in course AND now() < assignment->deadline
```

### `SubmissionPolicy`

```php
view(User $user, Submission $submission):
  - instructor: owns the assignment's course
  - student: submission->student_id == user->id

grade(User $user, Submission $submission):
  - instructor: owns the assignment's course
```

---

## 9. Request Flow Diagrams

### RBAC-only request (e.g., create course)

```
POST /api/v1/courses
  → JwtAuthenticate middleware (identity)
  → CheckPermission('create-courses') middleware
      → AuthorizationService::hasPermission(user, 'create-courses')
      → RbacChecker → Spatie [Redis cache hit]
      → 403 if false
  → CourseController::store()
  → CourseService::create()
```

### RBAC + ABAC request (e.g., student views lesson)

```
GET /api/v1/lessons/{id}
  → JwtAuthenticate middleware
  → CheckPermission('view-lessons') middleware    [RBAC gate]
  → LessonController::show()
  → AuthorizationService::authorize(user, 'view', $lesson)   [ABAC]
      → Admin? → pass
      → RBAC: hasPermission('view-lessons') → cached pass
      → ABAC: Gate::forUser(user)->inspect('view', $lesson)
          → LessonPolicy::view()
          → EnrollmentRepository::isEnrolled() [Redis cache]
          → 403 if not enrolled
  → LessonService::get()
```

### Assignment submission with deadline (ABAC contextual)

```
POST /api/v1/assignments/{id}/submissions
  → JwtAuthenticate
  → CheckPermission('submit-assignments')    [RBAC: student only]
  → SubmissionController::store()
  → AuthorizationService::authorize(user, 'submit', $assignment)
      → AssignmentPolicy::submit()
          → isEnrolled? [cached]
          → now() < $assignment->deadline? [no DB — attribute on model]
          → 403 if either fails
  → SubmissionService::create()
```

---

## 10. Implementation Patterns

### Role seeding

```php
// database/seeders/RolePermissionSeeder.php
$permissions = [
    'manage-users', 'manage-courses', 'view-reports',
    'create-courses', 'edit-courses', 'delete-courses',
    'create-lessons', 'edit-lessons',
    'create-assignments', 'grade-submissions',
    'view-courses', 'view-lessons', 'view-assignments',
    'enroll-courses', 'submit-assignments', 'view-own-grades',
];

// Create all permissions
foreach ($permissions as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
}

// Create roles and assign permissions
$admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
$admin->syncPermissions($permissions); // admin gets all

$instructor = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'api']);
$instructor->syncPermissions([
    'create-courses', 'edit-courses', 'create-lessons', 'edit-lessons',
    'create-assignments', 'grade-submissions',
    'view-courses', 'view-lessons', 'view-assignments',
]);

$student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);
$student->syncPermissions([
    'view-courses', 'view-lessons', 'view-assignments',
    'enroll-courses', 'submit-assignments', 'view-own-grades',
]);
```

### Extensibility: adding a new role

```php
// 1. Create role
$ta = Role::create(['name' => 'teaching-assistant', 'guard_name' => 'api']);

// 2. Assign existing permissions
$ta->syncPermissions(['view-courses', 'view-lessons', 'grade-submissions']);

// 3. If new permission needed
Permission::create(['name' => 'assist-instructor', 'guard_name' => 'api']);
$ta->givePermissionTo('assist-instructor');

// 4. If new ABAC rule needed — add method to relevant Policy
// SubmissionPolicy::assistGrade(User $ta, Submission $submission): bool
```

### Route middleware usage

```php
// routes/api.php
Route::middleware(['jwt.authenticate'])->group(function () {

    // RBAC-gated — any authenticated user with permission
    Route::get('/courses', [CourseController::class, 'index'])
        ->middleware('permission:view-courses');

    // RBAC-gated for write operations
    Route::post('/courses', [CourseController::class, 'store'])
        ->middleware('permission:create-courses');

    Route::patch('/courses/{course}', [CourseController::class, 'update'])
        ->middleware('permission:edit-courses');

    // RBAC + ABAC — permission gates entry, policy gates resource
    Route::get('/lessons/{lesson}', [LessonController::class, 'show'])
        ->middleware('permission:view-lessons');

    Route::post('/assignments/{assignment}/submissions', [SubmissionController::class, 'store'])
        ->middleware('permission:submit-assignments');
});
```

---

## 11. Enforcement Rules for AI Agents

**ALL agents implementing LMS authorization MUST:**
- Call `AuthorizationService::authorize()` for any resource-level check inside service methods
- Use `CheckPermission` middleware (Spatie's `permission:` alias) for route-level RBAC gates
- Never call `Gate::allows()` directly in controllers — always go through `AuthorizationService`
- Never perform enrollment DB queries without going through `EnrollmentRepository` (ensures cache hit)
- Always use the `api` guard name when assigning roles/permissions
- Run `php artisan permission:cache-reset` after seeding role/permission changes

**Anti-patterns:**
- ❌ `$user->hasPermissionTo()` in a controller — use middleware instead
- ❌ Enrollment check inline in controller — use `AuthorizationService`
- ❌ `Enrollment::where(...)->exists()` outside `EnrollmentRepository` — bypasses cache
- ❌ Hardcoding role names in policy logic — always compare against permission names
- ❌ Forgetting `guard_name: 'api'` on permissions/roles — will cause guard mismatch exceptions
- ❌ Admin special-casing in policies — `AuthorizationService` handles admin short-circuit
