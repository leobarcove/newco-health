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

Prerequisites: PHP ≥ 8.3, Composer, Node ≥ 22. (Docker optional — sqlite is the default local DB.)

```bash
make fresh       # wipe + migrate + seed the full dev dataset
make api         # API + Filament backoffice on :8000
make web         # PWA dev server on :5173 (proxies /api → :8000)

# optional extras
make up          # docker: postgres + redis + mailpit + minio
make reverb      # websockets on :8080 (chat polls without it)
make queue       # scheduler: booking reminders, no-show/hold sweeps
make test        # Pest + web build     ·  make e2e  # Playwright journey
```

### Seeded sign-ins (local OTP code is always `000000`)

| Who | Sign in | What you'll see |
|---|---|---|
| Patient **Bisi** | `+234 801 111 1111` + code `000000` | Live consult with Dr Amara, concluded consult with prescription `RX-SAMPLE23`, 2 dependants, active sponsor, tomorrow's booking |
| Patient **Chuka** | `+234 802 222 2222` + code `000000` | Waiting in the queue |
| Doctor **Amara** | `+234 809 999 9991` + code `000000` | Mid-consult with Bisi, SOAP note started, Mon–Fri availability |
| Doctor **Tunde** | `+234 809 999 9992` + code `000000` | Free — sees Chuka in the queue; tomorrow's booking with Bisi |
| Sponsor | `sponsor@newco.local` / `sponsorpass` (via /sponsor/login) | ₦10,000 wallet, "Mum" (Bisi) as active beneficiary |
| Pharmacy | `pharmacy@newco.local` / `pharmacypass` (via /pharmacy/login) | Counter portal — look up `RX-SAMPLE23` and dispense |
| Staff | `admin@newco.local` / `password` at `/admin` | Filament backoffice: consults board, credentialing, compliance console |

The fixed OTP works because `.env` sets `OTP_TEST_CODE=000000` — the bypass is hard-disabled in production builds.

### External services — all simulated locally, zero accounts needed

Every third-party service sits behind a driver interface that auto-selects a local
simulator when its credentials are absent (`config/services.php`). Add the real
key → the real driver activates. No code changes, ever.

| Service | Without credentials (local) | Observe it |
|---|---|---|
| **Paystack / Flutterwave** | `FakeGateway`: checkout settles instantly, refunds approve, payout transfers succeed | Payments board in `/admin`; consult queues on "pay" |
| **Termii SMS** | `LogSmsSender` writes every message to the log | `make sms` (OTPs, reminders, invites, nudges) |
| **WhatsApp Cloud** | `UnavailableWhatsAppSender` — the Notifier chain honestly falls through to SMS | `make sms` |
| **Web push** | **Fully real locally** — VAPID keys are self-generated in `.env`, no third party involved | Chrome: allow notifications on a queued consult, then have the doctor join |
| **OTP SMS codes** | `OTP_TEST_CODE=000000` accepts any phone (non-production only) | sign-in table above |
| **Daily.co video** | Not simulated — the video module is unbuilt until an account exists | — |

The same pattern means **staging can run the entire business with zero paid
accounts**, and each real credential can be added independently, in any order.

### Local testing notes

- **Offline intake & web push work under `make web`** (the service worker runs
  in dev via `devOptions`). Test offline: DevTools → Network → Offline → submit
  an intake → the "saved — sending when you're back online" state appears.
- **WebSockets:** run `make reverb` in a second terminal — `apps/web/.env.local`
  (gitignored, mirrors the API's Reverb/VAPID keys) makes the SPA connect;
  without it chat falls back to 3-second polling, which also works.
- **Postgres parity:** local default is SQLite. To test against Postgres 18
  (`make up` first): set `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=newco
  DB_USERNAME=newco DB_PASSWORD=local` in `apps/api/.env`, then `make fresh`.
  Only Postgres enforces the bookings anti-double-booking index at the DB layer.
- **Filament MFA is required** — first `/admin` login asks you to scan a TOTP
  QR with any authenticator app (this is deliberate; it matches production).
- **Re-seeding:** `make fresh` is the supported reset; `make seed` on a
  populated database can hit unique constraints.
- **Video calls** show the simulated panel locally (fake driver); the
  `video_consults` flag is seeded on.

## Ground rules

- **British English** everywhere: file names, identifiers, copy (licence, centre, programme).
- **Migrations are never auto-run on remote servers** — human-triggered deploy step only (dev plan §10).
- **Budgets are law:** patient first-load JS ≤ 200 KB gzipped; CI fails the build beyond it.
- Every irreversible decision gets a one-page ADR in `docs/adr/`.

## Status

Phase 1, Sprint 1 — repository skeleton. See dev plan §13 for the sprint map.
