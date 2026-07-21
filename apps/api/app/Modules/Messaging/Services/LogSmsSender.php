<?php

namespace App\Modules\Messaging\Services;

use Illuminate\Support\Facades\Log;

/**
 * Local/testing driver — writes the SMS to the log instead of sending.
 * Production binds TermiiSmsSender (Phase 1 sprint 1: Termii account pending).
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('sms.outbound', ['phone' => $phone, 'message' => $message]);
    }
}
