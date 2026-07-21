# Nigeria Telehealth — UI/UX & Design System Plan

> Companion to `nigeria-telehealth-startup-plan.md` (business) and `nigeria-telehealth-dev-plan.md` (engineering).
> Prepared: 21 July 2026 · Status: Draft v1
> Scope: **every surface** — patient PWA, doctor console, sponsor portal, Filament backoffice.

---

## 1. What "World-Class" Means Here

For our users, world-class is **not** decoration. It is:

| Audience | World-class means |
|---|---|
| Patient on a ₦80k Android, 3G, first telehealth use | Never lost, never waiting without explanation, never blamed for a network failure; finishes a consult feeling *cared for* |
| Doctor between hospital shifts | Zero wasted clicks; notes + prescribing at typing speed; feels like a professional instrument, not a form |
| Diaspora sponsor in London at 11pm | Instant reassurance: mum's care status legible in five seconds, payment in three taps, receipts that look bank-grade |
| Ops staff in the backoffice | Dense, scannable, keyboard-driven; the queue state of the whole platform in one glance |

**North-star design metrics** (reviewed monthly, same table as the KPI board):
consult completion rate ≥ 90% · time-to-first-doctor-message < 10 min · intake abandonment < 15% · sponsor checkout completion ≥ 85% · doctor consult wrap-up (notes + prescription) ≤ 90 s · System Usability Scale ≥ 80 on quarterly tests.

**The standard we hold ourselves to:** OPay/Moniepoint-level clarity (the apps our users already trust with money), WhatsApp-level familiarity (the interaction model they already know), NHS-app-level clinical trustworthiness.

---

## 2. Design Principles (ordered; earlier wins conflicts)

1. **Calm under bad conditions.** The unhappy paths — dropped video, queued sync, failed payment — get *more* design attention than happy paths. A network failure must read as "we've got you" (auto-downgrade, saved state), never as an error the patient caused.
2. **One thing per screen.** Patients see a single primary action per view. No dashboards for patients — a person with malaria symptoms gets a "Start a consult" button, not a control centre.
3. **Familiar beats novel.** Chat looks and behaves like WhatsApp (bubbles, ticks, voice notes). Payment looks like Paystack checkout. We spend our novelty budget on nothing — recognition is trust.
4. **Legible to everyone.** Design for the least-experienced user at arm's length in sunlight: 16px+ body, high contrast, plain words ("Talk to a doctor", never "Initiate teleconsultation").
5. **Speed is a design feature.** Skeletons over spinners, optimistic UI on every send, instant tap feedback (<100 ms perceived). Performance budgets (dev plan §8) are design constraints, not engineering trivia.
6. **Trust is designed, not claimed.** Doctor's real face, name, MDCN number on every consult; prices shown before commitment, receipts after; NDPC/security signals at the exact moments of hesitation (payment, personal data, camera).
7. **Density for professionals.** Doctors and staff get the *opposite* of the patient app: information-dense, keyboard-first, minimal chrome. Same tokens, different dial.

---

## 3. Design System — "Ruby" *(working name)*

One token source feeding all surfaces; tokens are the single contract between Figma and code.

### 3.1 Foundations

- **Tokens** (W3C design-token JSON in `packages/config/tokens/`): colour, type scale, spacing (4px base), radii, elevation, motion durations. Exported to Tailwind preset (SPA) + Filament theme (backoffice). One change propagates everywhere.
- **Colour:** one brand hue + a warm neutral ramp. Semantic-first naming (`surface`, `ink`, `positive`, `caution`, `critical`, `info`) — clinical states map to semantics, never raw hues. All pairs pass WCAG AA at minimum; core flows target AAA. Dark mode: patient app ships light-only at MVP (sunlight legibility priority); doctor console + backoffice ship dark mode day one (night-shift doctors asked every competitor for this — cheap differentiator).
- **Typography:** system font stack only (zero font bytes — loading budget). Scale: 16/18/20/24/30/38. Patient surfaces max two sizes per screen.
- **Iconography:** single consistent set (Lucide), outline style, 24px grid, always paired with labels on patient surfaces — never icon-only for primary actions.
- **Motion:** 150–250 ms, ease-out, purposeful only (state change, spatial continuity). Respects `prefers-reduced-motion`. No decorative animation on patient surfaces — it costs battery and CPU on low-end devices.

### 3.2 Component library

- **SPA:** Radix primitives + Tailwind, styled by our tokens (shadcn-style, vendored not installed — we own the code). Storybook as the living catalogue; every component ships with all its states documented.
- **Backoffice:** Filament themed via the same tokens (CSS variables). We do not fight Filament's layout system — we restyle it. Custom Filament components only where ops efficiency demands (queue board, consult timeline).
- **Component inventory (MVP):** buttons, inputs (phone-first with +234 mask, OTP boxes), chat thread (bubbles, image, voice note, prescription card, system events), consult status banner, doctor identity card, queue position indicator, connection-quality pill, payment sheet, receipt, empty/error/offline states, skeletons, bottom navigation, PWA install prompt.

### 3.3 The states doctrine (where world-class is won or lost)

Every screen is designed in **six states before visual polish begins**: loading (skeleton) · empty (helpful, next-action) · partial/offline (cached data + sync badge) · error (recovery action, never a dead end) · success · degraded (low-bandwidth variant). A screen PR without all six is incomplete — enforced in design review and Storybook.

---

## 4. Signature Experiences (the flows that define the product)

### 4.1 Patient: symptom → doctor (the golden path)

- **Entry:** one screen, one button. Returning users see "Continue your consult" if a thread is open.
- **Intake:** conversational stepper (one question per screen, big tap targets, progress dots), body-map picker for symptoms, photo attach with auto-compression. Fully usable offline; submits when signal returns — with an explicit "Saved — we'll send when you're back online" state.
- **Queue:** live position + honest wait estimate + "we'll notify you" (leave the app, get push/SMS). Never a silent spinner.
- **The consult thread** is the product's centrepiece: WhatsApp-familiar chat; doctor identity card pinned at top (photo, name, MDCN licence); voice notes first-class both directions; "upgrade to voice/video" appears **only** when the connection-quality check passes; modality downgrades announced by a gentle system message in-thread ("Network dipped — continuing by voice"), never a modal, never an error.
- **Close:** prescription arrives as a card in-thread (pharmacy pickup code + map or delivery status), plain-language care summary, 72h follow-up window visibly open.

### 4.2 Doctor console (SPA, desktop-first + mobile-capable)

- Three-pane consult workspace: queue | active thread | notes+prescribe. No navigation during a consult — everything reachable without leaving the patient.
- Keyboard-first: `/` templates for common conditions (malaria, URTI, hypertension follow-up), tab-through SOAP-lite fields, formulary autocomplete with dose defaults, one-key consult conclusion.
- Earnings always one click away: today's consults, running total, next payout date — the retention screen, designed with the same care as patient surfaces.
- Dark mode default option; dense mode toggle.

### 4.3 Sponsor portal (the emotional surface)

- Care dashboard leads with the human: beneficiary's name and photo, last check-in, next scheduled consult, medication status — *reassurance in five seconds*, transactions second.
- Consent-gated clinical detail behind an explicit "Mum has shared her care details with you" panel — privacy design that builds family trust rather than breaking it.
- Checkout: three taps, local currency display (£/$ with ₦ equivalence), bank-grade receipts, plan management without dark patterns (cancel is one honest click).

### 4.4 Backoffice (Filament)

- **Live queue board** as the home screen: every open consult, wait time, doctor load, network-quality alerts — the mission-control view, colour-coded by semantic tokens.
- Command palette (Filament native) + keyboard shortcuts for the ten most frequent ops actions.
- Consult audit view: full thread + state-machine timeline + PHI-access trail in one scroll — designed for the medical director's 15-minute daily sample review.
- Dense tables, sticky filters, saved views per role. Zero decoration; ops speed is the aesthetic.

---

## 5. Accessibility & Inclusion (non-negotiable, CI-checked)

- **WCAG 2.2 AA minimum** across all surfaces; automated axe checks in Playwright CI + manual screen-reader pass (TalkBack — the Android reality) each release.
- Tap targets ≥ 48px; thumb-zone placement for primary actions (bottom third); full functionality one-handed.
- Language: English + Nigerian Pidgin at launch (UI copy professionally written in both, not machine-translated); Hausa in Phase 2 with RTL-readiness checked (Ajami users read Latin-script Hausa — no RTL needed, but verify with northern users, not assumptions).
- Reading level: patient copy at ~12-year-old reading level; every clinical term paired with plain language ("Hypertension (high blood pressure)").
- Voice notes as an *inclusion feature*: low-literacy users can conduct their entire consult side by voice — design flows so typing is never mandatory for patients.

---

## 6. Content Design & Tone

- Voice: warm, direct, competent — "a good nurse, not a brochure". British English spelling; Nigerian English vocabulary where it's the natural term.
- A `copy/` catalogue in the repo: every string ID'd, both languages, reviewed by the medical director for clinical accuracy and by a Nigerian copywriter for register. No developer-written patient-facing copy ships unreviewed.
- Error messages follow the pattern: what happened → it's handled/not your fault → the one next step. ("Payment didn't go through. You haven't been charged. Try again or pay by transfer.")
- Money is always explicit: full price before any commitment, ₦ symbol, no hidden fees, refund policy one tap from every payment screen.

---

## 7. Design Process & Ops

- **Team:** one senior product designer (contract-to-perm, Nigerian-market experience required) embedded from Phase 1 sprint 1; founder-level design review weekly. Budget for a design-research assistant for field testing.
- **Research cadence:** 5 usability sessions every two weeks (rotating: Lagos mass-market users, doctors, diaspora via remote) on real mid-range devices with throttled connections — the test bench mirrors dev plan §8's real-device check. Findings feed the next sprint directly.
- **Figma:** one library file = one token source = Storybook parity. Components promoted to the library only after shipping. Drift between Figma and Storybook is a bug.
- **Definition of done (design):** all six states designed · both languages fit without truncation · AA contrast verified · 3G prototype walkthrough done · thumb-reach checked on a 5-inch screen.
- **Design QA in every PR:** screenshots/recordings against the Figma spec; visual regression via Playwright screenshots on the golden journeys.
- **Quarterly:** SUS survey + full accessibility audit + competitor UX teardown (are we still clearly better than Reliance/CribMD's app experience? — that gap is part of the moat).

---

## 8. Phasing (aligned to dev plan §13)

| Phase | Design deliverables |
|---|---|
| **0 (validation)** | Brand identity (name, logo, tokens v1) · landing page · WhatsApp templates tone · patient golden-path prototype tested with 10 users **before** sprint 1 |
| **1 (MVP)** | Sprint 1: tokens + component library seed + Storybook · Sprints 2–8: each feature ships with its six states; sprint 6 backoffice theme; sprint 7 sponsor portal; sprint 8 full a11y audit + polish pass |
| **2** | Hausa localisation UX + northern-user field research · chronic-care programme surfaces · dark mode for patient app (re-evaluate) · TWA store assets |
| **3** | USSD/IVR flow design (voice-first UX discipline) · design-system v2 review |

---

## 9. Anti-Goals (what world-class is NOT here)

- No custom illustration systems, 3D, or animation flourishes — budget goes to states, copy, and research.
- No web fonts, no hero images on patient flows, nothing that costs bytes for beauty.
- No dashboardy patient home screen. No gamification of health. No dark patterns anywhere (retention comes from care quality, not tricks).
- No design that only works on the designer's iPhone 15 — if it hasn't been seen on a ₦80k Android on 3G, it isn't designed yet.
