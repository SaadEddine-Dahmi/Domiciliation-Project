<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EntrepriseFactory extends Factory
{
    protected $model = \App\Models\Entreprise::class;

    public function definition(): array
    {
        return [
            'raison_sociale'  => $this->faker->company(),
            'forme_juridique' => $this->faker->randomElement(['SARL', 'SA', 'SAS']),
            'adresse'         => $this->faker->address(),
            'ville'           => $this->faker->city(),
            'pays'            => 'Maroc',
            'capital'         => $this->faker->numberBetween(10000, 500000),
            'date_creation'   => $this->faker->date(),
            'statut'          => 'actif',
            // domiciliataire_id is always overridden explicitly in tests via
            // Entreprise::factory()->create(['domiciliataire_id' => $owner->id])
        ];
    }
}
