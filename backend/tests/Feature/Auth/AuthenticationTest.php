<?php
// tests/Feature/Auth/AuthenticationTest.php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends TestCase
{
    // ── Register ───────────────────────────────────────────

    /** @test */
    public function domiciliataire_can_register_and_starts_as_pending(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nom'      => 'Dahmi',
            'prenom'   => 'Saad',
            'email'    => 'saad@test.ma',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.status', 'pending');

        // Token should NOT be returned for pending users
        $response->assertJsonMissing(['token']);

        $this->assertDatabaseHas('users', [
            'email'  => 'saad@test.ma',
            'status' => 'pending',
            'role'   => 'domiciliataire',
        ]);
    }

    /** @test */
    public function register_requires_valid_email(): void
    {
        $this->postJson('/api/auth/register', [
            'nom'      => 'Test',
            'email'    => 'not-an-email',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    /** @test */
    public function register_requires_minimum_password_length(): void
    {
        $this->postJson('/api/auth/register', [
            'nom'      => 'Test',
            'email'    => 'test@test.ma',
            'password' => '123',
        ])->assertStatus(422);
    }

    /** @test */
    public function duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'existing@test.ma']);

        $this->postJson('/api/auth/register', [
            'nom'      => 'Test',
            'email'    => 'existing@test.ma',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    // ── Login ──────────────────────────────────────────────

    /** @test */
    public function active_user_can_login_and_receives_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'active@test.ma',
            'password' => Hash::make('password123'),
            'status'   => 'active',
            'role'     => 'domiciliataire',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'active@test.ma',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    /** @test */
    public function login_is_case_insensitive_for_email(): void
    {
        User::factory()->create([
            'email'    => 'user@test.ma',
            'password' => Hash::make('password123'),
            'status'   => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'USER@TEST.MA',
            'password' => 'password123',
        ])->assertStatus(200);
    }

    /** @test */
    public function wrong_password_returns_422(): void
    {
        User::factory()->create([
            'email'    => 'user@test.ma',
            'password' => Hash::make('correct'),
            'status'   => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'user@test.ma',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    /** @test */
    public function pending_user_cannot_login(): void
    {
        User::factory()->create([
            'email'    => 'pending@test.ma',
            'password' => Hash::make('password123'),
            'status'   => 'pending',
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'pending@test.ma',
            'password' => 'password123',
        ])->assertStatus(403)
          ->assertJsonPath('success', false);
    }

    /** @test */
    public function rejected_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'rejected@test.ma',
            'password' => Hash::make('password123'),
            'status' => 'rejected',
            'rejection_reason' => 'Dossier incomplet.',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'rejected@test.ma',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            // FIX: check inside the message string, not as a fragment array
            ->assertJsonFragment(['message' => 'Votre compte a été rejeté. Raison : Dossier incomplet.']);
    }

    /** @test */
    public function approved_user_with_future_activation_date_cannot_login(): void
    {
        User::factory()->create([
            'email'           => 'approved@test.ma',
            'password'        => Hash::make('password123'),
            'status'          => 'approved',
            'activation_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'approved@test.ma',
            'password' => 'password123',
        ])->assertStatus(403);
    }

    /** @test */
    public function approved_user_auto_activates_when_activation_date_passed(): void
    {
        $user = User::factory()->create([
            'email'           => 'autoactivate@test.ma',
            'password'        => Hash::make('password123'),
            'status'          => 'approved',
            'activation_date' => now()->subDay()->toDateString(),
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'autoactivate@test.ma',
            'password' => 'password123',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'     => $user->id,
            'status' => 'active',
        ]);
    }

    // ── Me / Logout ────────────────────────────────────────

    /** @test */
    public function authenticated_user_can_get_their_profile(): void
    {
        $user = $this->actingAsDomiciliataire();

        $this->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    /** @test */
    public function unauthenticated_request_to_me_returns_401(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    /** @test */
    public function user_can_logout(): void
    {
        $this->actingAsDomiciliataire();

        $this->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
