<?php

namespace Database\Factories;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class UserFactory extends Factory
{
    protected static ?string $password;

   
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'role'              => UserRoleEnum::Buyer->value,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

   
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRoleEnum::Admin->value,
        ]);
    }

    public function organizer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRoleEnum::Organizer->value,
        ]);
    }

    
    public function buyer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRoleEnum::Buyer->value,
        ]);
    }
}
