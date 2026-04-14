<?php
// tests/Unit/ContratModelTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;

class ContratModelTest extends TestCase
{
    private function makeContrat(array $attrs = []): Contrat
    {
        $owner = User::factory()->domiciliataire()->create();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);

        return Contrat::factory()->create(array_merge([
            'domiciliataire_id' => $owner->id,
            'entreprise_id' => $entreprise->id,
        ], $attrs));
    }

    /** @test */
    public function active_contrat_is_visible_to_client(): void
    {
        $contrat = $this->makeContrat(['statut' => 'active']);
        $this->assertTrue($contrat->isVisibleToClient());
    }

    /** @test */
    public function draft_contrat_is_not_visible_to_client(): void
    {
        $contrat = $this->makeContrat(['statut' => 'draft']);
        $this->assertFalse($contrat->isVisibleToClient());
    }

    /** @test */
    public function expired_contrat_is_not_visible_to_client(): void
    {
        $contrat = $this->makeContrat(['statut' => 'expired']);
        $this->assertFalse($contrat->isVisibleToClient());
    }

    /** @test */
    public function activate_sets_status_to_active(): void
    {
        $contrat = $this->makeContrat([
            'statut' => 'draft',
            'date_fin' => now()->addYear()->toDateString(),
        ]);

        $contrat->activate();
        $this->assertEquals('active', $contrat->fresh()->statut);
    }

    /** @test */
    public function activate_sets_next_alert_date(): void
    {
        $contrat = $this->makeContrat([
            'statut' => 'draft',
            'date_fin' => now()->addYear()->toDateString(),
            'notification_delay_months' => 1,
        ]);

        $contrat->activate();

        $expected = now()->addYear()->subMonth()->toDateString();
        $this->assertEquals($expected, $contrat->fresh()->next_alert_date->toDateString());
    }

    /** @test */
    public function expire_sets_status_to_expired(): void
    {
        $contrat = $this->makeContrat(['statut' => 'active']);
        $contrat->expire();
        $this->assertEquals('expired', $contrat->fresh()->statut);
    }

    /** @test */
    public function terminate_sets_status_to_terminated(): void
    {
        $contrat = $this->makeContrat(['statut' => 'active']);
        $contrat->terminate();
        $this->assertEquals('terminated', $contrat->fresh()->statut);
    }
}