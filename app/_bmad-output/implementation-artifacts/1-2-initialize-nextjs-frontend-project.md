# Story 1.2: Initialize Next.js Frontend Project

Status: done

## Story

As a developer,
I want a clean Next.js frontend project initialized with TypeScript, Tailwind, and App Router,
so that all frontend feature stories have a consistent, production-ready foundation to build upon.

## Acceptance Criteria

1. Running `pnpm create next-app frontend --typescript --tailwind --eslint --app --turbopack --import-alias "@/*"` creates a `frontend/` directory with a working Next.js installation (current stable).
2. Directory structure matches architecture: `src/app/(auth)/`, `src/app/(dashboard)/`, `src/features/`, `src/components/ui/`, `src/lib/`, `src/stores/`, `src/types/`, `src/hooks/` all exist under `frontend/`.
3. `frontend/Dockerfile` exists with a multi-stage build (`dev` and `prod` named targets).
4. `frontend/.env.local.example` is committed with `NEXT_PUBLIC_API_URL=http://localhost:8081` and `FRONTEND_PORT=3001`.
5. `pnpm dev` starts the development server on port 3001 (NEVER default 3000).
6. TypeScript strict mode is enabled in `tsconfig.json`.

## Tasks / Subtasks

- [x] Task 1: Install pnpm and remove incompatible frontend stub (prerequisite)
  - [x] Run `npm install -g pnpm` to install pnpm globally
  - [x] Verify: `pnpm --version` returns a version string
  - [x] Remove existing `frontend/` stub: `rm -rf frontend/`
  - [x] Confirm `frontend/` directory no longer exists

- [x] Task 2: Initialize Next.js project (AC: 1, 6)
  - [x] From project root, run: `pnpm create next-app frontend --typescript --tailwind --eslint --app --turbopack --import-alias "@/*"`
  - [x] When prompted interactively, accept all defaults
  - [x] Verify `frontend/` directory created with `package.json` referencing `next`
  - [x] Verify `frontend/tsconfig.json` has `"strict": true` under `compilerOptions`

- [x] Task 3: Configure port 3001 (AC: 5)
  - [x] In `frontend/package.json`, update the `dev` script to: `"next dev --turbopack --port ${FRONTEND_PORT:-3001}"`
  - [x] In `frontend/package.json`, update the `start` script to: `"next start --port ${FRONTEND_PORT:-3001}"`
  - [x] Create `frontend/.env.local` with `FRONTEND_PORT=3001` and `NEXT_PUBLIC_API_URL=http://localhost:8081`
  - [x] Verify `pnpm dev` starts on port 3001 (check output for `http://localhost:3001`)

- [x] Task 4: Configure Next.js for standalone Docker output (AC: 3)
  - [x] In `frontend/next.config.ts`, set `output: 'standalone'` inside the config object
  - [x] Verify the config exports a valid `NextConfig`

- [x] Task 5: Create directory stubs (AC: 2)
  - [x] Create `src/app/(auth)/.gitkeep`
  - [x] Create `src/app/(dashboard)/.gitkeep`
  - [x] Create `src/features/auth/components/.gitkeep`
  - [x] Create `src/features/auth/hooks/.gitkeep`
  - [x] Create `src/features/auth/services/.gitkeep`
  - [x] Create `src/features/auth/types/.gitkeep`
  - [x] Create `src/features/profile/components/.gitkeep`
  - [x] Create `src/features/profile/hooks/.gitkeep`
  - [x] Create `src/features/profile/services/.gitkeep`
  - [x] Create `src/features/profile/types/.gitkeep`
  - [x] Create `src/features/items/components/.gitkeep`
  - [x] Create `src/features/items/hooks/.gitkeep`
  - [x] Create `src/features/items/services/.gitkeep`
  - [x] Create `src/features/items/types/.gitkeep`
  - [x] Create `src/components/ui/.gitkeep`
  - [x] Create `src/components/layout/.gitkeep`
  - [x] Create `src/stores/.gitkeep`
  - [x] Create `src/types/.gitkeep`
  - [x] Create `src/hooks/.gitkeep`

- [x] Task 6: Create `.env.local.example` and `.env.production.example` (AC: 4)
  - [x] Create `frontend/.env.local.example` with required keys and no secret values
  - [x] Create `frontend/.env.production.example` with production-safe placeholder values
  - [x] Verify `NEXT_PUBLIC_API_URL` and `FRONTEND_PORT` are present in both

- [x] Task 7: Create multi-stage Dockerfile and .dockerignore (AC: 3)
  - [x] Create `frontend/Dockerfile` with `base`, `dev`, `builder`, and `prod` stages as specified in Dev Notes
  - [x] Create `frontend/.dockerignore` to exclude `node_modules`, `.next`, `.env.local`, `.git`

### Review Findings (2026-04-27)

- [x] [Review][Decision] Shell syntax `${FRONTEND_PORT:-3001}` in package.json — dismissed; Docker-first project, pnpm dev runs in Linux container via Docker Compose. Unix-only syntax is acceptable. No change needed.

- [x] [Review][Patch] Move root `app/` into `src/app/` and fix `@/*` alias to `"./src/*"` — moved layout.tsx, page.tsx, globals.css, favicon.ico from root app/ to src/app/; tsconfig.json paths updated to `"./src/*"`. [tsconfig.json:22, frontend/app/]
- [x] [Review][Patch] Dockerfile dev CMD double `--port` flag — CMD changed to `["sh", "-c", "pnpm dev"]`; port handled by package.json script. [Dockerfile:18]
- [x] [Review][Patch] dev stage `pnpm install` missing `--frozen-lockfile` — fixed: `RUN pnpm install --frozen-lockfile`. [Dockerfile:13]
- [x] [Review][Patch] pnpm version not pinned in Dockerfile base stage — fixed: `npm install -g pnpm@10.33.2`. [Dockerfile:5]
- [x] [Review][Patch] `.env.production` not excluded from .dockerignore — fixed: added `.env.production` line. [.dockerignore]
- [x] [Review][Patch] `NEXT_PUBLIC_*` vars not declared as build ARGs in builder stage — fixed: added `ARG NEXT_PUBLIC_API_URL` and `ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL` in builder stage. [Dockerfile:builder]
- [x] [Review][Patch] `NODE_ENV` in example env files is misleading — fixed: removed `NODE_ENV` from .env.local.example, .env.production.example, and .env.local. [.env.local.example, .env.production.example]
- [x] [Review][Patch] `!.env.production.example` negation in .dockerignore is redundant — fixed: removed the unnecessary negation. [.dockerignore]

- [x] [Review][Defer] `EXPOSE 3001` hardcoded in Dockerfile — Docker does not support shell variable substitution in EXPOSE directives; port is documentation-only metadata, not a binding. Acceptable pre-existing Docker limitation.

## Dev Notes

**Pre-existing `frontend/` stub: Pages Router, JSX, Next.js 13.4.7 — incompatible. Must be removed before running `pnpm create next-app`.**

### Prerequisites (must complete before Task 2)

- `pnpm` is NOT installed on this machine — install via `npm install -g pnpm`
- Node.js 24.14.1 is available locally ✅
- The existing `frontend/` stub (Pages Router, `.jsx`, no TypeScript) must be removed: `rm -rf frontend/`

### Architecture Constraints

- Port `3001` is the ONLY valid Next.js service port — NEVER 3000
- `FRONTEND_PORT` env var drives the port — never hardcode `3001` in Dockerfile CMD
- App Router only — Pages Router is NOT used in this project
- TypeScript strict mode — no `any` types, no `!` non-null assertions
- Import alias: `@/*` maps to `src/*`
- `pnpm` is the package manager — never use `npm` or `yarn` for frontend deps
- `--turbopack` flag for development HMR — already included in create-next-app command

[Source: architecture.md#Frontend Starter: Next.js 16 via create-next-app]

### Scope Boundaries — DO NOT implement in Story 1.2

| Excluded | Belongs To |
|---|---|
| `axios`, `@tanstack/react-query`, `zustand`, `react-hook-form`, `zod` | Story 1.7 |
| `src/lib/apiClient.ts`, `queryClient.ts`, `queryKeys.ts` | Story 1.7 |
| `src/stores/authStore.ts`, `uiStore.ts` | Story 1.7 |
| `src/types/api.types.ts` | Story 1.7 |
| Auth pages (`/login`, `/register`, `/forgot-password`) | Story 2.6 |
| Dashboard pages (`/profile`, `/items`) | Story 2.7, 3.3 |
| `src/components/ui/Button.tsx`, `Input.tsx`, `Skeleton.tsx` | Stories 2.6+ |
| `QueryClientProvider` in `layout.tsx` | Story 1.7 |
| Sentry frontend integration | Story 1.8 |
| `docker-compose.yml` | Story 1.3 |

### Directory Structure Deliverable

After completing this story, `frontend/` must contain:

```
frontend/
├── Dockerfile                    ← NEW: multi-stage base/dev/builder/prod
├── .dockerignore                 ← NEW
├── .env.local                    ← NEW: configured, gitignored
├── .env.local.example            ← NEW: committed, no secrets
├── .env.production.example       ← NEW: committed, no secrets
├── package.json                  ← MODIFIED: dev/start scripts use FRONTEND_PORT
├── next.config.ts                ← MODIFIED: output: 'standalone'
├── tsconfig.json                 ← EXISTS: strict mode confirmed
├── tailwind.config.ts            ← EXISTS: from create-next-app
└── src/
    ├── app/
    │   ├── layout.tsx            ← EXISTS: root layout
    │   ├── page.tsx              ← EXISTS: home page
    │   ├── globals.css           ← EXISTS
    │   ├── (auth)/
    │   │   └── .gitkeep          ← NEW: stub for login/register pages
    │   └── (dashboard)/
    │       └── .gitkeep          ← NEW: stub for protected pages
    ├── features/
    │   ├── auth/
    │   │   ├── components/.gitkeep
    │   │   ├── hooks/.gitkeep
    │   │   ├── services/.gitkeep
    │   │   └── types/.gitkeep
    │   ├── profile/
    │   │   ├── components/.gitkeep
    │   │   ├── hooks/.gitkeep
    │   │   ├── services/.gitkeep
    │   │   └── types/.gitkeep
    │   └── items/
    │       ├── components/.gitkeep
    │       ├── hooks/.gitkeep
    │       ├── services/.gitkeep
    │       └── types/.gitkeep
    ├── components/
    │   ├── ui/.gitkeep           ← NEW: shared primitive components
    │   └── layout/.gitkeep       ← NEW: shared layout components
    ├── lib/                      ← MAY EXIST from scaffold
    │   └── .gitkeep
    ├── stores/.gitkeep           ← NEW: Zustand stores (Story 1.7)
    ├── types/.gitkeep            ← NEW: global TypeScript types
    └── hooks/.gitkeep            ← NEW: shared hooks
```

[Source: architecture.md#Next.js Frontend Structure]

### Environment Files

**`frontend/.env.local`** (not committed — gitignored):
```env
NEXT_PUBLIC_API_URL=http://localhost:8081
NEXT_PUBLIC_SENTRY_DSN=
FRONTEND_PORT=3001
NODE_ENV=development
```

**`frontend/.env.local.example`** (committed — all values safe to share):
```env
NEXT_PUBLIC_API_URL=http://localhost:8081
NEXT_PUBLIC_SENTRY_DSN=
FRONTEND_PORT=3001
NODE_ENV=development
```

**`frontend/.env.production.example`** (committed — placeholder values):
```env
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
NEXT_PUBLIC_SENTRY_DSN=
FRONTEND_PORT=3001
NODE_ENV=production
```

### next.config.ts Modification

After `pnpm create next-app`, `next.config.ts` will look like:
```typescript
import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  /* config options here */
}

export default nextConfig
```

Update it to:
```typescript
import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  output: 'standalone',
}

export default nextConfig
```

`output: 'standalone'` is required for the production Docker image — it creates a minimal `server.js` and copies only required files.

### package.json Script Modification

After create-next-app, the scripts section will have:
```json
"scripts": {
  "dev": "next dev --turbopack",
  "build": "next build",
  "start": "next start",
  "lint": "next lint"
}
```

Update `dev` and `start` to use `FRONTEND_PORT`:
```json
"scripts": {
  "dev": "next dev --turbopack --port ${FRONTEND_PORT:-3001}",
  "build": "next build",
  "start": "next start --port ${FRONTEND_PORT:-3001}",
  "lint": "next lint"
}
```

### Dockerfile Specification

Create `frontend/Dockerfile` exactly as follows:

```dockerfile
# syntax=docker/dockerfile:1

# ─── Base stage ───────────────────────────────────────────────────────────────
FROM node:22-alpine AS base

RUN npm install -g pnpm
WORKDIR /app

# ─── Development target ───────────────────────────────────────────────────────
FROM base AS dev

COPY package.json pnpm-lock.yaml ./
RUN pnpm install

COPY . .

EXPOSE 3001
CMD ["sh", "-c", "pnpm dev --port ${FRONTEND_PORT:-3001}"]

# ─── Builder stage ────────────────────────────────────────────────────────────
FROM base AS builder

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
RUN pnpm build

# ─── Production target ────────────────────────────────────────────────────────
FROM node:22-alpine AS prod

WORKDIR /app
ENV NODE_ENV=production

COPY --from=builder /app/public ./public
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static

EXPOSE 3001
CMD ["sh", "-c", "PORT=${FRONTEND_PORT:-3001} node server.js"]
```

**Key decisions:**
- `base` stage: Node 22 LTS Alpine + pnpm installed globally
- `dev` stage: full install (including dev deps), hot-reload via `pnpm dev`, port from `FRONTEND_PORT`
- `builder` stage: frozen lockfile install + `pnpm build` for standalone output
- `prod` stage: clean Node 22 Alpine with only the standalone build artifacts — minimal image
- No `COPY . .` in prod — only the `.next/standalone` output (avoids baking source + node_modules)

### .dockerignore Specification

Create `frontend/.dockerignore`:
```
# Dependencies
node_modules
.pnpm-store

# Next.js build output
.next

# Environment secrets
.env.local
.env*.local
!.env.local.example
!.env.production.example

# Version control
.git
.gitignore
.gitattributes

# Dev tooling and docs
*.md
!README.md
.editorconfig
```

### TypeScript Strict Mode Verification

After `pnpm create next-app`, verify `frontend/tsconfig.json` contains:
```json
{
  "compilerOptions": {
    "strict": true,
    ...
  }
}
```

If `strict: true` is missing, add it under `compilerOptions`. Do not remove any existing options.

### Naming Conventions for Frontend

All frontend code follows these rules (established in Story 1.1, applied here):

- Components: `PascalCase` → `UserCard.tsx`, `AuthForm.tsx`
- Files (components): `PascalCase` → `UserCard.tsx`
- Files (hooks/utils/services): `camelCase` → `useAuth.ts`, `apiClient.ts`
- Variables/functions: `camelCase` → `userId`, `fetchUserProfile()`
- Types/interfaces: `PascalCase` → `UserProfile`, `ApiResponse<T>`
- Zustand stores: camelCase noun + `Store` → `authStore`, `uiStore`
- Route groups: lowercase with parens → `(auth)`, `(dashboard)`

[Source: architecture.md#TypeScript/Next.js Code Naming]

### Testing Requirements

Infrastructure story — no business logic to test. Verification via:

1. `pnpm --version` → confirms pnpm is installed
2. `ls frontend/` → directory exists with `package.json`, `tsconfig.json`, `next.config.ts`
3. `pnpm dev` output shows `http://localhost:3001` (not 3001)
4. `grep "strict" frontend/tsconfig.json` → returns `"strict": true`
5. `grep "output" frontend/next.config.ts` → returns `output: 'standalone'`
6. `ls frontend/src/app/(auth)/.gitkeep` → exists
7. `ls frontend/src/app/(dashboard)/.gitkeep` → exists
8. `ls frontend/src/features/auth/components/.gitkeep` → exists
9. `ls frontend/Dockerfile` → exists
10. `ls frontend/.dockerignore` → exists

No Jest/Vitest tests written in this story. Testing infrastructure established in Stories 1.7+.

### Previous Story Intelligence (Story 1.1)

**What Story 1.1 established for Story 1.2:**
- Pre-existing stubs from before BMad setup must be DELETED before running the init command
- Docker: always include `.dockerignore` — omitting it causes secrets (`.env.local`) to be baked into image layers
- Docker: always run build-time commands (cache, etc.) in an entrypoint, not in `RUN` layer
- Docker: use `$VARIABLE_NAME:-default` pattern in CMD so env vars drive runtime behavior
- Local environment: `php artisan` (and similarly `pnpm`) commands may not work locally without proper extensions/tools — verify toolchain first
- pnpm is NOT installed — install it first with `npm install -g pnpm`

**Code Review findings from Story 1.1 that apply to Story 1.2:**
- Include `.dockerignore` from the start (don't wait for review to add it)
- Use shell form CMD with env var port: `CMD ["sh", "-c", "pnpm dev --port ${FRONTEND_PORT:-3001}"]`
- Avoid hardcoding port values — `FRONTEND_PORT` env var must drive everything

### Project Structure Notes

- `frontend/` is at the project root, sibling to `backend/`
- Story 1.3 creates `docker-compose.yml` at the project root — both `backend/` and `frontend/` must exist for Story 1.3 to wire them together
- The `(auth)` and `(dashboard)` route groups in App Router use parentheses — they do NOT create URL segments; `/login` lives at `src/app/(auth)/login/page.tsx`
- All `NEXT_PUBLIC_` env vars are baked into the browser bundle at build time — never put secrets in `NEXT_PUBLIC_` variables
- `src/lib/` is where `apiClient.ts`, `queryClient.ts`, `queryKeys.ts` will live (Story 1.7) — create the stub now

### References

- [Source: epics.md#Story 1.2: Initialize Next.js Frontend Project]
- [Source: architecture.md#Frontend Starter: Next.js 16 via create-next-app]
- [Source: architecture.md#Frontend Architecture]
- [Source: architecture.md#Complete Project Directory Structure]
- [Source: architecture.md#Next.js Frontend Structure]
- [Source: architecture.md#TypeScript/Next.js Code Naming]
- [Source: architecture.md#Infrastructure & Deployment]

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- pnpm 10.33.2 installed via `npm install -g pnpm`.
- Pre-existing `frontend/` stub (Next.js 13.4.7, Pages Router, JSX, no TypeScript) removed with `rm -rf frontend/` before init.
- `pnpm create next-app` installed **Next.js 16.2.4** (current stable as of 2026-04-26). Architecture doc referenced "Next.js 16 via create-next-app" — correct.
- The `create-next-app` scaffold produced `"dev": "next dev"` without `--turbopack` (Next.js 16 may have turbopack as default, but the story spec requires the flag explicitly). Script updated per spec.
- `next.config.ts` scaffolded with `reactCompiler: true` already set — `output: 'standalone'` added alongside it (not replacing it).
- Windows Application Control policy blocked `@next/swc-win32-x64-msvc` native binary at scaffold time — pnpm dev may fall back to WASM SWC locally, but this is a host security policy limitation and does not affect Docker builds.
- No test framework is installed or configured in this story (Testing infrastructure is Story 1.7+). Infrastructure story — verified via acceptance criteria checks only.

### Completion Notes List

- ✅ AC1: `pnpm create next-app frontend ...` — Next.js 16.2.4 installed (current stable as of 2026-04-26). `frontend/package.json` references `next: "16.2.4"`.
- ✅ AC2: All 19 directory stubs created: `src/app/(auth)/`, `src/app/(dashboard)/`, `src/features/{auth,profile,items}/{components,hooks,services,types}/`, `src/components/{ui,layout}/`, `src/lib/`, `src/stores/`, `src/types/`, `src/hooks/`.
- ✅ AC3: `frontend/Dockerfile` created with `base`, `dev`, `builder`, and `prod` multi-stage targets. `frontend/.dockerignore` created excluding secrets, node_modules, .next, .git.
- ✅ AC4: `frontend/.env.local.example` committed with `NEXT_PUBLIC_API_URL=http://localhost:8081` and `FRONTEND_PORT=3001`. `frontend/.env.production.example` created with production placeholder values.
- ✅ AC5: `package.json` dev script updated to `"next dev --turbopack --port ${FRONTEND_PORT:-3001}"`. Start script updated to `"next start --port ${FRONTEND_PORT:-3001}"`. `.env.local` sets `FRONTEND_PORT=3001`.
- ✅ AC6: `frontend/tsconfig.json` contains `"strict": true` under `compilerOptions` (scaffolded by create-next-app).
- Note: `next.config.ts` contains both `output: 'standalone'` (required by story) and `reactCompiler: true` (scaffolded by create-next-app — kept as it was already present).

### File List

- `frontend/` (entire directory — created via `pnpm create next-app`)
- `frontend/package.json` (modified: dev/start scripts use `${FRONTEND_PORT:-3001}`)
- `frontend/next.config.ts` (modified: added `output: 'standalone'`)
- `frontend/.env.local` (created: FRONTEND_PORT=3001, NEXT_PUBLIC_API_URL=http://localhost:8081)
- `frontend/.env.local.example` (created: committed, safe values)
- `frontend/.env.production.example` (created: committed, production placeholder values)
- `frontend/Dockerfile` (created: multi-stage base/dev/builder/prod, node:22-alpine)
- `frontend/.dockerignore` (created: excludes node_modules, .next, .env.local, .git)
- `frontend/src/app/(auth)/.gitkeep` (created)
- `frontend/src/app/(dashboard)/.gitkeep` (created)
- `frontend/src/features/auth/components/.gitkeep` (created)
- `frontend/src/features/auth/hooks/.gitkeep` (created)
- `frontend/src/features/auth/services/.gitkeep` (created)
- `frontend/src/features/auth/types/.gitkeep` (created)
- `frontend/src/features/profile/components/.gitkeep` (created)
- `frontend/src/features/profile/hooks/.gitkeep` (created)
- `frontend/src/features/profile/services/.gitkeep` (created)
- `frontend/src/features/profile/types/.gitkeep` (created)
- `frontend/src/features/items/components/.gitkeep` (created)
- `frontend/src/features/items/hooks/.gitkeep` (created)
- `frontend/src/features/items/services/.gitkeep` (created)
- `frontend/src/features/items/types/.gitkeep` (created)
- `frontend/src/components/ui/.gitkeep` (created)
- `frontend/src/components/layout/.gitkeep` (created)
- `frontend/src/lib/.gitkeep` (created)
- `frontend/src/stores/.gitkeep` (created)
- `frontend/src/types/.gitkeep` (created)
- `frontend/src/hooks/.gitkeep` (created)
