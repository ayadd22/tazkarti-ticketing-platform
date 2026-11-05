<?php

namespace App\Listeners;

use App\Events\ReservationPaid;
use App\Jobs\GenerateTicketFileJob;
use App\Jobs\SendTicketSmsJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class QueuePaidReservationTickets implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public bool $afterCommit = true;

    public function handle(ReservationPaid $event): void
    {
        $event->reservation
            ->tickets()
            ->select('id')
            ->chunkById(100, function ($tickets) {
                foreach ($tickets as $ticket) {
                    GenerateTicketFileJob::dispatch($ticket)
                        ->onQueue('ticket-files');

                    SendTicketSmsJob::dispatch($ticket)
                        ->onQueue('notifications');
                }
            });
    }

    public function failed(ReservationPaid $event, \Throwable $exception): void
    {
        Log::error('Queueing paid reservation tickets failed', [
            'reservation_id' => $event->reservation->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}