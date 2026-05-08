# Story 1.3: Docker Compose & Nginx Orchestration

Status: done

## Story

As a developer,
I want all services orchestrated via Docker Compose with Nginx as reverse proxy,
So that the full system can be started with a single command and all services communicate correctly on their custom ports.

## Acceptance Criteria

1. Running `docker compose up` starts all 5 services: backend (8081), frontend (3001), mysql (3307), redis (6380), nginx (80).
2. All ports are driven by environment variables — no hardcoded port values in any config file.
3. Nginx routes `/api/*` requests to `backend:8081` and `/*` requests to `frontend:3001`.
4. All services are on the `app_network` internal bridge — only Nginx exposes ports to the host; MySQL and Redis expose host-side ports for developer tooling only in dev.
5. `docker compose up` from a clean state completes without error.
6. `.env.example`, `.env.staging.example`, `.env.production.example` are committed to the repo root.
7. `nginx/conf.d/dev.conf` and `nginx/conf.d/prod.conf` exist with correct routing rules.

## Tasks / Subtasks

- [x] Task 1: Replace root `docker-compose.yml` — 5 services, env-var ports, health checks (AC: 1, 2, 4, 5)
  - [x] Delete existing broken `docker-compose.yml` (references non-existent `Dockerfile.prod`, wrong ports, no network)
  - [x] Write new `docker-compose.yml` with services: `backend`, `frontend`, `mysql`, `redis`, `nginx`
  - [x] `mysql` service: `image: mysql:8.0`, ports `${DB_PORT:-3307}:3306`, healthcheck, named volume `mysql_data`
  - [x] `redis` service: `image: redis:7-alpine`, ports `${REDIS_PORT:-6380}:6379`, healthcheck, named volume `redis_data`
  - [x] `backend` service: `build target: dev`, volumes for hot-reload, `depends_on` mysql+redis with `condition: service_healthy`
  - [x] `frontend` service: `build target: dev`, volumes for hot-reload + node_modules exclusion
  - [x] `nginx` service: `image: nginx:1.25-alpine`, mounts `dev.conf` as default.conf, depends on backend+frontend
  - [x] All services joined to `app_network` bridge network
  - [x] `restart: unless-stopped` on all services

- [x] Task 2: Fix `backend/.env` internal ports for container networking (prerequisite for Task 1 to work)
  - [x] Change `DB_PORT=3307` → `DB_PORT=3306` (MySQL listens on 3306 inside the container; 3307 is the host-side mapping)
  - [x] Change `REDIS_PORT=6380` → `REDIS_PORT=6379` (Redis listens on 6379 inside the container; 6380 is host-side)
  - [x] Update `backend/.env.example` with the same corrections
  - [x] Add `DB_ROOT_PASSWORD=rootsecret` to `backend/.env` (needed for MySQL healthcheck)

- [x] Task 3: Create root `.env` file (gitignored — Docker Compose variables) (AC: 2)
  - [x] Create `.env` at project root with all compose-level variables (see Dev Notes: Root .env Inventory)
  - [x] Verify `.gitignore` at root excludes `.env` (add if missing)

- [x] Task 4: Create root env example files (AC: 6)
  - [x] Create `.env.example` at project root (dev-safe placeholder values)
  - [x] Create `.env.staging.example` at project root (staging placeholder values)
  - [x] Create `.env.production.example` at project root (production placeholder values)

- [x] Task 5: Create `nginx/nginx.conf` main config and `conf.d/` directory
  - [x] Create `nginx/` directory at project root
  - [x] Create `nginx/nginx.conf` with worker config, MIME types, gzip, upstream definitions
  - [x] Create `nginx/conf.d/` directory

- [x] Task 6: Create `nginx/conf.d/dev.conf` — dev HTTP proxy routing (AC: 3, 7)
  - [x] `/api/` → `proxy_pass http://backend:8081` with standard proxy headers
  - [x] `/` → `proxy_pass http://frontend:3001` with WebSocket upgrade headers (for Next.js HMR)
  - [x] `listen 80` with `server_name _;` (catch-all)

- [x] Task 7: Create `nginx/conf.d/prod.conf` — production FastCGI + proxy routing (AC: 7)
  - [x] `/api/` routes handled via `fastcgi_pass backend:9000` (PHP-FPM) with `fastcgi_params`
  - [x] `/` → `proxy_pass http://frontend:3001` (Next.js standalone server)
  - [x] `SCRIPT_FILENAME` set to `/var/www/html/public$fastcgi_script_name`
  - [x] `root /var/www/html/public;` directive for static file serving

- [x] Task 8: Create `docker-compose.prod.yml` override file
  - [x] Override `backend` build target to `prod`, remove volume mounts
  - [x] Override `frontend` build target to `prod`, remove volume mounts
  - [x] Mount `nginx/conf.d/prod.conf` instead of `dev.conf`
  - [x] Add shared volume `backend_public:/var/www/html/public` for nginx to serve static files

- [x] Task 9: Validate and verify (AC: 5)
  - [x] Run `docker compose config` and confirm it parses without errors
  - [x] Confirm all 5 services are present in the validated output
  - [x] Confirm no hardcoded port numbers exist (grep for `3306`, `6379`, `3000`, `8000`, `9000` in compose files)

## Dev Notes

### Pre-existing State
- A broken `docker-compose.yml` exists at the project root — it references `Dockerfile.prod` (removed in Story 1.1's review), uses wrong ports (frontend on 3000, MySQL exposed without port mapping, nginx on 9000), and has no `app_network`. **Replace it entirely.**
- `backend/.env` was configured in Story 1.1 with `DB_PORT=3307` and `REDIS_PORT=6380` — these are HOST-SIDE port mappings, NOT internal container ports. Fix before Task 1 succeeds.

### CRITICAL: Host Port vs Container-Internal Port

MySQL and Redis use NON-STANDARD internal ports only from the host perspective:

| Service | Host Port (dev access) | Container Internal Port |
|---------|------------------------|------------------------|
| MySQL   | `${DB_PORT:-3307}`     | `3306` (always)        |
| Redis   | `${REDIS_PORT:-6380}`  | `6379` (always)        |

**`backend/.env` must use container-internal ports:**
- `DB_PORT=3306` (backend → mysql:3306 inside Docker)
- `REDIS_PORT=6379` (backend → redis:6379 inside Docker)

**Root `.env` (Docker Compose) must use host-side ports:**
- `DB_PORT=3307` (host maps 3307 → mysql:3306)
- `REDIS_PORT=6380` (host maps 6380 → redis:6379)

The docker-compose.yml backend service environment block overrides `DB_PORT` and `REDIS_PORT` so the Laravel app inside the container always sees `3306` and `6379`, regardless of the root `.env` host-side values.

### Architecture Constraints
- All services on `app_network` internal bridge — never use Docker's default network
- Only Nginx exposes external ports (80) in production; MySQL/Redis dev port exposure is developer convenience only
- `restart: unless-stopped` on ALL services (NFR2: 99.9% uptime)
- Health checks on MySQL and Redis are required — backend `depends_on` must use `condition: service_healthy`
- Queue worker service (`php artisan queue:work`) is added in **Story 1.5** — do NOT add it here
- Do NOT run `php artisan migrate` — migrations belong to **Story 1.4**
- No hardcoded port numbers anywhere; all values from env vars with sane defaults

### docker-compose.yml (complete specification)

```yaml
services:
  backend:
    build:
      context: ./backend
      target: dev
    volumes:
      - ./backend:/var/www/html
      - /var/www/html/vendor
    env_file: ./backend/.env
    environment:
      DB_HOST: mysql
      DB_PORT: "3306"
      REDIS_HOST: redis
      REDIS_PORT: "6379"
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app_network
    restart: unless-stopped

  frontend:
    build:
      context: ./frontend
      target: dev
    volumes:
      - ./frontend:/app
      - /app/node_modules
      - /app/.next
    env_file: ./frontend/.env.local
    networks:
      - app_network
    restart: unless-stopped

  mysql:
    image: mysql:8.0
    ports:
      - "${DB_PORT:-3307}:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "-u", "root", "-p${DB_ROOT_PASSWORD}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    networks:
      - app_network
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    ports:
      - "${REDIS_PORT:-6380}:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3
      start_period: 5s
    networks:
      - app_network
    restart: unless-stopped

  nginx:
    image: nginx:1.25-alpine
    ports:
      - "${NGINX_PORT:-80}:80"
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./nginx/conf.d/dev.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - backend
      - frontend
    networks:
      - app_network
    restart: unless-stopped

volumes:
  mysql_data:
  redis_data:

networks:
  app_network:
    driver: bridge
```

**Important notes:**
- `backend` service: vendor and .next directories excluded from bind mounts via named anonymous volumes (`/var/www/html/vendor`, `/app/node_modules`, `/app/.next`) — prevents host node_modules/vendor from overwriting container-installed deps
- `DB_HOST: mysql` and `REDIS_HOST: redis` override backend/.env values — these are the Docker service names
- `DB_PORT: "3306"` and `REDIS_PORT: "6379"` override host-side port values — backend connects to container-internal ports
- MySQL ports expose `${DB_PORT:-3307}:3306` — `DB_PORT` in root `.env` is the HOST side (3307), `3306` is always the MySQL container-internal port
- `env_file: ./backend/.env` passes ALL backend env vars; environment block overrides specific values

### Root `.env` Inventory (Docker Compose level — gitignored)

```env
# Nginx
NGINX_PORT=80

# Backend (host-side port exposure for developer tooling)
APP_PORT=8081

# Frontend (host-side port exposure for developer tooling)
FRONTEND_PORT=3001

# MySQL (host-side port for tools like TablePlus/DBeaver; internal always 3306)
DB_PORT=3307
DB_ROOT_PASSWORD=rootsecret
DB_DATABASE=app_db
DB_USERNAME=app_user
DB_PASSWORD=secret

# Redis (host-side port for redis-cli; internal always 6379)
REDIS_PORT=6380
```

### Root `.env.example` Template

```env
# Nginx
NGINX_PORT=80

# Backend
APP_PORT=8081

# Frontend
FRONTEND_PORT=3001

# MySQL
DB_PORT=3307
DB_ROOT_PASSWORD=
DB_DATABASE=app_db
DB_USERNAME=app_user
DB_PASSWORD=

# Redis
REDIS_PORT=6380
```

### `nginx/nginx.conf` (main config)

```nginx
worker_processes auto;

events {
    worker_connections 1024;
}

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;

    sendfile        on;
    keepalive_timeout 65;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    include /etc/nginx/conf.d/*.conf;
}
```

### `nginx/conf.d/dev.conf` (development — HTTP proxy to both services)

```nginx
upstream backend {
    server backend:8081;
}

upstream frontend {
    server frontend:3001;
}

server {
    listen 80;
    server_name _;

    client_max_body_size 20M;

    # API routes → Laravel (php artisan serve in dev)
    location /api/ {
        proxy_pass         http://backend;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 90s;
    }

    # All other routes → Next.js (with WebSocket support for HMR)
    location / {
        proxy_pass         http://frontend;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_read_timeout 90s;
    }
}
```

### `nginx/conf.d/prod.conf` (production — FastCGI for PHP-FPM backend)

```nginx
upstream backend_fpm {
    server backend:9000;
}

upstream frontend {
    server frontend:3001;
}

server {
    listen 80;
    server_name _;

    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    # API routes → Laravel PHP-FPM via FastCGI
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass         backend_fpm;
        fastcgi_index        index.php;
        fastcgi_param        SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include              fastcgi_params;
        fastcgi_read_timeout 90s;
    }

    # All other routes → Next.js standalone server
    location / {
        proxy_pass         http://frontend;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 90s;
    }
}
```

**Note on prod.conf:** The nginx container needs access to `backend/public/` for `SCRIPT_FILENAME` resolution. `docker-compose.prod.yml` mounts this via a named volume shared between the `backend` prod container and `nginx`.

### `docker-compose.prod.yml` (override for production)

```yaml
services:
  backend:
    build:
      context: ./backend
      target: prod
    volumes:
      - backend_public:/var/www/html/public

  frontend:
    build:
      context: ./frontend
      target: prod
      args:
        NEXT_PUBLIC_API_URL: ${NEXT_PUBLIC_API_URL}
    volumes: []

  nginx:
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./nginx/conf.d/prod.conf:/etc/nginx/conf.d/default.conf:ro
      - backend_public:/var/www/html/public:ro

volumes:
  backend_public:
```

**Usage:** `docker compose -f docker-compose.yml -f docker-compose.prod.yml up`

### `.env.staging.example`

```env
NGINX_PORT=80
APP_PORT=8081
FRONTEND_PORT=3001
DB_PORT=3307
DB_ROOT_PASSWORD=
DB_DATABASE=app_db
DB_USERNAME=app_user
DB_PASSWORD=
REDIS_PORT=6380
NEXT_PUBLIC_API_URL=https://api.staging.yourdomain.com
```

### `.env.production.example`

```env
NGINX_PORT=80
APP_PORT=8081
FRONTEND_PORT=3001
DB_PORT=3307
DB_ROOT_PASSWORD=
DB_DATABASE=app_db
DB_USERNAME=app_user
DB_PASSWORD=
REDIS_PORT=6380
NEXT_PUBLIC_API_URL=https://api.yourdomain.com
```

### Updated `backend/.env` Values (fix internal ports)

Change these two keys only:
```env
DB_PORT=3306        # was 3307 — MySQL internal container port
REDIS_PORT=6379     # was 6380 — Redis internal container port
```

Add (for MySQL healthcheck):
```env
DB_ROOT_PASSWORD=rootsecret
```

Same corrections apply to `backend/.env.example` (with blank `DB_ROOT_PASSWORD=`).

### Root `.gitignore` Must Exclude

The root `.gitignore` must contain:
```
.env
.env.local
```
It must NOT exclude `.env.example`, `.env.staging.example`, `.env.production.example` — those are committed.

### Testing Requirements

Infrastructure story — no PHPUnit/Jest tests. Verification via:
1. `docker compose config` — validates YAML and env substitution, lists all 5 services
2. `grep -r "3306\|6379\|3000\|8000" docker-compose.yml nginx/` — must return zero matches (no hardcoded internal ports)
3. `docker compose up -d` — all 5 services start, `docker compose ps` shows all as "Up"
4. `curl -I http://localhost` — Nginx responds (200 or 502 depending on backend readiness)
5. Verify `backend/.env` has `DB_PORT=3306` and `REDIS_PORT=6379`

### Scope Boundaries — DO NOT implement in Story 1.3

| Excluded | Belongs To |
|---|---|
| Queue worker service (`php artisan queue:work`) | Story 1.5 |
| `php artisan migrate` execution | Story 1.4 |
| Redis as CACHE_STORE/QUEUE_CONNECTION | Story 1.5 |
| SSL/TLS termination (HTTPS) | Post-MVP |
| `.github/workflows/ci.yml` | Post-MVP |
| Laravel Sanctum configuration | Story 1.6 |

### Previous Story Intelligence

**From Story 1.1 (backend):**
- Backend Dockerfile: `dev` target → `php artisan serve --host=0.0.0.0 --port=${APP_PORT:-8081}` on port 8081
- Backend Dockerfile: `prod` target → `php-fpm` on port 9000 (FastCGI, not HTTP)
- `docker-entrypoint.sh` runs artisan cache commands before starting php-fpm in prod
- `backend/.env`: currently has `DB_PORT=3307` and `REDIS_PORT=6380` — WRONG for container networking, fix in Task 2
- `APP_PORT=8081` in `backend/.env` — correct, backend artisan serve uses this
- Deferred from Story 1.1 review: the broken `docker-compose.yml` at root references removed `Dockerfile.prod` — replace it entirely

**From Story 1.2 (frontend):**
- Frontend Dockerfile: `dev` target → `CMD ["sh", "-c", "pnpm dev"]` (reads FRONTEND_PORT from .env.local via package.json script)
- Frontend Dockerfile: `prod` target → `CMD ["sh", "-c", "PORT=${FRONTEND_PORT:-3001} node server.js"]` — standalone Next.js
- `pnpm@10.33.2` pinned in frontend Dockerfile base stage
- `next.config.ts` has `output: 'standalone'` — standalone build copies minimal server.js + deps
- `frontend/.env.local` has `FRONTEND_PORT=3001` and `NEXT_PUBLIC_API_URL=http://localhost:8081`
- In Docker dev, frontend container uses `env_file: ./frontend/.env.local`

### Key Decisions

- **MySQL healthcheck uses `-h 127.0.0.1`** not `-h localhost` — MySQL's `localhost` may use socket instead of TCP, causing healthcheck to fail when MySQL isn't fully ready
- **Volume exclusions** (`/var/www/html/vendor`, `/app/node_modules`) prevent the host bind mount from wiping container-installed dependencies — this is a common Docker dev mistake
- **`start_period: 30s` on MySQL healthcheck** — MySQL takes time to initialize on first run; without it, backend may fail to start before MySQL is ready
- **`env_file` + `environment` override pattern** — `env_file: ./backend/.env` loads ALL backend config; the `environment:` block in compose overrides only the network-specific values (DB_HOST, DB_PORT, REDIS_HOST, REDIS_PORT)

## Review Findings

- [x] [Review][Decision] D1: Backend/frontend host port exposure — `APP_PORT` and `FRONTEND_PORT` are in root `.env.example` but neither service has a `ports:` mapping in `docker-compose.yml`. AC 4 says only Nginx exposes host ports. Options: (a) Remove `APP_PORT`/`FRONTEND_PORT` from root env files and drive Nginx upstream via envsubst; (b) Add `ports:` mappings to backend and frontend for direct dev access (bypasses Nginx, useful for Postman/curl)
- [x] [Review][Decision] D2: Production env file strategy — `docker-compose.prod.yml` inherits `env_file: ./backend/.env` (dev config) from base. No prod env file exists. Options: (a) Rely on CI/CD to inject env vars as `environment:` overrides; (b) Document that operator creates `backend/.env.production` (gitignored); (c) Add `env_file: ./backend/.env.production` override in `docker-compose.prod.yml`
- [x] [Review][Decision] D3: `docker-compose.prod.yml` `frontend.volumes: []` does NOT clear base bind-mounts — Docker Compose merges sequence fields, so the dev source tree bind-mount (`./frontend:/app`) and anonymous volumes remain active in prod, overriding the standalone build. Options: (a) Move all frontend dev volumes to a new `docker-compose.dev.yml` override so the base file has no frontend volumes; (b) Accept as-is if prod is always built locally with source available (unlikely for deploy)
- [x] [Review][Decision] D4: No backend or frontend health checks — nginx `depends_on` uses `service_started` because no `healthcheck` is defined on either app service. Options: (a) Add a simple TCP/HTTP health check to backend (`curl -sf http://localhost:8081/`) and frontend (`curl -sf http://localhost:3001/`); (b) Accept 502 window on cold start; (c) Defer until a `/api/health` endpoint exists in Story 1.6
- [x] [Review][Patch] P1: `prod.conf` `location ~ \.php$` matches ALL .php files globally — restrict to `location = /index.php` to prevent arbitrary PHP execution if any .php file lands in public/ [`nginx/conf.d/prod.conf:23`]
- [x] [Review][Patch] P2: `prod.conf` `SCRIPT_FILENAME` uses `$document_root$fastcgi_script_name` — hardcode to `$document_root/index.php` for single-entry-point app (defense in depth) [`nginx/conf.d/prod.conf:26`]
- [x] [Review][Patch] P3: MySQL and Redis port bindings expose to all host interfaces — add `127.0.0.1:` prefix: `127.0.0.1:${DB_PORT:-3307}:3306` and `127.0.0.1:${REDIS_PORT:-6380}:6379` [`docker-compose.yml:40,61`]
- [x] [Review][Patch] P4: `docker-compose.prod.yml` has no `networks:` stanza — if run standalone (without base compose file), all services are isolated and 502 on every request [`docker-compose.prod.yml`]
- [x] [Review][Patch] P5: MySQL healthcheck will permanently fail when `.env.example` is copied as-is (empty `DB_ROOT_PASSWORD`) — simplify healthcheck to `mysqladmin ping -h 127.0.0.1` (no password flag) [`docker-compose.yml:49`]
- [x] [Review][Patch] P6: `nginx.conf` `gzip on` is inert for proxied responses — `gzip_proxied` defaults to `off`, so no response from backend or frontend is ever compressed; add `gzip_proxied any;` [`nginx/nginx.conf:14`]
- [x] [Review][Patch] P7: Root `.env.example` missing `DB_ROOT_PASSWORD=` placeholder — required by MySQL service `environment:` block in `docker-compose.yml` [`/.env.example`]
- [x] [Review][Patch] P8: Root `.env.example` missing `NEXT_PUBLIC_API_URL=` placeholder — referenced as build arg in `docker-compose.prod.yml` but absent from template [`/.env.example`]
- [x] [Review][Patch] P9: Root `.gitignore` should protect `.env.staging` and `.env.production` — developers will create these from examples and fill real credentials; currently nothing prevents them being committed [`/.gitignore`]
- [x] [Review][Patch] P10: `frontend` `env_file: ./frontend/.env.local` causes hard compose failure if file doesn't exist — use object form with `required: false` (Compose v2.24+) [`docker-compose.yml:32`]
- [x] [Review][Patch] P11: `DB_ROOT_PASSWORD` added to `backend/.env` and `backend/.env.example` unnecessarily — backend app never needs root DB access; this credential belongs only in root `.env` (MySQL service) and leaks into the backend container's environment [`backend/.env`, `backend/.env.example`]
- [x] [Review][Defer] Deferred: Redis has no password — architectural choice, future hardening story (Story 1.5+) [`docker-compose.yml`] — deferred, pre-existing
- [x] [Review][Defer] Deferred: `NEXT_PUBLIC_API_URL=http://localhost:8081` in frontend/.env.local — backend port not exposed, browser calls fail; root cause is Story 1.2 scope [`frontend/.env.local`] — deferred, pre-existing
- [x] [Review][Defer] Deferred: Anonymous vendor/node_modules volumes go stale after rebuild — inherent Docker volume behavior; document `docker compose down -v` in onboarding — deferred, pre-existing
- [x] [Review][Defer] Deferred: Nginx upstream keepalive and FastCGI buffering tuning — performance optimization, not broken — deferred, pre-existing
- [x] [Review][Defer] Deferred: `storage/` and `bootstrap/cache/` write permissions on Windows host — Story 1.1 scope [`docker-compose.yml`] — deferred, pre-existing

## Dev Agent Record

### Agent Model Used

claude-sonnet-4-6

### Debug Log References

None — implementation proceeded without blockers.

### Completion Notes List

- Replaced broken 4-service docker-compose.yml (referenced removed Dockerfile.prod, wrong ports) with correct 5-service compose: backend, frontend, mysql, redis, nginx
- MySQL healthcheck uses `CMD-SHELL` form with `$$MYSQL_ROOT_PASSWORD` (not `CMD` array) to enable shell variable expansion inside the container; references `MYSQL_ROOT_PASSWORD` (the container env var) not `DB_ROOT_PASSWORD`
- Backend service environment block overrides `DB_PORT: "3306"` and `REDIS_PORT: "6379"` so Laravel always connects to container-internal ports, regardless of host-side port values in root `.env`
- Fixed `backend/.env` and `backend/.env.example`: `DB_PORT` 3307→3306, `REDIS_PORT` 6380→6379; added `DB_ROOT_PASSWORD=rootsecret`
- Root `.env` (gitignored) holds Docker Compose-level host-side port values (DB_PORT=3307, REDIS_PORT=6380)
- Root `.gitignore` created (was missing) with `.env` and `.env.local` exclusions
- Named anonymous volumes (`/var/www/html/vendor`, `/app/node_modules`, `/app/.next`) prevent bind mounts from wiping container-installed deps
- `docker compose config` validated successfully — all 5 services present, no parse errors
- `docker-compose.prod.yml` override: backend/frontend switch to `prod` build target; frontend passes `NEXT_PUBLIC_API_URL` build arg; shared `backend_public` volume lets nginx serve Laravel's static files via FastCGI

### File List

- `docker-compose.yml` (replaced — 5 services, health checks, 127.0.0.1 port binding, required:false env_file)
- `docker-compose.dev.yml` (new — dev bind-mounts and anonymous volumes overlay)
- `docker-compose.prod.yml` (replaced — prod builds, env_file override, networks stanza, prod FPM healthcheck)
- `.env` (new — gitignored, compose-level host-side vars)
- `.env.example` (new — template with DB_ROOT_PASSWORD and NEXT_PUBLIC_API_URL)
- `.env.staging.example` (new)
- `.env.production.example` (new)
- `.gitignore` (new — protects .env, .env.local, .env.staging, .env.production)
- `nginx/nginx.conf` (new — gzip_proxied any, text/javascript and svg types added)
- `nginx/conf.d/dev.conf` (new)
- `nginx/conf.d/prod.conf` (replaced — location = /index.php, hardcoded SCRIPT_FILENAME)
- `backend/.env` (modified — DB_PORT=3306, REDIS_PORT=6379, removed DB_ROOT_PASSWORD)
- `backend/.env.example` (modified — same fixes)
- `backend/.env.production.example` (new — production env template for D2)
