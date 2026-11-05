<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Models\Event;
use App\Observers\EventObserver;
use App\Models\TicketType;
use App\Observers\TicketTypeObserver;
use App\Contracts\SmsServiceInterface;
use App\Contracts\TicketStorageInterface;
use App\Services\External\LogSmsService;
use App\Services\External\LocalTicketStorage;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         $this->app->bind(SmsServiceInterface::class, LogSmsService::class);

         $this->app->bind(TicketStorageInterface::class, LocalTicketStorage::class);
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
