# NewCo Health

> Low-bandwidth-first virtual telehealth for Nigeria — chat, voice and adaptive-video consultations with licensed Nigerian doctors, e-prescriptions fulfilled through partner pharmacies, and diaspora-sponsored family care.

**Working title.** Brand name pending CAC + domain checks (see `docs/nigeria-telehealth-startup-plan.md` §15).

## The three plans (read these first)

| Document | What it covers |
|---|---|
| [`docs/nigeria-telehealth-startup-plan.md`](docs/nigeria-telehealth-startup-plan.md) | Market research, competitor audit, moat strategy, regulatory checklist, pricing, roadmap |
| [`docs/nigeria-telehealth-dev-plan.md`](docs/nigeria-telehealth-dev-plan.md) | Architecture, stack decisions, budgets, CI/CD, security, phasing |
| [`docs/nigeria-telehealth-design-plan.md`](docs/nigeria-telehealth-design-plan.md) | Design system, UX principles, signature flows, accessibility |

## Architecture in one paragraph

Two deployables, no Node runtime in production, no app-store release flow. **`apps/api`** is a Laravel 13 modular monolith (PHP 8.5 in production) serving the REST API, WebSockets (Reverb), queue workers, and the staff backoffice (Filament at `/admin`). **`apps/web`** is a Vite + React 19 static SPA — the installable patient/doctor/sponsor PWA — served as files behind Caddy/Cloudflare. PostgreSQL 18 + Redis. Video by Daily.co, payments by Paystack (Flutterwave failover), SMS by Termii.

## Repository layout

```
apps/
  api/        Laravel 13 — API + Filament backoffice (+ app/Modules/* monolith modules)
  web/        Vite + React SPA — patient / doctor / sponsor PWA
packages/
  api-client/ TypeScript client generated from the OpenAPI spec
  config/     Shared lint/ts/tailwind presets + design tokens
infra/        docker-compose (local), deploy configs (production)
docs/         Plans, ADRs (docs/adr), runbooks (docs/runbooks)
```

## Local development

Prerequisites: PHP ≥ 8.3, Composer, Node ≥ 22, Docker.

```bash
make up          # postgres + redis + mailpit + minio (docker compose)
make api         # serve Laravel on :8000 (runs migrations first — local only)
make web         # Vite dev server on :5173 (proxies /api → :8000)
```

Backoffice: http://localhost:8000/admin · PWA: http://localhost:5173

## Ground rules

- **British English** everywhere: file names, identifiers, copy (licence, centre, programme).
- **Migrations are never auto-run on remote servers** — human-triggered deploy step only (dev plan §10).
- **Budgets are law:** patient first-load JS ≤ 200 KB gzipped; CI fails the build beyond it.
- Every irreversible decision gets a one-page ADR in `docs/adr/`.

## Status

Phase 1, Sprint 1 — repository skeleton. See dev plan §13 for the sprint map.
