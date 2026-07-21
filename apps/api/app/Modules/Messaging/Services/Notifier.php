<?php

namespace App\Modules\Messaging\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The notification fallback chain (dev plan §5.1): push → WhatsApp → SMS.
 * Each channel reports whether it could actually reach the user; the chain
 * stops at the first success. Critical messages always end at SMS — the rung
 * that works on every phone in Nigeria.
 */
class Notifier
{
    public function __construct(
        private readonly WhatsAppSender $whatsapp,
        private readonly SmsSender $sms,
    ) {
    }

    public function notify(User $user, string $message): void
    {
        if ($this->tryPush($user, $message)) {
            return;
        }

        if ($user->phone !== null && $this->whatsapp->send($user->phone, $message)) {
            return;
        }

        if ($user->phone !== null) {
            $this->sms->send($user->phone, $message);
        }
    }

    /** Web push lands when the custom service worker ships; subscriptions are already stored. */
    private function tryPush(User $user, string $message): bool
    {
        $hasSubscription = DB::table('push_subscriptions')->where('user_id', $user->id)->exists();

        if ($hasSubscription) {
            // Delivery pending the injectManifest service-worker switch — log and fall through.
            Log::info('push.skipped_pending_sw', ['user_id' => $user->id]);
        }

        return false;
    }
}
