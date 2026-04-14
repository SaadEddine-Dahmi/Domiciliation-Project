<?php
// tests/Feature/Dashboard/DashboardTest.php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;

class DashboardTest extends TestCase
{
    /** @test */
    public function admin_dashboard_returns_global_stats(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/dashboard/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonStructure(['data' => [
                'total_domiciliataires',
                'total_clients',
                'total_contrats',
                'contrats_actifs',
            ]]);
    }

    /** @test */
    public function domiciliataire_dashboard_returns_tenant_scoped_stats(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        Contrat::factory()->active()->create([
            'domiciliataire_id' => $owner->id,
            'entreprise_id'     => $entreprise->id,
        ]);

        $this->getJson('/api/dashboard/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'domiciliataire')
            ->assertJsonPath('data.total_clients', 1)
            ->assertJsonPath('data.contrats_actifs', 1);
    }

    /** @test */
    public function client_dashboard_returns_their_contrat_info(): void
    {
        $domiciliataire = User::factory()->domiciliataire()->create();
        $clientUser     = $this->actingAsClient();
        $entreprise     = Entreprise::factory()->create([
            'domiciliataire_id' => $domiciliataire->id,
            'client_user_id'    => $clientUser->id,
        ]);

        Contrat::factory()->active()->create([
            'domiciliataire_id' => $domiciliataire->id,
            'entreprise_id'     => $entreprise->id,
        ]);

        $this->getJson('/api/dashboard/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'client')
            ->assertJsonStructure(['data' => ['entreprise', 'contrat', 'domiciliataire']]);
    }

    /** @test */
    public function unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }
}
