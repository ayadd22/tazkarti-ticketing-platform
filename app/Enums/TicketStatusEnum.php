<?php

namespace App\Enums;

enum TicketStatusEnum: string
{
    case Valid = 'valid';
    case Used = 'used';
    case Cancelled = 'cancelled';
}
