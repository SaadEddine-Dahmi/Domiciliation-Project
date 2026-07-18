<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates an admin user, authenticates as them via Sanctum, and returns
     * the user instance so the test can use its id/attributes.
     */
    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'admin', 'status' => 'active'], $attributes));
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    /**
     * Creates a domiciliataire user, authenticates as them, and returns the instance.
     * This is the "tenant owner" in most tests.
     */
    protected function actingAsDomiciliataire(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'domiciliataire', 'status' => 'active'], $attributes));
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    /**
     * Creates a client-role user, authenticates as them, and returns the instance.
     */
    protected function actingAsClient(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'client', 'status' => 'active'], $attributes));
        $this->actingAs($user, 'sanctum');
        return $user;
    }
}
