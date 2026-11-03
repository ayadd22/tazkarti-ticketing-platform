<?php
namespace App\Enums;

enum EventStatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';
}