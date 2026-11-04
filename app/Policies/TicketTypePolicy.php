<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy
{
  
    public function create(User $user, Event $event): bool
    {
        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        return $user->role === UserRoleEnum::Organizer
            && $user->id === $event->organizer_id;
    }

   
    public function update(User $user, TicketType $ticketType): bool
    {
        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        return $user->role === UserRoleEnum::Organizer
            && $user->id === $ticketType->event->organizer_id;
    }

    
    public function delete(User $user, TicketType $ticketType): bool
    {
        return $this->update($user, $ticketType);
    }
}
