<?php

namespace Tests\Feature\Article;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    /** Client role gets an empty article list (no clause library access). */
    public function test_client_gets_empty_article_list(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/articles')
            ->assertOk()
            ->assertJson(['success' => true, 'data' => []]);
    }

    /** Domiciliataire only sees their own tenant-scoped articles (IDOR guard). */
    public function test_articles_are_tenant_scoped(): void
    {
        $tenantA = User::factory()->create(['role' => 'domiciliataire']);
        $tenantB = User::factory()->create(['role' => 'domiciliataire']);

        Article::create(['domiciliataire_id' => $tenantA->id, 'title' => 'A1', 'body' => 'Body A', 'is_active' => true]);
        Article::create(['domiciliataire_id' => $tenantB->id, 'title' => 'B1', 'body' => 'Body B', 'is_active' => true]);

        $res = $this->actingAs($tenantA, 'sanctum')->getJson('/api/articles');

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('A1', $res->json('data.0.title'));
    }

    /** Domiciliataire can create an article, auto-scoped to their own tenant id. */
    public function test_domiciliataire_can_create_article(): void
    {
        $user = User::factory()->create(['role' => 'domiciliataire']);

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/articles', [
            'title' => 'ARTICLE 1 — DURÉE',
            'body' => 'Le présent contrat est prévu pour {{duree_mois}} mois.',
            'is_active' => true,
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('articles', [
            'domiciliataire_id' => $user->id,
            'title' => 'ARTICLE 1 — DURÉE',
        ]);
    }

    /** A tenant cannot update another tenant's article (IDOR). */
    public function test_cannot_update_other_tenants_article(): void
    {
        $owner = User::factory()->create(['role' => 'domiciliataire']);
        $intruder = User::factory()->create(['role' => 'domiciliataire']);

        $article = Article::create([
            'domiciliataire_id' => $owner->id,
            'title' => 'Original',
            'body' => 'Body',
            'is_active' => true,
        ]);

        $this->actingAs($intruder, 'sanctum')
            ->putJson("/api/articles/{$article->id}", [
                'title' => 'Hacked',
                'body' => 'Hacked body',
                'is_active' => true,
            ])
            ->assertStatus(404); // forTenant() scope -> findOrFail 404
    }

    /** Deleting an article removes it permanently. */
    public function test_domiciliataire_can_delete_article(): void
    {
        $user = User::factory()->create(['role' => 'domiciliataire']);
        $article = Article::create([
            'domiciliataire_id' => $user->id,
            'title' => 'To Delete',
            'body' => 'Body',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/articles/{$article->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }
}
