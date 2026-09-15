<?php

namespace Tests\Feature\Contrat;

use App\Models\Article;
use App\Models\Contrat;
use App\Models\DomiciliataireProfile;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratPdfTest extends TestCase
{
    use RefreshDatabase;

    // Streaming the PDF requires an authenticated tenant. Company
    // profile fields now live on domiciliataire_profiles, so nom_societe
    // is set via a separate DomiciliataireProfile row rather than passed
    // to User::factory()->create().
    public function test_pdf_stream_requires_authentication(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        DomiciliataireProfile::create(['user_id' => $tenant->id, 'nom_societe' => 'Ma Société']);

        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);
        Representant::create([
            'representable_id' => $entreprise->id,
            'representable_type' => Entreprise::class,
            'nom' => 'Gerant',
            'cin' => 'AB123',
        ]);

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'titre_contrat' => 'Contrat de Domiciliation',
            'date_debut' => now(),
        ]);

        $this->get("/api/contrats/{$contrat->id}/pdf/stream")->assertStatus(401);

        $token = $tenant->createToken('pdf-test')->plainTextToken;

        $res = $this->get("/api/contrats/{$contrat->id}/pdf/stream?token={$token}");

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_token_resolution_pulls_from_representant_not_entreprise(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        DomiciliataireProfile::create(['user_id' => $tenant->id, 'nom_societe' => 'Ma Société']);

        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);
        Representant::create([
            'representable_id' => $entreprise->id,
            'representable_type' => Entreprise::class,
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

        $token = $tenant->createToken('pdf-test')->plainTextToken;

        $res = $this->get("/api/contrats/{$contrat->id}/pdf/stream?token={$token}");

        $res->assertOk();
        $this->assertGreaterThan(500, strlen($res->getContent()));
    }

    public function test_stream_pdf_returns_signed_file_when_contract_is_legalised(): void
    {
        config()->set('filesystems.contracts_disk', 'minio');
        Storage::fake('minio');

        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        DomiciliataireProfile::create(['user_id' => $tenant->id, 'nom_societe' => 'Ma Société']);

        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);
        Representant::create([
            'representable_id' => $entreprise->id,
            'representable_type' => Entreprise::class,
            'nom' => 'Gerant',
            'cin' => 'AB123',
        ]);

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'date_debut' => now(),
            'scanned_pdf_path' => "tenants/{$tenant->id}/contracts/contract.pdf",
        ]);

        Storage::disk('minio')
            ->put("tenants/{$tenant->id}/contracts/contract.pdf", '%PDF-1.4 signed content');

        $token = $tenant->createToken('pdf-test')->plainTextToken;

        $res = $this->get("/api/contrats/{$contrat->id}/pdf/stream?token={$token}&mode=download");

        $res->assertOk();
        $this->assertSame('%PDF-1.4 signed content', $res->getContent());
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
    }

    public function test_signed_contract_pdf_upload_is_stored_on_tenant_minio_path(): void
    {
        config()->set('filesystems.contracts_disk', 'minio');
        Storage::fake('minio');

        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $entreprise = Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);

        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'date_debut' => now(),
            'date_signature' => now(),
            'ville_signature' => 'Casablanca',
            'statut' => 'draft',
        ]);

        $this->actingAs($tenant, 'sanctum')->post("/api/contrats/{$contrat->id}/activate", [
            'signed_pdf' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $path = $contrat->fresh()->scanned_pdf_path;

        $this->assertStringStartsWith("tenants/{$tenant->id}/contracts/", $path);
        Storage::disk('minio')->assertExists($path);
    }

    public function test_preview_endpoint_renders_contract_blade_html_from_draft_payload(): void
    {
        $this->actingAsDomiciliataire();

        $res = $this->postJson('/api/contracts/preview', [
            'titre_contrat' => 'Contrat de Domiciliation',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'duree_mois' => 12,
            'prix_mensuel' => 500,
            'prix_total' => 6000,
            'ville_signature' => 'Agadir',
            'date_signature' => '2026-01-01',
            'tokens' => [
                'domiciliataire_nom' => 'AST-FISC',
                'domiciliataire_rc' => 'RC-1',
                'raison_sociale' => 'CLIENT SARL',
                'gerant_nom' => 'Client Owner',
            ],
            'articles' => [
                ['title' => 'Redevance', 'body' => 'Prix: {{prix_mensuel}}', 'ordre' => 1],
            ],
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true);

        $html = $res->json('data.html');
        $this->assertStringContainsString('Contrat de Domiciliation', $html);
        $this->assertStringContainsString('AST-FISC', $html);
        $this->assertStringContainsString('Prix: 500,00 DH', $html);
    }
}
