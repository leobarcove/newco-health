# ADR-004: Booking system — templates + exceptions, slots never materialised

**Status:** Accepted · 21 July 2026

## Decision
Scheduled appointments are generated on demand from **weekly availability templates** (doctor-local times, ISO weekdays, configurable slot lengths) modified by **date exceptions** (whole/partial days off, ad-hoc extra hours). Slots are never stored — only confirmed `bookings` are rows. All instants are stored UTC; each doctor carries a timezone (default `Africa/Lagos`).

## Why not materialised slots
Materialising slot rows weeks ahead requires backfill jobs on every schedule change, invites drift between generated slots and the doctor's real template, and grows a junk table. Generating on read from the template keeps a single source of truth: change the template, availability changes instantly, nothing to reconcile.

## Double-booking guarantee (two layers)
1. **Application:** booking runs in a transaction that takes a row lock on the (doctor, instant) key and re-validates the slot against `AvailabilityService::isBookable` inside the lock.
2. **Storage:** Postgres partial unique index `(doctor_id, starts_at) WHERE state = 'confirmed'` as the backstop the application cannot bypass. (SQLite test runs rely on layer 1.)

## Lifecycle
`confirmed → completed | cancelled | no_show`. Reschedule = atomic cancel + rebook with `rescheduled_from_id` linking the audit chain. Patient cancellation enforces a config cutoff (default 2 h); staff cancellation bypasses it (audited with `cancelled_by`). A booked consult **bypasses the on-demand queue** — `BookingService::begin` walks the consult state machine straight to `in_consult` inside the begin window (5 min early → 15 min grace). No-shows are swept by the reminder command.

## Config-driven rules
Lead time, horizon, cutoffs, begin window, and reminder offsets live in `config/booking.php` so a future `markets` table can override per country (dev plan §16) without code changes.

## Consequences
- Slot listing cost scales with templates × horizon, not bookings — fine at any realistic doctor count; cacheable per doctor+date if ever needed.
- Reminders (24 h / 1 h SMS) are idempotent via per-offset sent-at columns, driven by `booking:send-reminders` every five minutes.
