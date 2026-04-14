<?php
// tests/Feature/Entreprise/EntrepriseTest.php

namespace Tests\Feature\Entreprise;

use Tests\TestCase;
use App\Models\Entreprise;
use App\Models\User;

class EntrepriseTest extends TestCase
{
    /** @test */
    public function domiciliataire_can_list_only_their_entreprises(): void
    {
        $owner = $this->actingAsDomiciliataire();
        Entreprise::factory()->count(3)->create(['domiciliataire_id' => $owner->id]);

        $other = User::factory()->domiciliataire()->create();
        Entreprise::factory()->count(2)->create(['domiciliataire_id' => $other->id]);

        $response = $this->getJson('/api/entreprises');
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function domiciliataire_can_create_entreprise(): void
    {
        $owner = $this->actingAsDomiciliataire();

        $this->postJson('/api/entreprises', [
            'raison_sociale' => 'BRONX IMMOBILIER',
            'forme_juridique' => 'SARL',
            'adresse' => '123 Rue Hassan II',
            'ville' => 'Agadir',
            'pays' => 'Maroc',
            'capital' => 100000,
            'date_creation' => '2020-01-01',
            'statut' => 'actif',
        ])->assertStatus(201)
            // FIX: EntrepriseResource wraps in 'data'
            ->assertJsonPath('data.raison_sociale', 'BRONX IMMOBILIER');

        $this->assertDatabaseHas('entreprises', [
            'raison_sociale' => 'BRONX IMMOBILIER',
            'domiciliataire_id' => $owner->id,
        ]);
    }

    /** @test */
    public function domiciliataire_can_update_their_entreprise(): void
    {
        $owner = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create([
            'domiciliataire_id' => $owner->id,
            'raison_sociale' => 'Old Name',
        ]);

        $this->putJson("/api/entreprises/{$entreprise->id}", [
            'raison_sociale' => 'New Name',
            'adresse' => '123 Rue',
            'capital' => 100000,
            'date_creation' => '2020-01-01',
        ])->assertStatus(200)
            // FIX: EntrepriseResource wraps in 'data'
            ->assertJsonPath('data.raison_sociale', 'New Name');
    }

    /** @test */
    public function raison_sociale_is_required(): void
    {
        $this->actingAsDomiciliataire();

        $this->postJson('/api/entreprises', [
            'adresse'       => '123 Rue',
            'capital'       => 100000,
            'date_creation' => '2020-01-01',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['raison_sociale']);
    }

   
    /** @test */
    public function domiciliataire_cannot_update_another_tenants_entreprise(): void
    {
        $this->actingAsDomiciliataire();

        $other      = User::factory()->domiciliataire()->create();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $other->id]);

        $this->putJson("/api/entreprises/{$entreprise->id}", [
            'raison_sociale' => 'Hacked',
            'adresse'        => '123 Rue',
            'capital'        => 100000,
            'date_creation'  => '2020-01-01',
        ])->assertStatus(404);
    }

    /** @test */
    public function domiciliataire_can_delete_their_entreprise(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);

        $this->deleteJson("/api/entreprises/{$entreprise->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('entreprises', ['id' => $entreprise->id]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_entreprises(): void
    {
        $this->getJson('/api/entreprises')->assertStatus(401);
    }
}