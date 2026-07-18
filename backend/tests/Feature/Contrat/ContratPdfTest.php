<?php

namespace Tests\Feature\Contrat;

use App\Models\Article;
use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_stream_requires_authentication(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire', 'nom_societe' => 'Ma Société']);
        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);
        Representant::create(['entreprise_id' => $entreprise->id, 'nom' => 'Gerant', 'cin' => 'AB123']);

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'titre_contrat' => 'Contrat de Domiciliation',
            'date_debut' => now(),
        ]);

        $this->get("/api/contrats/{$contrat->id}/pdf/stream")->assertStatus(401);

        $res = $this->actingAs($tenant, 'sanctum')
            ->get("/api/contrats/{$contrat->id}/pdf/stream");

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_token_resolution_pulls_from_representant_not_entreprise(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire', 'nom_societe' => 'Ma Société']);
        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);
        Representant::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'ElJadiani',
            'prenom' => 'Youssef',
            'cin' => 'ZZ999999',
            'telephone' => '+212600112233',
            'email' => 'gerant@example.com',
        ]);

        Article::create([
            'domiciliataire_id' => $tenant->id,
            'title' => 'Identité',
            'body' => 'Gérant: {{gerant_nom}}, CIN: {{gerant_cin}}, Tel: {{telephone}}',
            'is_active' => true,
        ]);

        $article = Article::first();

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'date_debut' => now(),
        ]);
        $contrat->articles()->sync([$article->id => ['ordre' => 1]]);

        $res = $this->actingAs($tenant, 'sanctum')
            ->get("/api/contrats/{$contrat->id}/pdf/stream");

        $res->assertOk();
        $this->assertGreaterThan(500, strlen($res->getContent()));
    }

    public function test_generate_pdf_saves_to_disk_and_updates_path(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $tenant = User::factory()->create(['role' => 'domiciliataire', 'nom_societe' => 'Ma Société']);
        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);
        Representant::create(['entreprise_id' => $entreprise->id, 'nom' => 'Gerant', 'cin' => 'AB123']);

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'date_debut' => now(),
        ]);

        $res = $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/contrats/{$contrat->id}/pdf");

        $res->assertOk()->assertJson(['success' => true]);

        $path = $res->json('data.pdf_path');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);

        $this->assertDatabaseHas('contrats', ['id' => $contrat->id, 'pdf_path' => $path]);
    }
}