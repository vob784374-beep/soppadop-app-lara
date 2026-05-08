# Story 1.6: API Foundation — Envelope, Exception Handler & Repository Pattern

Status: done

## Story

As a developer,
I want the API response envelope, centralized exception handler, and repository pattern infrastructure in place,
So that every API response is consistent and all feature stories build on a correct, layered architecture.

## Acceptance Criteria

1. **Given** the Laravel backend is running
   **When** any API endpoint returns a successful response
   **Then** the response follows the envelope: `{ "data": {}, "message": "string", "errors": null, "meta": null }`

2. **When** a validation error occurs
   **Then** the response returns HTTP 422 with `errors` populated as a field-keyed object

3. **When** an unhandled exception occurs
   **Then** the response returns HTTP 500 with `"message": "An unexpected error occurred."` — no stack trace exposed in the body

4. All exception types are mapped to the standard envelope via `bootstrap/app.php` `withExceptions()` (**not** `app/Exceptions/Handler.php` — that file does not exist in Laravel 13**)

5. `BaseRepository`, `UserRepositoryInterface`, `ItemRepositoryInterface` exist with stub implementations; concrete `UserRepository` and `ItemRepository` extend `BaseRepository`

6. `RepositoryServiceProvider` binds interfaces to implementations and is registered in `bootstrap/providers.php`

7. Laravel Sanctum config is published (`config/sanctum.php` must exist) — migrations were already published in Story 1.4

8. `ForceJsonResponse` middleware is created at `app/Http/Middleware/ForceJsonResponse.php` and prepended to the `api` middleware group via `bootstrap/app.php` `withMiddleware()`

## Tasks / Subtasks

- [x] Task 1: Publish Sanctum config (AC: 7)
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
  - [x] Confirm `backend/config/sanctum.php` now exists (migrations were already published in Story 1.4 — skip if prompted about them)

- [x] Task 2: Create `ForceJsonResponse` middleware and register it (AC: 8)
  - [x] Create `backend/app/Http/Middleware/ForceJsonResponse.php` (see Dev Notes: ForceJsonResponse)
  - [x] Register in `bootstrap/app.php` → `withMiddleware()` — prepend to the `api` group only (see Dev Notes: Middleware Registration)
  - [x] Verify: requests to `/api/v1/*` without an `Accept` header still get JSON responses

- [x] Task 3: Create `ApiResponse` helper class (AC: 1, 2, 3)
  - [x] Create `backend/app/Http/Responses/ApiResponse.php` (see Dev Notes: ApiResponse Helper)
  - [x] Methods: `success($data, $message, $meta, $status)` and `error($message, $errors, $status)`
  - [x] All controllers in future stories MUST use this class — never return raw `response()->json()` directly

- [x] Task 4: Configure centralized exception handling in `bootstrap/app.php` (AC: 3, 4)
  - [x] Add exception rendering inside `->withExceptions()` callback (see Dev Notes: Exception Handling)
  - [x] Map: `ValidationException` → 422 with errors array
  - [x] Map: `AuthenticationException` → 401 with `"message": "Unauthenticated."`
  - [x] Map: `ModelNotFoundException` → 404 with `"message": "Resource not found."`
  - [x] Map: any other `\Throwable` → 500 with `"message": "An unexpected error occurred."` (no stack trace)
  - [x] Guard: only render as JSON if `$request->expectsJson()` is true (ForceJsonResponse middleware ensures this for all `/api/v1/*` requests)

- [x] Task 5: Create Repository pattern infrastructure (AC: 5, 6)
  - [x] Create `backend/app/Repositories/Contracts/UserRepositoryInterface.php`
  - [x] Create `backend/app/Repositories/Contracts/ItemRepositoryInterface.php`
  - [x] Create `backend/app/Repositories/BaseRepository.php` with `paginate()` and `filter()` stub methods (see Dev Notes: BaseRepository)
  - [x] Create `backend/app/Repositories/UserRepository.php` extending BaseRepository, implementing UserRepositoryInterface
  - [x] Create `backend/app/Repositories/ItemRepository.php` extending BaseRepository, implementing ItemRepositoryInterface
  - [x] Create `backend/app/Providers/RepositoryServiceProvider.php` (see Dev Notes: RepositoryServiceProvider)
  - [x] Register `RepositoryServiceProvider` in `backend/bootstrap/providers.php`

- [x] Task 6: Write feature and unit tests (AC: 1–8)
  - [x] Create `backend/tests/Feature/ApiFoundationTest.php` (see Dev Notes: Feature Tests)
  - [x] Test: successful API response follows envelope structure
  - [x] Test: validation failure returns 422 with `errors` key populated
  - [x] Test: unhandled exception returns 500 with sanitized message, no stack trace
  - [x] Test: `ForceJsonResponse` sets `Accept: application/json` header
  - [x] Test: `UserRepositoryInterface` and `ItemRepositoryInterface` resolve from container
  - [x] Run: full test suite — 18/18 passed, 0 regressions

## Dev Notes

### ⚠️ Laravel 13 Critical Architecture Difference

The epic and architecture document mention `app/Exceptions/Handler.php`. **This file does not exist in Laravel 13.** Exception handling in Laravel 11+ is configured exclusively in `bootstrap/app.php` via the `withExceptions()` callback.

**Do NOT create `app/Exceptions/Handler.php`.**

The equivalent pattern is:

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\Throwable $e, Request $request) {
        // ...
    });
})
```

### Pre-existing State

| File | Status | Notes |
|------|--------|-------|
| `bootstrap/app.php` | exists | `apiPrefix: 'api/v1'` already configured; `withExceptions()` callback is empty |
| `bootstrap/providers.php` | exists | Only has `AppServiceProvider` — add `RepositoryServiceProvider` here |
| `routes/api.php` | exists | Empty — no routes yet (feature routes added in later stories) |
| `config/sanctum.php` | **missing** | Must be published in Task 1 |
| `app/Http/Middleware/` | **empty dir** | `ForceJsonResponse.php` must be created |
| `app/Repositories/` | **does not exist** | Create entire directory tree |
| `app/Providers/RepositoryServiceProvider.php` | **does not exist** | Must be created |
| Sanctum migrations | done (Story 1.4) | `2026_04_27_074038_create_personal_access_tokens_table.php` already exists |

### ForceJsonResponse Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
```

### Middleware Registration (Laravel 13 syntax)

In `bootstrap/app.php`, add to the `withMiddleware()` callback:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \App\Http\Middleware\ForceJsonResponse::class,
    ]);
})
```

`api(prepend: [...])` prepends only to the `api` middleware group (all `/api/v1/*` routes), leaving web routes unaffected.

### ApiResponse Helper

```php
<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        mixed $meta = null,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'data'    => $data,
            'message' => $message,
            'errors'  => null,
            'meta'    => $meta,
        ], $status);
    }

    public static function error(
        string $message,
        mixed $errors = null,
        int $status = 400
    ): JsonResponse {
        return response()->json([
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
            'meta'    => null,
        ], $status);
    }
}
```

File location: `backend/app/Http/Responses/ApiResponse.php`

### Exception Handling in `bootstrap/app.php`

```php
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\Throwable $e, Request $request): ?\Illuminate\Http\JsonResponse {
        if (! $request->expectsJson()) {
            return null; // let Laravel handle non-JSON requests normally
        }

        return match (true) {
            $e instanceof ValidationException => response()->json([
                'data'    => null,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
                'meta'    => null,
            ], 422),

            $e instanceof AuthenticationException => response()->json([
                'data'    => null,
                'message' => 'Unauthenticated.',
                'errors'  => null,
                'meta'    => null,
            ], 401),

            $e instanceof ModelNotFoundException => response()->json([
                'data'    => null,
                'message' => 'Resource not found.',
                'errors'  => null,
                'meta'    => null,
            ], 404),

            default => response()->json([
                'data'    => null,
                'message' => 'An unexpected error occurred.',
                'errors'  => null,
                'meta'    => null,
            ], 500),
        };
    });
})
```

**Critical:** The `return null` for non-JSON requests allows Laravel to render HTML error pages for web routes. Only `/api/v1/*` requests will have `expectsJson()` = true (guaranteed by `ForceJsonResponse` middleware).

### BaseRepository

```php
<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    public function __construct(protected Model $model) {}

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage);
    }

    public function filter(array $filters): static
    {
        return $this; // stub — concrete repos override for query-param filtering (Story 3.2)
    }
}
```

### Repository Interfaces (stubs)

```php
// UserRepositoryInterface.php
<?php
namespace App\Repositories\Contracts;
interface UserRepositoryInterface {}

// ItemRepositoryInterface.php
<?php
namespace App\Repositories\Contracts;
interface ItemRepositoryInterface {}
```

### Concrete Repositories

```php
// UserRepository.php
<?php
namespace App\Repositories;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }
}

// ItemRepository.php
<?php
namespace App\Repositories;
use App\Models\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;

class ItemRepository extends BaseRepository implements ItemRepositoryInterface
{
    public function __construct(Item $model)
    {
        parent::__construct($model);
    }
}
```

### RepositoryServiceProvider

```php
<?php

namespace App\Providers;

use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\ItemRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ItemRepositoryInterface::class, ItemRepository::class);
    }
}
```

Register in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
];
```

### Feature Tests

Tests register temporary routes at test time using `Route::get()` — this avoids adding test-only routes to production route files.

```php
<?php

namespace Tests\Feature;

use App\Http\Responses\ApiResponse;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_api_response_helper_returns_envelope(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Created', null, 201);
        $data = $response->getData(true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertNull($data['errors']);
        $this->assertNull($data['meta']);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_force_json_response_middleware_sets_accept_header(): void
    {
        Route::middleware('api')->get('/test-json-header', function (\Illuminate\Http\Request $request) {
            return response()->json(['accept' => $request->header('Accept')]);
        });

        $response = $this->get('/api/v1/test-json-header');
        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->json('accept'));
    }

    public function test_unhandled_exception_returns_sanitized_500_envelope(): void
    {
        Route::middleware('api')->get('/test-exception', function () {
            throw new \RuntimeException('Sensitive internal error details');
        });

        $response = $this->getJson('/api/v1/test-exception');
        $response->assertStatus(500)
            ->assertJson([
                'data'    => null,
                'message' => 'An unexpected error occurred.',
                'errors'  => null,
                'meta'    => null,
            ]);

        $this->assertStringNotContainsString('Sensitive internal error details', $response->content());
    }

    public function test_validation_exception_returns_422_with_errors(): void
    {
        Route::middleware('api')->post('/test-validation', function (\Illuminate\Http\Request $request) {
            $request->validate(['name' => 'required']);
        });

        $response = $this->postJson('/api/v1/test-validation', []);
        $response->assertStatus(422)
            ->assertJsonStructure([
                'data',
                'message',
                'errors' => ['name'],
                'meta',
            ]);
    }

    public function test_user_repository_resolves_from_container(): void
    {
        $repo = app(UserRepositoryInterface::class);
        $this->assertInstanceOf(UserRepositoryInterface::class, $repo);
    }

    public function test_item_repository_resolves_from_container(): void
    {
        $repo = app(ItemRepositoryInterface::class);
        $this->assertInstanceOf(ItemRepositoryInterface::class, $repo);
    }
}
```

**Note:** Tests that use `Route::middleware('api')` dynamically register routes visible only within that test. No test route pollution in production.

### Scope Boundaries — DO NOT implement in Story 1.6

| Excluded | Belongs To |
|----------|-----------|
| Sanctum API auth routes (`/api/v1/auth/*`) | Story 2.1 |
| `auth:sanctum` middleware on protected routes | Story 2.1 |
| Rate limiting configuration | Story 2.1+ |
| `UserResource` / `ItemResource` API transformers | Story 2.5, 3.1 |
| Actual business logic in repositories | Story 3.1, 3.2 |
| `AuthService`, `ItemService`, `ProfileService` | Story 2.1+ |
| `ApiException` custom exception class | Story 1.6 scope only if AC requires it — skip if not needed |

### Docker Commands

```bash
# Publish Sanctum config
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend \
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run tests
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test --filter=ApiFoundationTest

# Run full suite
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test
```

### References

- `_bmad-output/planning-artifacts/epics.md#Story 1.6` — AC source
- `_bmad-output/planning-artifacts/architecture.md` — API envelope format, repository pattern, middleware requirements
- `backend/bootstrap/app.php` — existing `withExceptions()` and `withMiddleware()` hooks (both currently empty)
- `backend/bootstrap/providers.php` — add `RepositoryServiceProvider` here
- Story 1.4 Dev Notes — `#[Fillable]` attribute pattern, PHPUnit.xml runs on SQLite in-memory for tests
- Story 1.5 Dev Notes — `phpunit.xml` overrides; tests run inside Docker; `config()` per-class override pattern

## Review Findings

- [x] [Review][Decision] `HttpExceptionInterface` exposes raw `$e->getMessage()` verbatim — resolved: keep as-is; `abort()` messages are user-facing by Laravel convention.

- [x] [Review][Patch] `BaseRepository::paginate()` accepts unused `$filters` parameter — fixed: removed `array $filters = []` from signature `[BaseRepository.php:12]`.

- [x] [Review][Defer] `BaseRepository::filter()` is an intentional no-op stub `[BaseRepository.php:17]` — deferred, pre-existing (Story 3.2 will implement filtering logic)
- [x] [Review][Defer] `UserRepositoryInterface` and `ItemRepositoryInterface` are empty stubs `[Contracts/]` — deferred, pre-existing (intentionally minimal per AC5; methods added in Story 3+)
- [x] [Review][Defer] `sanctum.expiration` is `null` — tokens never expire `[config/sanctum.php]` — deferred, pre-existing (token expiry configuration belongs to Story 2.1)
- [x] [Review][Defer] `ItemFactory` missing `user_id`, `Item::$fillable` missing `user_id` `[ItemFactory.php, Item.php]` — deferred, pre-existing (carried from Story 1.4; noted in that story's deferred work)
- [x] [Review][Defer] `ApiResponse::error()` default status hardcoded to 400 `[ApiResponse.php:23]` — deferred, pre-existing (acceptable for now; named constructors and proper defaults deferred to Story 2+)
- [x] [Review][Defer] `expectsJson()` guard may not fire for route-not-found 404s before middleware runs `[bootstrap/app.php:21]` — deferred, pre-existing (theoretical; API clients should send `Accept: application/json`; ForceJsonResponse handles well-behaved clients)

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- `bootstrap/app.php` exception handler required a `HttpExceptionInterface` case (Symfony HTTP exceptions like 404/405) added before `default`, otherwise `NotFoundHttpException` from unregistered test routes would fall through to 500.
- Test routes registered via `Route::middleware('api')->get('/path', ...)` do NOT inherit the `apiPrefix: 'api/v1'` prefix from `bootstrap/app.php` — that prefix only applies to routes loaded from `routes/api.php`. Fixed by registering test routes at the full `/api/v1/path` URI.
- `laravel/sanctum` was listed in `composer.json` but missing from `vendor/` on the host (vendor populated inside Docker). Ran `composer require laravel/sanctum:^4.3` on host to make local PHP commands work.

### Completion Notes List

- Added `HttpExceptionInterface` arm to `bootstrap/app.php` exception handler (between `ModelNotFoundException` and `default`) so HTTP exceptions return their correct status codes rather than 500.
- All 6 `ApiFoundationTest` tests pass; full suite 18/18.

### File List

- `backend/config/sanctum.php` — published via vendor:publish (Task 1)
- `backend/app/Http/Middleware/ForceJsonResponse.php` — created (Task 2)
- `backend/bootstrap/app.php` — modified: added `withMiddleware()` registration + full `withExceptions()` handler (Tasks 2, 4)
- `backend/app/Http/Responses/ApiResponse.php` — created (Task 3)
- `backend/app/Repositories/Contracts/UserRepositoryInterface.php` — created (Task 5)
- `backend/app/Repositories/Contracts/ItemRepositoryInterface.php` — created (Task 5)
- `backend/app/Repositories/BaseRepository.php` — created (Task 5)
- `backend/app/Repositories/UserRepository.php` — created (Task 5)
- `backend/app/Repositories/ItemRepository.php` — created (Task 5)
- `backend/app/Providers/RepositoryServiceProvider.php` — created (Task 5)
- `backend/bootstrap/providers.php` — modified: added RepositoryServiceProvider (Task 5)
- `backend/tests/Feature/ApiFoundationTest.php` — created (Task 6)
