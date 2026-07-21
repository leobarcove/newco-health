<?php

namespace App\Modules\Programmes\Console;

use App\Modules\Programmes\Services\ProgrammeService;
use Illuminate\Console\Command;

class TickProgrammes extends Command
{
    protected $signature = 'programmes:tick';

    protected $description = 'Send due chronic-care check-in nudges and lapse expired enrolments';

    public function handle(ProgrammeService $programmes): int
    {
        $result = $programmes->tick();
        $this->info("Check-in nudges: {$result['nudged']}; enrolments lapsed: {$result['lapsed']}");

        return self::SUCCESS;
    }
}
