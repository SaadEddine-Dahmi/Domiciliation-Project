<?php
// tests/Feature/Article/ArticleTest.php

namespace Tests\Feature\Article;

use Tests\TestCase;
use App\Models\Article;
use App\Models\User;

class ArticleTest extends TestCase
{
    /** @test */
    public function domiciliataire_can_list_only_their_articles(): void
    {
        $owner = $this->actingAsDomiciliataire();
        Article::factory()->count(3)->create(['domiciliataire_id' => $owner->id]);

        // Other tenant's articles
        $other = User::factory()->domiciliataire()->create();
        Article::factory()->count(2)->create(['domiciliataire_id' => $other->id]);

        $this->getJson('/api/articles')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function client_gets_empty_article_list(): void
    {
        $this->actingAsClient();

        $this->getJson('/api/articles')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function domiciliataire_can_create_article(): void
    {
        $owner = $this->actingAsDomiciliataire();

        $this->postJson('/api/articles', [
            'title'     => 'ARTICLE 1 — DURÉE',
            'body'      => 'Le contrat est valable pour {{duree_mois}} mois.',
            'is_active' => true,
        ])->assertStatus(201)
          ->assertJsonPath('data.title', 'ARTICLE 1 — DURÉE');

        $this->assertDatabaseHas('articles', [
            'domiciliataire_id' => $owner->id,
            'title'             => 'ARTICLE 1 — DURÉE',
        ]);
    }

    /** @test */
    public function client_cannot_create_article(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/articles', [
            'title' => 'Test',
            'body'  => 'Test body',
        ])->assertStatus(403);
    }

    /** @test */
    public function domiciliataire_can_update_their_own_article(): void
    {
        $owner   = $this->actingAsDomiciliataire();
        $article = Article::factory()->create(['domiciliataire_id' => $owner->id]);

        $this->putJson("/api/articles/{$article->id}", [
            'title'     => 'Updated Title',
            'body'      => 'Updated body',
            'is_active' => true,
        ])->assertStatus(200)
          ->assertJsonPath('data.title', 'Updated Title');
    }

    /** @test */
    public function domiciliataire_cannot_update_another_tenants_article(): void
    {
        $this->actingAsDomiciliataire();

        $other   = User::factory()->domiciliataire()->create();
        $article = Article::factory()->create(['domiciliataire_id' => $other->id]);

        $this->putJson("/api/articles/{$article->id}", [
            'title'     => 'Hacked',
            'body'      => 'Hacked body',
            'is_active' => true,
        ])->assertStatus(404);
    }

    /** @test */
    public function domiciliataire_can_delete_their_own_article(): void
    {
        $owner   = $this->actingAsDomiciliataire();
        $article = Article::factory()->create(['domiciliataire_id' => $owner->id]);

        $this->deleteJson("/api/articles/{$article->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    /** @test */
    public function domiciliataire_cannot_delete_another_tenants_article(): void
    {
        $this->actingAsDomiciliataire();

        $other   = User::factory()->domiciliataire()->create();
        $article = Article::factory()->create(['domiciliataire_id' => $other->id]);

        $this->deleteJson("/api/articles/{$article->id}")
            ->assertStatus(404);
    }

    /** @test */
    public function article_body_can_contain_template_variables(): void
    {
        $owner = $this->actingAsDomiciliataire();

        $this->postJson('/api/articles', [
            'title' => 'Variables Test',
            'body'  => 'Début: {{date_debut}}, Fin: {{date_fin}}, Société: {{raison_sociale}}',
        ])->assertStatus(201);
    }
}
