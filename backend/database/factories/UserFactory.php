<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        return [
            'nom'       => $this->faker->lastName(),
            'prenom'    => $this->faker->firstName(),
            'email'     => $this->faker->unique()->safeEmail(),
            'password'  => Hash::make('password123'),
            'telephone' => $this->faker->phoneNumber(),
            'role'      => 'domiciliataire',
            'status'    => 'active',
        ];
    }

    /** Pending domiciliataire account awaiting admin approval. */
    public function pending(): static
    {
        return $this->state(fn () => ['role' => 'domiciliataire', 'status' => 'pending']);
    }

    /** Approved but not yet auto-activated (activation_date in the future). */
    public function approved(): static
    {
        return $this->state(fn () => [
            'status'          => 'approved',
            'activation_date' => now()->addDay(),
        ]);
    }

    /** Explicit domiciliataire role — used when a test needs "another tenant". */
    public function domiciliataire(): static
    {
        return $this->state(fn () => ['role' => 'domiciliataire', 'status' => 'active']);
    }

    /** Explicit client role. */
    public function client(): static
    {
        return $this->state(fn () => ['role' => 'client', 'status' => 'active']);
    }

    /** Explicit admin role. */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin', 'status' => 'active']);
    }
}
