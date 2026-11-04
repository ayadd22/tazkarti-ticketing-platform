<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
  
    public function viewAny(?User $user): bool
    {
        return true;
    }

   
    public function view(?User $user, Event $event): bool
    {
        if ($event->status->value === 'published') {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        return $user->role === UserRoleEnum::Organizer
            && $user->id === $event->organizer_id;
    }

    
    public function create(User $user): bool
    {
        return $user->role === UserRoleEnum::Admin
            || $user->role === UserRoleEnum::Organizer;
    }

    
    public function update(User $user, Event $event): bool
    {
        if ($user->role === UserRoleEnum::Admin) {
            return true;
        }

        return $user->role === UserRoleEnum::Organizer
            && $user->id === $event->organizer_id;
    }

   
    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }
}
