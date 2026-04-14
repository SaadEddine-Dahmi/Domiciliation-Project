<?php
// database/factories/EntrepriseFactory.php

namespace Database\Factories;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntrepriseFactory extends Factory
{
    protected $model = Entreprise::class;

    public function definition(): array
    {
        return [
            'domiciliataire_id' => User::factory()->domiciliataire(),
            'raison_sociale' => fake()->company(),
            'forme_juridique' => fake()->randomElement(['SARL', 'SA', 'SAS']),
            'adresse' => fake()->streetAddress(),
            'ville' => fake()->city(),
            'pays' => 'Maroc',
            'capital' => 100000.00,
            'date_creation' => fake()->date(),
            'statut' => 'actif',
        ];
    }
}