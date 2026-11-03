<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';
    case Buyer = 'buyer';
}
