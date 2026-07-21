<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Http;

/** Flutterwave — the failover PSP (dev plan §6). Activates via FLUTTERWAVE_SECRET_KEY. */
class FlutterwaveGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function initialise(Payment $payment): array
    {
        $response = Http::withToken(config('services.flutterwave.secret'))
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $payment->reference,
                'amount' => $payment->amount_kobo / 100,
                'currency' => $payment->currency,
                'redirect_url' => config('app.url').'/payment-complete',
                'customer' => [
                    'email' => $payment->user->email ?? ($payment->user->phone.'@patients.newco.example'),
                ],
            ])->throw()->json();

        return ['checkout_url' => $response['data']['link']];
    }

    public function verify(Payment $payment): string
    {
        $response = Http::withToken(config('services.flutterwave.secret'))
            ->get('https://api.flutterwave.com/v3/transactions/verify_by_reference', ['tx_ref' => $payment->reference])
            ->throw()->json();

        return match ($response['data']['status'] ?? 'failed') {
            'successful' => Payment::STATUS_SUCCEEDED,
            'pending' => Payment::STATUS_PENDING,
            default => Payment::STATUS_FAILED,
        };
    }

    public function webhookIsValid(string $rawBody, ?string $signature): bool
    {
        return $signature !== null
            && hash_equals((string) config('services.flutterwave.webhook_secret'), $signature);
    }

    public function webhookEvents(array $payload): array
    {
        if (($payload['event'] ?? '') === 'charge.completed' && ($payload['data']['status'] ?? '') === 'successful') {
            return [[$payload['data']['tx_ref'] ?? '', Payment::STATUS_SUCCEEDED]];
        }

        return [];
    }

    public function refund(Payment $payment): bool
    {
        return Http::withToken(config('services.flutterwave.secret'))
            ->post('https://api.flutterwave.com/v3/refunds', ['tx_ref' => $payment->reference])
            ->successful();
    }

    public function transfer(int $amountKobo, string $reference, string $recipientCode): bool
    {
        return Http::withToken(config('services.flutterwave.secret'))
            ->post('https://api.flutterwave.com/v3/transfers', [
                'account_bank' => $recipientCode,
                'amount' => $amountKobo / 100,
                'currency' => 'NGN',
                'reference' => $reference,
            ])->successful();
    }
}
