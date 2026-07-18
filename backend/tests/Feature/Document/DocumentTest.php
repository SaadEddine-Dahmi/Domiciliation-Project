<?php

namespace Tests\Feature\Document;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    /** Domiciliataire can upload a document for one of their own entreprises. */
    public function test_domiciliataire_can_upload_document(): void
    {
        Storage::fake('local');
        Storage::fake('private');

        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $entreprise = Entreprise::create(['domiciliataire_id' => $tenant->id, 'raison_sociale' => 'CLIENT SARL']);
        $type = DocumentType::create(['name' => 'CIN', 'is_required' => true, 'has_expiration' => false]);

        $res = $this->actingAs($tenant, 'sanctum')->post('/api/documents', [
            'entreprise_id' => $entreprise->id,
            'document_type_id' => $type->id,
            'file' => UploadedFile::fake()->create('cin.pdf', 100, 'application/pdf'),
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('documents', ['entreprise_id' => $entreprise->id]);
    }

    /** Client role only sees documents belonging to their own entreprise. */
    public function test_client_sees_only_own_documents(): void
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $tenant = User::factory()->create(['role' => 'domiciliataire']);

        $ownEntreprise = Entreprise::create(['domiciliataire_id' => $tenant->id, 'client_user_id' => $clientUser->id, 'raison_sociale' => 'MINE']);
        $otherEntreprise = Entreprise::create(['domiciliataire_id' => $tenant->id, 'raison_sociale' => 'OTHER']);

        $type = DocumentType::create(['name' => 'CIN']);

        Document::create([
            'entreprise_id' => $ownEntreprise->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/1/a.pdf',
            'uploaded_by_user' => $tenant->id,
        ]);
        Document::create([
            'entreprise_id' => $otherEntreprise->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/2/b.pdf',
            'uploaded_by_user' => $tenant->id,
        ]);

        $res = $this->actingAs($clientUser, 'sanctum')->getJson('/api/documents');

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    /**
     * Document download requires ?token= (Authorization header is impossible
     * on plain browser GET).
     *
     * NOTE: both 'local' and 'private' disks are faked because
     * DocumentController::disk() picks 'private' if that disk exists in
     * config, else falls back to 'local'. Faking only one risks the file
     * landing on a disk the fake isn't watching, causing false 404s.
     */
    public function test_document_download_requires_query_token(): void
    {
        Storage::fake('local');
        Storage::fake('private');

        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $entreprise = Entreprise::create(['domiciliataire_id' => $tenant->id, 'raison_sociale' => 'CLIENT']);
        $type = DocumentType::create(['name' => 'CIN']);

        // Determine which disk the controller will actually use and write there.
        $disks = array_keys(config('filesystems.disks', []));
        $activeDisk = in_array('private', $disks, true) ? 'private' : 'local';

        Storage::disk($activeDisk)->put('documents/1/file.pdf', 'fake-pdf-content');

        $doc = Document::create([
            'entreprise_id' => $entreprise->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/1/file.pdf',
            'uploaded_by_user' => $tenant->id,
        ]);

        // No token -> 401
        $this->get("/api/documents/{$doc->id}/download")->assertStatus(401);

        // With token -> streams file
        $token = $tenant->createToken('api-token')->plainTextToken;
        $this->get("/api/documents/{$doc->id}/download?token={$token}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** Domiciliataire cannot delete a document belonging to another tenant. */
    public function test_cannot_delete_other_tenants_document(): void
    {
        $tenantA = User::factory()->create(['role' => 'domiciliataire']);
        $tenantB = User::factory()->create(['role' => 'domiciliataire']);

        $entrepriseA = Entreprise::create(['domiciliataire_id' => $tenantA->id, 'raison_sociale' => 'A']);
        $type = DocumentType::create(['name' => 'CIN']);

        $doc = Document::create([
            'entreprise_id' => $entrepriseA->id,
            'document_type_id' => $type->id,
            'file_path' => 'documents/1/file.pdf',
            'uploaded_by_user' => $tenantA->id,
        ]);

        $this->actingAs($tenantB, 'sanctum')
            ->deleteJson("/api/documents/{$doc->id}")
            ->assertStatus(404);
    }
}
