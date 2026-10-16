<?php

namespace App\Providers;

use App\Contracts\AthleteDocumentVault;
use App\Contracts\CommunityLookup;
use App\Contracts\ContextualAthleteInvitation;
use App\Contracts\DevelopmentLevelLookup;
use App\Contracts\EventLookup;
use App\Contracts\Events\PaymentRefunded;
use App\Contracts\Events\PaymentSucceeded;
use App\Contracts\OfficialDirectory;
use App\Contracts\RegistrationDraftProvider;
use App\Contracts\RosterProvider;
use App\Contracts\SchoolLookup;
use App\Contracts\TeamDocumentInspection;
use App\Listeners\AuthActivitySubscriber;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Policies\CommunityPolicy;
use App\Modules\Community\Services\AthleteDocumentVaultService;
use App\Modules\Community\Services\CommunityLookupService;
use App\Modules\Community\Services\InvitationService;
use App\Modules\Community\Services\RosterProviderService;
use App\Modules\Community\Services\TeamDocumentInspectionService;
use App\Modules\Event\Listeners\HandleRegistrationPaymentRefunded as EventPaymentRefunded;
use App\Modules\Event\Listeners\HandleRegistrationPaymentSucceeded as EventPaymentSucceeded;
use App\Modules\Event\Services\EventLookupService;
use App\Modules\Event\Services\EventRegistrationEmailService;
use App\Modules\Event\Services\RegistrationDraftService;
use App\Modules\Marketplace\Listeners\HandlePaymentRefunded as MarketplacePaymentRefunded;
use App\Modules\Marketplace\Listeners\HandlePaymentSucceeded as MarketplacePaymentSucceeded;
use App\Modules\MasterData\Services\DevelopmentLevelLookupService;
use App\Modules\MasterData\Services\SchoolLookupService;
use App\Modules\Ticketing\Listeners\HandlePaymentRefunded;
use App\Modules\Ticketing\Listeners\HandlePaymentSucceeded;
use App\Services\OfficialDirectoryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TeamDocumentInspection::class, TeamDocumentInspectionService::class);
        $this->app->bind(AthleteDocumentVault::class, AthleteDocumentVaultService::class);
        $this->app->bind(EventLookup::class, EventLookupService::class);
        $this->app->bind(CommunityLookup::class, CommunityLookupService::class);
        $this->app->bind(ContextualAthleteInvitation::class, InvitationService::class);
        $this->app->bind(RosterProvider::class, RosterProviderService::class);
        $this->app->bind(RegistrationDraftProvider::class, RegistrationDraftService::class);
        $this->app->bind(OfficialDirectory::class, OfficialDirectoryService::class);
        $this->app->bind(DevelopmentLevelLookup::class, DevelopmentLevelLookupService::class);
        $this->app->bind(SchoolLookup::class, SchoolLookupService::class);
        $this->app->singleton(EventRegistrationEmailService::class);
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

        View::composer('layouts.partials.sidebar', function (\Illuminate\View\View $view): void {
            $view->with('pendingOrganizerCount', auth()->user()?->can('user.view')
                ? User::where('kyc_status', 'pending')->count()
                : 0);
        });

        View::composer(['layouts.partials.panel-header', 'layouts.partials.panel-sidebar'], function (\Illuminate\View\View $view): void {
            $user = auth()->user();
            if (! $user) {
                $view->with([
                    'panelOfficialEntities' => collect(),
                    'panelActiveCommunity' => null,
                ]);

                return;
            }

            $activeCommunityId = CommunityLookupService::resolveActiveCommunityId((int) $user->id);
            if (! $activeCommunityId && ($user->hasRole('superadmin') || $user->hasRole('admin'))) {
                $activeCommunityId = (int) Community::whereNull('deleted_at')->where('is_active', true)->value('id');
            }

            $activeCommunity = $activeCommunityId ? app(CommunityLookup::class)->find($activeCommunityId) : null;

            $officials = CommunityOfficial::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->whereHas('community', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))
                ->with('community')
                ->get();

            $panelOfficialEntities = $officials->map(function ($official) use ($activeCommunityId) {
                $c = $official->community;

                return (object) [
                    'id' => (int) $c->id,
                    'name' => (string) $c->name,
                    'subtype' => (string) ($c->subtype ?? 'community'),
                    'position' => (string) ($official->position ?? 'Pengurus'),
                    'is_current' => (int) $c->id === (int) $activeCommunityId,
                ];
            });

            if ($panelOfficialEntities->isEmpty() && ($user->hasRole('superadmin') || $user->hasRole('admin')) && $activeCommunity) {
                $panelOfficialEntities = collect([(object) [
                    'id' => (int) $activeCommunity->id,
                    'name' => (string) $activeCommunity->name,
                    'subtype' => (string) ($activeCommunity->subtype ?? 'community'),
                    'position' => 'Admin Override',
                    'is_current' => true,
                ]]);
            }

            $view->with([
                'panelOfficialEntities' => $panelOfficialEntities,
                'panelActiveCommunity' => $activeCommunity,
            ]);
        });
    }
}
