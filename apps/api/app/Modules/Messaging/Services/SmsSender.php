<?php

namespace App\Modules\Messaging\Services;

interface SmsSender
{
    public function send(string $phone, string $message): void;
}
