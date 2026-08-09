<?php

namespace Tests\Feature\Representant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomiciliaireProfileTest extends TestCase
{
    use RefreshDatabase;

    /** A client user cannot access the domiciliataire profile endpoint. */
    public function test_client_cannot_view_profile(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/profile')
            ->assertStatus(403);
    }

    // Domiciliataire can update the company profile, including the
    // dynamic addresses array. The assertion now checks
    // domiciliataire_profiles (not users), since that's where
    // nom_societe/rc/if_fiscal/etc. actually persist after the split.
    public function test_domiciliataire_can_update_profile_with_addresses(): void
    {
        $user = User::factory()->create(['role' => 'domiciliataire']);

        $payload = [
            'nom_societe' => 'Ma Société de Domiciliation',
            'representant_legal' => 'Jean Dupont',
            'identite_representant' => 'AB123456',
            'rc' => '123456',
            'if_fiscal' => '789012',
            'tp' => '345678',
            'adresses' => [
                ['label' => 'Siège social', 'value' => '10 Rue Exemple, Casablanca'],
            ],
        ];

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'profile_complete' => true]);

        $this->assertDatabaseHas('domiciliataire_profiles', [
            'user_id' => $user->id,
            'nom_societe' => 'Ma Société de Domiciliation',
        ]);
    }

    /** hasCompleteProfile() correctly reflects missing fields. */
    public function test_profile_incomplete_when_fields_missing(): void
    {
        $user = User::factory()->create(['role' => 'domiciliataire']);

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $res->assertOk()->assertJsonPath('data.profile_complete', false);
    }
}