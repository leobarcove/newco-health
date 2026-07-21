<?php

namespace App\Modules\Compliance\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Append-only consent ledger (NDPA). Rows are never updated or deleted —
 * a withdrawal is a new `revoked` event; current state is the latest row.
 */
class ConsentLedger
{
    public const KIND_TELEMEDICINE_TERMS = 'telemedicine_terms';
    public const KIND_PRIVACY_POLICY = 'privacy_policy';
    public const KIND_SPONSOR_VISIBILITY = 'sponsor_visibility';

    public const KINDS = [
        self::KIND_TELEMEDICINE_TERMS,
        self::KIND_PRIVACY_POLICY,
        self::KIND_SPONSOR_VISIBILITY,
    ];

    public function grant(User $user, string $kind, ?string $ip = null, array $meta = []): void
    {
        $this->append($user, $kind, 'granted', $ip, $meta);
    }

    public function revoke(User $user, string $kind, ?string $ip = null): void
    {
        $this->append($user, $kind, 'revoked', $ip);
    }

    public function has(User $user, string $kind): bool
    {
        $latest = DB::table('consents')
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $latest?->action === 'granted';
    }

    /** @return array<string, bool> */
    public function statusFor(User $user): array
    {
        return collect(self::KINDS)
            ->mapWithKeys(fn (string $kind) => [$kind => $this->has($user, $kind)])
            ->all();
    }

    private function append(User $user, string $kind, string $action, ?string $ip, array $meta = []): void
    {
        DB::table('consents')->insert([
            'user_id' => $user->id,
            'kind' => $kind,
            'action' => $action,
            'ip' => $ip,
            'meta' => json_encode($meta),
            'created_at' => now(),
        ]);
    }
}
