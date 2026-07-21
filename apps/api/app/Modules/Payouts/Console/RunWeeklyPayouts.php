<?php

namespace App\Modules\Payouts\Console;

use App\Modules\Payouts\Services\PayoutService;
use Illuminate\Console\Command;

class RunWeeklyPayouts extends Command
{
    protected $signature = 'payouts:run';

    protected $description = 'Pay every doctor their pending consult earnings (scheduled weekly)';

    public function handle(PayoutService $payouts): int
    {
        $result = $payouts->run();

        $this->info("Doctors paid: {$result['paid']}; awaiting bank details: {$result['skipped_no_bank']}; transfer failures (will retry): {$result['failed']}");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
