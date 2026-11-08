<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEventCancellationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public function __construct(
        public int $reservationId,
        public int $eventId
    ) {}

    public function handle(): void
    {
        $reservation = Reservation::with('buyer')->findOrFail($this->reservationId);
        $event = Event::findOrFail($this->eventId);

        Mail::raw(
            "We are sorry to inform you that the event '{$event->title}' has been cancelled. Your reservation #{$reservation->id} has been processed accordingly.",
            function ($message) use ($reservation, $event) {
                $message
                    ->to($reservation->buyer->email)
                    ->subject("Event Cancelled: {$event->title}");
            }
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Event cancellation notification failed.', [
            'reservation_id' => $this->reservationId,
            'event_id' => $this->eventId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
