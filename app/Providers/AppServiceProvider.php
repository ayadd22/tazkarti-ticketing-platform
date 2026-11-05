<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Models\Event;
use App\Observers\EventObserver;
use App\Models\TicketType;
use App\Observers\TicketTypeObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::observe(EventObserver::class);
        TicketType::observe(TicketTypeObserver::class);
    }
}
