# NewCo Health — Demo Walkthrough & Test Script

A scripted tour of the whole platform, written to be shown to a practising
doctor. Each scenario has a story, the roles involved, and exact steps.

- **Part A — the daily flows** (~30–40 min): what happens on a normal day.
- **Part B — edge cases & failure behaviour** (~20 min): what happens when
  things go wrong, when rules are tested, and when someone tries what they
  shouldn't.

---

## Before you start

**Reset to a clean, fully seeded state (run before every demo):**

```bash
cd ~/Code/newco-health
make fresh        # wipes the database, migrates, reseeds every fixture
make api          # if not already running → http://localhost:8000
make web          # if not already running → http://localhost:5173
```

Optional, in a spare terminal — shows every simulated SMS (OTPs, reminders,
invites) as it "sends":

```bash
make sms
```

> After a reset, any browser tab that was signed in holds a stale session —
> just sign in again.

### The cast (all seeded — OTP code is always `000000`)

| Role | Sign in at | Credentials | Background |
|---|---|---|---|
| **Bisi Adewale** — patient | `localhost:5173/login` | `801 111 1111` | Mid-consult with Dr Amara; has two dependants (her mother Mama Ronke, her son Damilola); sponsored by her daughter abroad; has a booking tomorrow and an old prescription `RX-SAMPLE23` |
| **Chuka Obi** — patient | `localhost:5173/login` | `802 222 2222` | Sitting in the queue with a persistent cough |
| **Dr Amara Okafor** — doctor | `localhost:5173/login` | `809 999 9991` | Mid-consult with Bisi; SOAP note started; Mon–Fri 09:00–13:00 availability; has earnings |
| **Dr Tunde Bakare** — doctor | `localhost:5173/login` | `809 999 9992` | Free; sees the queue; has tomorrow's booking with Bisi |
| **Ngozi** — diaspora sponsor | `localhost:5173/sponsor/login` | `sponsor@newco.local` / `sponsorpass` | Bisi's daughter in Houston; ₦10,000 care wallet; sponsors "Mum" |
| **Pharmacy counter** | `localhost:5173/pharmacy/login` | `pharmacy@newco.local` / `pharmacypass` | HealthPlus Yaba, a partner pharmacy |
| **Ops staff** | `localhost:8000/admin` | `admin@newco.local` / `password` | Backoffice; first login enrols TOTP (scan the QR with any authenticator app) |

**Window setup:** normal browser window for patients; an incognito window (or a
second browser) for the doctor, so both sit side by side.

---

## Scenario 1 — The core consult: queue → chat → notes → prescription

**Background.** Chuka, a 30-something in Yaba, has had a dry cough for two
weeks. He can't afford a half-day off work for a clinic queue, so he opened
the app during his lunch break. He's been waiting a few minutes.

**Roles:** Dr Tunde (incognito window) · Chuka (normal window)

1. **[Dr Tunde]** Sign in → the **Queue** board shows *Chuka Obi* with a
   waiting-time badge. *Point out: the doctor sees who is waiting, but not the
   complaint — clinical detail unlocks only on acceptance.*
2. **[Dr Tunde]** Press **Accept** → the consult workspace opens: thread on
   the left, everything else one tap away.
3. **[Chuka]** Sign in → **Continue your consult** → his screen says
   *"Dr Tunde Bakare has joined your consult."*
4. Chat in both directions. *Point out: WhatsApp-familiar bubbles — zero
   learning curve for patients.*
5. **[Chuka]** Tap the **camera** icon → attach any photo (it is compressed to
   ≤200 KB and stripped of location metadata before upload). Tap the **mic**
   icon → record a short voice note → tap ■ to send. *Point out: a patient who
   can't type can run their whole consult by voice.*
6. **[Dr Tunde]** Open the **Notes** tab → complete the S/O/A/P fields → Save.
   *Point out: notes are doctor-only — the patient and any sponsor can never
   see them.*
7. **[Dr Tunde]** Open **Prescribe** → search `Artemether` → select → dosage
   `4 tablets twice daily`, `3` days → **Issue prescription**.
8. **[Chuka]** The prescription card appears in the thread with a pickup code
   (e.g. `RX-AB12CD34`). **Write the code down** for Scenario 2.
9. **[Dr Tunde]** **End consult** → Chuka sees *"you can reply for the next
   72 hours"*. *Point out: the follow-up window closes automatically after
   72 hours.*

---

## Scenario 2 — Pharmacy dispensing

**Background.** Chuka walks into HealthPlus Yaba after work and reads his
code to the pharmacist.

**Role:** Pharmacy counter (any window/tab)

1. Sign in at `localhost:5173/pharmacy/login`.
2. Type the `RX-` code from Scenario 1 → **Look up**.
3. *Point out: the counter sees the medicines, the prescribing doctor and
   their MDCN number, and the patient's FIRST NAME ONLY — no complaint, no
   history. Privacy by design.*
4. **Mark as dispensed** → try the same code again → it is spent. One code,
   one collection.
5. Also try the seeded `RX-SAMPLE23` (Bisi's old prescription).

---

## Scenario 3 — Voice/video upgrade (the "ladder")

**Background.** Sometimes chat isn't enough — the doctor wants to hear the
cough. Calls are an upgrade on the same consult, never a separate thing, and
the buttons only appear when the connection can carry them.

**Roles:** any live consult pair (e.g. Bisi `801 111 1111` ↔ Dr Amara
`809 999 9991`, who are seeded mid-consult)

1. In a live consult, both sides see **phone/camera icons** in the bar under
   the header.
2. Tap one → a call panel opens (locally it says *simulated* — with a real
   Daily.co key this becomes a live call, no code changes) and a system
   message lands in the thread.
3. **End call — back to chat** → the conversation continues exactly where it
   was. *Point out: on a Nigerian network, a dropped call never kills the
   consult — chat is the floor everything falls back to.*

---

## Scenario 4 — The red-flag emergency

**Background.** A patient with crushing chest pain opens the app. The one
thing a telemedicine platform must never do is put a heart attack in a queue.

**Role:** any patient

1. Home → **Talk to a doctor now**.
2. Describe anything, and tick **"Crushing chest pain right now"**.
3. Submit → instantly escalated: *"go to the nearest hospital now — our team
   has been alerted."* No queue. No payment. Ever.
4. **[Staff, later]** the escalation is visible on the admin consults board.

---

## Scenario 5 — Booked appointments

**Background.** Bisi prefers a fixed time with a doctor she knows.

**Roles:** Bisi · Dr Tunde

1. **[Bisi]** Home → **Book an appointment** → choose Dr Amara → **Tomorrow**
   → pick a time (all times Lagos) → optional note → **Confirm**.
2. **[Bisi]** **Appointments** shows it (plus her seeded booking with Dr
   Tunde). Press **Cancel** on one — allowed because it's more than 2 hours
   away.
3. **[Dr Tunde]** **Agenda** tab → tomorrow's booking with Bisi is on his day
   list. *Point out: "Begin" unlocks from 5 minutes before the slot — a
   booked consult skips the queue entirely.*
4. **[Dr Tunde]** **Schedule** tab → the weekly availability editor: windows,
   slot lengths, whole week saved as one unit. Patients can only book what's
   set here.

---

## Scenario 6 — The diaspora sponsor (the flagship flow)

**Background.** Ngozi lives in Houston. Instead of wiring cash home and
hoping it reaches a clinic, she funds a care wallet — her mother sees a
doctor, the money can't be diverted, and Ngozi sees that mum is okay
(only because mum agreed to share).

**Roles:** Ngozi (sponsor) · a patient to invite

1. **[Ngozi]** `localhost:5173/sponsor/login` → dashboard shows the ₦10,000
   wallet and **"Mum" (Bisi) active**, with her last consult status visible.
   *Point out: visible ONLY because Bisi consented — and she can switch it
   off at any time without losing the sponsorship.*
2. **[Ngozi]** **Top up** ₦5,000 → instant (simulated gateway; with real keys
   this is her foreign card).
3. **[Ngozi]** Add a family member: label `Papa`, phone `803 333 3333` →
   **Send invitation by SMS** (watch it in `make sms`).
4. **[Patient window]** Sign in as `803 333 3333` → an invitation banner sits
   on the home screen → **Accept**.
5. **[Ngozi]** Refresh → Papa is active.
6. *The payoff (see Scenario 8): when a sponsored patient consults, the fee
   comes silently out of this wallet.*

---

## Scenario 7 — Care programmes (chronic disease)

**Background.** Hypertension isn't an episode; it's a relationship. The
programme turns one-off consults into scheduled, doctor-led check-ins.

**Role:** any patient

1. Home → **Care programmes** → read the Hypertension Care card
   (₦10,000/month, check-in every 14 days).
2. **Enrol** → the card flips to enrolled with next-check-in and renewal
   dates.
3. *Point out: check-in reminders go out by SMS on schedule
   (`php artisan programmes:tick` fires one manually), and the fee can be
   covered by an employer or sponsor automatically.*
4. **Cancel programme** works instantly.

---

## Scenario 8 — Money: pay-per-consult, sponsor cover, employer cover

**Background.** Out of the box the demo runs in free mode. Flipping one
switch shows the full payment machine — including who *doesn't* pay.

**Setup:** edit `apps/api/.env` → `PAYMENTS_REQUIRED=true` → restart the API
(`make api`). Set back to `false` afterwards.

1. **[Chuka]** Start a new consult → it holds at an amber **"Consult fee:
   ₦2,500 — pay and join the queue"** panel → pay (simulated card settles
   instantly) → queued.
2. **[Bisi]** Start a consult → **no payment screen at all** — the thread
   says *"This consult is covered by your sponsor"*, and Ngozi's wallet
   balance drops.
3. **[Staff]** `/admin` → **Organisations** → create `Acme Ltd`, give it a
   balance, add a membership for any patient → that patient's next consult
   says *"covered by your employer's health plan"*.
4. *Point out the cover order: employer float → sponsor wallet → self-pay.
   And red-flag emergencies (Scenario 4) are never gated behind payment.*
5. **[Doctor]** **Earnings** tab → the doctor's 65% share appears the moment
   a paid consult is concluded. *Point out: weekly automatic payouts —
   doctor loyalty is a product feature.*

---

## Scenario 9 — Offline (the low-bandwidth promise)

**Background.** A patient in a poor-coverage area fills the intake form and
the network dies exactly as they press submit. On every other platform,
that's an error and a lost patient.

**Role:** any patient

1. Home → **Talk to a doctor now** → fill in the complaint.
2. DevTools (F12) → **Network** tab → set **Offline**.
3. Submit → a calm green screen: *"Saved — you're offline right now. We'll
   send your consult request the moment your connection returns."*
4. Set the network back to **Online** → go Home → **Continue your consult**
   is there. The request sent itself.

---

## Scenario 10 — Push notifications

**Background.** A queued patient shouldn't have to stare at the screen.

**Roles:** a queued patient + a doctor

1. **[Patient]** Start a consult → on the queue screen, the browser asks for
   notification permission → **Allow**.
2. Switch to a different tab or window.
3. **[Doctor]** Accept the consult → a real system notification fires:
   *"Dr … has joined your consult."* Clicking it focuses the app.

---

## Scenario 11 — The backoffice & compliance (for the medical director)

**Background.** Everything the platform does is observable, auditable, and
NDPA-disciplined.

**Role:** Staff at `localhost:8000/admin` (TOTP required — by design)

1. **Consults board** — every consult from today's demo with live state
   badges (green in-consult, amber queued, red escalated). Read-only:
   clinical records are never edited from the backoffice.
2. **Doctors** — credentialing board; Dr Ngozi Eze's licence is deliberately
   seeded to expire in 30 days (amber warning). Expired licences are blocked
   from consulting automatically.
3. **Payments** — every transaction from Scenario 8; use **Refund** on one
   (a sponsored payment refunds back to the sponsor's wallet).
4. **Compliance → PHI access log** — who read which record, when, from where
   — including everything you did in this demo. **Consent ledger** — every
   grant and withdrawal as an append-only event. **Audit events** — every
   state transition and money movement.
5. *Point out: patients can export all their data (`/api/me/data-export`)
   and request erasure — identity removed, clinical record retained
   pseudonymised, per medical-records law.*

---

# Part B — Edge cases & failure behaviour

These are the scenarios that separate a demo from a product. Run them after
Part A (same seeded state), any order. Where a rule is time-based, an artisan
command triggers it on demand — run those from `apps/newco-health/apps/api`
with `PAO_DISABLE=1 /usr/local/opt/php@8.3/bin/php artisan <command>`.

---

## E1 — Two patients race for the same slot

**Background.** Two people tap the same 09:00 slot within seconds of each
other. Exactly one may win; the loser must get a human answer, not a crash.

**Roles:** Bisi (normal window) · Chuka (incognito)

1. Both windows: Home → **Book an appointment** → **Dr Amara** → same day →
   both select the **same time slot** (don't confirm yet).
2. **[Bisi]** Confirm → booked.
3. **[Chuka]** Confirm → a calm message: *"That time may have just been
   taken — please pick another slot."* The slot grid refreshes without it.
4. *Under the hood: a transactional lock plus (on Postgres) a database-level
   unique index — the double-booking is impossible, not just unlikely.*

## E2 — Cancelling too close to the appointment

**Background.** Cancellation inside 2 hours would strand the doctor's slot.

**Role:** Bisi

1. Bisi's seeded booking with Dr Tunde is ~24h away → **Cancel** works.
2. Book a slot for **today** within the next 2 hours (extend Dr Amara's
   availability first via the doctor's **Schedule** tab if needed) → try to
   cancel → refused: *"Bookings can only be cancelled up to 2 hours before
   the appointment."*
3. *Staff can still cancel it from the backoffice (Bookings → Cancel) —
   ops override is deliberate, and audited.*

## E3 — An unpaid booking hold expires

**Background.** With payments on, choosing a slot holds it for 15 minutes.
An abandoned checkout must free the slot for everyone else.

**Setup:** `PAYMENTS_REQUIRED=true` (see Scenario 8), then restart the API.

1. **[Chuka]** Book a slot → it holds at **Unpaid** on the Appointments page
   (don't pay). The slot has vanished for other patients.
2. Trigger the sweep as if 15 minutes passed — first backdate the hold:
   in `apps/api`: `php artisan tinker --execute="\App\Modules\Scheduling\Models\Booking::where('state','pending_payment')->update(['created_at' => now()->subMinutes(16)]);"`
   then `php artisan booking:send-reminders`.
3. The booking flips to **cancelled**; the slot is bookable again.

## E4 — A doctor with an expired licence

**Background.** MDCN compliance isn't a checkbox — the platform physically
prevents an unlicensed doctor from practising.

**Roles:** Staff · Dr Tunde

1. **[Staff]** `/admin` → **Doctors** → note Dr Ngozi Eze's amber licence
   warning. Edit **Dr Tunde** → set his licence expiry to yesterday → Save.
2. **[Dr Tunde]** Try to **Accept** a queued consult → refused with the
   eligibility message. He also disappears from patients' booking list.
3. **[Staff]** Restore his expiry date → everything works again.

## E5 — Snooping is impossible

**Background.** Patient A must never see patient B's consult — even with a
direct link.

**Roles:** two patients

1. **[Bisi]** Open her live consult and copy the URL
   (`/consult/01XY…`).
2. **[Chuka]** Paste that URL while signed in as himself → the thread refuses
   to load (403 from the API); nothing clinical renders.
3. *Every such attempt is recorded — and note what is NOT in the PHI access
   log: denied requests never appear as access.*

## E6 — Wrong codes and brute force

**Background.** OTP sign-in must fail safely.

**Role:** any phone number at `/login`

1. Enter a wrong 6-digit code → *"That code didn't match or has expired."*
2. A code stops working after 5 wrong attempts against it, and code
   *requests* are rate-limited per phone (5/hour) — spam **Send my code**
   and the API answers with a polite too-many-requests message.
3. *Note: `000000` works locally only because `OTP_TEST_CODE` is set — the
   bypass is hard-disabled in production builds.*

## E7 — Consent is a living switch, not a signature

**Background.** NDPA consent can be withdrawn at any moment, and the product
must honour it immediately.

**Roles:** Bisi · Ngozi (sponsor)

1. **[Ngozi]** Dashboard currently shows Mum's last-consult status.
2. **[Bisi]** Withdraw sharing — in her signed-in tab run in the console:
   `fetch('/api/consents',{method:'POST',headers:{'Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('newco.token')},body:JSON.stringify({kind:'sponsor_visibility',granted:false})})`
3. **[Ngozi]** Refresh → the status is gone; the card now says care details
   are private. The sponsorship (and her ability to pay) is untouched.
4. **[Staff]** Compliance → **Consent ledger** → the withdrawal is a new
   append-only event; nothing was edited or deleted.

## E8 — The sponsor wallet runs dry

**Background.** Cover must fail *gracefully* into self-pay, never block care.

**Setup:** payments on (E3 setup).

1. **[Ngozi]** Note the wallet balance. **[Staff]** (or by consults) drain it
   below ₦2,500 — e.g. refund nothing, just have Bisi consult repeatedly, or
   set it directly: **Payments → Organisations** analogue is the wallet;
   simplest is 3–4 consults by Bisi.
2. **[Bisi]** Start one more consult → this time she sees the normal
   **"Pay ₦2,500"** panel — the fall-through to self-pay, with the sponsor
   wallet left untouched.

## E9 — One prescription, one collection (and typos)

**Background.** Pickup codes are money-adjacent; they must be single-use and
typo-tolerant in UX.

**Role:** Pharmacy counter

1. Look up a nonsense code (`RX-XXXXXX99`) → *"No active prescription with
   that code"* — no information leaks about whether it ever existed.
2. Dispense a valid code, then try it again → refused (spent). The
   dispensing pharmacy is stamped on the record (visible in `/admin` →
   Prescriptions? → Payments? — see the prescription row's pharmacy).

## E10 — The 72-hour follow-up window closes itself

**Background.** Concluded consults must not stay writable forever.

1. Conclude any consult (Part A, Scenario 1).
2. Backdate it and run the sweep, in `apps/api`:
   `php artisan tinker --execute="\App\Modules\Consults\Models\Consult::where('state','concluded')->update(['concluded_at' => now()->subHours(80)]);"`
   then `php artisan consults:close-followups`.
3. The patient's thread shows no composer any more — the episode is closed
   (state `closed` on the admin board).

## E11 — Appointment reminders and no-shows

**Background.** Patients forget; the system doesn't.

1. With a confirmed booking in the future: run
   `php artisan booking:send-reminders` → watch the reminder SMS in
   `make sms` (24h and 1h reminders each send exactly once — run the command
   twice to prove no duplicates).
2. Backdate a confirmed booking to the past (tinker, as in E10) → run the
   command again → it flips to **no-show** on the admin Bookings board.

## E12 — Kill every other session

**Background.** A patient's phone is stolen.

**Role:** any patient, two devices (use normal + incognito as "devices")

1. Sign in on both windows with the same number.
2. In one window's console:
   `fetch('/api/me/sessions',{headers:{Authorization:'Bearer '+localStorage.getItem('newco.token')}}).then(r=>r.json()).then(console.log)`
   → both devices listed by user-agent, the current one flagged.
3. `fetch('/api/me/sessions/revoke-others',{method:'POST',headers:{Authorization:'Bearer '+localStorage.getItem('newco.token')}})`
   → the other window's next action fails with 401 — signed out remotely.

## E13 — Hostile uploads

**Background.** An image field is an attack surface.

**Role:** any patient in a live consult

1. Try attaching an `.svg` file via the camera button — the picker filters
   it; if forced via the API it is rejected (SVG is script-capable and never
   accepted as an image).
2. Attach a huge photo → it is compressed client-side to ≤200 KB before
   upload; the server also enforces size and type limits independently.

## E14 — Flags kill features instantly

**Background.** Anything patient-visible can be switched off in production
without a deploy.

1. In `apps/api`:
   `php artisan tinker --execute="app(\App\Modules\Compliance\Services\FeatureFlags::class)->set('video_consults', false);"`
2. Within a minute (cache TTL), the call buttons vanish from every live
   consult; the API refuses call sessions with a human message.
3. Switch it back on the same way — buttons return. No deploy, no restart.

## E15 — Erasure is real (destructive — run last)

**Background.** A patient invokes their NDPA right to be forgotten.

**Role:** any disposable patient (e.g. `805 555 6666`)

1. Sign in, have a consult or two (or reuse an existing test patient).
2. Export first — visit `localhost:5173/api/me/data-export` → a JSON file of
   everything the platform holds on them downloads.
3. In the console:
   `fetch('/api/me/erase',{method:'POST',headers:{'Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('newco.token')},body:JSON.stringify({confirm:'DELETE MY ACCOUNT'})})`
4. Every session dies instantly; the phone number no longer signs in; in
   `/admin` the consults survive — attributed to *"Deleted User"* —
   because clinical records must be retained, pseudonymised, under
   medical-records law. The dependants' names read *"Redacted"*.

---

## After the demo

```bash
make fresh    # resets the world for the next run
```

Feedback from the doctor — especially on the consult workspace, notes,
prescribing, and the queue — goes straight into the backlog. This demo runs
with zero external accounts; every simulated service (payments, SMS,
WhatsApp, video) becomes real by adding its API key, with no code changes.
