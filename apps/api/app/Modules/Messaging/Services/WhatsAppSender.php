<?php

namespace App\Modules\Messaging\Services;

interface WhatsAppSender
{
    /** Returns true only when the message was accepted for delivery. */
    public function send(string $phone, string $message): bool;
}
