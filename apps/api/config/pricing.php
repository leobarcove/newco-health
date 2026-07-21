<?php

/*
 * All amounts in kobo (integer money). Config-driven so the future `markets`
 * table can override per country (dev plan §16). Prices per startup plan §11.
 */
return [
    'consult_price_kobo' => env('PRICING_CONSULT_KOBO', 250_000), // ₦2,500

    // Doctor's share of a consult fee (startup plan §10: 60–70%).
    'doctor_share_percent' => env('PRICING_DOCTOR_SHARE', 65),

    // Gate: when false (local/dev/tests), consults queue without payment.
    'payments_required' => env('PAYMENTS_REQUIRED', true),
];
