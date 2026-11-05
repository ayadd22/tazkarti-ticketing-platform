<?php

namespace App\Contracts;

interface TicketStorageInterface
{
    public function store(string $ticketCode, string $contents): string;
}