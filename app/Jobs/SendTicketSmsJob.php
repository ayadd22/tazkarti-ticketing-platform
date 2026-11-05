<?php

namespace App\Jobs;

use App\Contracts\SmsServiceInterface;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendTicketSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public function __construct(
        public Ticket $ticket
    ) {}

    public function handle(SmsServiceInterface $sms): void
    {
        DB::transaction(function () use ($sms) {

            $ticket = Ticket::query()
                ->whereKey($this->ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ticket->sms_sent_at) {
                return;
            }

            $ticket->loadMissing('reservation.buyer');

            $phone = $ticket->reservation->buyer->phone;

            if (!$phone) {
                throw new RuntimeException(
                    "Buyer phone number is missing for ticket {$ticket->id}."
                );
            }

            $sms->send(
                $phone,
                "Your ticket code is: {$ticket->code}"
            );

            $ticket->forceFill([
              'sms_sent_at' => now(),
             ])->save();
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Ticket SMS sending failed', [
            'ticket_id' => $this->ticket->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}