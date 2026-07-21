# ADR-003: Vite SPA over zero-build Livewire for patient surfaces

**Status:** Accepted · 21 July 2026

## Decision
Patient/doctor/sponsor surfaces are a Vite + React SPA with a service worker, accepting one ~30–60 s CI-only production build, instead of a truly zero-build server-rendered Livewire frontend.

## Context
Founder requirement was to avoid build time. Livewire would achieve zero build but forces a server round-trip per interaction and has no service-worker/offline story — which would destroy the low-bandwidth ladder (offline intake, queued sync, cache-first chat), the product's core differentiator. Next.js was separately rejected (ADR-001 context): SSR/SEO buys nothing behind a login and costs a production Node runtime plus the highest framework churn in the React ecosystem.

## Consequences
- Dev loop has zero build (Vite dev server); the only build is CI's static production bundle.
- No Node runtime in production; deploys are atomic static-file swaps.
- Service worker kept deliberately minimal: app-shell cache + background sync only.
