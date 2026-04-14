<?php
// tests/Feature/Contrat/ContratStateMachineTest.php

namespace Tests\Feature\Contrat;

use Tests\TestCase;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;

class ContratStateMachineTest extends TestCase
{
    /** @test */
    public function activate_transitions_contrat_from_draft_to_active(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $contrat    = Contrat::factory()->create([
            'domiciliataire_id' => $owner->id,
            'entreprise_id'     => $entreprise->id,
            'statut'            => 'draft',
            'date_fin'          => now()->addYear()->toDateString(),
        ]);

        $contrat->activate();

        $this->assertDatabaseHas('contrats', [
            'id'     => $contrat->id,
            'statut' => 'active',
        ]);
    }

    /** @test */
    public function activate_creates_alerte_record(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $contrat    = Contrat::factory()->create([
            'domiciliataire_id'        => $owner->id,
            'entreprise_id'            => $entreprise->id,
            'statut'                   => 'draft',
            'date_fin'                 => now()->addYear()->toDateString(),
            'notification_delay_months'=> 1,
        ]);

        $contrat->activate();

        $this->assertDatabaseHas('alertes', [
            'contrat_id' => $contrat->id,
            'envoye'     => false,
        ]);
    }

    /** @test */
    public function expire_transitions_active_contrat_to_expired(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $contrat    = Contrat::factory()->active()->create([
            'domiciliataire_id' => $owner->id,
            'entreprise_id'     => $entreprise->id,
        ]);

        $contrat->expire();

        $this->assertDatabaseHas('contrats', [
            'id'     => $contrat->id,
            'statut' => 'expired',
        ]);
    }

    /** @test */
    public function terminate_transitions_active_contrat_to_terminated(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $contrat    = Contrat::factory()->active()->create([
            'domiciliataire_id' => $owner->id,
            'entreprise_id'     => $entreprise->id,
        ]);

        $contrat->terminate();

        $this->assertDatabaseHas('contrats', [
            'id'     => $contrat->id,
            'statut' => 'terminated',
        ]);
    }
}