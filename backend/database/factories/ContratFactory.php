<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContratFactory extends Factory
{
    protected $model = \App\Models\Contrat::class;

    public function definition(): array
    {
        return [
            'titre_contrat'  => 'Contrat de Domiciliation',
            'date_debut'     => now()->toDateString(),
            'date_fin'       => now()->addYear()->toDateString(),
            'duree_mois'     => 12,
            'prix_mensuel'   => 500,
            'prix_total'     => 6000,
            'statut'         => 'draft',
            // domiciliataire_id / entreprise_id are always overridden explicitly
            // in tests since they must point at real, related rows.
        ];
    }

    /** Contract already in the 'active' lifecycle state. */
    public function active(): static
    {
        return $this->state(fn () => ['statut' => 'active']);
    }

    /** Contract already expired. */
    public function expired(): static
    {
        return $this->state(fn () => ['statut' => 'expired']);
    }

    /** Contract already terminated. */
    public function terminated(): static
    {
        return $this->state(fn () => ['statut' => 'terminated']);
    }
}
