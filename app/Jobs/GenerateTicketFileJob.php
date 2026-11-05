<?php

namespace App\Jobs;

use App\Contracts\TicketStorageInterface;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTicketFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    public function __construct(
        public Ticket $ticket
    ) {}

    public function handle(TicketStorageInterface $storage): void
    {
        $this->ticket->refresh();

        if ($this->ticket->file_path) {
            return;
        }

        $path = $storage->store(
            $this->ticket->code,
            "Ticket Code: {$this->ticket->code}"
        );

        $this->ticket->update([
            'file_path' => $path,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Ticket file generation failed', [
            'ticket_id' => $this->ticket->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}