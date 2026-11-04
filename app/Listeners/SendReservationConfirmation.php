<?php

namespace App\Listeners;

use App\Events\ReservationCreated;
use App\Mail\ReservationConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendReservationConfirmation implements ShouldQueue
{
    
    public bool $afterCommit = true;

    public function handle(ReservationCreated $event): void
    {
        $reservation = $event->reservation;

        
        if (! $reservation->relationLoaded('buyer')) {
            $reservation->load('buyer');
        }

        Mail::to($reservation->buyer->email)
            ->send(new ReservationConfirmationMail($reservation));
    }
}
