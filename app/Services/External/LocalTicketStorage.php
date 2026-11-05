<?php

namespace App\Services\External;

use App\Contracts\TicketStorageInterface;
use Illuminate\Support\Facades\Storage;

class LocalTicketStorage implements TicketStorageInterface
{
    public function store(string $ticketCode, string $contents): string
    {
        $path = "tickets/{$ticketCode}.txt";

        Storage::disk('local')->put($path, $contents);

        return $path;
    }
}