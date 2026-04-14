<?php
// database/factories/UserFactory.php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            // FIX: varchar(13) — use short fixed format, never faker phone
            'telephone' => '+212600000000',
            'role' => 'domiciliataire',
            'status' => 'active',
        ];
    }

    public function domiciliataire(): static
    {
        return $this->state(['role' => 'domiciliataire', 'status' => 'active']);
    }

    public function client(): static
    {
        return $this->state(['role' => 'client', 'status' => 'active']);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin', 'status' => 'active']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => 'rejected',
            'rejection_reason' => 'Dossier incomplet.',
        ]);
    }
}