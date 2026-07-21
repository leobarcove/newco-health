<?php

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Payments\Models\Wallet;
use DomainException;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    public function for(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    public function credit(User $user, int $amountKobo, string $reason): Wallet
    {
        return DB::transaction(function () use ($user, $amountKobo, $reason) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->for($user);

            $wallet->increment('balance_kobo', $amountKobo);
            $this->audit->record($wallet, 'wallet.credited', $user->id, ['amount_kobo' => $amountKobo, 'reason' => $reason]);

            return $wallet->refresh();
        });
    }

    /** Locked debit — throws rather than allowing a negative balance. */
    public function debit(User $user, int $amountKobo, string $reason): Wallet
    {
        return DB::transaction(function () use ($user, $amountKobo, $reason) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if ($wallet === null || $wallet->balance_kobo < $amountKobo) {
                throw new DomainException('Insufficient wallet balance.');
            }

            $wallet->decrement('balance_kobo', $amountKobo);
            $this->audit->record($wallet, 'wallet.debited', $user->id, ['amount_kobo' => $amountKobo, 'reason' => $reason]);

            return $wallet->refresh();
        });
    }
}
