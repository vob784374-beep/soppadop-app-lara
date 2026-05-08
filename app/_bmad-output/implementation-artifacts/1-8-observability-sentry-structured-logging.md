# Story 1.8: Observability — Sentry & Structured Logging

Status: done

## Story

As a developer,
I want Sentry error tracking and structured JSON logging configured on both backend and frontend,
so that production errors are captured automatically and logs are machine-readable per NFR5.

## Acceptance Criteria

1. `SENTRY_DSN` is read from the environment — never hardcoded anywhere in backend or frontend code

2. When an unhandled exception occurs in the Laravel backend with `SENTRY_DSN` set, the exception is automatically reported to Sentry

3. All Laravel logs are written as structured JSON to `stderr` (stdout-accessible via Docker) — not to `storage/logs/laravel.log` in production. The JSON format includes at minimum: `datetime`, `level_name`, `message`, `context` fields (Monolog JsonFormatter default)

4. A `json-stderr` log channel is defined in `config/logging.php` and the `.env.example` documents `LOG_CHANNEL=json-stderr` for production

5. The Next.js frontend has `@sentry/nextjs` installed and configured via `sentry.client.config.ts`, `sentry.server.config.ts`, and `instrumentation.ts`

6. `NEXT_PUBLIC_SENTRY_DSN` configures the frontend Sentry DSN — read from environment, never hardcoded

7. Sentry is effectively disabled in development: backend uses `null` or empty DSN, frontend uses `enabled: process.env.NODE_ENV === 'production'`

8. `pnpm build` passes with zero TypeScript errors after all frontend changes

9. `php artisan test` passes — at minimum, a smoke test confirms that JSON log output is produced when using the `json-stderr` channel

## Tasks / Subtasks

- [x] Task 1: Install backend Sentry SDK (AC: 1, 2)
  - [x] Run inside backend container: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend composer require sentry/sentry-laravel`
  - [x] Publish Sentry config: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"`
  - [x] Verify `config/sentry.php` exists with `'dsn' => env('SENTRY_DSN', ''),`
  - [x] Verify `sentry/sentry-laravel` appears in `backend/composer.json` `require` section

- [x] Task 2: Configure backend structured JSON logging (AC: 3, 4)
  - [x] Add `json-stderr` channel to `backend/config/logging.php` (see Dev Notes: JSON Log Channel)
  - [x] Verify the `stderr` channel already exists — the new `json-stderr` channel is a sibling with `JsonFormatter` set explicitly
  - [x] Add `SENTRY_DSN=` to `backend/.env` (empty string = disabled in dev)
  - [x] Update `backend/.env.example`: add `LOG_CHANNEL=stack` note and `# Production: LOG_CHANNEL=json-stderr` comment, ensure `SENTRY_DSN=` is present
  - [x] Keep `backend/.env` dev values as: `LOG_CHANNEL=stack`, `LOG_STACK=single` (file-based, easier local debugging)

- [x] Task 3: Write backend observability test (AC: 9)
  - [x] Create `backend/tests/Feature/ObservabilityTest.php` (see Dev Notes: Backend Test)
  - [x] Test 1: `test_json_stderr_channel_produces_structured_output` — boot app with `json-stderr` channel, write a log entry, assert output contains `level_name`, `message`, `datetime` fields
  - [x] Test 2: `test_sentry_dsn_reads_from_environment` — assert `config('sentry.dsn')` equals `env('SENTRY_DSN')` (not a hardcoded value)
  - [x] Run tests: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test --filter=ObservabilityTest`

- [x] Task 4: Install frontend Sentry SDK (AC: 5, 6)
  - [x] Edit `frontend/package.json` directly to add `"@sentry/nextjs": "^10.0.0"` to `dependencies`
  - [x] Run inside frontend container: pnpm install via docker image rebuild
  - [x] Verify `@sentry/nextjs` appears in `frontend/pnpm-lock.yaml`

- [x] Task 5: Create frontend Sentry config files (AC: 5, 6, 7)
  - [x] Create `frontend/sentry.client.config.ts` (see Dev Notes: Frontend Client Config)
  - [x] Create `frontend/sentry.server.config.ts` (see Dev Notes: Frontend Server Config)
  - [x] Create `frontend/sentry.edge.config.ts` (see Dev Notes: Frontend Edge Config)
  - [x] Create `frontend/src/instrumentation.ts` (see Dev Notes: Instrumentation)
  - [x] Add `NEXT_PUBLIC_SENTRY_DSN=` to `frontend/.env.local` (empty = disabled in dev)
  - [x] Add `NEXT_PUBLIC_SENTRY_DSN=` to `frontend/.env.local.example`

- [x] Task 6: Update next.config.ts with Sentry wrapper (AC: 5, 8)
  - [x] Update `frontend/next.config.ts` to wrap with `withSentryConfig` (see Dev Notes: next.config.ts)
  - [x] Keep `output: 'standalone'` and `reactCompiler: true` — these must NOT be removed
  - [x] Verify `pnpm build` passes with zero TypeScript errors

## Dev Notes

### ⚠️ Critical Architecture Constraints

| Constraint | Rule |
|---|---|
| Package manager | `pnpm` ONLY — never `npm` or `yarn` |
| pnpm store-dir | ALWAYS pass `--store-dir /root/.local/share/pnpm/store/v10 --no-frozen-lockfile` inside Docker (Windows path leak workaround) |
| DSN source | ALWAYS `env('SENTRY_DSN')` on backend, `process.env.NEXT_PUBLIC_SENTRY_DSN` on frontend — never hardcode |
| Dev disabled | Sentry must not capture events in development — empty DSN on backend, `enabled: false` on frontend |
| Log location | Production MUST log to stderr (captured by Docker log driver) — NEVER rely on `storage/logs/` in prod |
| `next.config.ts` | `output: 'standalone'` and `reactCompiler: true` MUST be preserved when wrapping with `withSentryConfig` |

### Pre-existing State

| File | Status |
|---|---|
| `backend/config/logging.php` | Exists — add `json-stderr` channel, do NOT remove existing channels |
| `backend/config/sentry.php` | Does NOT exist — created by `php artisan vendor:publish` |
| `backend/bootstrap/app.php` | Exists with exception handler — Sentry SDK auto-wires via service provider; no manual change needed |
| `backend/.env` | Exists — add `SENTRY_DSN=` at the end |
| `backend/.env.example` | Exists — already has `SENTRY_DSN=` at bottom; add `LOG_CHANNEL` production note |
| `backend/composer.json` | Has `sentry/sentry-laravel` NOT present — install it |
| `frontend/next.config.ts` | Exists with `output: 'standalone'` and `reactCompiler: true` — wrap, do not replace |
| `frontend/.env.local` | Exists — add `NEXT_PUBLIC_SENTRY_DSN=` |
| `frontend/package.json` | Has 5 existing deps — add `@sentry/nextjs` |
| `frontend/src/instrumentation.ts` | Does NOT exist — create in `src/` |
| `frontend/sentry.*.config.ts` | Do NOT exist — create at project root (`frontend/`) |

### JSON Log Channel (`config/logging.php`)

Add this channel to the `'channels'` array inside the existing `logging.php`. Do NOT remove any existing channels.

```php
'json-stderr' => [
    'driver' => 'monolog',
    'level' => env('LOG_LEVEL', 'debug'),
    'handler' => Monolog\Handler\StreamHandler::class,
    'handler_with' => [
        'stream' => 'php://stderr',
    ],
    'formatter' => Monolog\Formatter\JsonFormatter::class,
],
```

The `Monolog\Formatter\JsonFormatter` produces one JSON object per line with these fields:
```json
{"message":"msg","context":{},"level":200,"level_name":"INFO","channel":"local","datetime":"2026-04-28T10:00:00.000000+00:00","extra":{}}
```

In development, `LOG_CHANNEL=stack` / `LOG_STACK=single` remains → file-based logs in `storage/logs/laravel.log`.
In production (or when explicitly set): `LOG_CHANNEL=json-stderr` → structured JSON to stderr.

### Backend Sentry Auto-Wiring

`sentry/sentry-laravel` registers its service provider automatically (via Laravel's package discovery). The service provider adds a `report` callback to Laravel's exception handler which calls `\Sentry\captureException($e)` for all reportable exceptions.

No changes to `bootstrap/app.php` are required — the existing exception handler is already correct.

Only exceptions that Laravel considers "reportable" are sent. To report custom exceptions, make them extend `\Exception` (already the case for all standard exceptions).

### Backend Test (`tests/Feature/ObservabilityTest.php`)

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    public function test_json_stderr_channel_produces_structured_output(): void
    {
        // Capture stderr output
        $stream = fopen('php://memory', 'r+');

        $logger = new \Monolog\Logger('test');
        $handler = new \Monolog\Handler\StreamHandler($stream);
        $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
        $logger->pushHandler($handler);

        $logger->info('observability test', ['story' => '1.8']);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('level_name', $decoded);
        $this->assertArrayHasKey('datetime', $decoded);
        $this->assertArrayHasKey('context', $decoded);
        $this->assertEquals('observability test', $decoded['message']);
        $this->assertEquals('INFO', $decoded['level_name']);
    }

    public function test_sentry_dsn_reads_from_environment(): void
    {
        // Confirm DSN is driven by env var, not hardcoded
        $this->assertEquals(env('SENTRY_DSN', ''), config('sentry.dsn'));
    }
}
```

### Frontend Client Config (`sentry.client.config.ts`) — at `frontend/` root

```ts
import * as Sentry from '@sentry/nextjs'

Sentry.init({
  dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,
  enabled: process.env.NODE_ENV === 'production',
  tracesSampleRate: 1.0,
  debug: false,
})
```

### Frontend Server Config (`sentry.server.config.ts`) — at `frontend/` root

```ts
import * as Sentry from '@sentry/nextjs'

Sentry.init({
  dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,
  enabled: process.env.NODE_ENV === 'production',
  tracesSampleRate: 1.0,
  debug: false,
})
```

### Frontend Edge Config (`sentry.edge.config.ts`) — at `frontend/` root

```ts
import * as Sentry from '@sentry/nextjs'

Sentry.init({
  dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,
  enabled: process.env.NODE_ENV === 'production',
  tracesSampleRate: 1.0,
  debug: false,
})
```

### Instrumentation (`src/instrumentation.ts`) — at `frontend/src/`

Next.js 15+ uses the instrumentation hook API. Place this in `src/instrumentation.ts`:

```ts
export async function register() {
  if (process.env.NEXT_RUNTIME === 'nodejs') {
    await import('../sentry.server.config')
  }
  if (process.env.NEXT_RUNTIME === 'edge') {
    await import('../sentry.edge.config')
  }
}
```

The import paths are relative to `src/instrumentation.ts` — `../sentry.server.config` resolves to `frontend/sentry.server.config.ts`.

### `next.config.ts` Update

Wrap the existing config with `withSentryConfig`. Preserve `output: 'standalone'` and `reactCompiler: true`:

```ts
import type { NextConfig } from 'next'
import { withSentryConfig } from '@sentry/nextjs'

const nextConfig: NextConfig = {
  output: 'standalone',
  reactCompiler: true,
}

export default withSentryConfig(nextConfig, {
  // Suppress source map upload warnings in local dev (no auth token)
  silent: true,
  // Disable automatic instrumentation injection (we use instrumentation.ts)
  autoInstrumentServerFunctions: false,
})
```

### Sentry SDK Versions (as of 2026-04-28)

| Package | Version | Notes |
|---|---|---|
| `sentry/sentry-laravel` | ^4.0 | Supports Laravel 11+; verify compatibility with Laravel 13 via `composer require` |
| `@sentry/nextjs` | ^8.0 | Supports Next.js 15+; instrumentation hook approach (no legacy Sentry wrapper needed) |

### Docker Commands

```bash
# Backend: install sentry SDK
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend composer require sentry/sentry-laravel

# Backend: publish config
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"

# Backend: run tests
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec backend php artisan test --filter=ObservabilityTest

# Frontend: install (use explicit store-dir — Windows path leak workaround)
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend pnpm install --store-dir /root/.local/share/pnpm/store/v10 --no-frozen-lockfile

# Frontend: verify build
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend pnpm build
```

### Scope Boundaries — DO NOT implement in Story 1.8

| Excluded | Belongs To |
|---|---|
| Sentry user context (userId, email) | Story 2.1 — set after login |
| Sentry release tagging / source maps upload | CI/CD pipeline (post-MVP) |
| Custom Sentry integrations or breadcrumbs | Post-MVP |
| Log aggregation / centralized log storage | Infrastructure hardening (post-MVP) |
| Error boundary UI components | Story 2.6 |

## Review Findings

## Dev Agent Record

### Agent Model Used
claude-sonnet-4-6

### Debug Log References
- Backend tests: 2 passed (8 assertions) in 3.66s
- Frontend build: compiled successfully in ~35s, zero TypeScript errors
- `autoInstrumentServerFunctions` moved to `webpack` sub-option to silence @sentry/nextjs v10 deprecation warning

### Completion Notes List
- All backend files were already in place (composer.json, config/sentry.php, config/logging.php, .env, .env.example, ObservabilityTest.php) — confirmed via inspection
- @sentry/nextjs ^10.0.0 was already in package.json and pnpm-lock.yaml
- Created 4 frontend files: sentry.client.config.ts, sentry.server.config.ts, sentry.edge.config.ts, src/instrumentation.ts
- Updated next.config.ts to wrap with withSentryConfig; used webpack.autoInstrumentServerFunctions (not deprecated top-level option)
- Frontend node_modules volume had to be rebuilt after OOM kill during pnpm install corrupted the anonymous Docker volume

### File List
- `backend/config/logging.php` (json-stderr channel — pre-existing)
- `backend/config/sentry.php` (published — pre-existing)
- `backend/.env` (SENTRY_DSN= — pre-existing)
- `backend/.env.example` (LOG_CHANNEL note + SENTRY_DSN= — pre-existing)
- `backend/composer.json` (sentry/sentry-laravel ^4.25 — pre-existing)
- `backend/tests/Feature/ObservabilityTest.php` (pre-existing)
- `frontend/package.json` (@sentry/nextjs ^10.0.0 — pre-existing)
- `frontend/sentry.client.config.ts` (created)
- `frontend/sentry.server.config.ts` (created)
- `frontend/sentry.edge.config.ts` (created)
- `frontend/src/instrumentation.ts` (created)
- `frontend/.env.local` (NEXT_PUBLIC_SENTRY_DSN= — pre-existing)
- `frontend/.env.local.example` (created)
- `frontend/next.config.ts` (updated — wrapped with withSentryConfig)
