<?php

return [
    // Months to keep PHI read-access logs (audit_events and clinical records are never pruned here).
    'phi_access_log_months' => env('RETENTION_PHI_ACCESS_MONTHS', 24),
];
