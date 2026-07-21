<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Messaging\Services\SmsSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    public const CODE_LENGTH = 6;
    public const TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const REQUESTS_PER_HOUR = 5;

    public function __construct(private readonly SmsSender $sms)
    {
    }

    /**
     * Generate and send a one-time code. Returns false when rate-limited.
     */
    public function request(string $phone): bool
    {
        $key = 'otp:'.$phone;

        if (RateLimiter::tooManyAttempts($key, self::REQUESTS_PER_HOUR)) {
            return false;
        }
        RateLimiter::hit($key, 3600);

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        OtpCode::where('phone', $phone)->delete();
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->sms->send($phone, __('Your NewCo Health code is :code. It expires in :minutes minutes.', [
            'code' => $code,
            'minutes' => self::TTL_MINUTES,
        ]));

        return true;
    }

    /**
     * Verify a code; on success returns the (created-if-new) user, else null.
     */
    public function verify(string $phone, string $code): ?User
    {
        $otp = OtpCode::where('phone', $phone)->latest()->first();

        if ($otp === null || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return null;
        }

        $otp->delete();

        return User::firstOrCreate(
            ['phone' => $phone],
            ['name' => '', 'role' => User::ROLE_PATIENT, 'password' => str()->random(40)],
        );
    }
}
