<?php

declare(strict_types=1);

namespace App\Modules\Communication\Providers;

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Communication\Services\CommunicationEmailDelivery;
use Illuminate\Support\Facades\Gate;
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
    public function boot(): void
    {
        Gate::define('assistant.use', fn (User $user): bool => $user->is_active && $user->roles->contains(
            fn ($role): bool => in_array($role->panel_access, ['internal', 'external', 'both'], true)
        ));
    }
}
