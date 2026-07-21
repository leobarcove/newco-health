<?php

/*
 * Booking rules — config-driven so a future `markets` table can override
 * per country (dev plan §16 multi-country readiness) without code changes.
 */
return [
    // Earliest a patient may book ahead of now.
    'min_lead_minutes' => env('BOOKING_MIN_LEAD_MINUTES', 60),

    // How far into the future slots are offered.
    'horizon_days' => env('BOOKING_HORIZON_DAYS', 14),

    // Cancellation/reschedule cut-off before the appointment.
    'cancel_cutoff_minutes' => env('BOOKING_CANCEL_CUTOFF_MINUTES', 120),

    // A consult may begin this many minutes before/after the booked start.
    'begin_early_minutes' => env('BOOKING_BEGIN_EARLY_MINUTES', 5),
    'begin_grace_minutes' => env('BOOKING_BEGIN_GRACE_MINUTES', 15),

    // Reminder offsets (minutes before start) → flag column that records the send.
    'reminders' => [
        ['offset_minutes' => 1440, 'column' => 'reminded_24h_at'],
        ['offset_minutes' => 60, 'column' => 'reminded_1h_at'],
    ],
];
