<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Paystack driver. Activates when PAYSTACK_SECRET_KEY is configured
 * (AppServiceProvider binding) — until then FakeGateway serves local dev.
 */
class PaystackGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    public function initialise(Payment $payment): array
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $payment->user->email ?? ($payment->user->phone.'@patients.newco.example'),
                'amount' => $payment->amount_kobo,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'channels' => ['card', 'bank', 'ussd', 'bank_transfer', 'mobile_money'],
            ])->throw()->json();

        return ['checkout_url' => $response['data']['authorization_url']];
    }

    public function verify(Payment $payment): string
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->get("https://api.paystack.co/transaction/verify/{$payment->reference}")
            ->throw()->json();

        return match ($response['data']['status'] ?? 'failed') {
            'success' => Payment::STATUS_SUCCEEDED,
            'abandoned', 'pending', 'ongoing' => Payment::STATUS_PENDING,
            default => Payment::STATUS_FAILED,
        };
    }

    public function webhookIsValid(string $rawBody, ?string $signature): bool
    {
        return $signature !== null
            && hash_equals(hash_hmac('sha512', $rawBody, (string) config('services.paystack.secret')), $signature);
    }

    public function webhookEvents(array $payload): array
    {
        if (($payload['event'] ?? '') === 'charge.success') {
            return [[$payload['data']['reference'] ?? '', Payment::STATUS_SUCCEEDED]];
        }

        return [];
    }

    public function refund(Payment $payment): bool
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/refund', ['transaction' => $payment->reference]);

        return $response->successful();
    }
}
