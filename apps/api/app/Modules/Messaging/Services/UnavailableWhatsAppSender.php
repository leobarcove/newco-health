<?php

namespace App\Modules\Messaging\Services;

/** Local/unverified driver: WhatsApp can't deliver, so the chain reaches SMS. */
class UnavailableWhatsAppSender implements WhatsAppSender
{
    public function send(string $phone, string $message): bool
    {
        return false;
    }
}
