<?php
// tests/Feature/Auth/ActivationTest.php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;

class ActivationTest extends TestCase
{
    /** @test */
    public function admin_can_list_pending_users(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(3)->pending()->create();

        $this->getJson('/api/admin/users/pending')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function non_admin_cannot_list_pending_users(): void
    {
        $this->actingAsDomiciliataire();

        $this->getJson('/api/admin/users/pending')
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_approve_pending_user(): void
    {
        $this->actingAsAdmin();
        $pending = User::factory()->pending()->create();

        $this->postJson("/api/admin/users/{$pending->id}/approve", [
            'activation_date' => now()->toDateString(),
        ])->assertStatus(200)
          ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id'     => $pending->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function admin_can_reject_pending_user_with_reason(): void
    {
        $this->actingAsAdmin();
        $pending = User::factory()->pending()->create();

        $this->postJson("/api/admin/users/{$pending->id}/reject", [
            'reason' => 'Documents manquants.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'               => $pending->id,
            'status'           => 'rejected',
            'rejection_reason' => 'Documents manquants.',
        ]);
    }

    /** @test */
    public function approve_requires_activation_date(): void
    {
        $this->actingAsAdmin();
        $pending = User::factory()->pending()->create();

        $this->postJson("/api/admin/users/{$pending->id}/approve", [])
            ->assertStatus(422);
    }

    /** @test */
    public function reject_requires_reason(): void
    {
        $this->actingAsAdmin();
        $pending = User::factory()->pending()->create();

        $this->postJson("/api/admin/users/{$pending->id}/reject", [])
            ->assertStatus(422);
    }
}
