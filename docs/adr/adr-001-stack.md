# ADR-001: Core stack — Laravel 13 monolith + Vite React SPA

**Status:** Accepted · 21 July 2026

## Decision
Laravel 13 (PHP 8.5 in production) modular monolith for all backend concerns; Vite + React 19 static SPA for all patient/doctor/sponsor surfaces; PostgreSQL 18 + Redis; Daily.co (video), Paystack primary / Flutterwave failover (payments), Termii (SMS).

## Context
Founder requirements: easy to deploy, fast build, easy to maintain by a 2–4 person team, fast performance/loading, highly secure, and **no app-store approval flows**. Team background is PHP (CLEA platform); Laravel talent is abundant in Nigeria.

## Consequences
- No microservices, no Kubernetes, no Kafka. Module boundaries in `app/Modules/*` are the future extraction seams if ever needed.
- OpenAPI spec is the contract; any future client (native app, partner, USSD gateway) consumes the same API.
- Full details: `docs/nigeria-telehealth-dev-plan.md`.
