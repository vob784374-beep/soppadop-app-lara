# syntax=docker/dockerfile:1

# ─── Base stage ───────────────────────────────────────────────────────────────
FROM node:22-alpine AS base

RUN npm install -g pnpm@10.33.2
WORKDIR /app

# ─── Development target ───────────────────────────────────────────────────────
FROM base AS dev

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .

EXPOSE 3001
CMD ["sh", "-c", "pnpm dev"]

# ─── Builder stage ────────────────────────────────────────────────────────────
FROM base AS builder

ARG NEXT_PUBLIC_API_URL
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL

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
