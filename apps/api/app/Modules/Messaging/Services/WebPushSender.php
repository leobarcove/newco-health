<?php

namespace App\Modules\Messaging\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/** VAPID web push — the first rung of the Notifier chain. */
class WebPushSender
{
    /** Returns true when at least one device accepted the push. */
    public function send(User $user, string $message): bool
    {
        if (! config('services.vapid.public') || ! config('services.vapid.private')) {
            return false;
        }

        $subscriptions = DB::table('push_subscriptions')->where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return false;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => config('services.vapid.public'),
                    'privateKey' => config('services.vapid.private'),
                ],
            ]);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
                    ]),
                    json_encode(['title' => 'NewCo Health', 'body' => $message]),
                );
            }

            $delivered = false;
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $delivered = true;
                } elseif ($report->isSubscriptionExpired()) {
                    DB::table('push_subscriptions')->where('endpoint', $report->getEndpoint())->delete();
                }
            }

            return $delivered;
        } catch (Throwable $e) {
            Log::warning('push.failed', ['error' => $e->getMessage()]);

            return false; // chain falls through to WhatsApp/SMS
        }
    }
}
