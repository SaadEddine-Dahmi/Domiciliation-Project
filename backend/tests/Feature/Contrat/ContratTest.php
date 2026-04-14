<?php
// tests/Feature/Contrat/ContratTest.php

namespace Tests\Feature\Contrat;

use Tests\TestCase;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;

class ContratTest extends TestCase
{
    /** @test */
    public function domiciliataire_can_create_contrat(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);

        $this->postJson('/api/contrats', [
            'entreprise_id' => $entreprise->id,
            'date_debut'    => now()->toDateString(),
            'date_fin'      => now()->addYear()->toDateString(),
            'prix_mensuel'  => 500,
            'prix_total'    => 6000,
        ])->assertStatus(201)
          ->assertJsonPath('data.statut', 'draft');
    }

    /** @test */
    public function contrat_always_starts_as_draft(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);

        // Even if active is passed, it should be created as draft
        $response = $this->postJson('/api/contrats', [
            'entreprise_id' => $entreprise->id,
            'date_debut'    => now()->toDateString(),
            'statut'        => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.statut', 'draft');
    }

    /** @test */
    public function domiciliataire_can_list_only_their_contrats(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        Contrat::factory()->count(2)->create([
            'domiciliataire_id' => $owner->id,
            'entreprise_id'     => $entreprise->id,
        ]);

        // Other tenant's contrats
        $other      = User::factory()->domiciliataire()->create();
        $otherEnt   = Entreprise::factory()->create(['domiciliataire_id' => $other->id]);
        Contrat::factory()->create([
            'domiciliataire_id' => $other->id,
            'entreprise_id'     => $otherEnt->id,
        ]);

        $this->getJson('/api/contrats')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function contrat_entreprise_must_belong_to_tenant(): void
    {
        $this->actingAsDomiciliataire();

        // Try to create contrat with another tenant's entreprise
        $other      = User::factory()->domiciliataire()->create();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $other->id]);

        $this->postJson('/api/contrats', [
            'entreprise_id' => $entreprise->id,
            'date_debut'    => now()->toDateString(),
        ])->assertStatus(404);
    }

    /** @test */
    public function can_terminate_active_contrat(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $contrat    = Contrat::factory()->active()->create([
            'domiciliataire_id' => $owner->id,
            'entreprise_id'     => $entreprise->id,
        ]);

        $this->postJson("/api/contrats/{$contrat->id}/terminate")
            ->assertStatus(200);

        $this->assertDatabaseHas('contrats', [
            'id'     => $contrat->id,
            'statut' => 'terminated',
        ]);
    }

    /** @test */
    public function cannot_terminate_draft_contrat(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $contrat    = Contrat::factory()->create([
            'domiciliataire_id' => $owner->id,
            'entreprise_id'     => $entreprise->id,
            'statut'            => 'draft',
        ]);

        $this->postJson("/api/contrats/{$contrat->id}/terminate")
            ->assertStatus(404);
    }

    /** @test */
    public function client_can_only_see_active_contrats(): void
    {
        $domiciliataire = User::factory()->domiciliataire()->create();
        $clientUser     = $this->actingAsClient();
        $entreprise     = Entreprise::factory()->create([
            'domiciliataire_id' => $domiciliataire->id,
            'client_user_id'    => $clientUser->id,
        ]);

        Contrat::factory()->create([
            'domiciliataire_id' => $domiciliataire->id,
            'entreprise_id'     => $entreprise->id,
            'statut'            => 'draft',
        ]);

        Contrat::factory()->active()->create([
            'domiciliataire_id' => $domiciliataire->id,
            'entreprise_id'     => $entreprise->id,
        ]);

        $response = $this->getJson('/api/contrats');
        $response->assertStatus(200);

        // Client should only see the active one
        foreach ($response->json('data') as $contrat) {
            $this->assertEquals('active', $contrat['statut']);
        }
    }
}
