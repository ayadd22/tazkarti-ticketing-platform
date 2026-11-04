<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    
    public function viewAny(User $user): bool
    {
        return $user->role === UserRoleEnum::Admin
            || $user->role === UserRoleEnum::Buyer;
    }

  
    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        return $user->role === UserRoleEnum::Buyer
            && $user->id === $reservation->buyer_id;
    }

   
    public function create(User $user): bool
    {
        return $user->role === UserRoleEnum::Buyer;
    }
}
