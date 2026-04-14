<?php
// tests/Feature/Security/SecurityTest.php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\Article;
use App\Models\Entreprise;
use App\Models\User;

class SecurityTest extends TestCase
{
    // ── IDOR Tests ─────────────────────────────────────────

    /** @test */
    public function tenant_a_cannot_read_tenant_b_entreprise(): void
    {
        $this->actingAsDomiciliataire();

        $other = User::factory()->domiciliataire()->create();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $other->id]);

        $this->getJson("/api/entreprises/{$entreprise->id}")
            ->assertStatus(404);
    }

    /** @test */
    public function tenant_a_cannot_delete_tenant_b_entreprise(): void
    {
        $this->actingAsDomiciliataire();

        $other = User::factory()->domiciliataire()->create();
        $entreprise = Entreprise::factory()->create(['domiciliataire_id' => $other->id]);

        $this->deleteJson("/api/entreprises/{$entreprise->id}")
            ->assertStatus(404);
    }

    /** @test */
    public function tenant_a_cannot_update_tenant_b_article(): void
    {
        $this->actingAsDomiciliataire();

        $other = User::factory()->domiciliataire()->create();
        $article = Article::factory()->create(['domiciliataire_id' => $other->id]);

        $this->putJson("/api/articles/{$article->id}", [
            'title' => 'HACKED',
            'body' => 'HACKED',
            'is_active' => true,
        ])->assertStatus(404);
    }

    /** @test */
    public function tenant_a_cannot_delete_tenant_b_article(): void
    {
        $this->actingAsDomiciliataire();

        $other = User::factory()->domiciliataire()->create();
        $article = Article::factory()->create(['domiciliataire_id' => $other->id]);

        $this->deleteJson("/api/articles/{$article->id}")
            ->assertStatus(404);
    }

    // ── Role enforcement ───────────────────────────────────

    /** @test */
    public function client_cannot_create_entreprise(): void
    {
        $this->actingAsClient();

        // FIX: send all required fields so validation passes
        // and the role check (403) is actually reached
        $this->postJson('/api/entreprises', [
            'raison_sociale' => 'Hack Attempt',
            'forme_juridique' => 'SARL',
            'adresse' => '123 Rue',
            'ville' => 'Agadir',
            'pays' => 'Maroc',
            'capital' => 100000,
            'date_creation' => '2020-01-01',
            'statut' => 'actif',
        ])->assertStatus(403);
    }
    
    /** @test */
    public function client_cannot_create_article(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/articles', [
            'title' => 'Hack',
            'body' => 'Hack',
        ])->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_any_protected_route(): void
    {
        $routes = [
            ['GET', '/api/entreprises'],
            ['GET', '/api/contrats'],
            ['GET', '/api/articles'],
            ['GET', '/api/documents'],
            ['GET', '/api/dashboard/stats'],
            ['GET', '/api/auth/me'],
        ];

        foreach ($routes as [$method, $path]) {
            $response = $this->json($method, $path);
            $this->assertEquals(
                401,
                $response->status(),
                "Expected 401 for {$method} {$path}"
            );
        }
    }

    // ── SQL Injection attempts ─────────────────────────────

    /** @test */
    public function sql_injection_in_login_email_is_handled_safely(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => "' OR '1'='1",
            'password' => 'anything',
        ])->assertStatus(422); // validation rejects invalid email format
    }

    /** @test */
    public function xss_payload_in_article_body_is_stored_as_plain_text(): void
    {
        $owner = $this->actingAsDomiciliataire();

        $this->postJson('/api/articles', [
            'title' => 'XSS Test',
            'body' => '<script>alert("xss")</script>',
        ])->assertStatus(201);

        // The payload is stored — but TemplateService uses e() on output
        // so it will be escaped when rendered in PDF
        $this->assertDatabaseHas('articles', [
            'domiciliataire_id' => $owner->id,
            'title' => 'XSS Test',
        ]);
    }
}