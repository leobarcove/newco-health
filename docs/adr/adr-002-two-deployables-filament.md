# ADR-002: Two deployables — backoffice is Filament inside the API

**Status:** Accepted · 21 July 2026

## Decision
The staff backoffice is a Filament panel at `/admin` inside `apps/api`, not a separate frontend app. The system has exactly two deployables: the Laravel app and the static SPA.

## Context
An earlier draft had three deployables (separate Next.js admin). Filament provides staff CRUD consoles (tables, forms, filters, role gates) nearly free, shares the Laravel codebase/auth/policies, and removes an entire deployment. Staff work from good office connections, so patient low-bandwidth budgets don't constrain the backoffice.

## Consequences
- Zero JavaScript build for the entire admin side (Livewire/Blade).
- `/admin` is edge-restricted (Cloudflare Access) in addition to app auth.
- The doctor console is **not** Filament — doctors are supply-side users and get a designed SPA experience (`apps/web` `doctor/` routes).
- Filament's fast major-release cadence is contained: staff-only blast radius, majors pinned per phase.
