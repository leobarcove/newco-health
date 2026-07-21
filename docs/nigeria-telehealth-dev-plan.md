# Nigeria Telehealth — Development Plan

> Companion to `nigeria-telehealth-startup-plan.md` (business) and `nigeria-telehealth-design-plan.md` (UI/UX & design system). This document defines **how we build it**.
> Prepared: 21 July 2026 · Status: Draft v1.2 (Vite SPA + Filament revision; security & future-proofing hardening)
> Founder requirements: **easy to deploy · fast build · easy to maintain · fast performance · fast loading · highly secure · no app-store approval flows.**

---

## 1. Guiding Principles

1. **No app-store gate.** Every user-facing surface ships as a web deployment we control. Push to `main` → live in minutes. No Apple/Google review queue, ever, for day-to-day releases.
2. **Boring technology, few moving parts.** One backend, one database, one cache. No microservices, no Kubernetes, no Kafka. A 2–4 person team must be able to hold the whole system in their heads.
3. **Two deployables, one repository.** One Laravel app (API + Filament backoffice) and one static React SPA. No Node runtime in production at all.
4. **Low-bandwidth is an engineering budget, not a slogan.** Hard numeric budgets (Section 8) enforced in CI.
5. **Buy the hard parts.** Video (Daily.co), payments (Paystack/Flutterwave), messaging (Termii, Meta WhatsApp Cloud API), error tracking (Sentry). We write business logic, not infrastructure.
6. **British English** in file names, folder names, function names, copy, and documentation throughout (licence, localisation, centre, programme).

---

## 2. Release Strategy — the "no approval queue" architecture

### 2.1 Why PWA-first

| Option | Release friction | Verdict |
|---|---|---|
| **PWA (installable web app)** | None — deploy like a website | ✅ **Primary strategy** |
| Native/React Native via stores | Apple 1–3 days, Google hours–days, every release; rejection roulette | ❌ Not for MVP |
| Expo + OTA updates | Initial submission + review still required; native changes re-reviewed; OTA policy grey zones | ⚠️ Only if a native-only need emerges |
| **TWA (Trusted Web Activity)** | One lightweight Play review, once; app is just a wrapper around the live PWA — store listing without store-release coupling | ✅ Phase 2, for Play-Store discoverability |

The market makes this easy: Nigerian patients are overwhelmingly on **Android + Chrome**, where PWAs are first-class (install prompt, web push, offline service worker). Diaspora sponsors use a normal web dashboard. iOS web push has worked since 16.4 for the minority case.

**Native-need triggers** (revisit RN/Expo only if one of these becomes real): persistent background call ringing requirements, Bluetooth medical-device pairing, or Play-Store-exclusive distribution deals. None applies to MVP.

### 2.2 Build-time policy (founder requirement: avoid build time)

| Surface | Build step | Reality |
|---|---|---|
| `api` (Laravel) | **None** — PHP doesn't compile; deploy = `composer install` + config cache | seconds |
| Backoffice (Filament) | **None** — Livewire/Blade ships inside the Laravel app; no npm, no bundler | zero |
| `web` (Vite SPA) | Dev: **none** (instant dev server + hot reload). Production: one automated ~30–60 s build **in CI**, never on a developer's machine | push → live in ~2–3 min |

The one place a true zero-build frontend exists (server-rendered Livewire/Alpine for the patient app) was considered and **rejected**: it forces a server round-trip per interaction and has no service-worker/offline story, which would destroy the low-bandwidth ladder (offline intake, queued sync, cache-first chat) — the product's core differentiator. The ~30 s CI build is the entire price of keeping it; no developer ever waits on it interactively. (ADR-003.)

### 2.3 What this changes from the business plan

Business plan §5.1/§8.1 assumed React Native apps. **Revised:** patient and doctor surfaces are PWAs at launch; the CLEA React Native video-call screen is demoted from "port" to "reference for reconnect edge-case logic". Everything else in the business plan stands.

---

## 3. System Landscape

```
                    ┌─────────────────────────────────────────┐
                    │              Cloudflare (CDN/WAF)        │
                    └──────┬──────────────────┬───────────────┘
                           │                  │
              ┌────────────▼───┐              │
              │ web (React SPA)│              │ /admin (edge-restricted)
              │ static files:  │              │
              │ patient PWA +  │              │
              │ doctor PWA +   │              │
              │ sponsor portal │              │
              └────────────┬───┘              │
                           │  REST/JSON (OpenAPI) + WebSockets
              ┌────────────▼──────────────────▼─────────────┐
              │         api (Laravel 13, PHP 8.5)            │
              │  modular monolith + queue workers + Reverb   │
              │  + Filament admin panel (backoffice, /admin) │
              └───┬─────────┬──────────┬───────────┬────────┘
                  │         │          │           │
             PostgreSQL   Redis    S3-compat   Integrations:
             (PHI, NG-    (queue,  object      Daily.co · Paystack ·
              hosted)     cache,   storage     Flutterwave · Termii ·
                          Reverb)  (NG-hosted) WhatsApp Cloud · WebPush
```

**Two deployables** (revised from three — maintenance-driven decision, see ADR-002):

| Deployable | Serves | Stack |
|---|---|---|
| `api` | All business logic, auth, webhooks, WebSockets (Reverb), queue workers, **and the backoffice via Filament at `/admin`** | Laravel 13, PHP 8.5, Filament (latest stable) |
| `web` | Patient PWA, doctor PWA, diaspora sponsor portal — **static files, no server runtime** | Vite + React 19 SPA, TypeScript, React Router, TanStack Query, `vite-plugin-pwa` |

Why not Next.js: the app lives behind a login, so SSR/SEO — Next's core value — buys nothing here, while it costs a Node runtime in production and the highest framework churn in the React ecosystem. A Vite SPA is the same React with static-file deployment. Why Filament for backoffice: staff CRUD consoles (tables, forms, filters, role gates) are Filament's exact sweet spot, it lives inside the Laravel app (no separate deployable, no API-client layer for admin), and staff work from decent office connections so the patient low-bandwidth budgets don't apply to it. Patient/doctor/sponsor share the one SPA via route groups — same design system, same auth and API client.

---

## 4. Monorepo Layout

New repository (do **not** build inside clea-monorepo):

```
newco-health/
├── apps/
│   ├── api/                  # Laravel 13 (+ Filament backoffice at /admin)
│   │   ├── app/
│   │   │   ├── modules/      # modular monolith (Section 5.1)
│   │   │   └── filament/     # backoffice resources/pages/widgets
│   │   ├── database/migrations/
│   │   └── tests/            # Pest
│   └── web/                  # Vite + React SPA (patient + doctor + sponsor PWA)
│       └── src/
│           ├── routes/
│           │   ├── patient/
│           │   ├── doctor/
│           │   └── sponsor/
│           ├── features/     # consult-thread, payments, triage, ...
│           └── lib/          # api client, offline queue, push
├── packages/
│   ├── api-client/           # TypeScript client generated from OpenAPI spec
│   └── config/               # shared eslint/tsconfig/tailwind presets
├── infra/
│   ├── docker-compose.yml    # local dev: postgres, redis, mailpit, minio
│   ├── deploy/               # production compose files + Caddy config
│   └── scripts/
├── docs/
│   ├── adr/                  # architecture decision records (one page each)
│   └── runbooks/             # deploy, incident, restore-from-backup
└── .github/workflows/        # ci.yml, deploy-api.yml, deploy-web.yml
```

(No turborepo needed with a single JS app — plain npm workspaces suffice; add build orchestration only if a second JS app ever appears.)

---

## 5. Application Design

### 5.1 API — Laravel 13 modular monolith

One codebase, hard module boundaries (folders + no cross-module model imports; interactions via each module's service class):

| Module | Owns |
|---|---|
| `identity` | Auth (phone + OTP via Termii; email/password + 2FA for sponsors/staff), sessions (Sanctum), roles/permissions, consent ledger |
| `patients` | Profiles, dependants, medical history vault, sponsor↔beneficiary links + consent toggles |
| `doctors` | Onboarding, MDCN licence verification + expiry tracking, availability roster, auto-suspension |
| `consults` | The core state machine (Section 5.4), queue (Redis sorted sets), triage intake, consult threads/messages, Daily.co room orchestration (ported from CLEA's `DailyCoController` pattern) |
| `prescribing` | Formulary (Nigerian Essential Medicines List), e-prescriptions, PDF generation, pharmacy routing + pickup codes |
| `payments` | Paystack primary / Flutterwave failover, NGN + FX checkout, wallets, subscriptions, webhooks, reconciliation |
| `payouts` | Doctor earnings ledger, weekly payout runs (Paystack Transfers), payout statements |
| `programmes` | Chronic-care subscriptions, scheduled check-ins, adherence nudges |
| `messaging` | Termii SMS, WhatsApp Cloud API, FCM/web push — one `Notifier` interface, channel fallback chain (push → WhatsApp → SMS) |
| `compliance` | PHI access audit trail, NDPA data-subject requests, breach workflow, retention jobs |

Conventions: Laravel defaults everywhere (Eloquent, form requests, policies, queued jobs on Redis via Horizon). No CQRS, no event sourcing, no repositories-over-Eloquent ceremony.

### 5.2 Web PWA (Vite + React SPA)

- Static build served by Caddy/Cloudflare — **no server runtime**; a deploy is an atomic swap of a files folder.
- **Service worker via `vite-plugin-pwa` (Workbox)**: offline app shell + background-sync queue for form submissions (intake completed offline syncs when signal returns).
- Installable manifest; web push (VAPID) subscriptions stored per device.
- Routing: React Router route groups `patient/`, `doctor/`, `sponsor/`; lazy-loaded per group so each audience downloads only its slice.
- Chat UI = the canonical consult surface; renders from local cache first, reconciles with server.
- **Video via Daily.co Prebuilt** embedded component — we do not build a call UI in v1; connection-quality gate decides whether the "upgrade to video" button even appears.
- State: TanStack Query + the generated `api-client`; no global state library.

### 5.3 Backoffice (Filament, inside `api`)

- Filament resources for every ops entity: consults board, fulfilment queue, doctor credentialing, payout batches, refunds, compliance console — tables/forms/filters/exports come free; we write policies and actions, not UI plumbing.
- Role-gated panels via Laravel policies; `/admin` edge-restricted (Cloudflare Access) on top.
- Every PHI read logged via middleware (feeds `compliance` module).
- **Doctor web console is NOT Filament** — doctors get a dedicated desktop-friendly view inside the `web` SPA (`doctor/` routes): queue board, consult workspace (thread + notes + prescribe side-by-side). Filament stays staff-only.

### 5.4 The consult state machine (the heart of the system)

```
requested → triaged → queued → assigned → in_consult ⇄ (video|voice|chat modality switches)
   → concluded → prescription_issued? → fulfilled? → closed (72h follow-up window)
        ↘ escalated_emergency (red-flag routing, at any stage)
        ↘ abandoned / refunded
```

Rules: every transition is an audited event; modality switches (video↔voice↔chat) do **not** create a new consult — the thread persists; a dropped connection auto-downgrades modality rather than failing the consult (business plan §6 ladder).

---

## 6. Integrations

| Integration | Approach | Failure posture |
|---|---|---|
| **Paystack** (primary) | Checkout (cards/transfer/USSD), Transfers API for payouts, webhooks with signature verification + idempotency keys | Auto-failover to Flutterwave on init failure; reconciliation job nightly |
| **Flutterwave** (secondary) | Same abstraction behind a `PaymentGateway` interface | — |
| **FX checkout (sponsors)** | Paystack international cards first; add Stripe only if decline rates demand it | Monitor decline rate by issuer country |
| **Daily.co** | Server-side room + short-lived meeting tokens (CLEA pattern); rooms named by consult ULID; auto-expire | Voice-only fallback on same room; PSTN coordinator callback as last rung |
| **Termii** | OTP + transactional SMS, sender ID registration early (takes weeks — start Month 0) | Queue with retry/backoff |
| **WhatsApp Cloud API** | Template messages (reminders, prescription-ready, payment links); inbound routed to care coordinators in admin | Business verification takes weeks — start Month 0 |
| **Push** | Web push (VAPID) for PWA — on installed Android PWAs this is functionally native-grade (system tray, actions, works with browser closed). FCM only if/when TWA ships | Nigerian reality: Transsion (Tecno/Infinix/itel) battery optimisers can delay background delivery — for native apps too — so critical alerts always cascade push → WhatsApp → SMS, and the sprint device bench includes a Tecno. Web push cannot do full-screen call ringing (a §2.1 native trigger, not needed for patient-initiated consults) |

---

## 7. Data Model (core entities)

`users` (polymorphic role: patient/doctor/sponsor/staff) · `patients` · `dependants` · `sponsorships` (sponsor↔beneficiary, plan, consent flags) · `doctors` (mdcn_licence_no, licence_expires_at, status) · `consults` (state, modality, patient_id, doctor_id, daily_room) · `consult_messages` (canonical record; text/image/voice-note/system) · `consult_notes` (SOAP-lite, doctor-only) · `prescriptions` + `prescription_items` (formulary FK) · `pharmacies` + `fulfilments` (pickup_code, state) · `payments` / `wallets` / `subscriptions` · `payout_batches` + `payout_items` · `programmes` + `programme_enrolments` + `check_ins` · `consents` (append-only) · `phi_access_log` (append-only) · `audit_events` (append-only).

PHI columns (names, clinical text, phone) encrypted at rest via Laravel's encrypted casts; keys in env-level KMS. ULIDs for all public IDs.

---

## 8. Low-Bandwidth Budgets (CI-enforced)

| Budget | Limit | Enforcement |
|---|---|---|
| Patient PWA first-load JS | ≤ 200 KB gzipped | Vite build size check (`rollup-plugin-visualizer` + CI budget), fails the build |
| Any patient route payload | ≤ 300 KB total | Lighthouse CI on 3G throttle profile |
| Time-to-interactive on throttled 3G / mid-range Android profile | ≤ 5 s | Lighthouse CI budget file |
| Image uploads | client-side compress to ≤ 200 KB, EXIF-stripped | upload pipeline |
| API responses | paginated, gzip/brotli, no unbounded lists | code review checklist |
| Offline | intake + history readable offline; writes queue and sync | Playwright test with network-offline emulation |

Real-device check each sprint: one mid-range Android (~2 GB RAM) on a throttled connection runs the smoke flow.

---

## 9. Environments & Infrastructure

| Env | Where | Notes |
|---|---|---|
| `local` | Docker Compose (postgres, redis, mailpit, minio) | one-command setup: `make up` |
| `staging` | Small VPS, EU or ZA — no real PHI, synthetic data only | auto-deploy on merge to `main` |
| `production` | **Nigeria-hosted** (NITDA Level 3): shortlist Rack Centre / MainOne-Equinox Lagos / Nobus cloud — 2 app VMs + managed-style Postgres VM + Redis | deploy on git tag |

- Provisioning: Docker Compose per VM + **Caddy** (auto-TLS) — deliberately not Kubernetes.
- Cloudflare in front (Lagos PoP): CDN for static/PWA assets, WAF, rate limiting; `admin` edge-restricted (Cloudflare Access).
- Backups: nightly encrypted Postgres dumps to NG object storage + weekly restore drill (runbook).
- Monitoring: Sentry (api + web + admin), Uptime probes **from Nigerian networks** (MTN/Glo vantage), Laravel Horizon dashboard, a single Grafana/Prometheus box only when scale demands.

---

## 10. CI/CD

```
PR → lint + typecheck + Pest + bundle/Lighthouse budgets + Playwright smoke
merge to main → auto-deploy staging (both deployables)
git tag vX.Y.Z → production deploy:
    1. build + push images (immutable, tag-addressed)
    2. deploy api (rolling, health-checked)   ← migrations NOT auto-run
    3. deploy web (atomic swap of static files)
rollback = redeploy previous tag (api image + web artefact both retained);
           one runbook command, rehearsed in the weekly deploy
```

**Migration policy (firm):** migrations are never auto-executed on remote servers by CI. Each release's migrations are reviewed for backwards-compatibility (expand → migrate → contract pattern) and run as a **deliberate, human-triggered step** in the deploy runbook, with a tested rollback. Destructive migrations require a second reviewer.

Release cadence: ship whenever green — that is the entire point of the PWA strategy. Patient-visible changes gated behind simple DB-driven feature flags (a `features` table + cached lookup; no third-party flag service).

---

## 11. Testing Strategy (minimal but ruthless on the money paths)

| Layer | Tool | Coverage philosophy |
|---|---|---|
| API unit/feature | Pest | Consult state machine, payments/webhooks, payouts, prescribing = **near-100%**; everything else pragmatic |
| Contract | OpenAPI spec is source of truth; `api-client` regenerated in CI; drift fails build | prevents web/api desync |
| E2E | Playwright | 5 golden journeys only: register→consult→pay, doctor accept→conclude→prescribe, sponsor subscribe→beneficiary consult, pharmacy fulfil, refund |
| Load | k6, pre-launch + before campaigns | queue + WebSocket fan-out under 500 concurrent consults |
| Manual | sprint-end real-device pass on throttled 3G | the budget tables above |

No aspirational 80%-everything mandates — test depth follows blast radius.

---

## 12. Security & Compliance Implementation

**Identity & access**
- Sanctum sessions; OTP rate-limiting; sponsor/staff 2FA (TOTP); staff sessions short-lived with device list + remote revoke.
- RBAC via policies; sponsor sees billing + care-status only unless patient consent toggle is on (enforced in policy layer, not UI).
- WebSocket channels (Reverb) use signed, per-user channel authorisation — no open channels; consult rooms joinable only by their two participants + authorised staff.
- Infra accounts (Cloudflare, registrar, hosting, Paystack dashboard) on hardware-key MFA; no shared logins.

**Application hardening**
- Security headers on both deployables: strict CSP (no third-party scripts except Daily.co/Paystack allow-listed), HSTS preload, frame-ancestors none (except Daily embed route), Referrer-Policy strict.
- Object storage private-by-default; every file access via short-lived signed URLs; uploads virus-scanned (ClamAV container) before storage.
- All webhook endpoints: signature verification + idempotency + replay-window checks.
- CI security gates: secret scanning (gitleaks), SAST (Semgrep OWASP ruleset), `composer audit` + `npm audit` — failing severity blocks merge, weekly scheduled run regardless.

**Data protection**
- `phi_access_log` middleware on every PHI read (who/what/why), surfaced in admin compliance console.
- Consent ledger append-only; NDPA data-subject export/delete jobs.
- Secrets: never in git; per-environment encrypted secrets (SOPS + age) with deploy-time injection; quarterly rotation of PSP/API keys documented in a runbook.
- Backups: nightly encrypted dumps **plus** an immutable (object-lock) weekly copy — ransomware posture, not just disaster recovery; weekly restore drill.
- Postgres: app connects as least-privilege role (no DDL); a separate migration role used only in the human-triggered migration step.

**Assurance**
- 72-hour breach runbook in `docs/runbooks/`; Sentry PII-scrubbing on.
- Pen-test before public launch and annually; dependency audit weekly (above).

---

## 13. Delivery Phases

### Phase 0 — Concierge validation (Months 0–3) · ~0.5 dev
Almost no code: landing page + Paystack payment links + WhatsApp Business + Calendly-style booking + a spreadsheet. **Build nothing that a coordinator can fake.** Meanwhile: register Termii sender ID, WhatsApp business verification, Daily.co + Paystack accounts, NG hosting quotes, repo + CI skeleton.

### Phase 1 — MVP build (Months 3–7) · 2–3 devs, eight 2-week sprints

| Sprint | Deliverable |
|---|---|
| 1 | Repo, CI/CD, infra up, auth (OTP), design system seed |
| 2 | Patient onboarding + dependants + triage intake (offline-capable) |
| 3 | Consult engine v1: queue, assignment, **chat consult** end-to-end |
| 4 | Payments (Paystack NGN) + pricing + receipts; doctor console v1 (accept, thread, conclude) |
| 5 | Voice/video via Daily.co Prebuilt + connection-quality gate + modality downgrade |
| 6 | Prescribing + formulary + PDF + pharmacy pickup codes; admin ops board |
| 7 | Sponsor portal: FX checkout, beneficiary linking, care dashboard; payouts v1 (weekly batch) |
| 8 | Compliance console, notifications fallback chain, load test, pen-test fixes, **soft launch** |

### Phase 2 — Retention & reach (Months 7–14)
Chronic-care programmes module · WhatsApp template flows · Hausa i18n (scaffolded from day one) · TWA Play-Store wrapper (one-time review, listing only) · WellaHealth fulfilment integration · SME employer plans · reporting warehouse (nightly Postgres replica, not a "data platform").

### Phase 3 — Experiments (Months 14+)
USSD/IVR pilots · NHIA/HMO integration if reimbursement lands · additional states · evaluate native app only if a §2.1 trigger fired.

---

## 14. Team & Ways of Working

- **Phase 1 team:** 1 full-stack Laravel-leaning dev, 1 frontend/PWA dev, 1 founder-engineer across both + infra. Add QA-minded contractor at sprint 6.
- Trunk-based development; PRs small; CI is the reviewer of record for budgets.
- ADRs (one page) for every irreversible choice — the plan's decisions are ADR-001…n on day one.
- Weekly dependency-update window; quarterly framework-version review. Boring beats novel.

---

## 15. Decisions Locked vs Open

**Locked by this plan:** PWA-first (no store approvals) · Laravel 13 modular monolith · **Vite + React SPA (static) + Filament backoffice — no Node runtime in production** · Postgres/Redis · Daily.co Prebuilt · Paystack primary · new monorepo (not clea-monorepo) · Docker Compose + Caddy (no k8s) · migrations human-triggered.

**Open:** NG hosting vendor (quotes, Month 1) · Daily.co vs 100ms cost benchmark at >5k consults/mo · Stripe addition for sponsor FX (decline-rate driven) · TWA timing in Phase 2.

**Version policy:** the versions in this document are current as of **July 2026** (Laravel 13.8 / PHP 8.5 / Vite 7 + React 19 / Filament latest stable / PostgreSQL 18). At build kickoff (Phase 1, sprint 1), re-verify the latest **stable** of each and adopt it — never start a greenfield project on an outgoing major. After kickoff: pin majors for the phase, take minor/patch updates in the weekly dependency window, and review majors quarterly (§14). New majors released mid-phase are not adopted mid-sprint.

---

## 16. Future-Proofing & Performance Levers

Levers deliberately **not** used at MVP, documented so scaling is a config change, not a redesign:

| Pressure | Lever (in order) | Notes |
|---|---|---|
| API throughput | 1) opcache + config/route caching (day one) → 2) **Laravel Octane on FrankenPHP** (same codebase, one container-image change) → 3) add app VMs behind Caddy | Octane is the flip-a-switch answer to "PHP is slow"; don't pay its debugging complexity before load justifies it |
| DB load | EXPLAIN review for any query >50 ms (CI habit) → read replica (also feeds reporting) → PgBouncer | Postgres 18 async I/O already helps cold reads |
| WebSocket fan-out | Reverb scales horizontally via its Redis pub/sub mode | config, not code |
| Queue depth | Horizon auto-balancing; split critical (OTP, payments) vs bulk (nudges) queues **from day one** | queue names cost nothing now, save an incident later |
| Static/media | Already CDN'd; images served in AVIF/WebP with size variants generated on upload | |
| Loading speed | Budgets in §8; fonts = system stack only (zero font bytes); route-level prefetch only on good connections (`navigator.connection` heuristic) | |

**Structural escape hatches (why this architecture won't need a rewrite):**
- **OpenAPI contract** = every future client (native app if a §2.1 trigger fires, partner HMO integration, USSD gateway) consumes the same API; the backend never changes shape for a new frontend.
- **Modular monolith** = any module (e.g. `payments`) can be extracted to a service later by keeping its service-class interface; module boundaries are the future microservice seams — if ever needed.
- **Static SPA** = portable to any host in minutes; no vendor or runtime lock-in.
- **Multi-country readiness (cheap now, painful later):** country/currency/language live in a `markets` config table from day one — prices, phone validation, formulary, and PSP selection are keyed by market, never hard-coded to Nigeria. Expansion to Ghana/Kenya becomes data + partnerships, not a refactor.
- **AI lane (optional, Phase 3+):** Laravel 13's first-party AI SDK gives a native path to triage-assist or consult summarisation later — behind the same modular boundary (`consults`), and only with NDPC-compliant data handling and clinical sign-off. Not an MVP commitment.

**Known churn risks, contained:** Filament majors (pin per phase; it only touches staff UI, never patients) · Workbox/service-worker (keep the SW minimal: shell cache + background sync only — resist the urge to get clever there) · Daily.co pricing at scale (§15 open decision; the room-orchestration module isolates the vendor behind one class).
