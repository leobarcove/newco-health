<?php

namespace App\Modules\Consults\Console;

use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Services\ConsultStateMachine;
use Illuminate\Console\Command;

class CloseFollowUpWindows extends Command
{
    protected $signature = 'consults:close-followups';

    protected $description = 'Close consults whose 72-hour follow-up window has passed';

    public function handle(ConsultStateMachine $machine): int
    {
        $closed = 0;

        Consult::where('state', Consult::STATE_CONCLUDED)
            ->where('concluded_at', '<', now()->subHours(72))
            ->each(function (Consult $consult) use ($machine, &$closed) {
                $machine->transition($consult, Consult::STATE_CLOSED);
                $closed++;
            });

        $this->info("Follow-up windows closed: {$closed}");

        return self::SUCCESS;
    }
}
