# Nigeria Virtual Telehealth — Comprehensive Startup Plan

> Working title: **"NewCo Health"** (name TBD — check CAC availability + .ng/.com.ng domain before committing)
> Prepared: 21 July 2026 · Status: Draft v1.1 — platform strategy revised to PWA-first; see companion `nigeria-telehealth-dev-plan.md`
> Prior art: CLEA platform (Malaysia, COVID-era) — used as reference architecture only, not a starter codebase.

---

## 1. Executive Summary

We are building a **low-bandwidth-first virtual telehealth platform for Nigeria**: patients consult licensed Nigerian doctors via chat, voice, and adaptive video; prescriptions are fulfilled through partner pharmacies; staff and doctors operate through a web backoffice. We deliberately invert the usual telehealth build: **chat and voice are the primary modalities, video is the upgrade** — because ~80% of African teleconsults happen by voice/chat and ~40% of initiated rural video sessions fail to complete.

Three systems will be built:

| # | System | Users | Platform |
|---|--------|-------|----------|
| 1 | **Patient app** | Patients + diaspora sponsors | **Installable PWA** (Android-first; no app-store approval flow), WhatsApp entry |
| 2 | **Doctor app** | Consulting doctors | **PWA** (same Next.js codebase) + desktop web console |
| 3 | **Backoffice** | Ops staff, care coordinators, pharmacy fulfilment, finance, compliance, medical director; doctors' desktop console | Web (Next.js) |

The market has no scaled winner: $21.8M raised across 38 Nigerian telemedicine startups since 2019, willingness-to-use at 96%, HMO integration passing 60% of providers — yet the consumer leaders still run on thin products (Ruby Doctors literally operates on WordPress + Calendly + WhatsApp). The moat we will build is **not the app**: it is (a) a differentiated wedge (Section 4), (b) doctor-side loyalty through fast reliable payouts, and (c) regulatory/data-residency compliance done properly from day one.

---

## 2. Market Context (research summary, July 2026)

- **Size / growth:** Nigerian digital health revenue projected ₦185B+ in 2026 (≈$770M market in 2024). Telemedicine = 35% of healthtech startup activity.
- **Behaviour shift:** fintech (OPay, Moniepoint, Paystack) normalised the phone as an interface for serious services; app-based transaction trust is no longer the barrier it was pre-2020.
- **Constraints that shape product:**
  - Doctor density 3.8 per 10,000 (vs India 7.3) — worsened by *japa* emigration. Doctor supply is a real constraint **and** a recruitment opportunity: remaining doctors actively seek supplemental income (telemedicine adds ₦200k–₦800k/month; locum shifts pay ₦30k–₦80k).
  - Connectivity: >70% of rural users report inconsistent network; 40% of initiated telemedicine sessions fail. Voice (80%) and chat dominate as actual consult modalities.
  - Payments: thin consumer wallets. Competitors price at ₦1,000/consult (CribMD) up to ₦2,500–₦5,000/year subscriptions (Mobihealth). B2B (employers/HMOs) is where the money is.
  - **NHIA has no telemedicine reimbursement provisions yet** — do not build a plan that depends on national insurance rails.
- **Proven playbook:** Reliance Health used telemedicine as the entry point, then expanded to insurance + clinics. "Telemedicine as front door, not end goal."

---

## 3. Competitor Audit

### 3.1 B2B / insurance-leaning players (deep audit, July 2026)

| Company | Model & pricing | Funding | Traction | Key weakness |
|---|---|---|---|---|
| **Reliance Health** | Vertically integrated payer-provider (HMO + own clinics + telemedicine); retail ₦3,500–₦13,500/mo, core is employer/group | **~$48M** ($40M Series B, General Atlantic 2022; YC) | 150k+ enrollees, ~1,200 business clients, ~15 own clinics, 3,800+ partners | **Laid off ~106 staff (Jul 2025) chasing break-even**; app ~3.6/5 with recurring **medication-delivery failure** complaints; telemedicine gated behind insurance; excludes uninsured cash payers |
| **WellaHealth** | Pharmacy-infrastructure SaaS + embedded micro-insurance from **₦1,700/quarter** | ~$1.3M (grant/accelerator-heavy; figures vary) | **2,790 pharmacies onboarded (~2,000 active weekly, 36 states)**, ~₦1B transactions | Thin capital; consult layer light and pharmacy-mediated; weak consumer brand by design |
| **Clafiya** | Community-health-worker home care + virtual consults; B2C/SME/HMO/schools | $610K pre-seed (Jul 2023; later $3.8M seed unverified) | WhatsApp-first via **Aproko Doctor (AwaDoc)** partnership | Smallest capital base; CHW ops-heavy; traction undisclosed |
| **mDoc (CompleteHealth)** | Chronic-disease self-care coaching (diabetes/hypertension); B2B/B2G/DTC; **USSD + AI coach + community hubs** | ~$248K, nearly all grants | **130k–150k members, 86% women, majority <$2/day**; 2.28M interactions | Grant-dependent; coaching-led not doctor-consult-led; not built for acute on-demand care |
| **Helium Health** | Provider-side EMR/HMIS (HeliumOS) + facility lending (HeliumCredit); HeliumDoc booking is secondary | **~$42M** ($30M Series B, AXA IM Alts 2023) | 10,000+ health workers, 1M+ patients on EMR — widest in West Africa | Not a consumer telehealth company; no low-bandwidth consumer channel |

**Two strategic reads from this side of the market:**
1. **The direct-to-consumer, on-demand, acute-care consult as the core product is an open lane.** Reliance gates it behind insurance enrolment; mDoc coaches rather than consults; Helium is B2B rails; Clafiya leads with in-person CHWs. Nobody owns "see a doctor now, any network, no insurance required."
2. **WellaHealth and Helium are rails, not just rivals.** A 2,790-pharmacy fulfilment network and the region's widest EMR base are partnership targets — integrating beats rebuilding, and Reliance's #1 complaint category (failed in-house medication delivery) is a warning against vertical over-reach.

### 3.2 Consumer telemedicine players (deep audit, July 2026)

Sector frame: telemedicine is ~35% of Nigerian healthtech startups but historically only ~5% of funding — the most crowded, least-capitalised subsector, with **no scaled winner**. A peer-reviewed barriers study puts session non-completion at ~40%; >70% of rural users report inconsistent networks.

| Company | Model & pricing | Funding | Traction | Key weakness |
|---|---|---|---|---|
| **Mobihealth** | Subscription ₦2,500–₦5,000/**yr** (donor-subsidised); telehealth cabins + mobile clinics | Best-capitalised via grants/DFIs: $1M USTDA, Afreximbank facility (→ ~$65M ambition), ~$1M equity 2024 | 150k+ consults claimed | Grant-dependent economics; fragmented multi-app portfolio; London-centric |
| **CribMD** | Subs ₦3,000–₦19,000/mo incl. **house calls**; ~₦1,000 one-time consult | ~$3.2M (Sputnik ATX, Norrsken; seed May 2021 — nothing since) | Claims 36-state coverage | **Diaspora payer plan promised in-app but never shipped** (app-review complaints); stalled execution signal |
| **Doctoora** | Prepaid membership + 20+ physical centres (Lagos/Abuja) | ~$25K pre-seed + grant — undercapitalised | 20+ centres | Capital-heavy physical model on tiny funding; slow momentum |
| **iWello** | **₦300/consult**, micro-plans from ₦500; app + **WhatsApp consults**; diaspora sponsorship of consults in model | None found (bootstrapped) | ~140 clients, **14 doctors** (2025) | Philosophically closest to low-bandwidth-first — proves demand, shows the execution/capital gap |
| **Tremendoc** | ₦1,000–₦3,000/mo subs + HMO/corporate tier; therapists, fertility | None announced | n/a | Launch-era payment friction (only 2 banks supported); leans on HMO channel, weak direct-consumer pull |
| **DoctorCare247** | Pay-per-visit, video premium-priced; diaspora-*doctor* narrative | None found | n/a | Opaque pricing (in-app only), thin public data, low momentum |
| **KompleteCare** | Marketplace: from **₦4,500/consult**; Plus ₦7,500/mo (5 consults); free HMO tier; hospital booking + labs | None disclosed | 750+ doctors claimed | Most expensive per-consult; breadth over depth; Abuja-centric |

Full source-cited profiles preserved in research notes (Techpoint, TechCabal Jul 2026, Crunchbase, PMC barriers study, company pricing pages).

### 3.3 Ruby Doctors (already audited directly)

- **What they are:** operations-led business, not a tech platform. WordPress site; consult booking via **Calendly**; home care/specialist/prescriptions via **WhatsApp deep-links**; stock WordPress shop.
- **Real assets:** claimed 200+ medical/IT/support staff, **3,000+ partner pharmacies across 36 states**, NDPC certification (advertised prominently).
- **Weaknesses observed first-hand:** no product (no app, no queue, no records portal); site intermittently redirected to a spam Telegram bot during audit — symptom of a compromised WordPress install; serious trust/security liability for a health brand.
- **Lesson:** demand exists at a service level even with near-zero tech; pharmacy network + staffing is their moat, UX is their exposed flank.

### 3.4 Gap map and moat conclusions (both audits complete)

**Occupied positions — do not attack head-on:**
- Employer/HMO integrated care → Reliance Health ($48M, but margin-stressed)
- Pharmacy fulfilment infrastructure → WellaHealth (partner with them instead)
- Provider EMR/facility software → Helium Health
- Chronic-disease *coaching* for low-income women → mDoc (grant-funded niche; overlaps our chronic-care wedge only partially — see §4)

**Confirmed vacant positions (agreed by both audit tracks independently):**
- **DTC on-demand acute care, insurance-optional, any-network** — the core lane we take
- **Low-bandwidth-first real-time consults** — only mDoc (USSD coaching) and Clafiya/iWello (WhatsApp, sub-scale) gesture at it; no one leads with it
- **Diaspora-as-payer** — attempted (CribMD, promised and never shipped), unowned
- **Northern states + Hausa** — no player found on either audit track
- **Doctor-led longitudinal chronic care** (vs mDoc's coach-led model) — open
- The **uninsured cash-paying patient** — structurally ignored by the funded players

**Failure patterns the audits confirm** — our plan must invert each one:
1. Pure telemedicine is a feature, not a business → we bundle fulfilment (pharmacy, chronic care) from Phase 1.
2. Video-first UX collides with a ~40% session-failure reality → our service ladder is chat/voice-first (§6).
3. Payment friction kills conversion (Tremendoc's 2-bank launch, DoctorCare247's hidden pricing) → dual PSP, transfer/USSD rails, published prices.
4. Thin doctor supply punishes availability (iWello: 14 doctors) → doctor-payout moat + 2× roster over-recruitment (§10, §14).
5. Diaspora is talked about, never productised — **CribMD promised a diaspora plan in-app and never shipped it** → strongest external validation of our primary wedge; execution, not ideation, is the gap.
6. Brand fragmentation (Mobihealth's five apps, Ruby's two domains) → one brand, one app per audience.
7. Vertical over-reach on delivery (Reliance's in-house medication delivery is its #1 complaint source) → pickup-code-first, partner logistics second.

---

## 4. Differentiation & Moat Strategy

> Final selection to be confirmed against the completed competitor audit; current thesis below.

**Principle: be slightly different, not exotic.** The consult itself is a commodity; differentiation must come from *who pays*, *how care continues after the consult*, and *why doctors stay*.

### Wedge candidates (ranked, current thesis)

1. **Diaspora-sponsored family care (primary wedge).**
   Diaspora Nigerians already spend $300–$600 per family health emergency plus $25–$45 transfer fees, and complain that remitted cash gets diverted. Existing diaspora products are HMO *insurance* plans (₦20k–₦60k/yr via Novo, Mediplan, AXA, Reliance) — none are a **telehealth-first "care account"** where a sponsor in London/Houston pays in GBP/USD, and mum in Ibadan gets unlimited chat triage + scheduled video/voice consults + medication delivery, with the sponsor seeing a care dashboard (with the patient's consent).
   *Why it's a moat:* USD/GBP revenue against naira costs (FX hedge), higher willingness-to-pay, emotional retention, and payment friction solved by Stripe/Paystack international cards — while local-only competitors fight over thin naira wallets.
   *Audit validation:* CribMD publicly promised a US/diaspora plan in its app and **never shipped it** (a recurring app-review complaint); iWello supports third-party-sponsored consults at micro-scale. The wedge is repeatedly attempted, never executed — the gap is operational, not conceptual.

2. **Chronic-care programmes as the retention engine.**
   Hypertension + diabetes management: monthly subscription including scheduled check-ins (chat/voice), medication refill delivery via pharmacy partners, BP/glucose logging (works offline, syncs later). Recurring revenue vs the episodic ₦1,000-consult trap; pairs perfectly with the diaspora wedge (elderly parents = chronic patients).
   *Positioning vs mDoc:* mDoc owns chronic-disease **coaching** (CHW/AI-coach-led, grant-funded, 86% low-income women). We are **doctor-led medical management** — consults, titration, e-prescriptions, refill logistics — a different clinical proposition at a different price point; avoid marketing language that collides with their coaching niche.

3. **Doctor-side moat: fastest, most reliable payouts in the market.**
   Weekly (eventually instant) settlement to doctors via Paystack transfers, transparent per-consult earnings screen, CPD/MDCN-renewal support. In a *japa* economy doctors are the scarce side of the marketplace — win their loyalty and competitor supply dries up.

4. **True low-bandwidth service ladder** (Section 6) — everyone claims it; almost nobody engineers for it. This is a product-quality differentiator, not a standalone moat, but it decides who wins outside Lagos/Abuja.

### Explicitly NOT doing (v1)
- Not an HMO/insurance play (capital + licence intensive; Reliance owns it).
- Not building our own clinics/labs (asset-light; partner instead).
- Not USSD-native consultations at launch (USSD for reminders/booking triggers only — full USSD care flows are a Phase 3 experiment).
- Not a marketplace of every specialty — launch = general practice + 2–3 high-demand tracks (women's health, mental health, chronic care).

---

## 5. Product Suite & System Design

### 5.1 Patient app (Android-first, installable PWA)

Core journeys:
1. **Onboard:** phone number + SMS OTP (Termii); optional NIN capture later for identity-verified records. Profile supports **dependants** (child, elderly parent) — critical for the diaspora wedge.
2. **Get care:** symptom intake form → triage → join doctor queue (on-demand) or book a slot. Modality selected by connection quality: chat → voice → video.
3. **After care:** e-prescription (PDF + in-app), pharmacy pickup code or delivery, follow-up thread stays open 72h, records vault.
4. **Pay:** Paystack (cards, bank transfer, USSD push, OPay/PalmPay), wallet top-up, or "sponsored by" (diaspora account covers it).

Low-end reality targets: first-load ≤ 200 KB JS (no 25 MB APK to download at all — a PWA advantage on costly data), runs on Android 8+/2 GB RAM devices in Chrome, all lists work offline-first with sync, aggressive image compression, no video autoplay anywhere.

### 5.2 Doctor app + web console

- **Mobile (PWA, shared Next.js codebase):** go online/offline toggle, queue view, accept consult, chat/voice/video console, structured consult notes (SOAP-lite), e-prescribe from a Nigerian drug formulary, earnings dashboard with payout history.
- **Web console (part of backoffice):** same consult workflow on desktop (doctors strongly prefer typing notes on a keyboard), schedule management, patient history view.
- Compliance guardrails baked in: MDCN licence number + annual practising licence expiry tracked; auto-suspension on expiry; consult recordings/notes retention per NDPA.

### 5.3 Backoffice (web, role-based)

| Role | Capabilities |
|------|--------------|
| **Ops / care coordinators** | Live queue monitoring, manual matching, escalation handling, WhatsApp/phone follow-ups, refunds |
| **Pharmacy fulfilment** | Prescription routing to partner pharmacies, delivery tracking, dispute resolution |
| **Medical director / clinical governance** | Consult audit sampling, protocol management, doctor onboarding/credential verification, incident review |
| **Finance** | Doctor payout runs, reconciliation (Paystack/Flutterwave), diaspora FX reporting |
| **Compliance/DPO** | NDPC audit trail, consent logs, data-subject requests, breach workflow (72h notification duty) |
| **Admin** | User management, pricing config, content (formulary, triage flows), analytics |

### 5.4 Channel layer (the "front doors")

- **WhatsApp Business API** — marketing entry + booking + reminders + async follow-ups. (Ruby Doctors proves WhatsApp *is* the market's comfort zone; we keep the clinical record in our backend, unlike them.)
- **SMS (Termii)** — OTP, appointment reminders, prescription-ready alerts, chronic-care nudges.
- **Voice fallback** — coordinator-initiated ordinary phone call when data fails mid-consult (the consult must never dead-end).
- **USSD (Phase 3 experiment)** — booking trigger + balance check on shared shortcode (Africa's Talking / local aggregator).

---

## 6. Low-Bandwidth Engineering Doctrine

The **service ladder** — every consult degrades gracefully, never fails outright:

```
Video (WebRTC, adaptive)        — good 3G/4G/WiFi
  ↓ auto-downgrade
Voice-only over same session    — weak data
  ↓ auto-downgrade
Async chat (store-and-forward)  — intermittent data
  ↓ fallback
PSTN phone call by coordinator  — no data at all
```

Rules:
1. **Chat transcripts are the canonical record**; voice/video are enhancements layered on a persistent thread.
2. Start every video session at low resolution and step **up** on measured throughput, never down from HD.
3. All queues/writes are offline-tolerant: patient can complete intake with zero connectivity; submission syncs when signal returns.
4. Text + compressed images (wound photos, drug packs, test results) cover a large share of clinical need — first-class support, EXIF-stripped, ≤ 200 KB uploads.
5. Payment must survive disconnection: bank-transfer references and USSD push don't require a live session.
6. Measure and publish an internal **Consult Completion Rate** by state/network — the metric competitors ignore (industry baseline: ~60% completion in rural areas; our target ≥ 90%).

---

## 7. Nigerian Localisation

| Dimension | Decision |
|----------|----------|
| **Currency** | NGN everywhere patient-facing; USD/GBP/CAD/EUR checkout for diaspora sponsors |
| **Payments** | Paystack primary (cards, transfer, USSD, mobile money); Flutterwave secondary/failover; Stripe only for diaspora foreign-currency checkout |
| **Phone/identity** | +234 formats via libphonenumber; SMS OTP (Termii); NIN optional-then-encouraged; no BVN collection (avoid fintech-grade scrutiny we don't need) |
| **Languages** | Launch: English + Nigerian Pidgin UI strings. Phase 2: Hausa (northern expansion is an underserved wedge), then Yoruba, Igbo. i18n scaffolding from day one (i18next — same as CLEA) |
| **Naming/copy** | British English spelling throughout product and codebase (licence, programme, centre) — matches Nigerian English |
| **Clinical** | Nigerian Essential Medicines List as formulary base; malaria/typhoid/URTI-heavy triage protocols; MDCN 2020 telemedicine guideline consent flow before first consult |
| **Data residency** | Health data = NITDA National Cloud Policy 2025 **Level 3 (sensitive)** → host primary PHI in-country (Section 8); NDPC registration as data controller; local DPO appointed |
| **Trust signals** | Display MDCN licence number on every doctor profile; NDPC certification badge (Ruby Doctors advertises theirs for a reason); doctor photo + real name |

---

## 8. Technical Architecture

### 8.1 Stack (chosen for team continuity + hiring market)

| Layer | Choice | Rationale |
|-------|--------|-----------|
| Backend API | **Laravel 13 (PHP 8.5)** modular monolith | Team's CLEA experience is PHP; Laravel talent is abundant and affordable in Nigeria; monolith > microservices at this stage |
| DB | PostgreSQL 18 + Redis | Consult/queue state in Redis; durable records in Postgres; field-level encryption for PHI |
| Realtime | Laravel Reverb (WebSockets) | Queue position, chat delivery, call signalling |
| Video/voice | **Daily.co** (proven in CLEA — port the room/token orchestration pattern from `clea-api/DailyCoController.php`) | Known-good; revisit 100ms/Agora at scale for cost + Lagos edge PoPs |
| Patient/doctor apps | **PWA-first (Vite + React SPA, static files)** — installable web apps, zero app-store approval flow, no Node server in production; Android Play-Store presence later via TWA wrapper (one-time review, listing only) | Revised from React Native, then from Next.js (SSR buys nothing behind a login) — see `nigeria-telehealth-dev-plan.md` §2–3; CLEA video-call lifecycle kept as reference for reconnect edge cases |
| Backoffice | **Filament (inside the Laravel app)** | Staff CRUD consoles nearly free; removes an entire deployable; zero JS build |
| Push | FCM primary (OneSignal optional) | Android-first market |
| SMS/OTP | Termii (Nigerian rates, local senders IDs) | |
| Hosting | **Primary PHI in Nigeria** (Rack Centre / MainOne-Equinix Lagos, or Nobus/Layer3 cloud) + AWS Cape Town (af-south-1) for stateless/static workloads; CDN via Cloudflare Lagos PoP | NITDA Level 3 classification; also lower latency than EU regions |
| Observability | Sentry + uptime probes **from Nigerian networks** (MTN/Glo/Airtel vantage points), not just US probes | |

### 8.2 What we mine from CLEA (and nothing else)

| Asset | Location | Action |
|-------|----------|--------|
| Daily.co room/token orchestration | `clea-api/app/Http/Controllers/DailyCoController.php` | Port to new Laravel backend, de-hardcode domains |
| RN video-call lifecycle handling | `clea/src/screens/vd/video-call/index.js` | Use as reference for reconnect/temp-quit edge cases |
| Queue/case domain model | `clea-api/.../VirtualDoctorController.php` (find-a-doctor, addToQueue, queue position, case-per-consult) + e-prescription PDF flow | Re-implement schema/flow cleanly |
| **Everything else** | OpenCart backend, VPCR/RTK COVID flows, Stripe/MYR/Malay localisation, superseded repos | Do **not** carry over |

### 8.3 Security & compliance architecture

- Consent ledger (immutable log of every consent grant/withdrawal — NDPA requirement).
- Sponsor/patient data separation: diaspora sponsor sees billing + high-level care status only; clinical detail requires explicit patient consent toggle.
- Audit trail on every PHI read (who/when/why) — feeds the compliance console.
- Breach playbook wired to the 72-hour NDPC notification duty.
- Backups encrypted, stored in-country; cross-border transfer register maintained by DPO.

---

## 9. Regulatory & Compliance Checklist

| # | Item | Authority | When |
|---|------|-----------|------|
| 1 | Incorporate Ltd | CAC | Month 0 |
| 2 | NIPC registration + business permit (if any foreign shareholding) | NIPC / Min. of Interior | Month 0–1 |
| 3 | Register as data controller; appoint DPO; DPIA | NDPC (NDPA 2023) | Month 1–2 |
| 4 | Health facility registration (Lagos: **HEFAMAA**, annual renewal) | State | Month 1–3 |
| 5 | Verify every doctor: MDCN registration + current annual practising licence | MDCN | Continuous |
| 6 | Consent + records per MDCN 2020 telemedicine guidelines & Code of Ethics G.22 | MDCN | Built into product |
| 7 | Pharmacy partners hold PCN licences; prescription-only medicines dispensed against valid e-prescription | PCN | Partner onboarding |
| 8 | NOTAP registration for any foreign technology-transfer agreements | NOTAP | If applicable |
| 9 | Annual filings, FIRS tax, licence renewals calendar | CAC/FIRS | Continuous |
| 10 | Watch NHIA for telemedicine reimbursement rules (none today — upside optionality) | NHIA | Monitor |

Engage a Lagos health-regulatory firm in Month 0 — this is a known, navigable checklist, not an open legal question. Penalties for NDPA breaches reach ₦10M or 2% of gross revenue.

---

## 10. Clinical Operations

- **Medical Director (hire #1 on clinical side):** owns protocols, doctor credentialing, consult audits, HEFAMAA relationship.
- **Doctor recruitment:** target moonlighting hospital doctors (supplement salaries of ₦350k–₦800k). Offer: flexible shifts, per-consult fee ₦700–₦1,500 (60–70% of consult price), **weekly payouts**, zero admin. Start with 15–25 vetted GPs covering a 7am–11pm roster; 24/7 once volume justifies.
- **Triage & escalation:** red-flag symptom routing to "go to facility now" advice + partner-facility directory; never let the platform sit on emergencies (MDCN liability + patient safety).
- **Pharmacy fulfilment:** start with 30–50 PCN-licensed pharmacies in Lagos/Abuja (chains: HealthPlus, MedPlus + independents), pickup-code model first, delivery via Kwik/Gokada partners second. (Ruby Doctors' 3,000-pharmacy claim shows how big this network can get — but 50 reliable ones beat 3,000 loose affiliations.)
  - **Priority play: approach WellaHealth for a fulfilment partnership.** Their 2,790-pharmacy network (~2,000 active weekly, 36 states, wallet rails) is exactly the last-mile layer we need, they position as infrastructure-not-consumer-brand by design, and integrating with them beats rebuilding. Reliance's in-house delivery — its most-complained-about feature — is the cautionary tale for doing this vertically too early. Helium Health's EMR base is a similar rails-partnership candidate for facility referrals (Phase 2+).

---

## 11. Business Model & Pricing (v1 hypotheses — validate in Phase 0)

| Stream | Price point | Notes |
|--------|-------------|-------|
| Pay-per-consult (local) | ₦1,500–₦2,500 GP chat/voice; ₦3,500–₦5,000 video/specialist track | Above CribMD's ₦1,000 — we sell reliability, not rock-bottom |
| **Diaspora family plan** | $10–$25/month per covered family member | Unlimited chat triage, N consults/month, medication delivery add-on billed at cost+margin |
| Chronic-care programme | ₦7,500–₦15,000/month | Includes scheduled check-ins + refill logistics |
| B2B (SMEs/employers) | ₦1,500–₦3,000 PEPM | Phase 2; sell "telehealth benefit without HMO cost" |
| Margin engines | Pharmacy fulfilment margin, lab-referral fees (Phase 2) | |

Unit-economics sanity: doctor cost 60–70% of consult fee; Daily.co + SMS + payment fees ≈ ₦150–₦300/consult; contribution positive from consult one at ₦1,500+. Diaspora subscriptions carry the CAC.

---

## 12. Roadmap

### Phase 0 — Foundations & validation (Months 0–3)
- CAC/NDPC/HEFAMAA processes started; regulatory counsel engaged.
- 30 diaspora customer interviews + 30 local patient interviews; pricing tests via landing pages.
- Concierge MVP: WhatsApp + human coordinators + 5 contracted doctors + manual Paystack links. **Sell before building.** Target: 100 paid consults, measure completion & repeat rates.
- Hire: Medical Director (part-time ok), 1 ops lead.

### Phase 1 — Build & launch MVP (Months 3–7)
- Backend + patient app (chat/voice consults, Paystack, e-prescriptions, pharmacy pickup) + doctor web console + minimal backoffice (ops, finance, clinical audit).
- Video ships **behind a connection-quality gate** (only offered when the network can carry it).
- Diaspora checkout (foreign cards) + sponsor dashboard v1.
- Launch: Lagos + diaspora (UK/US Nigerian communities via church/association/influencer channels).
- Targets: 1,000 registered patients, 500 consults/month, ≥85% consult completion, 25 active doctors.

### Phase 2 — Retention & B2B (Months 7–14)
- Chronic-care programmes; doctor mobile app; Hausa localisation + one northern-city pilot (Kano/Kaduna); SME employer plans; lab-referral partnerships; delivery logistics.
- Targets: 3,000 consults/month, 30% of revenue in FX, first 10 SME contracts.

### Phase 3 — Moat deepening (Months 14–24)
- USSD/IVR experiments; NHIA/HMO integration if reimbursement rules land; expand states; consider seed raise on demonstrated FX-revenue story.

---

## 13. KPIs

| Category | Metric | Target (Month 12) |
|----------|--------|-------------------|
| Reliability | Consult Completion Rate (per network/state) | ≥ 90% |
| Access | Median wait to doctor (on-demand) | < 10 min |
| Retention | 90-day repeat-consult rate | ≥ 35% |
| Diaspora | % revenue in foreign currency | ≥ 30% |
| Supply | Doctor monthly churn | < 5% |
| Supply | Median payout delay | < 7 days |
| Clinical | Audited-consult protocol compliance | ≥ 95% |
| Unit | Contribution margin per consult | Positive from launch |

---

## 14. Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Doctor supply (japa) | Weekly payouts, flexible shifts, over-recruit 2× roster need; diaspora-based Nigerian-licensed doctors as backfill (MDCN licence still required) |
| Naira volatility | Diaspora FX revenue as natural hedge; price reviews quarterly |
| Payment failure rates | Dual PSP (Paystack + Flutterwave) with automatic failover; transfer/USSD options |
| Data breach reputation | In-country PHI hosting, audit trails, pen-test before launch (Ruby Doctors' compromised site = cautionary tale) |
| Incumbent response (Reliance et al.) | They are structurally committed to HMO/B2B; our diaspora + chronic-care wedge is off their main axis |
| Regulatory drift (state-by-state facility rules) | Counsel on retainer; HEFAMAA-first, add states deliberately |
| Low consumer willingness-to-pay | B2C is the wedge, not the endgame — diaspora + B2B carry economics |

---

## 15. Open Decisions (for founders)

1. **Company/brand name** + CAC and domain availability.
2. Concierge MVP first (recommended, Phase 0) vs straight to build?
3. Video vendor final call: Daily.co (known) vs 100ms/Agora (cheaper at scale) — benchmark both on MTN/Glo 3G before Phase 1 code freeze.
4. In-country hosting vendor shortlist (Rack Centre vs MainOne vs Nobus) — request quotes Month 1.
5. Fundraising posture: bootstrap Phase 0–1 vs pre-seed now.

---

## Appendix A — Source Notes

- TechCabal (Jul 2026): "Why Nigeria's telemedicine sector is growing again" — funding, adoption, HMO integration data.
- Goldsmiths LLP / Mondaq / ThisDay: Nigerian telemedicine regulatory framework (CAC, MDCN, HEFAMAA, NDPA, NOTAP).
- NITDA National Cloud Policy 2025 — health data Level 3 in-country hosting.
- Techpoint/Clafiya/PMC systematic review — competitor pricing, modality mix (80% voice), session-failure rates.
- nairacompare/PharmAccess HealthConnect/Mediplan — diaspora health-payment landscape.
- ClinikEHR salary guides 2026 — doctor income and locum economics.
- Competitor audits (21 Jul 2026): Crunchbase/Tracxn funding records, company pricing pages (KompleteCare, Meedsy/Nairasworth for Reliance), TechCabal/Techpoint/Technext coverage, Swiss Re Foundation (WellaHealth), Gates Foundation/Yale SOM (mDoc), General Atlantic/YC (Reliance), AXA IM Alts (Helium).
- Direct site audit of rubydoctors.com (21 Jul 2026, via browser).
- CLEA monorepo audit (internal, 21 Jul 2026) — reusable assets list in §8.2.

**Evidence-quality caveat:** several figures are marked from secondary sources and dated — Reliance enrollee counts are 2022–23 vintage (likely understated today), Clafiya's rumoured $3.8M seed/YC W26 is unverified, and Clafiya/mDoc/Helium consumer pricing was not published. Re-verify any number before using it in an investor deck.
