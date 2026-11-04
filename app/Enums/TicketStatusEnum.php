<?php

namespace App\Enums;

enum TicketStatusEnum: string
{
    case Pending   = 'pending';   
    case Valid     = 'valid';     
    case Used      = 'used';     
    case Cancelled = 'cancelled';
}
