<?php
// database/factories/RepresentantFactory.php

namespace Database\Factories;

use App\Models\Representant;
use App\Models\Entreprise;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepresentantFactory extends Factory
{
    protected $model = Representant::class;

    public function definition(): array
    {
        return [
            'entreprise_id'  => Entreprise::factory(),
            'nom'            => fake()->lastName(),
            'prenom'         => fake()->firstName(),
            'cin'            => strtoupper(fake()->bothify('??######')),
            'nationalite'    => 'Marocaine',
            'date_naissance' => fake()->date('Y-m-d', '-25 years'),
            'adresse'        => fake()->address(),
            'telephone'      => '+212600000000',
            'email'          => fake()->safeEmail(),
        ];
    }
}
