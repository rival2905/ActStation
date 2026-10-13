<?php

namespace App\Providers;

use App\Contracts\CommunityLookup;
use App\Contracts\DevelopmentLevelLookup;
use App\Contracts\EventLookup;
use App\Contracts\Events\PaymentRefunded;
use App\Contracts\Events\PaymentSucceeded;
use App\Contracts\OfficialDirectory;
use App\Contracts\RosterProvider;
use App\Listeners\AuthActivitySubscriber;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Policies\CommunityPolicy;
use App\Modules\Community\Services\CommunityLookupService;
use App\Modules\Community\Services\RosterProviderService;
use App\Modules\Event\Listeners\HandleRegistrationPaymentRefunded as EventPaymentRefunded;
use App\Modules\Event\Listeners\HandleRegistrationPaymentSucceeded as EventPaymentSucceeded;
use App\Modules\Event\Services\EventLookupService;
use App\Modules\Marketplace\Listeners\HandlePaymentRefunded as MarketplacePaymentRefunded;
use App\Modules\Marketplace\Listeners\HandlePaymentSucceeded as MarketplacePaymentSucceeded;
use App\Modules\MasterData\Services\DevelopmentLevelLookupService;
use App\Modules\Ticketing\Listeners\HandlePaymentRefunded;
use App\Modules\Ticketing\Listeners\HandlePaymentSucceeded;
use App\Services\OfficialDirectoryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EventLookup::class, EventLookupService::class);
        $this->app->bind(CommunityLookup::class, CommunityLookupService::class);
        $this->app->bind(RosterProvider::class, RosterProviderService::class);
        $this->app->bind(OfficialDirectory::class, OfficialDirectoryService::class);
        $this->app->bind(DevelopmentLevelLookup::class, DevelopmentLevelLookupService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::subscribe(AuthActivitySubscriber::class);
        Event::listen(PaymentSucceeded::class, HandlePaymentSucceeded::class);
        Event::listen(PaymentRefunded::class, HandlePaymentRefunded::class);
        Event::listen(PaymentSucceeded::class, MarketplacePaymentSucceeded::class);
        Event::listen(PaymentRefunded::class, MarketplacePaymentRefunded::class);
        Event::listen(PaymentSucceeded::class, EventPaymentSucceeded::class);
        Event::listen(PaymentRefunded::class, EventPaymentRefunded::class);
        Gate::before(fn ($user) => $user->hasRole('superadmin') ? true : null);

        Gate::policy(Community::class, CommunityPolicy::class);
    }
}
