<?php

namespace App\Enums;

enum ReservationStatusEnum: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Expired = 'expired';
}
