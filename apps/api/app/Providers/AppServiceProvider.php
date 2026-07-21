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
    }

    public function boot(): void
    {
        Gate::policy(Consult::class, ConsultPolicy::class);
    }
}
