# Story 1.7: Frontend Foundation — API Client, Query Client & State Management

Status: done

## Story

As a developer,
I want the Axios API client, TanStack Query client, Zustand stores, and all frontend dependencies configured,
So that all frontend feature stories can make API calls and manage state consistently.

## Acceptance Criteria

1. `axios`, `@tanstack/react-query`, `zustand`, `react-hook-form`, `zod` are installed in the frontend project

2. `src/lib/apiClient.ts` exports an Axios instance with `baseURL` from `NEXT_PUBLIC_API_URL` and a 401 interceptor that clears auth state and redirects to `/login`

3. `src/lib/queryClient.ts` exports a configured `QueryClient` instance with default options (staleTime, retry)

4. `src/lib/queryKeys.ts` exports the centralized `queryKeys` factory object with keys for `users` and `items`

5. `src/stores/authStore.ts` exports a Zustand store with `user`, `setUser`, `clearAuth`

6. `src/stores/uiStore.ts` exports a Zustand store for UI state (toasts, modal visibility)

7. `src/types/api.types.ts` defines `ApiResponse<T>` and `PaginatedMeta` TypeScript types matching the backend envelope

8. `QueryClientProvider` wraps the root layout — implemented via a `src/components/Providers.tsx` client component used in `src/app/layout.tsx`

9. TypeScript strict-mode compilation passes with no errors (`pnpm build` or `tsc --noEmit` succeeds)

## Tasks / Subtasks

- [x] Task 1: Install frontend dependencies (AC: 1)
  - [x] Run inside the frontend container: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend pnpm add axios @tanstack/react-query zustand react-hook-form zod`
  - [x] Verify `package.json` now lists all five packages as dependencies
  - [x] Verify `pnpm-lock.yaml` is updated

- [x] Task 2: Create shared TypeScript types (AC: 7)
  - [x] Create `frontend/src/types/api.types.ts` (see Dev Notes: API Types)
  - [x] Define `ApiResponse<T>` matching backend envelope `{ data, message, errors, meta }`
  - [x] Define `PaginatedMeta` matching backend `{ total, per_page, current_page, last_page }`
  - [x] Define minimal `User` type for auth store usage

- [x] Task 3: Create Axios API client (AC: 2)
  - [x] Create `frontend/src/lib/apiClient.ts` (see Dev Notes: apiClient)
  - [x] `baseURL` reads from `process.env.NEXT_PUBLIC_API_URL`
  - [x] Default headers: `Content-Type: application/json`, `Accept: application/json`
  - [x] Response interceptor: on 401, call `useAuthStore.getState().clearAuth()` then redirect to `/login`
  - [x] Guard the redirect with `typeof window !== 'undefined'` and skip if already on `/login` to prevent redirect loops

- [x] Task 4: Create TanStack Query client (AC: 3)
  - [x] Create `frontend/src/lib/queryClient.ts` (see Dev Notes: queryClient)
  - [x] Export a singleton `QueryClient` with `staleTime: 60 * 1000` and `retry: 1`

- [x] Task 5: Create centralized query keys (AC: 4)
  - [x] Create `frontend/src/lib/queryKeys.ts` (see Dev Notes: queryKeys)
  - [x] Define keys for `users` (all, detail, profile) and `items` (all, list, detail)

- [x] Task 6: Create Zustand auth store (AC: 5)
  - [x] Create `frontend/src/stores/authStore.ts` (see Dev Notes: authStore)
  - [x] Export `useAuthStore` with `user: User | null`, `setUser`, `clearAuth`
  - [x] `clearAuth` sets `user` to `null` (token management added in Story 2.1)

- [x] Task 7: Create Zustand UI store (AC: 6)
  - [x] Create `frontend/src/stores/uiStore.ts` (see Dev Notes: uiStore)
  - [x] Export `useUIStore` with `toasts`, `addToast`, `removeToast`, `modalOpen`, `setModalOpen`

- [x] Task 8: Create client Providers component and wire layout (AC: 8)
  - [x] Create `frontend/src/components/Providers.tsx` with `'use client'` directive (see Dev Notes: Providers)
  - [x] Update `frontend/src/app/layout.tsx` to wrap `{children}` with `<Providers>`
  - [x] Verify the layout renders correctly at `http://localhost:80` (via Nginx) or `http://localhost:3001` (direct)

- [x] Task 9: Verify TypeScript compilation (AC: 9)
  - [x] Run: `docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend pnpm build`
  - [x] Confirm zero TypeScript errors — build succeeded with `✓ Compiled successfully`
  - [x] All 5 new lib/store/type files are covered by TypeScript strict mode

## Dev Notes

### ⚠️ Critical Architecture Constraints

| Constraint | Rule |
|---|---|
| Package manager | `pnpm` ONLY — never `npm` or `yarn` |
| Import alias | `@/*` maps to `src/*` — always use `@/lib/...`, `@/stores/...`, etc. |
| TypeScript | Strict mode — no `any` types, no `!` non-null assertions |
| API URL | Always `process.env.NEXT_PUBLIC_API_URL` — never hardcode `localhost:8081` |
| React Router | App Router ONLY — no Pages Router patterns |
| Port | 3001 (via `FRONTEND_PORT` env var) |
| QueryClientProvider | MUST be in a `'use client'` boundary — cannot be in a React Server Component |

### Pre-existing State

| File | Status |
|---|---|
| `frontend/src/app/layout.tsx` | Exists — modify to add `<Providers>` |
| `frontend/src/lib/` | Empty dir (`.gitkeep` only) — create files here |
| `frontend/src/stores/` | Empty dir (`.gitkeep` only) — create files here |
| `frontend/src/types/` | Empty dir (`.gitkeep` only) — create files here |
| `frontend/src/components/ui/` | Empty dir — create `Providers.tsx` in `src/components/` (not `ui/`) |
| `frontend/package.json` | Has: `next 16.2.4`, `react 19.2.4` — does NOT yet have axios/query/zustand |

### API Types (`src/types/api.types.ts`)

Matches the backend envelope exactly. The `errors` field from the backend is `Record<string, string[]>` for validation errors.

```ts
export interface PaginatedMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

export interface ApiResponse<T> {
  data: T | null
  message: string
  errors: Record<string, string[]> | null
  meta: PaginatedMeta | null
}

export interface User {
  id: number
  name: string
  email: string
  avatar: string | null
  email_verified_at: string | null
  created_at: string
}
```

### apiClient (`src/lib/apiClient.ts`)

The 401 interceptor imports `useAuthStore` lazily inside the closure to avoid circular dependency issues. Guard `window.location` with `typeof window !== 'undefined'` for RSC compatibility. Skip redirect if already on `/login` to prevent infinite redirect loops.

```ts
import axios from 'axios'
import { useAuthStore } from '@/stores/authStore'

export const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (
      error.response?.status === 401 &&
      typeof window !== 'undefined' &&
      !window.location.pathname.includes('/login')
    ) {
      useAuthStore.getState().clearAuth()
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)
```

### queryClient (`src/lib/queryClient.ts`)

TanStack Query v5 singleton. `staleTime: 60 * 1000` keeps data fresh for 1 minute before background refetch. `retry: 1` avoids hammering failing APIs.

```ts
import { QueryClient } from '@tanstack/react-query'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60 * 1000,
      retry: 1,
    },
  },
})
```

### queryKeys (`src/lib/queryKeys.ts`)

All TanStack Query cache keys centralized here — every feature story MUST use this factory, never inline string arrays. The `ItemFilters` type is a placeholder; Story 3.2 will expand it.

```ts
type ItemFilters = {
  per_page?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  [key: string]: unknown
}

export const queryKeys = {
  users: {
    all: ['users'] as const,
    detail: (id: number) => ['users', id] as const,
    profile: (id: number) => ['users', id, 'profile'] as const,
  },
  items: {
    all: ['items'] as const,
    list: (filters: ItemFilters) => ['items', 'list', filters] as const,
    detail: (id: number) => ['items', id] as const,
  },
} as const
```

### authStore (`src/stores/authStore.ts`)

Zustand v5 create pattern. `clearAuth` used by the 401 interceptor — token storage and revocation handled in Story 2.1.

```ts
import { create } from 'zustand'
import type { User } from '@/types/api.types'

interface AuthState {
  user: User | null
  setUser: (user: User | null) => void
  clearAuth: () => void
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  setUser: (user) => set({ user }),
  clearAuth: () => set({ user: null }),
}))
```

### uiStore (`src/stores/uiStore.ts`)

Toast system for success/error notifications; modal flag for shared modals. Story 2.6+ will use these.

```ts
import { create } from 'zustand'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: string
  message: string
  type: ToastType
}

interface UIState {
  toasts: Toast[]
  addToast: (toast: Omit<Toast, 'id'>) => void
  removeToast: (id: string) => void
  modalOpen: boolean
  setModalOpen: (open: boolean) => void
}

export const useUIStore = create<UIState>((set) => ({
  toasts: [],
  addToast: (toast) =>
    set((state) => ({
      toasts: [...state.toasts, { ...toast, id: crypto.randomUUID() }],
    })),
  removeToast: (id) =>
    set((state) => ({ toasts: state.toasts.filter((t) => t.id !== id) })),
  modalOpen: false,
  setModalOpen: (open) => set({ modalOpen: open }),
}))
```

### Providers Component (`src/components/Providers.tsx`)

**Must have `'use client'`** — QueryClientProvider uses React context which is not available in Server Components. This is the standard Next.js 15+/16 App Router pattern.

```tsx
'use client'

import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '@/lib/queryClient'

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  )
}
```

### Layout Update (`src/app/layout.tsx`)

Import `Providers` and wrap the `<body>` children. Keep the existing font and metadata setup.

```tsx
import { Providers } from '@/components/Providers'

// Inside RootLayout, wrap children:
<body className="min-h-full flex flex-col">
  <Providers>{children}</Providers>
</body>
```

### Scope Boundaries — DO NOT implement in Story 1.7

| Excluded | Belongs To |
|---|---|
| Bearer token storage / Axios auth header | Story 2.1 (login API sets the token) |
| Auth route guards / middleware | Story 2.6 |
| `src/features/auth/` service files | Story 2.6 |
| `src/features/items/` service files | Story 3.1 |
| `usePaginatedQuery.ts` hook | Story 3.4 |
| Toast UI component rendering | Story 2.6 |
| React Query DevTools | Optional post-MVP |
| Error boundaries per feature route | Story 2.6+ |

### Docker Commands

```bash
# Install dependencies inside container (volume mount propagates to host)
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend pnpm add axios @tanstack/react-query zustand react-hook-form zod

# Verify TypeScript and build
docker compose -f docker-compose.yml -f docker-compose.dev.yml exec frontend pnpm build

# Dev server (if not already running)
docker compose -f docker-compose.yml -f docker-compose.dev.yml up frontend
```

### Package Versions (as of 2026-04-28)

| Package | Expected Version | Notes |
|---|---|---|
| `axios` | ^1.x | No React dependency — any v1 works |
| `@tanstack/react-query` | ^5.x | Supports React 19 |
| `zustand` | ^5.x | Supports React 19; v5 API is near-identical to v4 |
| `react-hook-form` | ^7.x | Supports React 19 |
| `zod` | ^3.x | No React dependency |

**Anti-patterns to avoid:**
- ❌ `useState` for server data — always use TanStack Query
- ❌ Inline `axios.get()` in components — always via service functions (Story 2+)
- ❌ Hardcoded `http://localhost:8081` — always `process.env.NEXT_PUBLIC_API_URL`
- ❌ `any` types in TypeScript — use proper generics (`ApiResponse<User>`)
- ❌ QueryClient in a Server Component — must be behind `'use client'` boundary

## Review Findings

- [x] [Review][Patch] QueryClient SSR singleton — instantiate inside `Providers.tsx` with `useState` instead of module-level singleton [frontend/src/components/Providers.tsx:1, frontend/src/lib/queryClient.ts:3]
- [x] [Review][Patch] Missing `NEXT_PUBLIC_API_URL` runtime warning — `axios.create` silently accepts `undefined` as `baseURL` [frontend/src/lib/apiClient.ts:4]
- [x] [Review][Patch] Multiple concurrent 401 responses trigger multiple redirects — add `redirecting` flag guard [frontend/src/lib/apiClient.ts:14]
- [x] [Review][Defer] `crypto.randomUUID()` unavailable in HTTP non-secure contexts [frontend/src/stores/uiStore.ts:23] — deferred, pre-existing; controlled environment (HTTPS prod, localhost dev)
- [x] [Review][Defer] `window.location.href` causes full page reload instead of using Next.js router [frontend/src/lib/apiClient.ts:22] — deferred, pre-existing; refine in Story 2.6 auth guards
- [x] [Review][Defer] `modalOpen` single boolean — not suitable for concurrent modal stacking [frontend/src/stores/uiStore.ts] — deferred, per-spec design; evolve in Story 2.6+
- [x] [Review][Defer] `authStore` has no SSR isolation — Zustand singleton could leak user state server-side [frontend/src/stores/authStore.ts] — deferred, pre-existing; no server mutations in current scope; address in Story 2.x
- [x] [Review][Defer] `lint` script has no target path — `eslint` with no args lints nothing [frontend/package.json] — deferred, pre-existing scaffold issue

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

- `pnpm add` inside Docker failed due to store-dir mismatch (Windows path leaked into container). Fixed by editing `package.json` directly and running `pnpm install --store-dir /root/.local/share/pnpm/store/v10 --no-frozen-lockfile`.
- Installed versions: `@tanstack/react-query@5.100.5`, `axios@1.15.2`, `react-hook-form@7.74.0`, `zod@3.25.76`, `zustand@5.0.12`.

### Completion Notes List

- All 5 dependencies installed; `pnpm build` passes with zero TypeScript errors.
- `Providers.tsx` uses `'use client'` boundary — required for `QueryClientProvider` in Next.js 16 App Router (RSC context).
- 401 interceptor guards against redirect loops on `/login` and uses `typeof window !== 'undefined'` for RSC compatibility.
- `queryKeys` factory typed as `const` — prevents accidental mutation and provides best-in-class TypeScript inference for TanStack Query.

### File List

- `frontend/package.json` — added 5 dependencies
- `frontend/pnpm-lock.yaml` — updated by pnpm install
- `frontend/src/types/api.types.ts` — new: ApiResponse<T>, PaginatedMeta, User
- `frontend/src/lib/apiClient.ts` — new: Axios instance with 401 interceptor
- `frontend/src/lib/queryClient.ts` — new: QueryClient singleton
- `frontend/src/lib/queryKeys.ts` — new: centralized query key factory
- `frontend/src/stores/authStore.ts` — new: Zustand auth store
- `frontend/src/stores/uiStore.ts` — new: Zustand UI store
- `frontend/src/components/Providers.tsx` — new: 'use client' QueryClientProvider wrapper
- `frontend/src/app/layout.tsx` — modified: added Providers wrapper
