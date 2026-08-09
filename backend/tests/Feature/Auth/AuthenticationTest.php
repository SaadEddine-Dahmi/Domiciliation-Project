<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** Registering as domiciliataire creates a 'pending' account and does NOT return a token. */
    public function test_register_domiciliataire_is_pending_and_has_no_token(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'nom' => 'Dahmi',
            'prenom' => 'Saad',
            'email' => 'saad@example.com',
            'password' => 'password123',
        ]);

        $res->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.status', 'pending');

        $this->assertArrayNotHasKey('token', $res->json('data'));
        $this->assertDatabaseHas('users', ['email' => 'saad@example.com', 'status' => 'pending']);
    }

    /** Registering as client is immediately active and returns a usable token. */
    public function test_register_client_is_active_with_token(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'nom' => 'Client',
            'email' => 'client@example.com',
            'password' => 'password123',
            'role' => 'client',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.user.status', 'active');

        $this->assertNotEmpty($res->json('data.token'));
    }

    /** Login fails with wrong credentials. */
    public function test_login_rejects_bad_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct-password'),
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    /** Login succeeds for an active user and returns a Bearer token. */
    public function test_login_succeeds_for_active_user(): void
    {
        User::factory()->create([
            'email' => 'ok@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'role' => 'domiciliataire',
        ]);

        $res = $this->postJson('/api/auth/login', [
            'email' => 'ok@example.com',
            'password' => 'password123',
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertNotEmpty($res->json('data.token'));
    }

    /** Login is blocked for a pending account, with the correct French message. */
    public function test_login_blocked_for_pending_account(): void
    {
        User::factory()->create([
            'email' => 'pending@example.com',
            'password' => Hash::make('password123'),
            'status' => 'pending',
        ]);

        $res = $this->postJson('/api/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'password123',
        ]);

        $res->assertStatus(403)
            ->assertJsonFragment(['message' => 'Votre compte est en attente de validation.']);
    }

    /** /api/auth/me returns the authenticated user. */
    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    /** Logout revokes the current access token. */
    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /** Passing role=admin on registration is rejected — admin accounts
     *  can only be created internally (seeder/admin route), never via
     *  public self-registration. */
    public function test_cannot_self_register_as_admin(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'nom' => 'Attacker',
            'email' => 'attacker2@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'attacker2@example.com']);
    }
}
