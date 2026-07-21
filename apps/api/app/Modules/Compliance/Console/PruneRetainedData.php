<?php

namespace App\Modules\Compliance\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention jobs (dev plan §5.1 compliance): append-only logs are kept only
 * as long as their purpose requires (NDPA data-minimisation).
 * Clinical/audit records are NOT touched — different retention regime.
 */
class PruneRetainedData extends Command
{
    protected $signature = 'compliance:prune';

    protected $description = 'Prune expired OTPs, stale PHI access logs, and dead push subscriptions per retention policy';

    public function handle(): int
    {
        $otps = DB::table('otp_codes')->where('expires_at', '<', now()->subDay())->delete();

        $phi = DB::table('phi_access_log')
            ->where('created_at', '<', now()->subMonths((int) config('retention.phi_access_log_months', 24)))
            ->delete();

        $push = DB::table('push_subscriptions')
            ->where('updated_at', '<', now()->subMonths(6))
            ->delete();

        $this->info("Pruned — expired OTPs: {$otps}; PHI access logs beyond retention: {$phi}; stale push subscriptions: {$push}");

        return self::SUCCESS;
    }
}
