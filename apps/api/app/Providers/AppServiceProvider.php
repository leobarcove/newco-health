<?php

namespace App\Providers;

use App\Modules\Consults\Models\Consult;
use App\Modules\Messaging\Services\LogSmsSender;
use App\Modules\Messaging\Services\SmsSender;
use App\Modules\Messaging\Services\TermiiSmsSender;
use App\Policies\ConsultPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, function () {
            return config('services.termii.key')
                ? new TermiiSmsSender()
                : new LogSmsSender();
        });

        $this->app->bind(\App\Modules\Consults\Services\VideoGateway::class, function () {
            return config('services.daily.key')
                ? new \App\Modules\Consults\Services\DailyVideoGateway()
                : new \App\Modules\Consults\Services\FakeVideoGateway();
        });

        $this->app->bind(\App\Modules\Messaging\Services\WhatsAppSender::class, function () {
            return config('services.whatsapp.token')
                ? new \App\Modules\Messaging\Services\MetaWhatsAppSender()
                : new \App\Modules\Messaging\Services\UnavailableWhatsAppSender();
        });

        // Real gateways activate the moment credentials exist; fake until then.
        // Paystack-primary with Flutterwave failover when both are configured.
        $this->app->bind(\App\Modules\Payments\Services\PaymentGateway::class, function () {
            if (! config('services.paystack.secret')) {
                return new \App\Modules\Payments\Services\FakeGateway();
            }

            $paystack = new \App\Modules\Payments\Services\PaystackGateway();

            return config('services.flutterwave.secret')
                ? new \App\Modules\Payments\Services\FailoverGateway($paystack, new \App\Modules\Payments\Services\FlutterwaveGateway())
                : $paystack;
        });
    }

    public function boot(): void
    {
        Gate::policy(Consult::class, ConsultPolicy::class);
        Gate::policy(\App\Modules\Scheduling\Models\Booking::class, \App\Policies\BookingPolicy::class);
    }
}
