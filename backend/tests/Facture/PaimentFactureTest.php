<?php

namespace Tests\Facture;

use App\Models\Contrat;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaimentFactureTest extends TestCase
{
    use RefreshDatabase;

    /** Recording a payment auto-creates a Facture with a generated numero_facture. */
    public function test_recording_payment_creates_facture_with_generated_number(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $entreprise = Entreprise::create(['domiciliataire_id' => $tenant->id, 'raison_sociale' => 'CLIENT SARL']);
        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'date_debut' => now(),
            'prix_total' => 5000,
            'statut' => 'active',
        ]);

        $res = $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/contrats/{$contrat->id}/paiements", [
                'montant' => 2000,
                'date_paiement' => now()->toDateString(),
                'mode_paiement' => 'virement',
            ]);

        $res->assertCreated();
        $this->assertNotEmpty($res->json('data.facture.numero_facture'));
        $this->assertStringStartsWith('FAC-' . date('Y'), $res->json('data.facture.numero_facture'));
    }

    /** Payment summary correctly computes restant and pourcentage. */
    public function test_payment_summary_computes_remaining_and_percentage(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $entreprise = Entreprise::create(['domiciliataire_id' => $tenant->id, 'raison_sociale' => 'CLIENT SARL']);
        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'date_debut' => now(),
            'prix_total' => 1000,
            'statut' => 'active',
        ]);

        $this->actingAs($tenant, 'sanctum')->postJson("/api/contrats/{$contrat->id}/paiements", [
            'montant' => 400,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => 'espèces',
        ])->assertCreated();

        $res = $this->actingAs($tenant, 'sanctum')->getJson("/api/contrats/{$contrat->id}/paiements/summary");

        $res->assertOk();
        $this->assertEquals(1000, $res->json('data.prix_total'));
        $this->assertEquals(400, $res->json('data.total_paye'));
        $this->assertEquals(600, $res->json('data.restant'));
        $this->assertEquals(40, $res->json('data.pourcentage'));
    }

    /** Facture PDF requires ?token= query param (browser-opened endpoint). */
    public function test_facture_pdf_requires_query_token(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $entreprise = Entreprise::create(['domiciliataire_id' => $tenant->id, 'raison_sociale' => 'CLIENT SARL']);
        $contrat = Contrat::create([
            'domiciliataire_id' => $tenant->id,
            'entreprise_id' => $entreprise->id,
            'date_debut' => now(),
            'prix_total' => 500,
            'statut' => 'active',
        ]);

        $paymentRes = $this->actingAs($tenant, 'sanctum')->postJson("/api/contrats/{$contrat->id}/paiements", [
            'montant' => 500,
            'date_paiement' => now()->toDateString(),
            'mode_paiement' => 'virement',
        ]);

        $factureId = $paymentRes->json('data.facture.id');

        $this->get("/api/factures/{$factureId}/pdf")->assertStatus(401);

        $token = $tenant->createToken('api-token')->plainTextToken;
        $this->get("/api/factures/{$factureId}/pdf?token={$token}&mode=preview")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
