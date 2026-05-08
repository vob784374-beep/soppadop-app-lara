# Story 1.5: Redis Cache & Queue Configuration

Status: done

## Story

As a developer,
I want Redis configured as both the cache driver and queue driver,
So that API response caching and async job dispatch (email verification, password reset) work correctly.

## Acceptance Criteria

1. **Given** Redis is running on port 6380 (host) / 6379 (container internal)
   **When** `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis` are set in `backend/.env`
   **Then** `Cache::put('test', 'value', 60)` stores and retrieves the value correctly via Redis

2. **Given** a `TestJob` class implementing `ShouldQueue` exists
   **When** `TestJob::dispatch()` is called
   **Then** a job is visible in the Redis queue (`queues:default` list in DB 0)

3. **Given** the Docker Compose stack
   **When** `docker compose up` is run
   **Then** a `queue-worker` service runs `php artisan queue:work` and is visible in `docker compose ps`

4. The queue worker automatically restarts on failure — Docker restart policy `unless-stopped`

5. `REDIS_HOST`, `REDIS_PORT`, and `REDIS_PASSWORD` are all read from environment variables — no hardcoded values

## Tasks / Subtasks

- [x] Task 1: Update `backend/.env` and `backend/.env.example` (AC: 1, 5)
  - [x] Change `CACHE_STORE=file` → `CACHE_STORE=redis` in `backend/.env`
  - [x] Change `QUEUE_CONNECTION=sync` → `QUEUE_CONNECTION=redis` in `backend/.env`
  - [x] Confirm `REDIS_HOST=redis`, `REDIS_PORT=6379`, `REDIS_PASSWORD=` are already present (do NOT change port — container uses 6379 internally; 6380 is the host-side port)
  - [x] Apply identical changes to `backend/.env.example`

- [x] Task 2: Add `queue-worker` service to `docker-compose.yml` and volume mount to `docker-compose.dev.yml` (AC: 3, 4)
  - [x] Add `queue-worker` service to base `docker-compose.yml` (see Dev Notes: Queue Worker Service)
  - [x] Command must be `["php", "artisan", "queue:work", "--sleep=3", "--tries=3"]`
  - [x] Set `restart: unless-stopped`, `depends_on: backend (service_healthy)`, `networks: app_network`
  - [x] Add `queue-worker` volumes block to `docker-compose.dev.yml` (mirrors backend volume mounts for hot-reload)

- [x] Task 3: Create `TestJob` (AC: 2)
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan make:job TestJob`
  - [x] Verify it implements `ShouldQueue` and uses the standard traits (see Dev Notes: TestJob)
  - [x] Keep `handle()` empty — this job is for integration verification only

- [x] Task 4: Write feature tests (AC: 1, 2)
  - [x] Create `backend/tests/Feature/RedisConfigTest.php` (see Dev Notes: Feature Tests)
  - [x] Test: cache put + get round-trip via Redis
  - [x] Test: `TestJob::dispatch()` adds one job to the `queues:default` Redis list
  - [x] Add `tearDown()` cleanup for any Redis keys written during tests

- [x] Task 5: Start stack and verify queue worker (AC: 3, 4)
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d`
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml ps` — confirm `queue-worker` status is `running`
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml logs queue-worker` — confirmed via `docker exec ps aux`: PID 1 is `php artisan queue:work --sleep=3 --tries=3`

## Dev Notes

### Pre-existing State (Do NOT change unless a task requires it)

| What | Current value | Required change |
|------|--------------|-----------------|
| `REDIS_CLIENT` | `phpredis` | None — phpredis extension installed via `pecl install redis` in Dockerfile |
| `REDIS_HOST` | `redis` | None — matches Docker Compose service name |
| `REDIS_PORT` | `6379` | None — container-internal port (host port 6380 is for local dev tooling only) |
| `REDIS_PASSWORD` | `` (empty) | None |
| `CACHE_STORE` | `file` | **Change to `redis`** |
| `QUEUE_CONNECTION` | `sync` | **Change to `redis`** |

Redis container already defined in `docker-compose.yml` on `app_network`. No Docker changes needed for the Redis service itself.

### Redis Connection Architecture

Laravel's `config/database.php` defines two Redis connections:

| Connection | DB | Used by |
|-----------|-----|---------|
| `default` | 0 | Queue driver, general Redis operations |
| `cache` | 1 | Cache facade when `CACHE_STORE=redis` |

DB separation prevents queue keys (`queues:default`, etc.) from colliding with cache keys. Both connections point to the same `REDIS_HOST:REDIS_PORT`.

**Anti-pattern to avoid:** Do NOT configure `CACHE_STORE=database` or override `config/database.php` redis connections — the defaults are correct.

### .env Changes

```dotenv
# Before (in both .env and .env.example):
CACHE_STORE=file
QUEUE_CONNECTION=sync

# After:
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

All other Redis keys (`REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_CLIENT`) are already correct.

### Queue Worker Service

Add to `docker-compose.yml` (base file, below the `redis` service):

```yaml
  queue-worker:
    build:
      context: ./backend
      target: dev
    env_file: ./backend/.env
    environment:
      DB_HOST: mysql
      DB_PORT: "3306"
      REDIS_HOST: redis
      REDIS_PORT: "6379"
    command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3"]
    depends_on:
      backend:
        condition: service_healthy
    networks:
      - app_network
    restart: unless-stopped
```

Add to `docker-compose.dev.yml` (mirrors backend volume mounts for code hot-reload):

```yaml
  queue-worker:
    volumes:
      - ./backend:/var/www/html
      - /var/www/html/vendor
```

**Why `depends_on: backend`:** The queue worker reuses the backend image; waiting for `backend` to be healthy ensures the DB migration has run before the worker starts processing jobs.

**Note on prod:** This story only configures the dev queue worker. Production queue worker setup (using `target: prod`, optimized image) is deferred to the production deployment story.

### TestJob

`php artisan make:job TestJob` generates the file. Verify it looks like:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void {}
}
```

File location: `backend/app/Jobs/TestJob.php`

### Feature Tests

**Critical context:** `phpunit.xml` globally overrides `CACHE_STORE=array` and `QUEUE_CONNECTION=sync` for all tests. `RedisConfigTest` must override these per-class using `config()` in `setUp()` to test real Redis. Do NOT modify `phpunit.xml` — all other tests must remain isolated with array cache and sync queue.

```php
<?php

namespace Tests\Feature;

use App\Jobs\TestJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'redis']);
        config(['queue.default' => 'redis']);
    }

    protected function tearDown(): void
    {
        Cache::forget('redis_test_key');
        Redis::connection('default')->del('queues:default');
        parent::tearDown();
    }

    public function test_cache_stores_and_retrieves_via_redis(): void
    {
        Cache::put('redis_test_key', 'hello_redis', 60);
        $this->assertEquals('hello_redis', Cache::get('redis_test_key'));
    }

    public function test_queue_dispatches_job_to_redis(): void
    {
        Redis::connection('default')->del('queues:default'); // ensure clean state
        TestJob::dispatch();
        $this->assertEquals(1, Redis::connection('default')->llen('queues:default'));
    }
}
```

**Why no `RefreshDatabase`:** These tests do not interact with the database — no DB trait needed.

**Why `config()` not `.env` override:** PHPUnit `.env` overrides are applied at bootstrap. In-test `config()` changes take effect for the current request/test cycle only, which is exactly what we need for per-class Redis enabling without affecting other tests.

### Docker Commands

```bash
# Restart dev stack to pick up docker-compose.yml changes
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Confirm queue-worker is running
docker compose -f docker-compose.yml -f docker-compose.dev.yml ps

# View queue worker logs
docker compose -f docker-compose.yml -f docker-compose.dev.yml logs queue-worker

# Run new tests
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test --filter=RedisConfigTest

# Run full suite (ensure no regressions)
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test
```

### Scope Boundaries — DO NOT implement in Story 1.5

| Excluded | Belongs To |
|----------|-----------|
| Sanctum configuration / auth routes | Story 1.6 |
| API response envelope | Story 1.6 |
| Repository pattern / RepositoryServiceProvider | Story 1.6 |
| Cache tagging for list endpoints (tag-invalidation) | Story 3.2 |
| Email verification job dispatch | Story 2.1 |
| Password reset job dispatch | Story 2.4 |
| Redis-based rate limiting | Story 1.6 |
| Production queue worker (prod target) | Production deployment story |

### References

- `_bmad-output/planning-artifacts/architecture.md` — AR9: Redis as cache + queue driver; Redis connection architecture
- `_bmad-output/planning-artifacts/epics.md#Story 1.5` — Acceptance Criteria source
- `backend/Dockerfile` — phpredis installed via `pecl install redis && docker-php-ext-enable redis`
- `docker-compose.yml` — existing Redis service on port 6380/6379; existing backend/frontend service pattern to follow
- `backend/.env` — current env state (REDIS_* already correct, CACHE_STORE and QUEUE_CONNECTION need updating)
- Story 1.4 Dev Notes — `WithoutModelEvents` in seeder, `factory()->for()` pattern, BusyBox curl healthcheck pattern

## Review Findings

- [x] [Review][Patch] P1: `queue-worker` missing explicit `redis: condition: service_healthy` in `depends_on` — redis is covered transitively (backend waits for redis), but if someone later removes backend's redis dependency the worker would start before Redis is ready; direct dependency makes it self-documenting [`docker-compose.yml:81-97`] — **PATCHED**

- [x] [Review][Defer] D1: `TestJob` lives in `app/Jobs/` production path — story AC explicitly required the class name; moving to `tests/Support/TestJob.php` would break AC wording; defer as a future refactor [`backend/app/Jobs/TestJob.php`] — deferred, required by story AC
- [x] [Review][Defer] D2: Theoretical race window in `test_queue_dispatches_job_to_redis` — live queue-worker polls every 3s; window between dispatch and llen assertion is microseconds; not a practical flakiness concern in the dev Docker environment — deferred, negligible risk
- [x] [Review][Defer] D3: No Docker healthcheck on `queue-worker` — Docker cannot detect zombie/blocked worker state; process could be running but not processing; production hardening concern [`docker-compose.yml:81-97`] — deferred, production story
- [x] [Review][Defer] D4: `RedisConfigTest` has no guard for non-Docker environments — `REDIS_HOST=redis` only resolves inside Docker; if someone runs `php artisan test` on host machine tests will fail with name resolution error; project workflow assumes Docker execution — deferred, by-design constraint
- [x] [Review][Defer] D5: Queue worker missing `--max-jobs` / `--max-time` flags — long-running worker accumulates PHP memory with no reset; production hardening concern [`docker-compose.yml:91`] — deferred, production story

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- Laravel 12 `make:job` uses `Illuminate\Foundation\Queue\Queueable` (a consolidated trait including Dispatchable) instead of the older multi-trait pattern. `TestJob::dispatch()` works correctly with this newer style.
- `queue-worker` logs appear empty when queue is idle — this is correct behavior; confirmed actual process via `docker exec ps aux` showing PID 1 = `php artisan queue:work --sleep=3 --tries=3`.
- `phpunit.xml` overrides `CACHE_STORE=array` and `QUEUE_CONNECTION=sync`; `RedisConfigTest` overrides per-class via `config()` in `setUp()` to avoid polluting other test isolation.

### Completion Notes List

- Updated `CACHE_STORE=file → redis` and `QUEUE_CONNECTION=sync → redis` in `backend/.env` and `backend/.env.example`.
- Added `queue-worker` service to `docker-compose.yml` with `restart: unless-stopped`, `depends_on: backend (service_healthy)`, command `php artisan queue:work --sleep=3 --tries=3`.
- Added `queue-worker` volume block to `docker-compose.dev.yml` mirroring backend mounts for code hot-reload.
- Created `backend/app/Jobs/TestJob.php` using Laravel 12 `Illuminate\Foundation\Queue\Queueable` consolidated trait.
- Created `backend/tests/Feature/RedisConfigTest.php` with 2 integration tests: cache round-trip and queue dispatch depth check. `tearDown()` cleans both the cache key and Redis queue list.
- All 12 tests pass (25 assertions), 0 regressions.
- Verified: queue-worker container running as PID 1, all 6 Docker services healthy.

### File List

- `backend/.env` (modified — CACHE_STORE=redis, QUEUE_CONNECTION=redis)
- `backend/.env.example` (modified — CACHE_STORE=redis, QUEUE_CONNECTION=redis)
- `docker-compose.yml` (modified — added queue-worker service)
- `docker-compose.dev.yml` (modified — added queue-worker volume mounts)
- `backend/app/Jobs/TestJob.php` (new — minimal ShouldQueue job for integration verification)
- `backend/tests/Feature/RedisConfigTest.php` (new — 2 Redis integration tests)
