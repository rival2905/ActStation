<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Contracts\EmailDelivery;
use App\Modules\Communication\Services\CommunicationEmailDelivery;
use Illuminate\Support\ServiceProvider;

class CommunicationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmailDelivery::class, CommunicationEmailDelivery::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
