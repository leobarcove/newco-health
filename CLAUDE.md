# CLAUDE.md — NewCo Health

Guidance for Claude Code when working in this repository.

## What this project is

Low-bandwidth-first virtual telehealth platform for Nigeria. Two deployables:
- `apps/api` — Laravel 13 modular monolith: REST API, Reverb WebSockets, Horizon queues, **Filament backoffice at `/admin`**.
- `apps/web` — Vite + React 19 static SPA: patient/doctor/sponsor **PWA** (installable, offline-capable). No Node runtime in production.

**Read the plans before non-trivial work:** `docs/nigeria-telehealth-dev-plan.md` (architecture/stack), `docs/nigeria-telehealth-design-plan.md` (UI/UX), `docs/nigeria-telehealth-startup-plan.md` (business context). Decisions there are binding; changes to them need an ADR in `docs/adr/`.

## Hard rules

1. **British English** in file names, folder names, identifiers, and copy: licence, centre, programme, localisation, colour (except where an upstream API dictates otherwise, e.g. CSS `color`).
2. **Never run `php artisan migrate` against any remote server.** Local/docker only. Production migrations are a human-triggered runbook step.
3. **Low-bandwidth budgets are hard limits** (dev plan §8): patient first-load JS ≤ 200 KB gzipped; every screen must handle its six states (loading / empty / offline / error / success / degraded); no web fonts; no hero images in patient flows.
4. **Modular monolith boundaries:** code in `apps/api/app/Modules/<X>` must not import models from another module — cross-module calls go through the other module's `Services/` class.
5. **PHI discipline:** clinical text, names, phone numbers are encrypted casts; every PHI read goes through the audit middleware; never log PHI (including in Sentry context or test fixtures).
6. **Keep the service worker minimal** — app-shell cache + background sync queue only. Do not add caching cleverness.
7. **No new runtime dependencies without justification** — prefer Laravel first-party (Sanctum, Reverb, Horizon) and existing deps. Anything novel needs an ADR.
8. **Payments/consults/payouts/prescribing code requires tests** (Pest) — these paths target near-100% coverage; a PR touching them without tests is incomplete.

## Local commands

```bash
make up            # docker compose: postgres, redis, mailpit, minio
make api           # php artisan serve on :8000 (php@8.3 local; 8.5 in prod images)
make web           # vite dev server on :5173
make test          # Pest (api) + typecheck/build (web)
```

Local PHP is at `/usr/local/opt/php@8.3/bin/php` (system default is 8.1 — too old; always use the 8.3 binary or `make` targets which handle it).

## Conventions

- Trunk-based: small PRs to `main`; staging auto-deploys from `main`; production deploys from tags (`vX.Y.Z`).
- Public IDs are ULIDs. API is OpenAPI-first — update the spec, regenerate `packages/api-client`; drift fails CI.
- Feature flags via the `features` table, not env vars, for anything patient-visible.
- Commit style: imperative, scoped prefix (`api:`, `web:`, `infra:`, `docs:`), e.g. `api: add consult state machine transitions`.
- UI copy: never invent patient-facing copy ad hoc — English + Pidgin strings live in the copy catalogue and need medical + copy review (design plan §6).

## Current status

Phase 1 Sprint 1 (skeleton). Sprint map in dev plan §13. Open decisions in dev plan §15.
