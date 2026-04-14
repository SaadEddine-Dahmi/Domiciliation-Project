<?php
// tests/Feature/Representant/RepresentantTest.php

namespace Tests\Feature\Representant;

use Tests\TestCase;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Models\User;

class RepresentantTest extends TestCase
{
    /** @test */
    public function can_create_representant_for_entreprise(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);

        $this->postJson("/api/entreprises/{$entreprise->id}/representant", [
            'nom'    => 'El Jadiani',
            'prenom' => 'Youssef',
            'cin'    => 'BJ422176',
        ])->assertStatus(201)
          ->assertJsonPath('data.nom', 'El Jadiani');
    }

    /** @test */
    public function cannot_create_second_representant_for_same_entreprise(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);

        Representant::factory()->create(['entreprise_id' => $entreprise->id]);

        $this->postJson("/api/entreprises/{$entreprise->id}/representant", [
            'nom' => 'Another',
            'cin' => 'AB123456',
        ])->assertStatus(422);
    }

    /** @test */
    public function can_get_representant_of_entreprise(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $rep        = Representant::factory()->create(['entreprise_id' => $entreprise->id]);

        $this->getJson("/api/entreprises/{$entreprise->id}/representant")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $rep->id);
    }

    /** @test */
    public function returns_null_data_when_no_representant_exists(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);

        $this->getJson("/api/entreprises/{$entreprise->id}/representant")
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    /** @test */
    public function can_update_representant(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        Representant::factory()->create(['entreprise_id' => $entreprise->id]);

        $this->putJson("/api/entreprises/{$entreprise->id}/representant", [
            'nom'    => 'Updated',
            'prenom' => 'Name',
            'cin'    => 'XY999999',
        ])->assertStatus(200)
          ->assertJsonPath('data.nom', 'Updated');
    }

    /** @test */
    public function can_delete_representant(): void
    {
        $owner      = $this->actingAsDomiciliataire();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $owner->id]);
        $rep        = Representant::factory()->create(['entreprise_id' => $entreprise->id]);

        $this->deleteJson("/api/entreprises/{$entreprise->id}/representant")
            ->assertStatus(200);

        $this->assertDatabaseMissing('representants', ['id' => $rep->id]);
    }

    /** @test */
    public function cannot_access_representant_of_another_tenants_entreprise(): void
    {
        $this->actingAsDomiciliataire();

        $other      = User::factory()->domiciliataire()->create();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $other->id]);
        Representant::factory()->create(['entreprise_id' => $entreprise->id]);

        $this->getJson("/api/entreprises/{$entreprise->id}/representant")
            ->assertStatus(404);
    }
}
