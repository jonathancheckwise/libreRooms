<?php

namespace App\Providers;

use App\Models\Owner;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\SystemSettings;
use App\Models\User;
use App\Observers\ReservationObserver;
use App\Policies\OwnerPolicy;
use App\Policies\RoomPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SystemSettings::class, function () {
            // Don't query database if not yet configured
            if (! \App\Http\Controllers\SetupController::isDatabaseConfigured()) {
                return null;
            }

            return SystemSettings::first();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(Owner::class, OwnerPolicy::class);

        // Global admins bypass all policy checks
        Gate::before(function (User $user, string $ability) {
            if ($user->is_global_admin) {
                return true;
            }
        });

        // Register observers
        Reservation::observe(ReservationObserver::class);

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            // Use our custom OIDC provider instead of the outdated package
            $event->extendSocialite('nextcloud', \App\Services\Auth\OidcProvider::class);
        });
        // Ne force le HTTPS qu'en production (permet le dev local en http).
        if (! app()->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
