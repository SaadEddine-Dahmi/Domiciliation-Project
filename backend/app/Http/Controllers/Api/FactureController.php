<?php
// app/Http/Controllers/Api/FactureController.php
//
// Access to invoices: listing, PDF generation, archive/restore, and deletion.
// Invoices themselves are only ever created by PaiementController::store(),
// which enforces that montant never exceeds the contract's remaining balance
// (see remainingBalance() there) — this controller does not duplicate that
// check since it never writes a Facture row.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        if ($user->role !== 'domiciliataire') {
            return null;
        }

        return Facture::with(['entreprise', 'contrat', 'domiciliataire', 'paiements'])
            ->where('domiciliataire_id', $user->id)
            ->find($id);
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/factures
     *
     * Lists invoices for the authenticated domiciliataire, newest first, with
     * the fields the invoices list page needs already eager-loaded.
     * Archived invoices are hidden unless ?include_archived=1 is sent.
     */
    public function index(Request $request)
    {
        if ($blocked = $this->denyUnlessDomiciliataire(auth()->user())) {
            return $blocked;
        }

        $tenantId = auth()->id();

        $factures = Facture::query()
            ->where('domiciliataire_id', $tenantId)
            ->when(!$request->boolean('include_archived'), fn($q) => $q->whereNull('archived_at'))
            ->with([
                'entreprise:id,raison_sociale,adresse,ville,pays,forme_juridique',
                'contrat:id,date_debut,date_fin,statut,prix_total',
                'paiements',
            ])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $factures]);
    }

    /**
     * POST /api/factures/{id}/archive
     */
    public function archive(int $id)
    {
        if ($blocked = $this->denyUnlessDomiciliataire(auth()->user())) {
            return $blocked;
        }

        $facture = Facture::where('domiciliataire_id', auth()->id())->findOrFail($id);
        $facture->forceFill(['archived_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => 'Facture archivée.',
            'data' => $facture->fresh(['entreprise', 'contrat', 'paiements']),
        ]);
    }

    /**
     * POST /api/factures/{id}/restore
     */
    public function restore(int $id)
    {
        if ($blocked = $this->denyUnlessDomiciliataire(auth()->user())) {
            return $blocked;
        }

        $facture = Facture::where('domiciliataire_id', auth()->id())->findOrFail($id);
        $facture->forceFill(['archived_at' => null])->save();

        return response()->json([
            'success' => true,
            'message' => 'Facture restaurée.',
            'data' => $facture->fresh(['entreprise', 'contrat', 'paiements']),
        ]);
    }

    /**
     * DELETE /api/factures/{id}
     *
     * A facture created from a payment owns that payment row in this app's
     * workflow, so deletion removes child paiements first to satisfy the
     * restrict-on-delete foreign key.
     */
    public function destroy(int $id)
    {
        if ($blocked = $this->denyUnlessDomiciliataire(auth()->user())) {
            return $blocked;
        }

        $facture = Facture::where('domiciliataire_id', auth()->id())->findOrFail($id);

        DB::transaction(function () use ($facture) {
            $facture->paiements()->delete();
            $facture->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Facture supprimée.',
        ]);
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

    private function forbiddenTenantResource(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "Accès interdit : les factures appartiennent aux domiciliataires.",
        ], 403);
    }

    private function denyUnlessDomiciliataire(?\App\Models\User $user): ?\Illuminate\Http\JsonResponse
    {
        return $user?->role === 'domiciliataire'
            ? null
            : $this->forbiddenTenantResource();
    }
}
