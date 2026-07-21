<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Meta WhatsApp Cloud API driver. Activates when WHATSAPP_TOKEN +
 * WHATSAPP_PHONE_ID are configured (business verification takes weeks —
 * dev plan §6, start Month 0).
 */
class MetaWhatsAppSender implements WhatsAppSender
{
    public function send(string $phone, string $message): bool
    {
        try {
            return Http::withToken(config('services.whatsapp.token'))
                ->post('https://graph.facebook.com/v21.0/'.config('services.whatsapp.phone_id').'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($phone, '+'),
                    'type' => 'text',
                    'text' => ['body' => $message],
                ])->successful();
        } catch (Throwable) {
            return false; // chain falls through to SMS
        }
    }
}
