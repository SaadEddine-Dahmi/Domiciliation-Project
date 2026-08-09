<?php

namespace Tests\Feature\Contrat;

use App\Models\Article;
use App\Models\Contrat;
use App\Models\DomiciliaireProfile;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratLifecycleTest extends TestCase
{
    use RefreshDatabase;

    // Creates a domiciliataire (tenant) with a company profile row,
    // one client entreprise with its representant, and two article
    // clause templates. The company fields (nom_societe, rc, if_fiscal)
    // now live on domiciliataire_profiles, not on the users table, so
    // they're created via a separate DomiciliaireProfile row rather
    // than passed to User::factory()->create().
    private function setupTenantClientArticles(): array
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);

        DomiciliaireProfile::create([
            'user_id'     => $tenant->id,
            'nom_societe' => 'Ma Société',
            'rc'          => 'RC123',
            'if_fiscal'   => 'IF456',
        ]);

        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale'    => 'CLIENT SARL',
        ]);

        Representant::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Gerant',
            'cin'           => 'XY123456',
        ]);

        $article1 = Article::create([
            'domiciliataire_id' => $tenant->id,
            'title'             => 'Article 1',
            'body'              => 'Durée: {{duree_mois}} mois',
            'is_active'         => true,
        ]);
        $article2 = Article::create([
            'domiciliataire_id' => $tenant->id,
            'title'             => 'Article 2',
            'body'              => 'Prix: {{prix_mensuel}}',
            'is_active'         => true,
        ]);

        return [$tenant, $entreprise, $article1, $article2];
    }

    /** Creating a contract with a custom title stores it exactly as typed. */
    public function test_create_contract_stores_dynamic_title_as_typed(): void
    {
        [$tenant, $entreprise] = $this->setupTenantClientArticles();

        $res = $this->actingAs($tenant, 'sanctum')->postJson('/api/contrats', [
            'entreprise_id' => $entreprise->id,
            'titre_contrat' => 'Convention de Domiciliation Commerciale',
            'date_debut'    => now()->toDateString(),
        ]);

        $res->assertCreated();
        $this->assertEquals(
            'Convention de Domiciliation Commerciale',
            $res->json('data.titre_contrat')
        );
    }

    /** Empty/null titre_contrat falls back to the default title. */
    public function test_create_contract_defaults_title_when_empty(): void
    {
        [$tenant, $entreprise] = $this->setupTenantClientArticles();

        $res = $this->actingAs($tenant, 'sanctum')->postJson('/api/contrats', [
            'entreprise_id' => $entreprise->id,
            'titre_contrat' => '',
            'date_debut'    => now()->toDateString(),
        ]);

        $res->assertCreated();
        $this->assertEquals('Contrat de Domiciliation', $res->json('data.titre_contrat'));
    }

    /** Articles sent as {id, ordre} objects are synced correctly into the pivot, in order. */
    public function test_articles_sync_preserves_order(): void
    {
        [$tenant, $entreprise, $article1, $article2] = $this->setupTenantClientArticles();

        $res = $this->actingAs($tenant, 'sanctum')->postJson('/api/contrats', [
            'entreprise_id' => $entreprise->id,
            'date_debut'    => now()->toDateString(),
            'articles' => [
                ['id' => (string) $article2->id, 'ordre' => 1],
                ['id' => (string) $article1->id, 'ordre' => 2],
            ],
        ]);

        $res->assertCreated();
        $contratId = $res->json('data.id');

        $this->assertDatabaseHas('contrat_articles', [
            'contrat_id' => $contratId,
            'article_id' => $article2->id,
            'ordre'      => 1,
        ]);
        $this->assertDatabaseHas('contrat_articles', [
            'contrat_id' => $contratId,
            'article_id' => $article1->id,
            'ordre'      => 2,
        ]);
    }

    /** Flat array of IDs (not objects) is also accepted by syncArticles(). */
    public function test_articles_flat_id_array_accepted(): void
    {
        [$tenant, $entreprise, $article1] = $this->setupTenantClientArticles();

        $res = $this->actingAs($tenant, 'sanctum')->postJson('/api/contrats', [
            'entreprise_id' => $entreprise->id,
            'date_debut'    => now()->toDateString(),
            'articles'      => [(string) $article1->id],
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('contrat_articles', [
            'contrat_id' => $res->json('data.id'),
            'article_id' => $article1->id,
        ]);
    }

    /** Updating a draft re-syncs articles (detach + attach). */
    public function test_update_contract_resyncs_articles(): void
    {
        [$tenant, $entreprise, $article1, $article2] = $this->setupTenantClientArticles();

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id'     => $entreprise->id,
            'titre_contrat'     => 'Contrat de Domiciliation',
            'date_debut'        => now(),
        ]);
        $contrat->articles()->sync([$article1->id => ['ordre' => 1]]);

        $this->actingAs($tenant, 'sanctum')
            ->putJson("/api/contrats/{$contrat->id}", [
                'articles' => [['id' => (string) $article2->id, 'ordre' => 1]],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('contrat_articles', [
            'contrat_id' => $contrat->id,
            'article_id' => $article1->id,
        ]);
        $this->assertDatabaseHas('contrat_articles', [
            'contrat_id' => $contrat->id,
            'article_id' => $article2->id,
        ]);
    }

    /** Only a draft contract can be activated; activation sets next_alert_date + creates an alerte. */
    public function test_activate_draft_contract(): void
    {
        [$tenant, $entreprise] = $this->setupTenantClientArticles();

        $contrat = Contrat::create([
            'domiciliataire_id'         => $tenant->id,
            'entreprise_id'             => $entreprise->id,
            'titre_contrat'             => 'Contrat de Domiciliation',
            'date_debut'                => now(),
            'date_fin'                  => now()->addMonths(12),
            'notification_delay_months' => 1,
            'statut'                    => 'draft',
        ]);

        $res = $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/contrats/{$contrat->id}/activate");

        $res->assertOk()->assertJsonPath('data.statut', 'active');
        $this->assertDatabaseHas('alertes', ['contrat_id' => $contrat->id]);
    }

    /** Cannot activate a contract that isn't a draft. */
    public function test_cannot_activate_non_draft_contract(): void
    {
        [$tenant, $entreprise] = $this->setupTenantClientArticles();

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id'     => $entreprise->id,
            'date_debut'        => now(),
            'statut'            => 'active',
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/contrats/{$contrat->id}/activate")
            ->assertStatus(422);
    }

    /** Terminating an active contract sets statut = terminated. */
    public function test_terminate_active_contract(): void
    {
        [$tenant, $entreprise] = $this->setupTenantClientArticles();

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id'     => $entreprise->id,
            'date_debut'        => now(),
            'statut'            => 'active',
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/contrats/{$contrat->id}/terminate")
            ->assertOk()
            ->assertJsonPath('data.statut', 'terminated');
    }

    /** Cannot terminate a non-active contract. */
    public function test_cannot_terminate_draft_contract(): void
    {
        [$tenant, $entreprise] = $this->setupTenantClientArticles();

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id'     => $entreprise->id,
            'date_debut'        => now(),
            'statut'            => 'draft',
        ]);

        $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/contrats/{$contrat->id}/terminate")
            ->assertStatus(422);
    }

    /** A tenant cannot view/edit another tenant's contract (IDOR). */
    public function test_contract_is_tenant_scoped(): void
    {
        [$tenantA, $entrepriseA] = $this->setupTenantClientArticles();
        $tenantB = User::factory()->create(['role' => 'domiciliataire']);

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenantA->id,
            'entreprise_id'     => $entrepriseA->id,
            'date_debut'        => now(),
        ]);

        $this->actingAs($tenantB, 'sanctum')
            ->getJson("/api/contrats/{$contrat->id}")
            ->assertStatus(404);
    }

    /** Index returns only the authenticated tenant's contracts, with articles ordered. */
    public function test_index_returns_only_own_contracts(): void
    {
        [$tenantA, $entrepriseA] = $this->setupTenantClientArticles();
        $tenantB = User::factory()->create(['role' => 'domiciliataire']);

        Contrat::create(['domiciliataire_id' => $tenantA->id, 'entreprise_id' => $entrepriseA->id, 'date_debut' => now()]);
        Contrat::create(['domiciliataire_id' => $tenantB->id, 'entreprise_id' => $entrepriseA->id, 'date_debut' => now()]);

        $res = $this->actingAs($tenantA, 'sanctum')->getJson('/api/contrats');

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }
}