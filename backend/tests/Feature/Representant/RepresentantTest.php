<?php

namespace Tests\Feature\Representant;

use App\Models\Entreprise;
use App\Models\Representant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepresentantTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithEntreprise(): array
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'ATLAS IMPORT EXPORT SARL',
            'statut' => 'actif',
        ]);

        return [$tenant, $entreprise];
    }

    /** Creating a representant for the first time succeeds. */
    public function test_can_create_representant(): void
    {
        [$tenant, $entreprise] = $this->tenantWithEntreprise();

        $res = $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/entreprises/{$entreprise->id}/representant", [
                'nom' => 'El Jadiani',
                'prenom' => 'Youssef',
                'cin' => 'BJ422176',
            ]);

        $res->assertCreated();
        $this->assertDatabaseHas('representants', ['entreprise_id' => $entreprise->id, 'cin' => 'BJ422176']);
    }

    /** Cannot create a second representant for the same entreprise (1-to-1 enforced). */
    public function test_cannot_create_duplicate_representant(): void
    {
        [$tenant, $entreprise] = $this->tenantWithEntreprise();

        Representant::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'First',
            'cin' => 'AA111111',
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/entreprises/{$entreprise->id}/representant", [
                'nom' => 'Second',
                'cin' => 'BB222222',
            ])
            ->assertStatus(422);
    }

    /** Update accepts partial payloads (sometimes rule fix). */
    public function test_update_representant_accepts_partial_payload(): void
    {
        [$tenant, $entreprise] = $this->tenantWithEntreprise();

        $rep = Representant::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Original',
            'cin' => 'CC333333',
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->putJson("/api/entreprises/{$entreprise->id}/representant", [
                'telephone' => '+212600000000',
            ])
            ->assertOk();

        $this->assertDatabaseHas('representants', [
            'id' => $rep->id,
            'telephone' => '+212600000000',
            'nom' => 'Original', // unchanged
        ]);
    }

    /** ClientController::index eager-loads representant so the wizard can autofill. */
    public function test_clients_index_includes_representant(): void
    {
        [$tenant, $entreprise] = $this->tenantWithEntreprise();
        Representant::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Gerant',
            'cin' => 'DD444444',
        ]);

        $res = $this->actingAs($tenant, 'sanctum')->getJson('/api/clients');

        $res->assertOk();
        $this->assertNotNull($res->json('data.0.representant'));
        $this->assertEquals('DD444444', $res->json('data.0.representant.cin'));
    }
}
