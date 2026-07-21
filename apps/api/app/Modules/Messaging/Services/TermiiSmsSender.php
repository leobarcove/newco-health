<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\Http;

/**
 * Termii transactional SMS driver.
 * Requires TERMII_API_KEY + TERMII_SENDER_ID (sender-ID registration takes weeks — dev plan §6).
 */
class TermiiSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Http::asJson()->post('https://api.ng.termii.com/api/sms/send', [
            'api_key' => config('services.termii.key'),
            'from' => config('services.termii.sender_id'),
            'to' => $phone,
            'sms' => $message,
            'type' => 'plain',
            'channel' => 'generic',
        ])->throw();
    }
}
