<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
   
    private const FIXED_USERS = [
        [
            'email' => 'admin@tazkarti.test',
            'name'  => 'Platform Admin',
            'role'  => UserRoleEnum::Admin,
        ],
        [
            'email' => 'organizer1@tazkarti.test',
            'name'  => 'Ahmed Organizer',
            'role'  => UserRoleEnum::Organizer,
        ],
        [
            'email' => 'organizer2@tazkarti.test',
            'name'  => 'Sara Organizer',
            'role'  => UserRoleEnum::Organizer,
        ],
        [
            'email' => 'buyer1@tazkarti.test',
            'name'  => 'Mohamed Buyer',
            'role'  => UserRoleEnum::Buyer,
        ],
        [
            'email' => 'buyer2@tazkarti.test',
            'name'  => 'Nour Buyer',
            'role'  => UserRoleEnum::Buyer,
        ],
    ];

   
    private const TARGET_RANDOM_BUYERS = 20;

    public function run(): void
    {
        $password = Hash::make('password');

       
        foreach (self::FIXED_USERS as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'role'              => $data['role']->value,
                    'password'          => $password,
                    'email_verified_at' => now(),
                ],
            );
        }

      
        $fixedEmails  = array_column(self::FIXED_USERS, 'email');
        $existingCount = User::where('role', UserRoleEnum::Buyer->value)
            ->whereNotIn('email', $fixedEmails)
            ->count();

        $deficit = max(0, self::TARGET_RANDOM_BUYERS - $existingCount);

        if ($deficit > 0) {
            User::factory()
                ->buyer()
                ->count($deficit)
                ->create();
        }

        $this->command->info(sprintf(
            'UserSeeder: %d fixed users ensured, %d random buyers created (%d total buyers in DB).',
            count(self::FIXED_USERS),
            $deficit,
            User::where('role', UserRoleEnum::Buyer->value)->count(),
        ));
    }
}
