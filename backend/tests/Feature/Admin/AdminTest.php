<?php
// tests/Feature/Admin/AdminTest.php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Entreprise;

class AdminTest extends TestCase
{
    /** @test */
    public function admin_can_list_all_domiciliataires(): void
    {
        $this->actingAsAdmin();
        User::factory()->domiciliataire()->count(3)->create();

        $this->getJson('/api/admin/domiciliataires')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function domiciliataire_cannot_access_admin_domiciliataires_list(): void
    {
        $this->actingAsDomiciliataire();

        $this->getJson('/api/admin/domiciliataires')
            ->assertStatus(403);
    }

    /** @test */
    public function admin_response_does_not_include_passwords(): void
    {
        $this->actingAsAdmin();
        User::factory()->domiciliataire()->create();

        $response = $this->getJson('/api/admin/domiciliataires');

        $response->assertStatus(200);

        // Verify no password field in any user record
        foreach ($response->json('data') as $user) {
            $this->assertArrayNotHasKey('password', $user);
        }
    }
}