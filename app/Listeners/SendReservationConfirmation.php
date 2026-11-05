<?php

namespace App\Listeners;

use App\Events\ReservationCreated;
use App\Mail\ReservationConfirmationMail;
use App\Models\Reservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendReservationConfirmation implements ShouldQueue
{
    public bool $afterCommit = true;


    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public function handle(ReservationCreated $event): void
    {
        DB::transaction(function () use ($event) {
            $reservation = Reservation::query()
                ->whereKey($event->reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

       
            if ($reservation->confirmation_email_sent_at !== null) {
                return;
            }

            $reservation->load('buyer');

            Mail::to($reservation->buyer->email)
                ->send(new ReservationConfirmationMail($reservation));

            $reservation->forceFill([
               'confirmation_email_sent_at' => now(),
            ])->save();
        });
    }


    public function failed(ReservationCreated $event, Throwable $exception): void
    {
    Log::error('Reservation confirmation email permanently failed.', [
        'reservation_id' => $event->reservation->id,
        'exception' => $exception->getMessage(),
    ]);
  }
}
