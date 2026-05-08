# Story 1.4: Database Migrations & Schema Foundation

Status: done

## Story

As a developer,
I want all initial database migrations created and indexed correctly,
so that the schema is version-controlled, reproducible across environments, and performs within NFR1 targets.

## Acceptance Criteria

1. Running `php artisan migrate` inside the backend container creates exactly four application tables: `users`, `personal_access_tokens`, `password_reset_tokens`, `items`.
2. `users` table has a unique index on `email` and a composite/single index on `created_at`.
3. `items` table has an index on `user_id` (via `foreignId` FK) and an index on `created_at`.
4. All migrations are idempotent — running `php artisan migrate:fresh` produces the same schema with no errors.
5. `UserFactory` and `ItemFactory` both exist and produce valid, persistable model instances.
6. `php artisan db:seed` runs without errors in development and creates at least one user and two items.

## Tasks / Subtasks

- [x] Task 1: Install Laravel Sanctum package (AC: 1)
  - [x] Run `composer require laravel/sanctum` inside the backend container
  - [x] Publish Sanctum migrations only: `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag=sanctum-migrations`
  - [x] Verify `database/migrations/*_create_personal_access_tokens_table.php` was created
  - [x] **Do NOT** publish Sanctum config or register Sanctum middleware/routes — that is Story 1.6

- [x] Task 2: Add `created_at` index to existing users migration (AC: 2)
  - [x] Open `database/migrations/0001_01_01_000000_create_users_table.php`
  - [x] After the `$table->rememberToken()` line inside the `users` table blueprint, add: `$table->index('created_at');`
  - [x] Verify the migration still runs cleanly with `migrate:fresh`

- [x] Task 3: Create `items` migration (AC: 3, 4)
  - [x] Generate: `php artisan make:migration create_items_table`
  - [x] Define the `items` table schema (see Dev Notes: Items Table Schema)
  - [x] Include `$table->foreignId('user_id')->constrained()->cascadeOnDelete();` (adds FK + index automatically)
  - [x] Add `$table->index('created_at');` after timestamps
  - [x] Verify `down()` method drops the table: `Schema::dropIfExists('items');`

- [x] Task 4: Create `Item` model (AC: 5)
  - [x] Generate: `php artisan make:model Item`
  - [x] Apply PHP 8 attribute syntax to match `User.php` style (see Dev Notes: Item Model)
  - [x] Set `$fillable` via `#[Fillable]` attribute: `['user_id', 'title', 'description']`
  - [x] Define `belongsTo(User::class)` relationship method `user()`

- [x] Task 5: Create `ItemFactory` (AC: 5)
  - [x] Generate: `php artisan make:factory ItemFactory --model=Item`
  - [x] Implement `definition()`: `user_id` via `User::factory()`, `title` via `fake()->sentence(4)`, `description` via `fake()->paragraph()` (nullable — sometimes null)

- [x] Task 6: Update `DatabaseSeeder` (AC: 6)
  - [x] Open `database/seeders/DatabaseSeeder.php`
  - [x] Keep existing user creation (test@example.com)
  - [x] After user creation, call `Item::factory(2)->create(['user_id' => $user->id])` for each test user
  - [x] Add `use App\Models\Item;` import

- [x] Task 7: Run and verify migrations (AC: 1–4)
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan migrate:fresh --seed`
  - [x] Confirm all four tables exist: `users`, `personal_access_tokens`, `password_reset_tokens`, `items`
  - [x] Confirm `php artisan migrate:fresh --seed` runs a second time without errors (idempotency)
  - [x] Confirm `php artisan db:seed` runs without errors on a fresh schema

- [x] Task 8: Write feature tests (AC: 1–6)
  - [x] Create `tests/Feature/DatabaseSchemaTest.php` (see Dev Notes: Test Requirements)
  - [x] Test: all four tables exist after migration
  - [x] Test: `UserFactory::new()->create()` persists a record with valid fields
  - [x] Test: `ItemFactory::new()->create()` persists a record linked to a user
  - [x] Test: `DatabaseSeeder` runs without exceptions (`$this->seed()`)
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test --filter=DatabaseSchemaTest`

## Dev Notes

### Pre-existing State

The following migrations already exist and must NOT be replaced:

| File | Tables Created | Notes |
|------|---------------|-------|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` | `users` missing `created_at` index — add it in Task 2 |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` | Leave untouched |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` | Leave untouched |

Sanctum is **not** in `composer.json` — install it in Task 1. The published migration creates `personal_access_tokens`.

`UserFactory` already exists at `database/factories/UserFactory.php` with an `unverified()` state — do not replace it.

### Items Table Schema

```php
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->timestamps();
    $table->index('created_at');
});
```

`foreignId('user_id')->constrained()` automatically creates a FK referencing `users.id` and adds an index on `user_id`. No separate `$table->index('user_id')` is needed.

### Item Model

Use PHP 8 attribute syntax — same pattern as `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'description'])]
class Item extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Do not** use the legacy `$fillable` array property — it conflicts with the attribute.

### ItemFactory

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title'   => fake()->sentence(4, false),
            'description' => fake()->optional(0.8)->paragraph(),
        ];
    }
}
```

`optional(0.8)` returns a value 80% of the time, null 20% — exercises the nullable column.

### Updated DatabaseSeeder

```php
<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        Item::factory(2)->create(['user_id' => $user->id]);
    }
}
```

### Test Requirements

Use `RefreshDatabase` trait so each test runs in a transaction that rolls back.

```php
<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_exist(): void
    {
        foreach (['users', 'personal_access_tokens', 'password_reset_tokens', 'items'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }
    }

    public function test_users_table_has_created_at_index(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'created_at'));
        // Column presence + migration running without error confirms index; deeper inspection is DB-vendor-specific
    }

    public function test_items_table_has_expected_columns(): void
    {
        foreach (['id', 'user_id', 'title', 'description', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('items', $column), "Column [items.{$column}] missing.");
        }
    }

    public function test_user_factory_creates_valid_record(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    public function test_item_factory_creates_valid_record(): void
    {
        $item = Item::factory()->create();

        $this->assertDatabaseHas('items', ['id' => $item->id, 'user_id' => $item->user_id]);
        $this->assertNotNull($item->title);
    }

    public function test_database_seeder_runs_without_errors(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseCount('items', 2);
    }
}
```

### Scope Boundaries — DO NOT implement in Story 1.4

| Excluded | Belongs To |
|---|---|
| Sanctum config publishing (`sanctum.php`) | Story 1.6 |
| Sanctum middleware (`EnsureFrontendRequestsAreStateful`) | Story 1.6 |
| Sanctum route registration (`/sanctum/csrf-cookie`) | Story 1.6 |
| Redis as CACHE_STORE / QUEUE_CONNECTION | Story 1.5 |
| Queue worker service | Story 1.5 |
| BaseRepository / RepositoryServiceProvider | Story 1.6 |
| API response envelope / exception handler | Story 1.6 |

### Docker Commands

All artisan commands must run inside the backend container:

```bash
# Start dev stack (if not already running)
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Install Sanctum
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend composer require laravel/sanctum

# Publish Sanctum migrations only
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan vendor:publish \
  --provider="Laravel\Sanctum\SanctumServiceProvider" --tag=sanctum-migrations

# Generate migration
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan make:migration create_items_table

# Generate model
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan make:model Item

# Generate factory
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan make:factory ItemFactory --model=Item

# Run fresh migration + seed
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan migrate:fresh --seed

# Run tests
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test --filter=DatabaseSchemaTest
```

### Project Structure Notes

- Migration files live in `backend/database/migrations/` — do not create a subdirectory
- Model lives in `backend/app/Models/Item.php`
- Factory lives in `backend/database/factories/ItemFactory.php`
- Test lives in `backend/tests/Feature/DatabaseSchemaTest.php`
- `php artisan make:*` generates files in the correct locations automatically

### References

- [Source: _bmad-output/planning-artifacts/epics.md#Story 1.4] — Acceptance Criteria
- [Source: backend/app/Models/User.php] — PHP 8 attribute syntax pattern (`#[Fillable]`, `#[Hidden]`)
- [Source: backend/database/factories/UserFactory.php] — Existing factory pattern
- [Source: backend/database/migrations/0001_01_01_000000_create_users_table.php] — Users table (add created_at index)

## Review Findings

- [x] [Review][Decision] D1: `user_id` in `#[Fillable]` enables mass-assignment of item ownership — Options: (A) Remove `user_id` from `#[Fillable]` and update `ItemFactory` to use `forceCreate()` or explicit state so controllers can never receive `user_id` from user input; (B) Keep in fillable and document that every controller must set `user_id` from the authenticated user, never from request input [`backend/app/Models/Item.php:10`] — **RESOLVED: Option A applied**

- [x] [Review][Patch] P1: `test_users_table_has_created_at_index` asserts column presence not index existence — rename and use `Schema::getIndexes('users')` to assert index actually exists [`backend/tests/Feature/DatabaseSchemaTest.php:23-25`] — **PATCHED**
- [x] [Review][Patch] P2: Seeder fails on re-run — `User::factory()->create(['email' => 'test@example.com'])` throws duplicate-key violation on second `db:seed`; replace with `firstOrCreate` [`backend/database/seeders/DatabaseSeeder.php:13`] — **PATCHED**
- [x] [Review][Patch] P3: `fake()->sentence(4, false)` second arg controls word-count variance not punctuation — titles always end with period; use `rtrim(fake()->sentence(4), '.')` or `fake()->words(4, true)` [`backend/database/factories/ItemFactory.php:14`] — **PATCHED**
- [x] [Review][Patch] P4: `WithoutModelEvents` removed from `DatabaseSeeder` — any future observer on User/Item will fire during seeding/tests; restore the trait [`backend/database/seeders/DatabaseSeeder.php`] — **PATCHED**
- [x] [Review][Patch] P5: Dev backend healthcheck probes `/` — fails if route removed or returns redirect; Laravel 13 ships `/up` health endpoint by default; change to `curl -sf http://localhost:8081/up` [`docker-compose.yml:18`] — **PATCHED**
- [x] [Review][Patch] P6: Cascade-delete behavior on `items.user_id` is untested — MySQL enforces FK constraints by default; added cascade test (removed SQLite-specific `PRAGMA foreign_keys = ON` which doesn't apply to MySQL) [`backend/tests/Feature/DatabaseSchemaTest.php`] — **PATCHED**

- [x] [Review][Defer] Prod FPM healthcheck `nc -w1 localhost 9000` only confirms TCP socket open, not FPM worker responsiveness — fix requires FPM ping endpoint config [`docker-compose.prod.yml:10`] — deferred, pre-existing
- [x] [Review][Defer] `items` migration `down()` drops without disabling FK constraints — harmless now (nothing references items), risk grows as schema expands [`backend/database/migrations/2026_04_27_000001_create_items_table.php:21`] — deferred, pre-existing
- [x] [Review][Defer] `ItemFactory` creates an orphan User by default when `user_id` not provided — standard Laravel factory pattern; callers should pass explicit `user_id` [`backend/database/factories/ItemFactory.php:13`] — deferred, pre-existing
- [x] [Review][Defer] `items.title` VARCHAR(255) has no application-level validation rule — belongs to Story 1.6 API request validation [`backend/database/migrations/2026_04_27_000001_create_items_table.php:14`] — deferred, pre-existing
- [x] [Review][Defer] `User` model missing `hasMany(Item::class)` inverse relationship — add incrementally when user-scoped queries are needed (Story 3) [`backend/app/Models/User.php`] — deferred, pre-existing
- [x] [Review][Defer] Framework scaffold tables (sessions, cache, cache_locks, jobs, job_batches, failed_jobs) not covered by schema tests — out of scope for Story 1.4 application-table focus — deferred, pre-existing

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- Sanctum vendor:publish created a NEW timestamped file instead of overwriting manual pre-created file — deleted manual `2019_12_14_000001_create_personal_access_tokens_table.php` and kept official `2026_04_27_074038_create_personal_access_tokens_table.php`. The official file uses `text('name')` and adds `->index()` on `expires_at`, differing slightly from manual version.
- Backend healthcheck `nc -z localhost 8081` consistently failed (BusyBox nc -z behaves unreliably with PHP artisan serve). Fixed to `curl -sf http://localhost:8081/ > /dev/null`. Also fixed prod healthcheck to use BusyBox-compatible `nc -w1 localhost 9000 < /dev/null` syntax for PHP-FPM.

### Completion Notes List

- Installed laravel/sanctum ^4.3 via composer require; published official migration which creates `personal_access_tokens` with `text('name')` and indexed `expires_at`.
- Added `$table->index('created_at')` to existing users migration `0001_01_01_000000_create_users_table.php`.
- Created `items` migration with foreignId FK (auto-indexes user_id), nullable description, and explicit created_at index.
- Created `Item` model using PHP 8 `#[Fillable]` attribute syntax (matching User.php pattern), with `belongsTo(User::class)`.
- Created `ItemFactory` using `fake()->optional(0.8)->paragraph()` to exercise nullable description.
- Updated `DatabaseSeeder`: removed WithoutModelEvents trait, added Item import, seeds 2 items per user.
- Fixed `docker-compose.yml` backend healthcheck from `nc -z` (fails with BusyBox on artisan serve) to `curl -sf`.
- Fixed `docker-compose.prod.yml` prod backend healthcheck from `nc -z` to `nc -w1 ... < /dev/null`.
- All 6 DatabaseSchemaTest tests pass. Full suite: 8/8 passed, 0 regressions.
- Verified: `migrate:fresh --seed` idempotent (ran twice), `db:seed` standalone works, all 4 AC tables confirmed via tinker.

### File List

- `backend/composer.json` (modified — added laravel/sanctum ^4.3)
- `backend/composer.lock` (modified — locked laravel/sanctum v4.3.1 and dependencies)
- `backend/database/migrations/0001_01_01_000000_create_users_table.php` (modified — added created_at index to users table)
- `backend/database/migrations/2026_04_27_000001_create_items_table.php` (new — items table with FK, nullable description, created_at index)
- `backend/database/migrations/2026_04_27_074038_create_personal_access_tokens_table.php` (new — published Sanctum 4.3 migration)
- `backend/app/Models/Item.php` (new — PHP 8 attribute syntax, HasFactory, belongsTo User)
- `backend/database/factories/ItemFactory.php` (new — user_id via factory, optional description)
- `backend/database/seeders/DatabaseSeeder.php` (modified — added Item seeding, removed WithoutModelEvents)
- `backend/tests/Feature/DatabaseSchemaTest.php` (new — 6 tests covering all ACs)
- `docker-compose.yml` (modified — backend healthcheck nc -z → curl -sf)
- `docker-compose.prod.yml` (modified — backend healthcheck nc -z → nc -w1)
