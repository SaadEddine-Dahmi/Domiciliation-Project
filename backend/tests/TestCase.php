<?php
// tests/TestCase.php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // ── Helpers ────────────────────────────────────────────

    /**
     * Create and authenticate a user with a given role.
     * Returns the user with Sanctum token set.
     */
    protected function actingAsRole(string $role, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role'   => $role,
            'status' => 'active',
        ], $overrides));

        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsDomiciliataire(array $overrides = []): User
    {
        return $this->actingAsRole('domiciliataire', $overrides);
    }

    protected function actingAsClient(array $overrides = []): User
    {
        return $this->actingAsRole('client', $overrides);
    }

    protected function actingAsAdmin(array $overrides = []): User
    {
        return $this->actingAsRole('admin', $overrides);
    }
}
