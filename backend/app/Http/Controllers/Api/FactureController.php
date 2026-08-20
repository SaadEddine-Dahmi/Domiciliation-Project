<?php
// app/Http/Controllers/Api/FactureController.php
//
// Read-only access to invoices: listing and PDF generation.
// Invoices themselves are only ever created by PaiementController::store(),
// which enforces that montant never exceeds the contract's remaining balance
// (see remainingBalance() there) — this controller does not duplicate that
// check since it never writes a Facture row.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class FactureController extends Controller
{
    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve a user from a ?token= query param.
     *
     * Required for PDF endpoints opened directly in the browser or an
     * <iframe>/<a href> — those requests cannot carry an Authorization header.
     */
    private function authenticateViaToken(Request $request): ?\App\Models\User
    {
        $value = $request->query('token');
        if (!$value)
            return null;

        $token = PersonalAccessToken::findToken($value);
        if (!$token)
            return null;
        if ($token->expires_at && $token->expires_at->isPast())
            return null;

        return $token->tokenable;
    }

    /**
     * Resolve an invoice by ID, tenant-scoped to the given user, with all
     * relations needed for PDF rendering eager-loaded.
     */
    private function resolveFacture(int $id, \App\Models\User $user): ?Facture
    {
        return Facture::with(['entreprise', 'contrat', 'domiciliataire', 'paiements'])
            ->where('domiciliataire_id', $user->id)
            ->find($id);
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/factures
     *
     * Lists all invoices for the authenticated domiciliataire, newest first,
     * with the fields the invoices list page needs already eager-loaded.
     */
    public function index()
    {
        $tenantId = auth()->id();

        $factures = Facture::query()
            ->where('domiciliataire_id', $tenantId)
            ->with([
                'entreprise:id,raison_sociale,adresse,ville,pays,forme_juridique',
                'contrat:id,date_debut,date_fin,statut,prix_total',
                'paiements',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $factures]);
    }

    // ── PDF ────────────────────────────────────────────────────────────────────

    /**
     * GET /api/factures/{id}/pdf?token=xxx&mode=preview|download
     *
     * Generates the invoice PDF. Auth is via ?token= query param (not the
     * Authorization header) because this URL is opened directly in a browser
     * tab or <iframe>.
     *
     *   ?mode=preview  (default) → inline, opens in the tab/iframe
     *   ?mode=download            → attachment, triggers a file download
     */
    public function pdf(Request $request, int $id)
    {
        $user = $this->authenticateViaToken($request);
        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $facture = $this->resolveFacture($id, $user);
        if (!$facture) {
            return response()->json(['message' => 'Facture introuvable.'], 404);
        }

        $pdf = Pdf::loadView('pdf.facture', [
            'facture' => $facture,
            'entreprise' => $facture->entreprise,
            'contrat' => $facture->contrat,
            'domiciliataire' => $facture->domiciliataire,
            'paiements' => $facture->paiements,
            'totalPaye' => $facture->paiements->sum('montant'),
        ])->setPaper('a4', 'portrait');

        $filename = 'facture-' . ($facture->numero_facture ?? $facture->id) . '.pdf';
        $mode = $request->query('mode', 'preview');

        if ($mode === 'download') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}