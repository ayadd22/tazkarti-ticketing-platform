<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\EventCacheService;
use Illuminate\Support\Facades\DB;

class EventObserver
{
    public function created(Event $event): void
    {
        $this->invalidateCache();
    }

    public function updated(Event $event): void
    {
        $this->invalidateCache();
    }

    public function deleted(Event $event): void
    {
        $this->invalidateCache();
    }

    public function restored(Event $event): void
    {
        $this->invalidateCache();
    }

    public function forceDeleted(Event $event): void
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