<?php

namespace Tests\Feature\Contrat;

use App\Models\Article;
use App\Models\Contrat;
use App\Models\DomiciliataireProfile;
use App\Models\Entreprise;
use App\Models\Representant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        \Illuminate\Support\Facades\Storage::fake('public');

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
            'scanned_pdf_path' => 'contrats/signed/contract.pdf',
        ]);

        \Illuminate\Support\Facades\Storage::disk('public')
            ->put('contrats/signed/contract.pdf', '%PDF-1.4 signed content');

        $token = $tenant->createToken('pdf-test')->plainTextToken;

        $res = $this->get("/api/contrats/{$contrat->id}/pdf/stream?token={$token}&mode=download");

        $res->assertOk();
        $this->assertSame('%PDF-1.4 signed content', $res->getContent());
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
    }
}
