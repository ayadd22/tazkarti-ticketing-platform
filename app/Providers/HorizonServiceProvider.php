<?php

namespace App\Providers;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate controls who may access the Horizon dashboard.
     * Only users whose role is `admin` are permitted.
     *
     * The $user parameter is nullable because Horizon calls this gate before
     * asserting authentication — a null value means an unauthenticated guest.
     *
     * NOTE on browser access with Sanctum:
     * Horizon routes use the `web` middleware (see config/horizon.php).
     * Sanctum API bearer tokens authenticate requests through the `api`
     * middleware and do NOT populate the session-based web guard that
     * these routes rely on. An admin must therefore be authenticated via
     * a session cookie (web guard) to access /horizon in a browser.
     * A Sanctum bearer token alone is not sufficient for this route.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user) {
            return $user !== null
                && $user->role === UserRoleEnum::Admin;
        });
    }
}
