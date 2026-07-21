<?php

namespace App\Modules\Prescribing\Console;

use App\Models\User;
use App\Modules\Prescribing\Models\Pharmacy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreatePharmacyLogin extends Command
{
    protected $signature = 'pharmacy:create-login {pcn_licence : PCN licence number} {email}';

    protected $description = 'Create a counter login for a registered pharmacy (prints a one-time password)';

    public function handle(): int
    {
        $pharmacy = Pharmacy::where('pcn_licence_no', $this->argument('pcn_licence'))->first();
        if ($pharmacy === null) {
            $this->error('No pharmacy with that PCN licence — create it in the backoffice first.');

            return self::FAILURE;
        }

        $password = Str::password(16, symbols: false);

        User::updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $pharmacy->name.' counter',
                'role' => User::ROLE_PHARMACY,
                'pharmacy_id' => $pharmacy->id,
                'password' => $password,
            ],
        );

        $this->info("Login created for {$pharmacy->name}.");
        $this->warn("One-time password (share securely, then have them change it): {$password}");

        return self::SUCCESS;
    }
}
