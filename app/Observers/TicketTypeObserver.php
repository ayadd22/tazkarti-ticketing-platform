<?php

namespace App\Observers;

use App\Models\TicketType;
use App\Services\EventCacheService;
use Illuminate\Support\Facades\DB;

class TicketTypeObserver
{
    public function created(TicketType $ticketType): void
    {
        $this->invalidateCache();
    }

    public function updated(TicketType $ticketType): void
    {
        $this->invalidateCache();
    }

    public function deleted(TicketType $ticketType): void
    {
        $this->invalidateCache();
    }

    public function restored(TicketType $ticketType): void
    {
        $this->invalidateCache();
    }

    public function forceDeleted(TicketType $ticketType): void
    {
        $this->invalidateCache();
    }

    private function invalidateCache(): void
    {
        DB::afterCommit(function () {
            app(EventCacheService::class)->invalidate();
        });
    }
}