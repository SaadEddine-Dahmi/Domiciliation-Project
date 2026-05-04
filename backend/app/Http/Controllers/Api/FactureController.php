<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class FactureController extends Controller
{
    // ── Authenticate from ?token= query param ─────────────
    // Required for PDF endpoints opened directly in browser
    // (browser cannot set Authorization header on navigation)
    private function authenticateViaToken(Request $request): ?\App\Models\User
    {
        $value = $request->query('token');
        if (!$value) return null;
        $token = PersonalAccessToken::findToken($value);
        if (!$token) return null;
        if ($token->expires_at && $token->expires_at->isPast()) return null;
        return $token->tokenable;
    }

    // ── Resolve facture for the authenticated user ─────────
    private function resolveFacture(int $id, \App\Models\User $user): ?Facture
    {
        return Facture::with([
            'entreprise',
            'contrat',
            'domiciliataire',
            'paiements',
        ])
        ->where('domiciliataire_id', $user->id)
        ->find($id);
    }

    // ─────────────────────────────────────────────────────
    // GET /api/factures
    // List all invoices for the authenticated domiciliataire
    // ─────────────────────────────────────────────────────
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

        return response()->json([
            'success' => true,
            'data'    => $factures,
        ]);
    }

    // ─────────────────────────────────────────────────────
    // GET /api/factures/{id}/pdf?token=xxx&mode=preview|download
    //
    // Generates a styled PDF for the given invoice.
    // Auth is via ?token= query param (not Authorization header)
    // because this URL is opened directly in browser/iframe.
    //
    // ?mode=preview  → Content-Disposition: inline  (opens in browser)
    // ?mode=download → Content-Disposition: attachment (triggers download)
    // ─────────────────────────────────────────────────────
    public function pdf(Request $request, int $id)
    {
        // Authenticate via query token
        $user = $this->authenticateViaToken($request);
        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        // Load facture with all needed relations
        $facture = $this->resolveFacture($id, $user);
        if (!$facture) {
            return response()->json(['message' => 'Facture introuvable.'], 404);
        }

        // Build the PDF from a Blade view
        $pdf = Pdf::loadView('pdf.facture', [
            'facture'         => $facture,
            'entreprise'      => $facture->entreprise,
            'contrat'         => $facture->contrat,
            'domiciliataire'  => $facture->domiciliataire,
            'paiements'       => $facture->paiements,
            'totalPaye'       => $facture->paiements->sum('montant'),
        ])
        ->setPaper('a4', 'portrait');

        $filename = 'facture-' . ($facture->numero_facture ?? $facture->id) . '.pdf';
        $mode     = $request->query('mode', 'preview'); // preview | download

        if ($mode === 'download') {
            return $pdf->download($filename);
        }

        // Default: stream inline for preview
        return $pdf->stream($filename);
    }
}
