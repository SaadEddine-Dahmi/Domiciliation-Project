<?php
// tests/Unit/UserModelTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;

class UserModelTest extends TestCase
{
    /** @test */
    public function active_user_with_no_activation_date_is_active(): void
    {
        $user = User::factory()->make([
            'status'          => 'active',
            'activation_date' => null,
        ]);

        $this->assertTrue($user->isActive());
    }

    /** @test */
    public function active_user_with_past_activation_date_is_active(): void
    {
        $user = User::factory()->make([
            'status'          => 'active',
            'activation_date' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($user->isActive());
    }

    /** @test */
    public function active_user_with_future_activation_date_is_not_active(): void
    {
        $user = User::factory()->make([
            'status'          => 'active',
            'activation_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertFalse($user->isActive());
    }

    /** @test */
    public function pending_user_is_not_active(): void
    {
        $user = User::factory()->make(['status' => 'pending']);
        $this->assertFalse($user->isActive());
    }

    /** @test */
    public function rejected_user_is_not_active(): void
    {
        $user = User::factory()->make(['status' => 'rejected']);
        $this->assertFalse($user->isActive());
    }

    /** @test */
    public function role_helpers_return_correct_values(): void
    {
        $admin  = User::factory()->make(['role' => 'admin']);
        $domici = User::factory()->make(['role' => 'domiciliataire']);
        $client = User::factory()->make(['role' => 'client']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isDomiciliataire());

        $this->assertTrue($domici->isDomiciliataire());
        $this->assertFalse($domici->isClient());

        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isAdmin());
    }
}