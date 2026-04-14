<?php
// database/factories/ContratFactory.php

namespace Database\Factories;

use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContratFactory extends Factory
{
    protected $model = Contrat::class;

    public function definition(): array
    {
        $domiciliataire = User::factory()->domiciliataire()->create();
        $entreprise     = Entreprise::factory()->create([
            'domiciliataire_id' => $domiciliataire->id,
        ]);

        return [
            'domiciliataire_id'        => $domiciliataire->id,
            'entreprise_id'            => $entreprise->id,
            'date_debut'               => now()->toDateString(),
            'date_fin'                 => now()->addYear()->toDateString(),
            'duree_mois'               => 12,
            'prix_mensuel'             => 500.00,
            'prix_total'               => 6000.00,
            'statut'                   => 'draft',
            'notification_delay_months'=> 1,
        ];
    }

    public function active(): static
    {
        return $this->state(['statut' => 'active']);
    }

    public function expired(): static
    {
        return $this->state([
            'statut'   => 'expired',
            'date_fin' => now()->subDay()->toDateString(),
        ]);
    }
}