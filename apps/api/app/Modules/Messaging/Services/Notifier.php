<?php

namespace App\Modules\Messaging\Services;

use App\Models\User;

/**
 * The notification fallback chain (dev plan §5.1): push → WhatsApp → SMS.
 * Each channel reports whether it could actually reach the user; the chain
 * stops at the first success. Critical messages always end at SMS — the rung
 * that works on every phone in Nigeria.
 */
class Notifier
{
    public function __construct(
        private readonly WebPushSender $push,
        private readonly WhatsAppSender $whatsapp,
        private readonly SmsSender $sms,
    ) {
    }

    public function notify(User $user, string $message): void
    {
        if ($this->push->send($user, $message)) {
            return;
        }

        if ($user->phone !== null && $this->whatsapp->send($user->phone, $message)) {
            return;
        }

        if ($user->phone !== null) {
            $this->sms->send($user->phone, $message);
        }
    }
}
