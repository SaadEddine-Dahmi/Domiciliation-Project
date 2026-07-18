<?php

namespace Tests\Feature\Admin;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /** Non-admin cannot access the domiciliataires listing. */
    public function test_non_admin_cannot_list_domiciliataires(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);

        $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/admin/domiciliataires')
            ->assertStatus(403);
    }

    /** Admin sees domiciliataires with counts, but never sensitive data (CIN, password). */
    public function test_admin_list_excludes_sensitive_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tenant = User::factory()->create(['role' => 'domiciliataire', 'nom' => 'Dahmi']);
        Entreprise::create(['domiciliataire_id' => $tenant->id, 'raison_sociale' => 'CLIENT SARL']);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/domiciliataires');

        $res->assertOk();
        $row = collect($res->json('data'))->firstWhere('id', $tenant->id);

        $this->assertEquals(1, $row['entreprises_count']);
        $this->assertArrayNotHasKey('cin', $row);
        $this->assertArrayNotHasKey('password', $row);
    }
}
