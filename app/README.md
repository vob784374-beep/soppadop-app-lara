# Monorepo: Laravel backend + Next.js frontend

This repository contains a production-minded scaffold for a Laravel backend and a Next.js + Tailwind frontend.

Quick start (local dev):

```bash
# from repo root (this folder)
docker-compose up --build
```

- Backend health: http://localhost:9000/
- Frontend: http://localhost:3000/

Auth pages (dev):
- Login: http://localhost:3000/auth/login
- Register: http://localhost:3000/auth/register

Notes:
- The `backend/` folder is a minimal scaffold. For a full Laravel app run `composer create-project laravel/laravel .` inside `backend/` and adjust `.env`.
- For production replace the PHP built-in server with PHP-FPM + Nginx and follow zero-downtime migration/rolling deployments.
