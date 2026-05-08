# Deferred Work

## Deferred from: code review of 1-1-initialize-laravel-backend-project (2026-04-27)

- **Containers run as root** — both dev and prod Docker stages run as root; add non-root `appuser` for production security hardening
- **SESSION_ENCRYPT=false** — session files stored as plaintext; enable `SESSION_ENCRYPT=true` as part of production security hardening
- **Redis has no password** — `REDIS_PASSWORD` is blank in dev; configure a strong Redis password for staging/production in Story 1.5
- **docker-compose.yml references removed `Dockerfile.prod`** — the old compose file at project root references a file that no longer exists; Story 1.3 will fully replace it
- **APP_MAINTENANCE_DRIVER=file not cluster-safe** — maintenance mode stored as local file; change to `cache` driver (Redis-backed) before any multi-replica deployment

## Deferred from: code review of 1-2-initialize-nextjs-frontend-project (2026-04-27)

- **`EXPOSE 3001` hardcoded in Dockerfile** — Docker does not support shell variable substitution in EXPOSE directives; the port is documentation-only metadata and does not affect actual port binding. Acceptable Docker limitation — no actionable fix.

## Deferred from: code review of 1-4-database-migrations-schema-foundation (2026-04-27)

- **Prod FPM healthcheck only tests TCP socket** — `nc -w1 localhost 9000` confirms the port is open, not that PHP-FPM workers are responsive. Fix requires configuring FPM's `ping.path` and using a proper FastCGI probe or `cgi-fcgi`.
- **`items` migration `down()` drops without FK guard** — `Schema::dropIfExists('items')` doesn't disable FK constraints first. Harmless now (nothing references items), but will fail if future migrations add reverse FKs. Add `disableForeignKeyConstraints` wrap when the schema grows.
- **`ItemFactory` creates orphan User by default** — `user_id => User::factory()` means bare `Item::factory()->create()` always creates a second user. Standard Laravel pattern but callers should pass explicit `user_id` to avoid surprise row counts in tests.
- **`items.title` VARCHAR(255) has no application-level validation** — no `max:255` rule anywhere; oversized input will cause a DB exception in MySQL strict mode. Add validation in Story 1.6 (`StoreItemRequest`).
- **`User` model missing `hasMany(Item::class)` inverse relationship** — add when user-scoped item queries are needed in Story 3+.
- **Framework scaffold tables not covered by schema tests** — `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` exist but are untested. Out of scope for Story 1.4.

## Deferred from: code review of 1-5-redis-cache-queue-configuration (2026-04-28)

- **`TestJob` in `app/Jobs/` production path** — story AC required the class name; move to `tests/Support/TestJob.php` as a future refactor to avoid confusing test-only artifacts in production code.
- **Theoretical race window in queue dispatch test** — `test_queue_dispatches_job_to_redis` asserts `llen=1` against live queue-worker polling every 3s; window is microseconds so not a practical issue, but noted for awareness.
- **No Docker healthcheck on `queue-worker`** — Docker cannot detect zombie/blocked worker; `restart: unless-stopped` only helps if the process exits. Add a healthcheck (e.g., `php artisan queue:monitor`) in a future production hardening story.
- **`RedisConfigTest` fails outside Docker** — `REDIS_HOST=redis` only resolves inside Docker containers; no `markTestSkipped()` guard for non-Docker environments. By-design constraint documented in story Dev Notes.
- **Queue worker missing `--max-jobs`/`--max-time` flags** — long-running `queue:work` accumulates PHP memory without restart; add `--max-jobs=1000` or `--max-time=3600` in a production hardening story.

## Deferred from: code review of 1-3-docker-compose-nginx-orchestration (2026-04-27)

- **Redis has no password** — `REDIS_PASSWORD` is blank and Redis container has no `requirepass`; Redis port is exposed to host on 6380 with no auth. Harden in Story 1.5 when Redis is configured as cache/queue driver.
- **`NEXT_PUBLIC_API_URL=http://localhost:8081` in frontend/.env.local** — browser API calls fail because backend has no host-side port mapping (only reachable via Nginx on port 80). Root cause is Story 1.2 scope; revisit when deciding whether to expose backend port directly for dev.
- **Anonymous vendor/node_modules volumes go stale after dependency updates** — after `composer.json` or `pnpm-lock.yaml` changes and a rebuild, old anonymous volumes shadow new image layer contents. Document that `docker compose down -v && docker compose up --build` is required after dependency changes.
- **Nginx upstream keepalive and FastCGI buffer tuning** — no `keepalive` directive in upstream blocks and no `fastcgi_buffers` tuning; performance sub-optimal under load but not broken. Add to a future performance hardening story.
- **`storage/` and `bootstrap/cache/` write permissions on Windows** — bind-mounted backend source with NTFS → WSL2 exposes files as UID 1000, not `www-data` (UID 82); Laravel file cache, sessions, and logs may fail. Investigate and fix in Story 1.4 or via a dedicated permissions init container.

## Deferred from: code review of 1-6-api-foundation-envelope-exception-handler-repository-pattern (2026-04-28)

- **`BaseRepository::filter()` is an intentional no-op stub** — returns `$this` unchanged; Story 3.2 will implement real query-param filtering via overrides in concrete repositories.
- **Empty repository interfaces (`UserRepositoryInterface`, `ItemRepositoryInterface`)** — stubs only; no methods defined. Method contracts will be added progressively as business logic is built out in Story 3+.
- **`sanctum.expiration` is `null` — tokens never expire** — `config/sanctum.php` published with default null expiry. Token lifetime should be configured in Story 2.1 when auth routes are implemented.
- **`ItemFactory` missing `user_id`, `Item::$fillable` missing `user_id`** — pre-existing from Story 1.4. `Item::factory()->create()` without explicit `user_id` risks constraint violations on MySQL; `user_id` mass-assignment is silently dropped. Address in Story 3.1 when item CRUD is built.
- **`ApiResponse::error()` default status hardcoded to 400** — callers omitting `$status` always get `400 Bad Request`, even for 404/403/409 errors. Named constructors (e.g., `notFound()`, `forbidden()`) deferred to Story 2+.
- **`expectsJson()` guard may not fire for route-not-found 404s before middleware** — `bootstrap/app.php` returns `null` (HTML) when `expectsJson()` is false; ForceJsonResponse may not have run yet for true 404s (no matching route). API clients that don't send `Accept: application/json` would get an HTML 404 instead of a JSON envelope. Acceptable for now; fix if clients report non-JSON errors in production.

## Deferred from: code review of 1-7-frontend-foundation-api-client-query-client-state-management (2026-04-28)

- **`crypto.randomUUID()` unavailable in non-secure HTTP contexts** — `uiStore.addToast` will throw `TypeError` if called on an HTTP (non-HTTPS) origin; server-side execution also unsafe. Mitigate when the toast UI is wired up in Story 2.6 with a fallback ID generator.
- **`window.location.href` in 401 interceptor causes full page reload** — bypasses Next.js App Router client-side navigation, discards React state and form data. Refactor to use a custom browser event + `router.push('/login')` in Story 2.6 when auth guards are implemented.
- **`modalOpen` single boolean insufficient for concurrent modals** — opening a second modal while one is already open will close both on the next `setModalOpen(false)` call. Evolve to a keyed map or modal stack in Story 2.6+.
- **`authStore` has no SSR isolation** — Zustand singleton could persist user state across concurrent server-render requests in Next.js; no server-side mutations exist in current scope so risk is theoretical. Evaluate when Story 2.x adds real auth data and session-bound renders.
- **`lint` script in `package.json` has no target path** — `"eslint"` with no arguments outputs help text and exits 0; lints nothing. Pre-existing scaffold issue; fix to `"next lint"` or `"eslint src"` as part of a future quality tooling story.
